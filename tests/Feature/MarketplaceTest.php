<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\CustomerLoginCode;
use App\Models\Listing;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Payments\CheckoutSession;
use App\Payments\GatewayEvent;
use App\Payments\PaymentGatewayInterface;
use App\Payments\RefundResult;
use App\Payments\StripePaymentGateway;
use App\Services\BidService;
use App\Services\PaymentService;
use App\Services\RankingService;
use App\Services\SafeMetadataService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\Exception\SignatureVerificationException;
use Tests\TestCase;

class FakeGateway implements PaymentGatewayInterface
{
    public GatewayEvent $event;

    public int $refundCalls = 0;

    public bool $failCheckout = false;

    public function configured(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'stripe';
    }

    public function createCheckout(Bid $bid, string $successUrl, string $cancelUrl): CheckoutSession
    {
        if ($this->failCheckout) {
            throw new \RuntimeException('Checkout unavailable');
        }

        return new CheckoutSession('cs_fixture', 'https://checkout.stripe.com/fixture');
    }

    public function parseWebhook(string $payload, string $signature): GatewayEvent
    {
        return $this->event;
    }

    public function refund(PaymentTransaction $transaction, int $amountCents, string $reason): RefundResult
    {
        return new RefundResult('re_fixture_'.++$this->refundCalls, 'succeeded');
    }
}
class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private FakeGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Mail::fake();
        Http::preventStrayRequests();
        $this->gateway = new FakeGateway;
        $this->app->instance(PaymentGatewayInterface::class, $this->gateway);
    }

    private function listing(array $attributes = []): Listing
    {
        $id = (string) Str::uuid();

        return Listing::query()->create(array_merge([
            'public_id' => $id, 'category_id' => Category::query()->first()->id, 'name' => 'Example project',
            'slug' => 'example-'.$id, 'url' => 'https://example.com/'.$id, 'normalized_url' => 'https://example.com/'.$id,
            'destination_host' => 'example.com', 'short_description' => 'A project description',
            'contact_email' => 'owner@example.com', 'status' => 'pending', 'metadata_status' => 'skipped',
        ], $attributes));
    }

    private function openMarket(): void
    {
        config(['marketplace.market_open' => true, 'marketplace.legal_ready' => true]);
        app(SettingsService::class)->set('market_open', '1', 'boolean');
    }

    private function paid(Listing $listing, int $amount = 500): PaymentTransaction
    {
        $bid = app(BidService::class)->createPending($listing, $amount, 'stripe');
        app(BidService::class)->confirm($bid, 'pi_fixture', $amount);

        return PaymentTransaction::query()->create(['bid_id' => $bid->id, 'provider' => 'stripe', 'provider_transaction_id' => 'pi_fixture_'.$bid->id, 'idempotency_key' => (string) Str::uuid(), 'amount_cents' => $amount, 'currency' => 'USD', 'status' => 'confirmed']);
    }

    public static function pages(): array
    {
        return array_map(fn ($p) => [$p], ['/', '/submit', '/contact', '/request-category', '/legal/terms', '/legal/privacy', '/legal/refunds', '/legal/listing-policy', '/customer/login', '/admin/login', '/sitemap.xml']);
    }

    #[DataProvider('pages')]
    public function test_public_pages_render_without_hosted_dependencies(string $path): void
    {
        $this->get($path)->assertOk();
        Http::assertNothingSent();
    }

    public function test_fresh_install_stays_closed_even_when_database_switch_is_on(): void
    {
        app(SettingsService::class)->set('market_open', '1', 'boolean');
        $this->assertFalse(app(PaymentService::class)->available());
        config(['marketplace.market_open' => true]);
        $this->assertFalse(app(PaymentService::class)->available());
        config(['marketplace.legal_ready' => true]);
        $this->assertTrue(app(PaymentService::class)->available());
    }

    public function test_contact_is_local_encrypted_and_private(): void
    {
        $this->post('/contact', ['name' => 'Example', 'email' => 'visitor@example.com', 'subject' => 'Support', 'message' => 'Private message'])->assertRedirect('/contact');
        $row = DB::table('contact_messages')->first();
        $this->assertNotSame('visitor@example.com', $row->email);
        $this->assertNotSame('Private message', $row->message);
        $this->assertSame('Private message', ContactMessage::query()->first()->message);
        $this->get('/admin/messages')->assertRedirect('/admin/login');
        Http::assertNothingSent();
    }

    public function test_contact_honeypot_rejects_spam(): void
    {
        $this->post('/contact', ['name' => 'Spam', 'email' => 'spam@example.com', 'subject' => 'Spam', 'message' => 'Spam', 'address' => 'filled'])->assertSessionHasErrors('address');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_admin_inbox_escapes_and_resolves_message(): void
    {
        $message = ContactMessage::create(['name' => 'Example', 'email' => 'visitor@example.com', 'subject' => '<script>alert(1)</script>', 'message' => '<img onerror=alert(1)>']);
        $this->withSession(['marketplace_admin' => 'admin@example.com'])->get('/admin/messages')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->post('/admin/messages/'.$message->id.'/resolve')->assertRedirect();
        $this->assertNotNull($message->fresh()->resolved_at);
    }

    public function test_admin_requires_correct_credentials(): void
    {
        config(['marketplace.admin_email' => 'admin@example.com', 'marketplace.admin_password_hash' => password_hash('Long fixture password', PASSWORD_BCRYPT)]);
        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'wrong'])->assertSessionHas('error');
        $this->assertFalse(session()->has('marketplace_admin'));
        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'Long fixture password'])->assertRedirect('/admin');
        $this->get('/admin')->assertOk();
    }

    public function test_payments_accumulate_and_confirmation_is_idempotent(): void
    {
        $listing = $this->listing();
        $first = $this->paid($listing);
        app(BidService::class)->confirm($first->bid, 'pi_fixture', 500);
        $this->paid($listing->fresh(), 300);
        $this->assertSame(800, $listing->fresh()->total_bid_cents);
        $this->assertDatabaseCount('activity_events', 2);
    }

    public function test_topup_minimum_differs_from_first_bid(): void
    {
        $this->openMarket();
        $listing = $this->listing();
        $this->paid($listing);
        $this->assertSame('https://checkout.stripe.com/fixture', app(PaymentService::class)->startCheckout($listing->fresh(), 100, 'https://example.com/success', 'https://example.com/cancel'));
        $this->expectException(\RuntimeException::class);
        app(PaymentService::class)->startCheckout($this->listing(), 100, 'https://example.com/success', 'https://example.com/cancel');
    }

    public function test_mismatched_payment_does_not_credit_listing(): void
    {
        $listing = $this->listing();
        $bid = app(BidService::class)->createPending($listing, 500, 'stripe');
        try {
            app(BidService::class)->confirm($bid, 'pi_wrong', 499);
            $this->fail('Mismatch accepted');
        } catch (\RuntimeException $e) {
        }
        $this->assertSame(0, $listing->fresh()->total_bid_cents);
        $this->assertSame('pending', $bid->fresh()->payment_status);
    }

    public function test_failed_checkout_leaves_failed_ledger(): void
    {
        $this->openMarket();
        $this->gateway->failCheckout = true;
        try {
            app(PaymentService::class)->startCheckout($this->listing(), 500, 'https://example.com/s', 'https://example.com/c');
            $this->fail();
        } catch (\RuntimeException $e) {
        }
        $this->assertDatabaseHas('payment_transactions', ['status' => 'failed']);
        $this->assertDatabaseHas('bids', ['payment_status' => 'failed']);
    }

    public function test_webhook_retry_and_duplicate_cannot_double_credit(): void
    {
        $this->openMarket();
        $listing = $this->listing();
        app(PaymentService::class)->startCheckout($listing, 500, 'https://example.com/s', 'https://example.com/c');
        $bid = Bid::query()->first();
        $this->gateway->event = new GatewayEvent('evt_fixture', 'checkout.session.completed', $bid->public_reference, 'pi_fixture', 499, 'paid');
        try {
            app(PaymentService::class)->handleWebhook('fixture', 'fixture');
            $this->fail();
        } catch (\RuntimeException $e) {
        }
        $this->assertDatabaseHas('webhook_events', ['status' => 'failed']);
        $this->gateway->event = new GatewayEvent('evt_fixture', 'checkout.session.completed', $bid->public_reference, 'pi_fixture', 500, 'paid');
        app(PaymentService::class)->handleWebhook('fixture', 'fixture');
        app(PaymentService::class)->handleWebhook('fixture', 'fixture');
        $this->assertSame(500, $listing->fresh()->total_bid_cents);
        $this->assertDatabaseCount('webhook_events', 1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_real_stripe_signature_verification_accepts_signed_fixture_and_rejects_tampering(): void
    {
        $secret = 'test-signing-fixture';
        config(['services.stripe.secret' => 'test-api-fixture', 'services.stripe.webhook_secret' => $secret]);
        $payload = json_encode(['id' => 'evt_fixture', 'object' => 'event', 'type' => 'checkout.session.completed', 'livemode' => false, 'data' => ['object' => ['id' => 'cs_fixture', 'object' => 'checkout.session', 'metadata' => ['bid_reference' => 'fixture'], 'payment_intent' => 'pi_fixture', 'amount_total' => 500, 'payment_status' => 'paid']]]);
        $time = time();
        $signature = 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$payload, $secret);
        $event = (new StripePaymentGateway)->parseWebhook($payload, $signature);
        $this->assertSame(500, $event->amountCents);
        $this->expectException(SignatureVerificationException::class);
        (new StripePaymentGateway)->parseWebhook($payload.' ', $signature);
    }

    public function test_partial_refunds_are_cumulative_and_never_overrefund(): void
    {
        $listing = $this->listing();
        $transaction = $this->paid($listing);
        $this->paid($listing->fresh(), 500); // Another payment must not make the first refundable twice.
        $this->withSession(['marketplace_admin' => 'admin@example.com']);
        $this->post('/admin/transactions/'.$transaction->id.'/refund', ['amount' => 2, 'reason' => 'Fixture partial refund'])->assertSessionHas('success');
        $this->assertSame(200, $transaction->fresh()->refunded_cents);
        $this->post('/admin/transactions/'.$transaction->id.'/refund', ['amount' => 3, 'reason' => 'Fixture final refund'])->assertSessionHas('success');
        $this->assertSame('refunded', $transaction->fresh()->status);
        $this->post('/admin/transactions/'.$transaction->id.'/refund', ['amount' => 1, 'reason' => 'Fixture excess refund'])->assertSessionHas('error');
        $this->assertSame(2, $this->gateway->refundCalls);
        $this->assertSame(500, $listing->fresh()->total_bid_cents);
    }

    public function test_negative_adjustment_cannot_create_negative_balance(): void
    {
        $listing = $this->listing();
        $this->expectException(\RuntimeException::class);
        app(BidService::class)->applyAdminAdjustment($listing, -1, 'Fixture adjustment');
    }

    public function test_tie_ranking_and_projection_respect_reached_time(): void
    {
        $one = $this->listing();
        $two = $this->listing();
        $this->paid($one);
        $this->travel(1)->seconds();
        $this->paid($two);
        $rank = app(RankingService::class);
        $this->assertSame(1, $rank->globalRank($one->fresh()));
        $this->assertSame(2, $rank->globalRank($two->fresh()));
        $this->assertSame(1, $rank->projectedGlobalRank($two->fresh(), 100));
    }

    public function test_owner_cannot_access_another_customers_listing(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $listing = $this->listing(['owner_user_id' => $owner->id]);
        $this->actingAs($other)->get('/submission/'.$listing->slug.'/manage')->assertForbidden();
        $this->actingAs($owner)->get('/submission/'.$listing->slug.'/manage')->assertOk();
    }

    public function test_login_codes_expire_are_single_use_and_limited(): void
    {
        $user = User::factory()->create();
        $record = CustomerLoginCode::create(['user_id' => $user->id, 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10)]);
        $data = ['email' => $user->email, 'code' => '123456'];
        $this->post('/customer/login/verify', $data)->assertRedirect('/customer');
        $this->assertAuthenticatedAs($user);
        $this->post('/customer/logout');
        $this->post('/customer/login/verify', $data)->assertSessionHasErrors('code');
        $this->assertGuest();
        $record->forceFill(['used_at' => null, 'expires_at' => now()->subMinute()])->save();
        $this->post('/customer/login/verify', $data)->assertSessionHasErrors('code');
        $record->forceFill(['expires_at' => now()->addMinutes(10), 'attempts' => 5])->save();
        $this->post('/customer/login/verify', $data)->assertSessionHasErrors('code');
    }

    public static function unsafeDestinations(): array
    {
        return array_map(fn ($ip) => [$ip], ['127.0.0.1', '10.1.2.3', '169.254.169.254', '192.168.1.1', '0.0.0.0']);
    }

    #[DataProvider('unsafeDestinations')]
    public function test_private_destinations_are_blocked(string $ip): void
    {
        $this->expectException(\RuntimeException::class);
        app(SafeMetadataService::class)->assertPublicDestination('http://'.$ip);
    }

    public function test_social_handles_normalize_without_network(): void
    {
        $this->assertSame('https://github.com/example', app(SafeMetadataService::class)->normalize('@example', 'github')['url']);
        $this->expectException(\RuntimeException::class);
        app(SafeMetadataService::class)->normalize('file:///etc/passwd');
    }
}
