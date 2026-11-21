<?php

use App\Models\User;
use App\Services\CommerceService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('admin:create {email?} {--name=Store Administrator}', function (): int {
    $email = strtolower($this->argument('email') ?? $this->ask('Admin email'));
    $name = $this->option('name');
    $password = $this->secret('Password (at least 12 characters, with letters and numbers)');
    $validator = Validator::make(['email' => $email, 'name' => $name, 'password' => $password], ['email' => 'required|email|max:255', 'name' => 'required|string|max:100', 'password' => ['required', Password::min(12)->letters()->numbers()]]);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }
    if (User::where('email', $email)->exists()) {
        $this->error('This email already has an account. No changes were made.');

        return 1;
    }
    $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
    $user->is_admin = true;
    $user->save();
    $this->info('Administrator created. Sign in at /login, then open /admin.');

    return 0;
})->purpose('Securely create an administrator without a default password');
Artisan::command('commerce:expire', function (CommerceService $commerce): void {
    $commerce->expire();
    $this->info('Expired checkout reservations released.');
})->purpose('Release expired stock holds');
Schedule::command('commerce:expire')->everyMinute()->withoutOverlapping();
