<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_and_update_a_staff_biography_and_position(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager)->post(route('users.store'), [
            'name' => 'New staff', 'email' => 'newstaff@example.test', 'role' => 'staff',
            'password' => 'a-long-test-password', 'password_confirmation' => 'a-long-test-password',
            'position' => 'Receptionist', 'biography' => "Joined the front desk.\nSpeaks Burmese and English.",
        ])->assertSessionHasNoErrors();
        $staff = User::where('email', 'newstaff@example.test')->firstOrFail();
        $this->assertSame('Receptionist', $staff->position);
        $this->assertStringContainsString('Burmese', $staff->biography);
        $this->assertSame('staff', $staff->role);
        $this->assertSame('Receptionist', AuditLog::where('action', 'user.created')->firstOrFail()->after['position']);

        $this->put(route('users.update', $staff), [
            'name' => 'New staff', 'role' => 'staff', 'active' => 1,
            'position' => 'Shift supervisor', 'biography' => 'Five years of hospitality experience.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Shift supervisor', $staff->fresh()->position);
        $audit = AuditLog::where('action', 'user.updated')->firstOrFail();
        $this->assertSame('Receptionist', $audit->before['position']);
        $this->assertSame('Shift supervisor', $audit->after['position']);
        $this->get(route('users.index'))->assertOk()->assertSee('Shift supervisor')->assertSee('Five years of hospitality experience.');
    }

    public function test_staff_cannot_edit_profiles_or_gain_access_through_their_position(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'position' => 'General manager']);
        $this->assertFalse($staff->isManager());
        $this->actingAs($staff)->get(route('users.index'))->assertForbidden();
        $this->put(route('users.update', $staff), ['name' => 'Staff', 'role' => 'manager', 'active' => 1, 'biography' => 'Edited'])->assertForbidden();
        $this->assertNull($staff->fresh()->biography);
    }

    public function test_optional_profile_fields_preserve_older_updates_and_can_be_cleared(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'position' => 'Owner', 'biography' => 'Original biography']);
        $this->actingAs($manager)->put(route('users.update', $manager), ['name' => 'Owner', 'role' => 'manager', 'active' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Owner', $manager->fresh()->position);
        $this->assertSame('Original biography', $manager->fresh()->biography);
        $this->put(route('users.update', $manager), ['name' => 'Owner', 'role' => 'manager', 'active' => 1, 'position' => '', 'biography' => ''])->assertSessionHasNoErrors();
        $this->assertNull($manager->fresh()->position);
        $this->assertNull($manager->fresh()->biography);
    }

    public function test_profile_lengths_are_validated_and_biographies_render_as_text(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'biography' => '<script>alert(1)</script>']);
        $this->actingAs($manager)->put(route('users.update', $manager), [
            'name' => 'Manager', 'role' => 'manager', 'active' => 1,
            'position' => str_repeat('x', 121), 'biography' => str_repeat('x', 4001),
        ])->assertSessionHasErrors(['position', 'biography']);
        $this->get(route('users.index'))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_manager_can_upload_view_replace_and_remove_a_private_staff_photo(): void
    {
        Storage::fake('local');
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager)->post(route('users.store'), [
            'name' => 'Photo staff', 'email' => 'photo@example.test', 'role' => 'staff',
            'password' => 'a-long-test-password', 'password_confirmation' => 'a-long-test-password',
            'photo' => UploadedFile::fake()->image('portrait.jpg', 200, 240),
        ])->assertSessionHasNoErrors();
        $staff = User::where('email', 'photo@example.test')->firstOrFail();
        $originalPath = $staff->photo_path;
        Storage::disk('local')->assertExists($originalPath);
        $this->get(route('users.photo', $staff))->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('users.index'))->assertOk()->assertSee(route('users.photo', $staff), false)->assertSee('multipart/form-data', false);
        $attributes = ['name' => 'Photo staff', 'role' => 'staff', 'active' => 1];
        $this->put(route('users.update', $staff), $attributes)->assertSessionHasNoErrors();
        $this->assertSame($originalPath, $staff->fresh()->photo_path);
        $this->put(route('users.update', $staff), [...$attributes, 'photo' => UploadedFile::fake()->image('new.png')])->assertSessionHasNoErrors();
        $replacementPath = $staff->fresh()->photo_path;
        $this->assertNotSame($originalPath, $replacementPath);
        Storage::disk('local')->assertMissing($originalPath);
        Storage::disk('local')->assertExists($replacementPath);
        $audit = AuditLog::where('action', 'user.updated')->latest('id')->firstOrFail();
        $this->assertSame($originalPath, $audit->before['photo_path']);
        $this->assertSame($replacementPath, $audit->after['photo_path']);
        $this->actingAs($staff)->get(route('users.photo', $staff))->assertForbidden();
        $this->put(route('users.update', $staff), [...$attributes, 'remove_photo' => 1])->assertForbidden();
        $this->actingAs($manager)->put(route('users.update', $staff), [...$attributes, 'remove_photo' => 1])->assertSessionHasNoErrors();
        $this->assertNull($staff->fresh()->photo_path);
        Storage::disk('local')->assertMissing($replacementPath);
        $this->get(route('users.photo', $staff))->assertNotFound();
    }

    public function test_photo_validation_and_rejected_account_changes_preserve_existing_photo(): void
    {
        Storage::fake('local');
        $manager = User::factory()->create(['role' => 'manager', 'photo_path' => 'staff-photos/original.png']);
        Storage::disk('local')->put($manager->photo_path, 'original');
        $attributes = ['name' => 'Manager', 'role' => 'manager', 'active' => 1];
        foreach ([
            UploadedFile::fake()->create('document.pdf', 20, 'application/pdf'),
            UploadedFile::fake()->createWithContent('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->image('large.jpg')->size(2049),
            UploadedFile::fake()->image('wide.png', 4097, 10),
        ] as $file) {
            $this->actingAs($manager)->put(route('users.update', $manager), [...$attributes, 'photo' => $file])->assertSessionHasErrors('photo');
        }
        $this->put(route('users.update', $manager), [...$attributes, 'photo' => UploadedFile::fake()->image('new.png'), 'remove_photo' => 1])->assertSessionHasErrors('photo');
        $this->put(route('users.update', $manager), [...$attributes, 'role' => 'staff', 'photo' => UploadedFile::fake()->image('new.png')])->assertSessionHasErrors('operation');
        $this->assertSame('staff-photos/original.png', $manager->fresh()->photo_path);
        $this->assertSame(['staff-photos/original.png'], Storage::disk('local')->allFiles('staff-photos'));
    }
}
