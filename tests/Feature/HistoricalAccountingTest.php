<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Floor;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\FrontDesk;
use App\Services\MonthlyFinancialStatements;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HistoricalAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_later_extension_and_checkout_do_not_rewrite_prior_month_revenue(): void
    {
        $this->travelTo(Carbon::parse('2026-10-31 14:00'));
        $user = User::factory()->create(['role' => 'manager']);
        $this->actingAs($user);
        $room = Room::create(['number' => '101', 'floor_id' => Floor::create(['number' => 1])->id, 'room_type_id' => RoomType::create(['name' => 'Standard'])->id, 'rate' => 10000]);
        $stay = app(FrontDesk::class)->checkIn(['room_id' => $room->id, 'guest_name' => 'Month boundary', 'gender' => 'female', 'phone' => '09', 'check_in_at' => now()->toDateTimeString(), 'expected_check_out_at' => '2026-11-02 12:00:00', 'room_charge' => 20000, 'deposit' => 5000, 'method' => 'cash', 'payment_term' => 'credit']);
        $this->travelTo(Carbon::parse('2026-11-01 10:00'));
        $report = fn () => app(MonthlyFinancialStatements::class)->build(Carbon::parse('2026-10-01'), Carbon::parse('2026-11-01'));
        $before = $report();
        $this->assertSame(10000, $before['earnedRevenue']);
        $this->assertSame(5000, $before['closingBalances']['receivables']);
        $this->put(route('stays.update', $stay), ['guest_name' => $stay->guest_name, 'gender' => 'female', 'phone' => '09', 'expected_check_out_at' => '2026-11-04 12:00:00'])->assertSessionHasNoErrors();
        $this->assertSame($before['closingBalances'], $report()['closingBalances']);
        app(FrontDesk::class)->checkOut($stay);
        $after = $report();
        $this->assertSame($before['closingBalances'], $after['closingBalances']);
        $this->assertSame(0, $after['balanceDifference']);
        $this->assertSame(0, $after['cashDifference']);
    }

    public function test_cross_month_late_payment_refund_and_expense_reconcile_independently(): void
    {
        $this->travelTo(Carbon::parse('2026-10-31 14:00'));
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager);
        $room = Room::create(['number' => '101', 'floor_id' => Floor::create(['number' => 1])->id, 'room_type_id' => RoomType::create(['name' => 'Standard'])->id, 'rate' => 10000]);
        $desk = app(FrontDesk::class);
        $stay = $desk->checkIn(['room_id' => $room->id, 'guest_name' => 'Reconciliation guest', 'gender' => 'female', 'phone' => '09', 'check_in_at' => now()->toDateTimeString(), 'expected_check_out_at' => '2026-11-02 12:00:00', 'room_charge' => 20000, 'deposit' => 5000, 'method' => 'cash', 'payment_term' => 'credit']);
        $this->travelTo(Carbon::parse('2026-11-01 10:00'));
        $desk->charge($stay, ['description' => 'Extra services', 'amount' => 3000]);
        $this->travelTo(Carbon::parse('2026-11-02 10:00'));
        $payment = $desk->payment($stay, ['amount' => 18000, 'method' => 'transfer']);
        Expense::create(['expense_date' => '2026-11-02', 'amount' => 2000, 'category' => 'other', 'description' => 'Supplies', 'created_by' => $manager->id]);
        $this->travelTo(Carbon::parse('2026-11-03 10:00'));
        $desk->reversePayment($stay, $payment, ['amount' => 3000, 'reason' => 'Partial payment refund', 'request_key' => (string) Str::uuid()]);
        $report = app(MonthlyFinancialStatements::class)->build(Carbon::parse('2026-11-01'), Carbon::parse('2026-12-01'));
        // October: 10,000 earned, 5,000 paid. November: 10,000 room + 3,000 extras,
        // 18,000 collected - 3,000 refunded - 2,000 supplies = 13,000 cash movement.
        $this->assertSame(13000, $report['earnedRevenue']);
        $this->assertSame(5000, $report['openingBalances']['cash']);
        $this->assertSame(18000, $report['closingBalances']['cash']);
        $this->assertSame(3000, $report['closingBalances']['receivables']);
        $this->assertSame($stay->fresh()->balance, $report['closingBalances']['receivables']);
        $this->assertSame(18000, $report['inflows']['operating']);
        $this->assertSame(5000, $report['outflows']['operating']);
        $this->assertSame(13000, $report['flows']['operating']);
        $this->assertSame(0, $report['balanceDifference']);
        $this->assertSame(0, $report['cashDifference']);
        $this->assertSame(20000, (int) Payment::sum('amount'));
    }
}
