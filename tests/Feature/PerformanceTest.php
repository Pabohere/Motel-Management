<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Stay $stay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 25)->setTime(12, 0));
        $this->staff = User::factory()->create(['role' => 'manager']);
        $room = Room::create([
            'number' => '101', 'floor_id' => Floor::create(['number' => 1])->id,
            'room_type_id' => RoomType::create(['name' => 'Standard'])->id,
            'rate' => 10000, 'status' => 'occupied',
        ]);
        $this->stay = Stay::create([
            'room_id' => $room->id, 'active_room_id' => $room->id,
            'guest_name' => 'Balance guest', 'phone' => '09',
            'check_in_at' => now()->subDay(), 'expected_check_out_at' => now()->addDay(),
            'room_charge' => 10000, 'created_by' => $this->staff->id,
        ]);
        $this->stay->charges()->create(['amount' => 2500, 'description' => 'Extra', 'created_by' => $this->staff->id]);
        $this->stay->payments()->create(['amount' => 4000, 'method' => 'cash', 'created_by' => $this->staff->id]);
    }

    public function test_financial_totals_do_not_load_histories_or_issue_queries_when_read(): void
    {
        $stay = Stay::withFinancialTotals()->findOrFail($this->stay->id);

        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->assertSame(12500, $stay->total);
            $this->assertSame(4000, $stay->paid);
            $this->assertSame(8500, $stay->balance);
            $this->assertFalse($stay->relationLoaded('charges'));
            $this->assertFalse($stay->relationLoaded('payments'));
            $this->assertCount(0, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_historical_totals_exclude_later_transactions_and_include_archived_stays(): void
    {
        $asOf = now();
        $this->travel(1)->days();
        $this->stay->charges()->create(['amount' => 1000, 'description' => 'Later extra', 'created_by' => $this->staff->id]);
        $this->stay->payments()->create(['amount' => 2000, 'method' => 'cash', 'created_by' => $this->staff->id]);
        $this->stay->delete();

        $historical = Stay::withTrashed()->withFinancialTotals($asOf)->findOrFail($this->stay->id);
        $current = Stay::withTrashed()->withFinancialTotals()->findOrFail($this->stay->id);

        $this->assertSame(12500, $historical->total);
        $this->assertSame(4000, $historical->paid);
        $this->assertSame(8500, $historical->balance);
        $this->assertSame(7500, $current->balance);
    }

    public function test_list_pages_use_totals_without_hydrating_transaction_histories(): void
    {
        $this->actingAs($this->staff);
        $hasLightweightBalance = fn ($stays) => $stays->count() === 1
            && $stays->first()->balance === 8500
            && ! $stays->first()->relationLoaded('charges')
            && ! $stays->first()->relationLoaded('payments');

        $this->get(route('dashboard'))->assertOk()->assertViewHas('activeStays', $hasLightweightBalance);
        $this->get(route('stays.index'))->assertOk()->assertViewHas('stays', $hasLightweightBalance);
        $this->get(route('reports.daily'))->assertOk()->assertViewHas('outstandingStays', $hasLightweightBalance);
        $this->get(route('reports.monthly'))->assertOk()->assertViewHas('outstandingStays', $hasLightweightBalance);
    }

    public function test_loaded_histories_take_precedence_over_an_earlier_aggregate_snapshot(): void
    {
        $stay = Stay::withFinancialTotals()->findOrFail($this->stay->id);
        $this->stay->payments()->create(['amount' => 2000, 'method' => 'cash', 'created_by' => $this->staff->id]);
        $stay->load(['charges', 'payments']);

        $this->assertSame(6000, $stay->paid);
        $this->assertSame(6500, $stay->balance);
    }

    public function test_empty_histories_return_zero_totals(): void
    {
        $this->stay->charges()->delete();
        $this->stay->payments()->delete();
        $stay = Stay::withFinancialTotals()->findOrFail($this->stay->id);

        $this->assertSame(10000, $stay->total);
        $this->assertSame(0, $stay->paid);
        $this->assertSame(10000, $stay->balance);
    }
}
