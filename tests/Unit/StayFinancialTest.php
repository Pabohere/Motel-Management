<?php

namespace Tests\Unit;

use App\Models\Charge;
use App\Models\Payment;
use App\Models\Stay;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class StayFinancialTest extends TestCase
{
    public function test_room_charge_is_the_full_balance_without_extras_or_payments(): void
    {
        $stay = new Stay(['room_charge' => 35000]);
        $stay->setRelation('charges', new Collection);
        $stay->setRelation('payments', new Collection);

        $this->assertSame(35000, $stay->total);
        $this->assertSame(0, $stay->paid);
        $this->assertSame(35000, $stay->balance);
    }

    public function test_multiple_charges_and_payments_are_reflected_in_the_remaining_balance(): void
    {
        $stay = new Stay(['room_charge' => 35000]);
        $stay->setRelation('charges', new Collection([
            new Charge(['amount' => 5000]),
            new Charge(['amount' => 2500]),
        ]));
        $stay->setRelation('payments', new Collection([
            new Payment(['amount' => 10000]),
            new Payment(['amount' => 12500]),
        ]));

        $this->assertSame(42500, $stay->total);
        $this->assertSame(22500, $stay->paid);
        $this->assertSame(20000, $stay->balance);
    }

    public function test_full_payment_reduces_balance_to_zero(): void
    {
        $stay = new Stay(['room_charge' => 35000]);
        $stay->setRelation('charges', new Collection([new Charge(['amount' => 5000])]));
        $stay->setRelation('payments', new Collection([new Payment(['amount' => 40000])]));

        $this->assertSame(0, $stay->balance);
    }
}
