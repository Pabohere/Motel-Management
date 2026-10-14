<?php

use App\Models\User;
use App\Services\Audit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

Artisan::command('motel:create-manager', function () {
    $name = text('Manager name', required: true, validate: ['name' => 'required|string|max:150']);
    $email = text('Manager email', required: true, validate: ['email' => 'required|email|max:255|unique:users']);
    $secret = password('Password (at least 12 characters)', required: true, validate: ['password' => 'required|min:12|max:128']);
    DB::transaction(function () use ($name, $email, $secret) {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($secret), 'role' => 'manager', 'active' => true]);
        Audit::record('user.manager_bootstrapped', $user, null, $user->only(['name', 'email', 'role', 'active']));
    });
    $this->info('Manager created. You can now sign in.');
})->purpose('Create a manager account interactively, without default credentials');

Artisan::command('motel:prepare-demo', function () {
    if (! app()->environment('demo')) {
        $this->error('This command can only run in the demo environment.');

        return 1;
    }

    $email = getenv('DEMO_MANAGER_EMAIL');
    $secret = getenv('DEMO_MANAGER_PASSWORD');
    if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! is_string($secret) || strlen($secret) < 12) {
        $this->error('Set DEMO_MANAGER_EMAIL and DEMO_MANAGER_PASSWORD (at least 12 characters).');

        return 1;
    }

    $manager = User::firstOrCreate(
        ['email' => $email],
        ['name' => 'Demo Manager', 'password' => Hash::make($secret), 'role' => 'manager', 'active' => true],
    );
    if (! $manager->isManager() || ! $manager->active) {
        $this->error('The demo manager email belongs to an incompatible account.');

        return 1;
    }

    Artisan::call('db:seed', ['--class' => 'DemoDataSeeder', '--force' => true, '--no-interaction' => true]);
    $this->info('Demo manager and sample data are ready.');

    return 0;
})->purpose('Seed a disposable demo environment with an env-configured manager');
