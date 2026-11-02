<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Payments\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class RankingLedgerExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Mail::fake();
        Http::preventStrayRequests();
        $gateway = \Mockery::mock(PaymentGatewayInterface::class);
        $this->app->instance(PaymentGatewayInterface::class, $gateway);
    }

    private function bid(array $attributes = []): Bid
    {
        $listing = Listing::query()->first() ?? Listing::query()->create([
            'public_id' => (string) Str::uuid(), 'category_id' => Category::query()->first()->id,
            'name' => 'Synthetic listing', 'slug' => 'synthetic', 'url' => 'https://example.com/',
            'normalized_url' => 'https://example.com/', 'destination_host' => 'example.com',
            'short_description' => 'Synthetic only', 'contact_email' => 'PRIVATE_EMAIL_CANARY@example.com',
            'status' => 'pending', 'metadata_status' => 'skipped',
        ]);

        $bid = Bid::query()->create(array_merge([
            'listing_id' => $listing->id, 'public_reference' => (string) Str::uuid(),
            'amount_cents' => 100, 'signed_delta_cents' => 100, 'previous_total_cents' => 500,
            'new_total_cents' => 600, 'currency' => 'USD', 'payment_provider' => 'stripe',
            'payment_status' => 'confirmed', 'payment_reference' => 'PRIVATE_PROVIDER_CANARY',
            'notes' => 'PRIVATE_NOTES_CANARY', 'created_at' => '2026-10-16 12:00:00',
        ], $attributes));
        $bid->forceFill(['created_at' => $attributes['created_at'] ?? '2026-10-16 12:00:00'])->save();

        return $bid;
    }

    private function url(array $dates = []): string
    {
        return route('admin.ranking-ledger.export', $dates ?: ['start_date' => '2026-10-16', 'end_date' => '2026-10-16']);
    }

    public function test_guests_and_customers_cannot_export(): void
    {
        $this->get($this->url())->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get($this->url())->assertRedirect(route('admin.login'));
    }

    public function test_allowlist_statuses_order_and_no_side_effects(): void
    {
        $records = [];
        foreach (['confirmed', 'pending', 'failed', 'refunded', 'confirmed'] as $index => $status) {
            $records[] = $this->bid([
                'payment_status' => $status, 'payment_provider' => $index === 4 ? 'admin_adjustment' : 'stripe',
                'signed_delta_cents' => $index === 3 ? -100 : 100,
                'created_at' => $index === 0 ? '2026-10-16 23:59:59' : '2026-10-16 00:00:00',
            ]);
        }
        $this->bid(['created_at' => '2026-10-15 23:59:59']);
        $this->bid(['created_at' => '2026-10-17 00:00:00']);
        $before = Bid::query()->get()->toArray();
        $response = $this->withSession(['marketplace_admin' => 'synthetic-admin'])->get($this->url());
        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $rows = array_map(fn ($line) => str_getcsv($line, ',', '"', ''), explode("\n", trim($csv)));
        $this->assertSame(['timestamp_utc', 'listing_id', 'reference', 'source', 'status', 'currency', 'signed_change_cents', 'prior_balance_cents', 'resulting_balance_cents'], array_shift($rows));
        $this->assertCount(5, $rows);
        $this->assertSame(array_map(fn ($i) => $records[$i]->public_reference, [1, 2, 3, 4, 0]), array_column($rows, 2));
        $this->assertSame('-100', $rows[2][6]);
        $this->assertSame(['500', '600'], array_slice($rows[2], 7));
        foreach (['PRIVATE_EMAIL_CANARY', 'PRIVATE_NOTES_CANARY', 'PRIVATE_PROVIDER_CANARY', 'provider_payload', 'management'] as $private) {
            $this->assertStringNotContainsString($private, $csv);
        }
        $this->assertSame($before, Bid::query()->get()->toArray());
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_dates_are_required_valid_ordered_and_bounded(): void
    {
        foreach ([
            [], ['start_date' => 'invalid', 'end_date' => '2026-10-16'],
            ['start_date' => '2026-10-17', 'end_date' => '2026-10-16'],
            ['start_date' => '2025-01-01', 'end_date' => '2026-01-02'],
        ] as $dates) {
            $url = route('admin.ranking-ledger.export', $dates);
            $this->withSession(['marketplace_admin' => 'synthetic-admin'])->getJson($url)->assertUnprocessable();
        }
        $this->withSession(['marketplace_admin' => 'synthetic-admin'])->get($this->url([
            'start_date' => '2026-01-01', 'end_date' => '2027-01-01',
        ]))->assertOk();
    }

    public function test_formula_safe_text_and_large_stable_results(): void
    {
        $record = $this->bid(['public_reference' => '=FORMULA', 'payment_provider' => "\t=SOURCE", 'payment_status' => '@STATUS', 'currency' => '+X']);
        $template = $record->getAttributes();
        unset($template['id']);
        $batch = [];
        for ($i = 0; $i < 1200; $i++) {
            $batch[] = array_merge($template, ['public_reference' => 'synthetic-'.$i]);
        }
        Bid::query()->insert($batch);
        $response = $this->withSession(['marketplace_admin' => 'synthetic-admin'])->get($this->url());
        $content = $response->streamedContent();
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $header = fgetcsv($stream, escape: '');
        $first = fgetcsv($stream, escape: '');
        $this->assertCount(9, $header);
        $this->assertSame(["'=FORMULA", "'\t=SOURCE", "'@STATUS", "'+X"], array_slice($first, 2, 4));
        $count = 1;
        $last = null;
        while (($row = fgetcsv($stream, escape: '')) !== false) {
            $count++;
            $last = $row;
        }
        fclose($stream);
        $this->assertSame(1201, $count);
        $this->assertSame('synthetic-1199', $last[2]);
    }
}
