<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Stay;
use App\Models\User;
use Carbon\CarbonInterface;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontDeskUsabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 3)->setTime(10, 0));
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->room = Room::create(['number' => '101', 'floor_id' => Floor::create(['number' => 1])->id,
            'room_type_id' => RoomType::create(['name' => 'Standard'])->id, 'rate' => 35000]);
        $this->actingAs($this->staff)->withSession(['locale' => 'en']);
    }

    public function test_dashboard_actions_precede_metrics_and_room_links_always_open_room_details(): void
    {
        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'class="room-summary"'), strpos($html, 'class="frontdesk-tools"'));
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($document);
        $this->assertSame(route('rooms.show', $this->room), $xpath->query('//a[@class="card-hit"]')->item(0)->getAttribute('href'));
        $this->assertSame(0, $xpath->query('//span[@class="metric-bars"]')->length);
        $this->assertSame(0, $xpath->query('//div[contains(@class,"room-status-icons")]')->length);
        $this->assertSame(1, $xpath->query('//article//a[@href="'.route('stays.create', ['room' => $this->room->id]).'"]')->length);
        $this->assertSame(0, $xpath->query('//article//a[@href="'.route('rooms.edit', $this->room).'"]')->length);
    }

    public function test_attention_counts_and_links_exclude_completed_stays_and_other_days(): void
    {
        $due = $this->stay('Due today', now()->addHour());
        $late = $this->stay('Overdue', now()->subMinute());
        $this->stay('Tomorrow', today()->addDay()->setTime(12, 0));
        $this->stay('Completed', now()->subHour())->update(['check_out_at' => now()]);
        $booking = ['room_id' => $this->room->id, 'guest_name' => 'Arrival', 'gender' => 'female', 'phone' => '09',
            'arrival_at' => now()->addHours(2), 'departure_at' => today()->addDay(), 'room_charge' => 35000, 'status' => 'booked', 'created_by' => $this->staff->id];
        $arrival = Reservation::create($booking);
        Reservation::create([...$booking, 'status' => 'cancelled']);
        Reservation::create([...$booking, 'arrival_at' => today()->addDay()]);
        $this->get(route('reservations.index', ['status' => 'booked', 'today' => 1]))->assertOk()->assertViewHas('reservations', fn ($reservations) => $reservations->modelKeys() === [$arrival->id]);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('arrivals', 1)->assertViewHas('departures', 1)->assertViewHas('overdue', 1);
        $this->get(route('stays.index', ['attention' => 'departures']))->assertOk()->assertViewHas('stays', fn ($stays) => $stays->modelKeys() === [$due->id]);
        $this->get(route('stays.index', ['attention' => 'overdue']))->assertOk()->assertViewHas('stays', fn ($stays) => $stays->modelKeys() === [$late->id]);
    }

    public function test_guest_search_matches_room_number_and_distinguishes_empty_results(): void
    {
        $this->get(route('stays.index'))->assertOk()->assertSee('No guests yet')->assertDontSee('No matching guests');
        $stay = $this->stay('Guest by room', today()->addDay());
        $this->get(route('stays.index', ['q' => '101']))->assertOk()->assertViewHas('stays', fn ($stays) => $stays->modelKeys() === [$stay->id]);
        $this->get(route('stays.index', ['q' => 'missing']))->assertOk()->assertSee('No matching guests')->assertSee('Clear filters')->assertDontSee('No guests yet');
    }

    public function test_forms_preserve_submitted_manual_charge_and_share_price_summary(): void
    {
        foreach (['stays.create', 'reservations.create'] as $route) {
            $this->withSession(['_old_input' => ['room_charge' => '42000']])->get(route($route))->assertOk()
                ->assertSee('id="price-summary"', false)->assertSee('data-manual="true"', false)->assertSee('value="42000"', false)
                ->assertSee('Manual price override.');
        }
    }

    private function stay(string $name, CarbonInterface $departure): Stay
    {
        return Stay::create(['room_id' => $this->room->id, 'guest_name' => $name, 'gender' => 'female', 'phone' => '09',
            'check_in_at' => today()->subDay(), 'expected_check_out_at' => $departure, 'room_charge' => 35000, 'created_by' => $this->staff->id]);
    }
}
