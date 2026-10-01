<?php

namespace App\Services;

use App\Mail\ListingLiveMail;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class CustomerAccountService
{
    public function provisionFor(Listing $listing): User
    {
        $email = strtolower(trim((string) $listing->contact_email));
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $listing->name, 'password' => Hash::make(Str::random(64))]
        );
        if (! $listing->owner_user_id) {
            $listing->forceFill(['owner_user_id' => $user->id])->save();
        }

        try {
            $url = route('customer.login');
            Mail::to($email)->send(new ListingLiveMail($url));
        } catch (Throwable $error) {
            Log::warning('Customer access email could not be sent.', ['listing_id' => $listing->id, 'exception' => $error::class]);
        }

        return $user;
    }
}
