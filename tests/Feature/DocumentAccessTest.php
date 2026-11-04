<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Audit;
use App\Services\FrontDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_independent_permissions_private_access_logging_and_explicit_deletion(): void
    {
        Storage::fake('local');
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager);
        $room = Room::create(['number' => '101', 'floor_id' => Floor::create(['number' => 1])->id, 'room_type_id' => RoomType::create(['name' => 'Standard'])->id, 'rate' => 10000]);
        $stay = app(FrontDesk::class)->checkIn(['room_id' => $room->id, 'phone' => '09', 'gender' => 'female', 'guest_name' => 'Private guest', 'check_in_at' => now()->toDateTimeString(), 'expected_check_out_at' => now()->addDay()->toDateTimeString(), 'room_charge' => 10000, 'payment_term' => 'credit', 'deposit' => 0]);
        $path = UploadedFile::fake()->image('id.jpg')->store('guest-identities', 'local');
        $document = $stay->identityDocuments()->create(['path' => $path]);
        $url = route('stays.identity.show', [$stay, $document]);
        $staff = User::factory()->create(['can_view_documents' => false, 'can_upload_documents' => false]);
        $this->actingAs($staff)->get($url)->assertForbidden();
        $this->get(route('stays.show', $stay))->assertOk()->assertDontSee($url, false);
        $this->put(route('stays.update', $stay), ['gender' => 'female', 'phone' => '09', 'guest_name' => $stay->guest_name, 'expected_check_out_at' => $stay->expected_check_out_at->toDateTimeString(), 'identity_documents' => [UploadedFile::fake()->image('another.jpg')]])->assertForbidden();
        $staff->update(['can_view_documents' => true]);
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.viewed', 'user_id' => $staff->id, 'ip_address' => '127.0.0.1']);
        $this->delete(route('stays.identity.delete', [$stay, $document]))->assertForbidden();
        $this->actingAs($manager)->delete(route('stays.identity.delete', [$stay, $document]))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($path);
        $this->assertSoftDeleted($document);
        $this->assertDatabaseHas('stays', ['id' => $stay->id]);
        $this->get($url)->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.deleted']);
    }

    public function test_audit_is_searchable_sanitized_and_append_only(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager);
        Audit::record('test.secret', $manager, null, ['password' => 'never-show', 'nested' => ['session_token' => 'never-show', 'safe' => 'okay']]);
        $log = AuditLog::where('action', 'test.secret')->firstOrFail();
        $this->assertSame(['nested' => ['safe' => 'okay']], $log->after);
        $this->get(route('audit.index', ['q' => 'test.secret']))->assertOk()->assertSee('test.secret')->assertDontSee('never-show');
        $this->expectException(\LogicException::class);
        $log->delete();
    }
}
