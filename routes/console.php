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
