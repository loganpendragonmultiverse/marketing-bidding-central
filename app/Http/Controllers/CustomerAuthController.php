<?php

namespace App\Http\Controllers;

use App\Mail\CustomerLoginCodeMail;
use App\Models\CustomerLoginCode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function login(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('customer.dashboard') : view('customer.login');
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $email = strtolower($request->validate(['email' => ['required', 'email', 'max:190']])['email']);
        $user = User::query()->where('email', $email)->first();
        if ($user) {
            CustomerLoginCode::query()->where('user_id', $user->id)->delete();
            $code = (string) random_int(100000, 999999);
            CustomerLoginCode::query()->create(['user_id' => $user->id, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(10)]);
            Mail::to($email)->send(new CustomerLoginCodeMail($code));
        }

        return back()->with('code_email', $email)->with('success', 'If that email has a customer account, a six-digit code is on its way.');
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'code' => ['required', 'digits:6']]);
        $user = User::query()->where('email', strtolower($data['email']))->first();
        $record = $user ? CustomerLoginCode::query()->where('user_id', $user->id)->whereNull('used_at')->where('expires_at', '>', now())->latest()->first() : null;
        if (! $record || $record->attempts >= 5 || ! Hash::check($data['code'], $record->code_hash)) {
            if ($record) {
                $record->increment('attempts');
            }

            return back()->with('code_email', $data['email'])->withErrors(['code' => 'That code is invalid or expired.']);
        }
        $record->forceFill(['used_at' => now()])->save();
        $user->forceFill(['email_verified_at' => $user->email_verified_at ?: now()])->save();
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('customer.dashboard');
    }

    public function dashboard(Request $request): View
    {
        return view('customer.dashboard', ['listings' => $request->user()->listings()->with('category')->latest()->get()]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login');
    }
}
