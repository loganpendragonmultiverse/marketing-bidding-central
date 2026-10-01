<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    public function record(string $action, string $reason, ?Model $model = null, ?array $before = null, ?array $after = null, ?Request $request = null): AuditLog
    {
        return AuditLog::query()->create([
            'admin_identifier' => (string) session('marketplace_admin', 'system'),
            'action' => $action,
            'auditable_type' => $model ? $model::class : null,
            'auditable_id' => $model?->getKey(),
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'ip_hash' => $request?->ip() ? hash_hmac('sha256', $request->ip(), (string) config('marketplace.analytics_salt')) : null,
            'created_at' => now(),
        ]);
    }
}
