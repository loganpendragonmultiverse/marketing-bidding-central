<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RankingLedgerExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        $start = CarbonImmutable::parse($data['start_date'], 'UTC')->startOfDay();
        $end = CarbonImmutable::parse($data['end_date'], 'UTC')->startOfDay();
        if ($start->diffInDays($end) > 365) {
            throw ValidationException::withMessages(['end_date' => 'Choose at most 366 calendar days.']);
        }

        // Capture a high-water mark so later inserts cannot extend an in-progress export.
        $lastId = Bid::query()->max('id') ?? 0;
        $query = Bid::query()->select([
            'id', 'created_at', 'listing_id', 'public_reference', 'payment_provider',
            'payment_status', 'currency', 'signed_delta_cents', 'previous_total_cents', 'new_total_cents',
        ])->where('created_at', '>=', $start)->where('created_at', '<', $end->addDay())
            ->where('id', '<=', $lastId)->orderBy('created_at')->orderBy('id');

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, [
                'timestamp_utc', 'listing_id', 'reference', 'source', 'status', 'currency',
                'signed_change_cents', 'prior_balance_cents', 'resulting_balance_cents',
            ], ',', '"', '');
            foreach ($query->lazy(500) as $bid) {
                fputcsv($output, [
                    $bid->created_at->utc()->format('Y-m-d\\TH:i:s\\Z'),
                    $bid->listing_id,
                    self::safeCell($bid->public_reference),
                    self::safeCell($bid->payment_provider),
                    self::safeCell($bid->payment_status),
                    self::safeCell($bid->currency),
                    $bid->signed_delta_cents,
                    $bid->previous_total_cents,
                    $bid->new_total_cents,
                ], ',', '"', '');
            }
            fclose($output);
        }, 'ranking-ledger-inspection-'.$data['start_date'].'-'.$data['end_date'].'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private static function safeCell(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[\\x00-\\x20=+@-]/', $value) ? "'".$value : $value;
    }
}
