<?php

namespace App\Services;

use App\Models\DomainBan;
use App\Models\Listing;
use App\Models\ListingAccessToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ListingService
{
    public function __construct(private readonly SafeMetadataService $metadata) {}

    public function create(array $data, ?UploadedFile $logo = null): array
    {
        $normalized = $this->metadata->normalize($data['url'], $data['platform'] ?? null);
        $this->assertDomainAllowed($normalized['host']);
        $this->metadata->assertPublicDestination($normalized['url']);

        if (Listing::withTrashed()->where('normalized_url', $normalized['url'])->exists()) {
            throw new RuntimeException('That website or profile already has a listing. Use customer login to manage it.');
        }

        $metadata = [];
        $metadataStatus = 'failed';
        try {
            $metadata = $this->metadata->inspect($normalized['url']);
            $metadataStatus = 'complete';
        } catch (Throwable $error) {
            $metadata = ['error' => mb_substr($error->getMessage(), 0, 240)];
        }

        $plainToken = Str::random(64);

        $listing = DB::transaction(function () use ($data, $logo, $normalized, $metadata, $metadataStatus, $plainToken): Listing {
            $baseSlug = Str::slug($data['name']) ?: 'project';
            $slug = $baseSlug;
            $suffix = 2;
            while (Listing::withTrashed()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }

            $logoPath = $logo?->storePublicly('logos', 'public');
            $listing = Listing::query()->create([
                'category_id' => $data['category_id'],
                'public_id' => (string) Str::uuid(),
                'name' => $data['name'],
                'slug' => $slug,
                'url' => $normalized['url'],
                'normalized_url' => $normalized['url'],
                'destination_host' => $normalized['host'],
                'short_description' => $data['short_description'] ?: ($metadata['description'] ?? ''),
                'description' => $data['description'] ?? null,
                'logo_path' => $logoPath,
                'logo_url' => $metadata['favicon_url'] ?? null,
                'image_url' => $metadata['image_url'] ?? null,
                'contact_email' => $data['contact_email'],
                'metadata_status' => $metadataStatus,
                'metadata_json' => $metadata,
                'status' => Listing::STATUS_PENDING,
            ]);

            ListingAccessToken::query()->create([
                'listing_id' => $listing->id,
                'token_hash' => hash('sha256', $plainToken),
                'purpose' => 'manage',
                'expires_at' => now()->addHours((int) config('marketplace.owner_token_hours')),
            ]);

            return $listing;
        });

        return [$listing, $plainToken];
    }

    public function authorizeOwner(Listing $listing, string $token): bool
    {
        if (strlen($token) < 32) {
            return false;
        }

        $access = ListingAccessToken::query()
            ->where('listing_id', $listing->id)
            ->where('purpose', 'manage')
            ->where('expires_at', '>', now())
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $access) {
            return false;
        }

        $access->forceFill(['used_at' => now()])->save();

        return true;
    }

    public function update(Listing $listing, array $data, ?UploadedFile $logo = null): Listing
    {
        if (! in_array($listing->status, [Listing::STATUS_PENDING, Listing::STATUS_ACTIVE], true)) {
            throw new RuntimeException('This listing cannot be edited in its current state.');
        }

        $normalized = $this->metadata->normalize($data['url'], $data['platform'] ?? null);
        $this->assertDomainAllowed($normalized['host']);
        $this->metadata->assertPublicDestination($normalized['url']);

        if (Listing::withTrashed()->where('normalized_url', $normalized['url'])->whereKeyNot($listing->getKey())->exists()) {
            throw new RuntimeException('That website or profile already belongs to another listing.');
        }

        $metadata = $listing->metadata_json ?? [];
        $metadataStatus = $listing->metadata_status;
        if ($normalized['url'] !== $listing->normalized_url) {
            try {
                $metadata = $this->metadata->inspect($normalized['url']);
                $metadataStatus = 'complete';
            } catch (Throwable $error) {
                $metadata = ['error' => mb_substr($error->getMessage(), 0, 240)];
                $metadataStatus = 'failed';
            }
        }

        $changes = [
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'url' => $normalized['url'],
            'normalized_url' => $normalized['url'],
            'destination_host' => $normalized['host'],
            'short_description' => $data['short_description'],
            'description' => $data['description'] ?? null,
            'metadata_status' => $metadataStatus,
            'metadata_json' => $metadata,
        ];
        if ($logo) {
            $changes['logo_path'] = $logo->storePublicly('logos', 'public');
        }

        $listing->forceFill($changes)->save();

        return $listing->fresh();
    }

    private function assertDomainAllowed(string $host): void
    {
        $banned = DomainBan::query()->pluck('normalized_domain')->contains(function (string $domain) use ($host): bool {
            return $host === $domain || str_ends_with($host, '.'.$domain);
        });

        if ($banned) {
            throw new RuntimeException('This domain is not eligible for listing.');
        }
    }
}
