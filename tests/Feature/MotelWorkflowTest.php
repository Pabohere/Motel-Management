<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\Room;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MotelWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(10, 0));
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->room = Room::create(['number' => '101', 'type' => 'Standard', 'rate' => 35000]);
    }

    private function checkIn(array $extra = []): Stay
    {
        $this->actingAs($this->staff)->post(route('stays.store'), array_replace([
            'room_id' => $this->room->id, 'guest_name' => 'Test Guest', 'phone' => '09123456789',
            'check_in_at' => now()->subMinute()->format('Y-m-d H:i:s'), 'expected_check_out_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'room_charge' => 35000, 'deposit' => 10000, 'method' => 'cash',
        ], $extra))->assertSessionHasNoErrors()->assertRedirect();

        return Stay::latest('id')->firstOrFail();
    }

    public function test_guest_routes_require_login(): void
    {
        foreach (['/', '/rooms', '/stays', '/reports/daily', '/users', '/audit'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->get('/login')->assertOk()->assertSee('name="password"', false);
    }

    public function test_login_logout_and_audit(): void
    {
        $this->staff->update(['password' => 'a-long-test-password']);
        $this->post('/login', ['email' => $this->staff->email, 'password' => 'a-long-test-password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($this->staff);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $this->staff->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.logout', 'user_id' => $this->staff->id]);
    }

    public function test_disabled_accounts_cannot_login_or_keep_using_a_session(): void
    {
        $this->staff->update(['active' => false, 'password' => 'a-long-test-password']);
        $this->post('/login', ['email' => $this->staff->email, 'password' => 'a-long-test-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($this->staff)->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_complete_front_desk_and_money_workflow(): void
    {
        $stay = $this->checkIn();
        $this->assertSame('occupied', $this->room->fresh()->status);
        $this->assertSame(25000, $stay->balance);
        $this->post(route('stays.charges', $stay), ['description' => 'Extra bed', 'amount' => 5000])->assertSessionHasNoErrors();
        $this->assertSame(30000, $stay->fresh()->balance);
        $this->post(route('stays.payments', $stay), ['amount' => 30000, 'method' => 'transfer'])->assertSessionHasNoErrors();
        $this->post(route('stays.checkout', $stay))->assertSessionHasNoErrors();
        $this->assertNotNull($stay->fresh()->check_out_at);
        $this->assertNull($stay->fresh()->active_room_id);
        $this->assertSame('dirty', $this->room->fresh()->status);
        $this->patch(route('rooms.status', $this->room), ['status' => 'available'])->assertSessionHasNoErrors();
        $this->assertSame('available', $this->room->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'room.cleaned', 'user_id' => $this->staff->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.created', 'user_id' => $this->staff->id]);
    }

    public function test_outstanding_balance_blocks_checkout_and_overpayment(): void
    {
        $stay = $this->checkIn();
        $this->post(route('stays.checkout', $stay))->assertSessionHasErrors('operation');
        $this->post(route('stays.payments', $stay), ['amount' => 25001, 'method' => 'cash'])->assertSessionHasErrors('operation');
        $this->assertNull($stay->fresh()->check_out_at);
        $this->assertSame(1, $stay->payments()->count());
    }

    public function test_unavailable_room_and_double_checkin_are_rejected(): void
    {
        $this->checkIn();
        $count = Stay::count();
        $this->post(route('stays.store'), ['room_id' => $this->room->id, 'guest_name' => 'Second', 'phone' => '09',
            'check_in_at' => now()->subMinute()->toDateTimeString(), 'expected_check_out_at' => now()->addDay()->toDateTimeString(),
            'room_charge' => 35000, 'deposit' => 0, 'method' => 'cash'])->assertSessionHasErrors('operation');
        $this->assertSame($count, Stay::count());
    }

    public function test_database_guards_against_two_active_stays(): void
    {
        $stay = $this->checkIn();
        $copy = $stay->replicate();
        $this->expectException(QueryException::class);
        $copy->save();
    }

    public function test_staff_cannot_manage_rooms_users_audit_or_maintenance(): void
    {
        $this->actingAs($this->staff);
        foreach (['/users', '/audit', route('rooms.edit', $this->room)] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/rooms', ['number' => '102', 'type' => 'Deluxe', 'rate' => 100])->assertForbidden();
        $this->post('/users', [])->assertForbidden();
        $this->put(route('rooms.update', $this->room), ['rate' => 1])->assertForbidden();
        $this->patch(route('rooms.status', $this->room), ['status' => 'maintenance'])->assertSessionHasErrors('operation');
        $this->room->update(['status' => 'maintenance']);
        $this->patch(route('rooms.status', $this->room), ['status' => 'available'])->assertSessionHasErrors('operation');
    }

    public function test_occupied_room_cannot_be_marked_clean_even_by_manager(): void
    {
        $this->checkIn();
        $this->staff->update(['role' => 'manager']);
        $this->patch(route('rooms.status', $this->room), ['status' => 'available'])->assertSessionHasErrors('operation');
        $this->assertSame('occupied', $this->room->fresh()->status);
    }

    public function test_manager_room_and_account_administration_is_audited(): void
    {
        $this->staff->update(['role' => 'manager']);
        $this->actingAs($this->staff);
        $this->post('/rooms', ['number' => '102', 'type' => 'Deluxe', 'rate' => 50000])->assertSessionHasNoErrors();
        $this->patch(route('rooms.status', $this->room), ['status' => 'maintenance'])->assertSessionHasNoErrors();
        $this->put(route('rooms.update', $this->room), ['number' => '101', 'type' => 'Updated', 'rate' => 45000])->assertSessionHasNoErrors();
        $this->post('/users', ['name' => 'Reception', 'email' => 'staff@example.test', 'password' => 'a-long-test-password', 'password_confirmation' => 'a-long-test-password', 'role' => 'staff'])->assertSessionHasNoErrors();
        $user = User::where('email', 'staff@example.test')->firstOrFail();
        $this->put(route('users.update', $user), ['name' => 'Reception', 'role' => 'staff', 'active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->active);
        $this->put(route('users.update', $this->staff), ['name' => 'Me', 'role' => 'staff', 'active' => 0])->assertSessionHasErrors('operation');
        $this->assertDatabaseHas('audit_logs', ['action' => 'room.updated']);
        $this->assertStringNotContainsString('a-long-test-password', AuditLog::all()->toJson());
    }

    public function test_negative_fractional_and_invalid_payments_are_rejected(): void
    {
        $stay = $this->checkIn();
        foreach ([-1, 0, 1.5] as $amount) {
            $this->post(route('stays.payments', $stay), ['amount' => $amount, 'method' => 'cash'])->assertSessionHasErrors('amount');
        }
        $this->post(route('stays.payments', $stay), ['amount' => 100, 'method' => 'unknown'])->assertSessionHasErrors('method');
        $this->post(route('stays.charges', $stay), ['description' => 'Extra', 'amount' => -100])->assertSessionHasErrors('amount');
    }

    public function test_guest_edits_and_completed_stay_guards(): void
    {
        $stay = $this->checkIn(['deposit' => 35000]);
        $data = ['guest_name' => 'Updated Guest', 'phone' => '09888', 'expected_check_out_at' => now()->addDays(2)->toDateTimeString(), 'notes' => 'Late departure'];
        $this->put(route('stays.update', $stay), $data)->assertSessionHasNoErrors();
        $this->assertSame('Updated Guest', $stay->fresh()->guest_name);
        $this->post(route('stays.checkout', $stay))->assertSessionHasNoErrors();
        $this->post(route('stays.checkout', $stay))->assertSessionHasErrors('operation');
        $this->put(route('stays.update', $stay), $data)->assertSessionHasErrors('operation');
        $this->post(route('stays.charges', $stay), ['description' => 'Late extra', 'amount' => 100])->assertSessionHasErrors('operation');
    }

    public function test_daily_report_uses_yangon_day_boundaries_and_historical_occupancy(): void
    {
        $this->room->update(['created_at' => now()->subDays(3)]);
        $this->travelTo(now()->subDay()->setTime(23, 59));
        $stay = $this->checkIn(['deposit' => 0]);
        $this->post(route('stays.payments', $stay), ['amount' => 10000, 'method' => 'cash'])->assertSessionHasNoErrors();
        $this->travelTo(now()->addDay()->setTime(0, 1));
        $this->post(route('stays.payments', $stay), ['amount' => 25000, 'method' => 'card'])->assertSessionHasNoErrors();
        $this->get('/reports/daily?date=2026-10-12')->assertOk()->assertViewHas('payments', fn ($payments) => $payments->sum('amount') === 10000)->assertViewHas('occupied', 1);
        $this->post(route('stays.checkout', $stay))->assertSessionHasNoErrors();
        $this->get('/reports/daily?date=2026-10-13')->assertOk()->assertViewHas('payments', fn ($payments) => $payments->sum('amount') === 25000)->assertViewHas('occupied', 0);
        $this->get('/reports/daily?date=2026-10-12')->assertViewHas('occupied', 1);
        $this->get('/reports/daily?date=2026-10-14')->assertSessionHasErrors('date');
    }

    public function test_all_views_render_with_live_and_completed_data(): void
    {
        $stay = $this->checkIn();
        foreach (['/', '/rooms', '/rooms?status=occupied', '/stays', '/stays?status=active', '/stays?q=Test', '/stays/create', route('stays.show', $stay), '/reports/daily', '/password'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->staff->update(['role' => 'manager']);
        foreach (['/users', '/audit', route('rooms.edit', $this->room)] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_password_change_requires_current_password(): void
    {
        $this->staff->update(['password' => 'initial-long-password']);
        $this->actingAs($this->staff);
        $this->put('/password', ['current_password' => 'wrong', 'password' => 'new-long-password', 'password_confirmation' => 'new-long-password'])->assertSessionHasErrors('current_password');
        $this->put('/password', ['current_password' => 'initial-long-password', 'password' => 'new-long-password', 'password_confirmation' => 'new-long-password'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-long-password', $this->staff->fresh()->password));
    }

    public function test_monthly_report_counts_received_money_and_dated_expenses_with_manager_access(): void
    {
        $stay = $this->checkIn();
        $this->get(route('reports.monthly'))->assertForbidden();
        $this->get(route('reports.monthly.export'))->assertForbidden();
        $this->post(route('expenses.store'), [])->assertForbidden();

        $this->staff->update(['role' => 'manager']);
        $this->actingAs($this->staff);
        $this->post(route('expenses.store'), ['expense_date' => '2026-09-30', 'category' => 'utilities', 'description' => 'September power', 'amount' => 3000])->assertSessionHasNoErrors();
        $this->post(route('expenses.store'), ['expense_date' => '2026-10-12', 'category' => 'maintenance', 'description' => 'Repair', 'amount' => 4000])->assertSessionHasNoErrors();
        $this->post(route('expenses.store'), ['expense_date' => '2026-10-14', 'category' => 'other', 'description' => 'Future', 'amount' => 100])->assertSessionHasErrors('expense_date');
        $this->post(route('expenses.store'), ['expense_date' => '2026-10-13', 'category' => 'other', 'description' => 'Invalid', 'amount' => -1])->assertSessionHasErrors('amount');
        $this->assertSame(2, Expense::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'expense.created', 'user_id' => $this->staff->id]);

        $this->get(route('reports.monthly', ['month' => '2026-10']))->assertOk()->assertViewHas('income', 10000)->assertViewHas('outgoings', 4000)->assertViewHas('net', 6000)->assertSee('Repair');
        $this->get(route('reports.monthly', ['month' => '2026-09']))->assertOk()->assertViewHas('income', 0)->assertViewHas('outgoings', 3000)->assertViewHas('net', -3000);
        $this->get(route('reports.monthly', ['month' => 'not-a-month']))->assertSessionHasErrors('month');
        $csv = $this->get(route('reports.monthly.export', ['month' => '2026-10']))->assertOk()->streamedContent();
        $this->assertStringContainsString('2026-10', $csv);
        $this->assertStringContainsString('Repair', $csv);
        $this->assertStringNotContainsString('September power', $csv);
    }

    public function test_up_to_five_identity_files_have_private_inline_previews(): void
    {
        Storage::fake('local');
        $stay = $this->checkIn(['identity_documents' => [
            UploadedFile::fake()->create('front.jpg', 20, 'image/jpeg'),
            UploadedFile::fake()->create('back.png', 20, 'image/png'),
            UploadedFile::fake()->create('letter.pdf', 20, 'application/pdf'),
        ]]);
        $this->assertSame(3, $stay->identityDocuments()->count());
        $first = $stay->identityDocuments()->firstOrFail();
        Storage::disk('local')->assertExists($first->path);
        $this->get(route('stays.index'))->assertOk()->assertSee('3 ဖိုင်');
        $this->get(route('stays.show', $stay))->assertOk()->assertSee('identity-preview-dialog');
        $preview = $this->get(route('stays.identity.show', [$stay, $first]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringStartsWith('inline;', $preview->headers->get('Content-Disposition'));
        $this->get(route('stays.identity', $stay))->assertOk();
        $pdf = $stay->identityDocuments()->orderBy('id')->skip(2)->firstOrFail();
        $this->get(route('stays.identity.show', [$stay, $pdf]))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $update = ['guest_name' => 'Test Guest', 'phone' => '09123456789', 'expected_check_out_at' => now()->addDay()->toDateTimeString()];
        $this->put(route('stays.update', $stay), [...$update, 'identity_documents' => [
            UploadedFile::fake()->create('extra-1.webp', 20, 'image/webp'),
            UploadedFile::fake()->create('extra-2.jpg', 20, 'image/jpeg'),
        ]])->assertSessionHasNoErrors();
        $this->assertSame(5, $stay->identityDocuments()->count());
        $this->put(route('stays.update', $stay), [...$update, 'identity_documents' => [
            UploadedFile::fake()->create('sixth.jpg', 20, 'image/jpeg'),
        ]])->assertSessionHasErrors('identity_documents');
        $this->assertSame(5, $stay->identityDocuments()->count());
        Storage::disk('local')->assertExists($first->path);

        $this->post(route('stays.store'), [
            'room_id' => $this->room->id, 'guest_name' => 'Bad file', 'phone' => '09',
            'check_in_at' => now()->subMinute()->toDateTimeString(), 'expected_check_out_at' => now()->addDay()->toDateTimeString(),
            'room_charge' => 35000, 'deposit' => 0, 'method' => 'cash',
            'identity_documents' => [UploadedFile::fake()->create('script.txt', 1, 'text/plain')],
        ])->assertSessionHasErrors('identity_documents.0');
        $this->put(route('stays.update', $stay), [...$update, 'identity_documents' => [
            UploadedFile::fake()->create('large.pdf', 6000, 'application/pdf'),
        ]])->assertSessionHasErrors('identity_documents.0');

        $otherRoom = Room::create(['number' => '102', 'type' => 'Standard', 'rate' => 35000]);
        $otherStay = Stay::create([
            'room_id' => $otherRoom->id, 'active_room_id' => $otherRoom->id, 'guest_name' => 'Other guest',
            'phone' => '09', 'check_in_at' => now(), 'expected_check_out_at' => now()->addDay(),
            'room_charge' => 35000, 'created_by' => $this->staff->id,
        ]);
        $this->get(route('stays.identity.show', [$otherStay, $first]))->assertNotFound();
        $this->post(route('stays.payments', $stay), ['amount' => 25000, 'method' => 'cash'])->assertSessionHasNoErrors();
        $this->post(route('stays.checkout', $stay))->assertSessionHasNoErrors();
        $this->get(route('stays.identity.show', [$stay, $first]))->assertOk();
        $this->put(route('stays.update', $stay), $update)->assertSessionHasErrors('operation');
        auth()->logout();
        $this->get(route('stays.identity.show', [$stay, $first]))->assertRedirect('/login');
    }

    public function test_six_identity_uploads_are_rejected_before_check_in_and_failed_check_in_cleans_files(): void
    {
        Storage::fake('local');
        $data = [
            'room_id' => $this->room->id, 'guest_name' => 'Guest', 'phone' => '09',
            'check_in_at' => now()->subMinute()->toDateTimeString(), 'expected_check_out_at' => now()->addDay()->toDateTimeString(),
            'room_charge' => 35000, 'deposit' => 0, 'method' => 'cash',
        ];
        $this->actingAs($this->staff)->post(route('stays.store'), [...$data, 'identity_documents' => array_map(
            fn (int $number) => UploadedFile::fake()->create("identity-$number.jpg", 20, 'image/jpeg'),
            range(1, 6)
        )])->assertSessionHasErrors('identity_documents');
        $this->assertSame(0, Stay::count());
        $this->assertSame([], Storage::disk('local')->allFiles('guest-identities'));

        $this->room->update(['status' => 'maintenance']);
        $this->post(route('stays.store'), [...$data, 'identity_documents' => [UploadedFile::fake()->create('id.jpg', 20, 'image/jpeg')]])->assertSessionHasErrors('operation');
        $this->assertSame([], Storage::disk('local')->allFiles('guest-identities'));
    }

    public function test_monthly_income_uses_yangon_month_boundaries(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(23, 59));
        $stay = $this->checkIn(['deposit' => 0]);
        $this->post(route('stays.payments', $stay), ['amount' => 10000, 'method' => 'cash'])->assertSessionHasNoErrors();
        $this->travelTo(now()->addMinutes(2));
        $this->post(route('stays.payments', $stay), ['amount' => 25000, 'method' => 'card'])->assertSessionHasNoErrors();
        $this->staff->update(['role' => 'manager']);
        $this->get(route('reports.monthly', ['month' => '2026-10']))->assertViewHas('income', 10000);
        $this->get(route('reports.monthly', ['month' => '2026-11']))->assertViewHas('income', 25000);
    }
}
