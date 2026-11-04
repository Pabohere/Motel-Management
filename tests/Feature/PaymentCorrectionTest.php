<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Floor;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Stay;
use App\Models\User;
use App\Services\FrontDesk;
use App\Services\MonthlyFinancialStatements;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private Stay $stay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-04 10:00'));
        $this->actingAs(User::factory()->create(['role' => 'manager']));
        $room = Room::create(['number' => '101', 'floor_id' => Floor::create(['number' => 1])->id, 'room_type_id' => RoomType::create(['name' => 'Standard'])->id, 'rate' => 30000]);
        $this->stay = app(FrontDesk::class)->checkIn(['room_id' => $room->id, 'phone' => '09', 'gender' => 'female', 'guest_name' => 'Ledger guest', 'check_in_at' => now()->toDateTimeString(), 'expected_check_out_at' => now()->addDay()->toDateTimeString(), 'room_charge' => 30000, 'payment_term' => 'credit', 'deposit' => 0, 'method' => 'cash']);
    }

    public function test_retries_are_idempotent_and_changed_payload_is_rejected(): void
    {
        $data = ['amount' => 10000, 'method' => 'transfer', 'reference' => 'BANK-123', 'request_key' => (string) Str::uuid()];
        foreach ([1, 2] as $attempt) {
            $this->post(route('stays.payments', $this->stay), $data)->assertSessionHasNoErrors();
        }
        $this->post(route('stays.payments', $this->stay), [...$data, 'amount' => 12000])->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(20000, $this->stay->fresh()->balance);
        $this->assertSame(1, AuditLog::where('action', 'payment.created')->count());
    }

    public function test_manager_can_partially_reverse_and_replace_without_mutating_original(): void
    {
        $original = app(FrontDesk::class)->payment($this->stay, ['amount' => 30000, 'method' => 'cash']);
        $data = ['amount' => 12000, 'reason' => 'Incorrect payment method', 'request_key' => (string) Str::uuid()];
        foreach ([1, 2] as $attempt) {
            $this->post(route('stays.payments.reverse', [$this->stay, $original]), $data)->assertSessionHasNoErrors();
        }
        $this->assertSame(12000, $this->stay->fresh()->balance);
        $this->assertSame(30000, $original->fresh()->amount);
        $this->assertDatabaseCount('payments', 2);
        $this->post(route('stays.payments.reverse', [$this->stay, $original]), [...$data, 'amount' => 18001, 'request_key' => (string) Str::uuid()])->assertSessionHasErrors('operation');
        $this->post(route('stays.payments', $this->stay), ['amount' => 12000, 'method' => 'transfer', 'reference' => 'CORRECTED', 'request_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->stay->fresh()->balance);
        $this->assertSame(30000, (int) Payment::sum('amount'));
        $report = app(MonthlyFinancialStatements::class)->build(Carbon::parse('2026-11-01'), Carbon::parse('2026-12-01'));
        $this->assertSame(30000, $report['closingBalances']['cash']);
        $this->assertSame(0, $report['balanceDifference']);
        $this->assertSame(0, $report['cashDifference']);
        $this->get(route('stays.show', $this->stay))->assertOk()->assertSee('CORRECTED');
    }

    public function test_staff_cannot_reverse_and_payments_cannot_be_silently_edited_or_deleted(): void
    {
        $payment = app(FrontDesk::class)->payment($this->stay, ['amount' => 1000, 'method' => 'cash']);
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->post(route('stays.payments.reverse', [$this->stay, $payment]), ['amount' => 1000, 'reason' => 'Mistake', 'request_key' => (string) Str::uuid()])->assertForbidden();
        foreach (['update', 'delete'] as $operation) {
            try {
                $operation === 'update' ? $payment->update(['amount' => 999]) : $payment->delete();
                $this->fail('Immutable ledger was changed.');
            } catch (\LogicException $error) {
                $this->assertStringContainsString('ayment', $error->getMessage());
            }
        }
        $this->assertSame(1000, $payment->fresh()->amount);
    }
}
