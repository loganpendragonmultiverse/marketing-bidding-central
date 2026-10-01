<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('marketplace:admin {--email=}', function (): void {
    $email = trim((string) ($this->option('email') ?: $this->ask('Administrator email')));
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('A valid email is required.');

        return;
    }
    $password = (string) $this->secret('Administrator password (at least 12 characters)');
    $confirm = (string) $this->secret('Repeat password');
    if (strlen($password) < 12 || ! hash_equals($password, $confirm)) {
        $this->error('Passwords must match and contain at least 12 characters.');

        return;
    }
    $path = base_path('.env');
    if (! is_file($path)) {
        $this->error('Create .env from .env.example first.');

        return;
    }
    $text = file_get_contents($path);
    foreach (['MARKETPLACE_ADMIN_EMAIL' => $email, 'MARKETPLACE_ADMIN_PASSWORD_HASH' => Hash::make($password)] as $key => $value) {
        $line = $key.'="'.$value.'"';
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
        $text = preg_match($pattern, $text) ? preg_replace_callback($pattern, fn () => $line, $text) : rtrim($text).PHP_EOL.$line.PHP_EOL;
    }
    file_put_contents($path, $text, LOCK_EX);
    chmod($path, 0600);
    $this->info('Administrator configuration saved. Refresh config cache before signing in.');
})->purpose('Configure an administrator without storing a plaintext password');
