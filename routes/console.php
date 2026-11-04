<?php

use App\Models\Reservation;
use App\Models\Room;
use App\Models\Stay;
use App\Models\User;
use App\Services\Audit;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;

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

Artisan::command('motel:seed-workflow-demo {--allow-production : Explicitly authorize sample business records on the live site}', function () {
    if (app()->environment('production')) {
        if (! $this->option('allow-production')) {
            $this->error('Use --allow-production to explicitly authorize demo records in live business reports.');

            return 1;
        }
        $directory = config('app.backup_directory') ?: dirname(base_path()).'/motel-backups';
        if ($this->call('motel:backup', ['directory' => $directory]) !== 0) {
            $this->error('Demo data was not added because the backup did not pass verification.');

            return 1;
        }
    }
    app(DemoDataSeeder::class)->seedWorkflow((bool) $this->option('allow-production'));
    $this->info('DEMO WORKFLOW READY: sample records are labelled Demo. Existing business records are preserved.');
    $this->table(['Record', 'Demo count'], [
        ['Rooms', Room::whereHas('roomType', fn ($query) => $query->where('name', 'like', 'Demo %'))->count()],
        ['Stays', Stay::where('phone', 'like', '099900%')->count()],
        ['Reservations', Reservation::where('notes', 'like', 'DEMO-WORKFLOW:%')->count()],
    ]);

    return 0;
})->purpose('Add repeatable workflow samples; production requires explicit authorization and a verified backup');

if (config('app.backup_directory')) {
    Schedule::command('motel:backup', [config('app.backup_directory')])->dailyAt('03:00')->withoutOverlapping()->appendOutputTo(storage_path('logs/backup.log'));
}
