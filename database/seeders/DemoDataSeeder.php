<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\Floor;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Stay;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DemoDataSeeder extends Seeder
{
    private const ROOM_NUMBERS = [
        501, 502, 503, 504, 505, 506, 507, 508, 509, 510, 511, 512,
        601, 602, 603, 604, 605, 606, 607, 608, 609, 610,
        701, 702, 703, 704, 705, 706,
    ];

    private const MALE_NAMES = [
        'Aung Min', 'Htet Naing', 'Ko Ko', 'Kyaw Zin', 'Min Thu', 'Nyi Nyi', 'Pyae Sone', 'Sai Aung', 'Soe Myat', 'Thet Paing',
        'Thu Ya', 'Wai Yan', 'Ye Htet', 'Zaw Lin', 'Aung Ko', 'Daniel Tan', 'James Lee', 'Lucas Chen', 'Rahul Sharma', 'Somchai Prasert',
        'Arjun Patel', 'David Park', 'John Miller', 'Kenji Sato', 'Michael Brown',
    ];

    private const FEMALE_NAMES = [
        'Ei Mon', 'Hnin Pwint', 'Khin Thazin', 'May Thu', 'Moe Thidar', 'Nan Su', 'Nandar Hlaing', 'Phyu Phyu', 'Su Myat', 'Thiri Aung',
        'Wai Phyo', 'Yoon Wadi', 'Yuzana', 'Zin Mar', 'Aye Chan', 'Anna Lee', 'Emily Chen', 'Fatima Khan', 'Hana Sato', 'Isabella Rossi',
        'Lina Park', 'Maria Gomez', 'Nina Patel', 'Sara Wilson', 'Sofia Tan',
    ];

    private const NOTES = [
        'ညပိုင်း Check-in', 'နှစ်ညတည်းခို', 'မိသားစုခရီး', 'အလုပ်ကိစ္စခရီး', 'ငွေလွှဲဖြင့်ပေးချေ',
        'အပိုအိပ်ရာတောင်းဆို', 'မနက်စောစော ထွက်မည်', 'ဧည့်ခန်းတွင် ငွေရှင်းမည်', 'တစ်ဦးတည်း တည်းခို', 'ကြိုတင်ငွေ ပေးပြီး',
    ];

    public function run(): void
    {
        $this->seedWorkflow();
    }

    public function seedWorkflow(bool $allowProduction = false): void
    {
        if (! ($allowProduction && app()->environment('production')) && ! app()->environment(['local', 'testing', 'demo'])) {
            throw new RuntimeException('Demo data ကို local, testing သို့မဟုတ် demo environment တွင်သာ ထည့်နိုင်သည်။');
        }

        $manager = User::where('role', 'manager')->where('active', true)->first();
        if (! $manager) {
            throw new RuntimeException('Demo data မထည့်မီ ./bin/motel motel:create-manager ဖြင့် manager account ဖန်တီးပါ။');
        }

        DB::transaction(function () use ($manager): void {
            $rooms = $this->seedRooms($manager);
            $this->seedStays($rooms, $manager);
            $this->seedReservations($rooms, $manager);
            $this->seedExpenses($manager);
        });
    }

    /** @return array<int, Room> */
    private function seedRooms(User $manager): array
    {
        $rooms = [];
        foreach (self::ROOM_NUMBERS as $number) {
            $type = match (intdiv($number, 100)) {
                5 => 'Demo Standard',
                6 => 'Demo Deluxe',
                default => 'Demo Suite',
            };
            $floor = Floor::firstOrCreate(['number' => intdiv($number, 100)]);
            $roomType = RoomType::firstOrCreate(['name' => $type]);
            $room = Room::firstOrCreate(['number' => (string) $number], [
                'floor_id' => $floor->id,
                'room_type_id' => $roomType->id,
                'rate' => match (intdiv($number, 100)) {
                    5 => 35000, 6 => 50000, default => 75000
                },
                'status' => 'available',
                'capacity' => intdiv($number, 100) - 3,
                'max_extra_beds' => 1,
                'extra_bed_rate' => 10000,
            ]);
            if ($room->room_type_id !== $roomType->id || $room->floor_id !== $floor->id) {
                throw new RuntimeException("အခန်း {$number} ရှိပြီးသားဖြစ်၍ demo data မထည့်ပါ။");
            }
            if ($room->wasRecentlyCreated) {
                $this->audit($manager, 'room.created', $room, $room->created_at);
            }
            $rooms[] = $room;
        }

        return $rooms;
    }

    /** @param array<int, Room> $rooms */
    private function seedStays(array $rooms, User $manager): void
    {
        $today = today();
        foreach (range(1, 50) as $number) {
            $phone = sprintf('099900%05d', $number);
            $existingStay = Stay::withTrashed()->where('phone', $phone)->first();
            if ($existingStay) {
                if (! str_starts_with($existingStay->guest_name, 'Demo ')) {
                    throw new RuntimeException("ဖုန်းနံပါတ် {$phone} ရှိပြီးသားဖြစ်၍ demo data မထည့်ပါ။");
                }

                continue;
            }
            $active = $number > 40;
            $room = $active ? $rooms[$number - 41] : $rooms[($number - 1) % count($rooms)];
            if ($active && $room->activeStay()->exists()) {
                throw new RuntimeException("အခန်း {$room->number} တွင် ဧည့်သည်ရှိပြီးသားဖြစ်၍ demo data မထည့်ပါ။");
            }
            $gender = $number % 2 === 0 ? 'female' : 'male';
            $name = $gender === 'male' ? self::MALE_NAMES[intdiv($number - 1, 2)] : self::FEMALE_NAMES[intdiv($number - 1, 2)];
            $checkIn = $active
                ? $today->copy()->subDays($number % 3)->setTime(9 + $number % 6, 15)
                : $today->copy()->subDays(50 - $number)->setTime(9 + $number % 8, 15);
            if ($active && $checkIn->isFuture()) {
                $checkIn = now()->subHours(3);
            }
            $nights = 1 + $number % 3;
            $checkOut = $active ? null : $checkIn->copy()->addDays($nights)->setTime(10 + $number % 3, 0);
            $expectedCheckOut = $active ? match ($number) {
                41 => now()->subHour(),
                42 => now()->addMinutes(30),
                default => $today->copy()->addDays($nights)->setTime(12, 0),
            } : $checkOut;
            if ($active && $checkIn->gte($expectedCheckOut)) {
                $checkIn = $expectedCheckOut->copy()->subDay();
            }
            $roomCharge = $room->rate * $nights;
            $stay = Stay::create([
                'room_id' => $room->id,
                'active_room_id' => $active ? $room->id : null,
                'guest_name' => sprintf('Demo %02d · %s', $number, $name),
                'gender' => $gender,
                'phone' => $phone,
                'notes' => 'DEMO / နမူနာအချက်အလက် · '.match ($number) {
                    41 => 'ထွက်ရန်ချိန်ကျော်၊ ကျန်ငွေရှင်းရန်',
                    42 => 'ငွေရှင်းပြီး၊ ယနေ့ Check-out လုပ်ကြည့်ရန်',
                    43 => 'နှစ်ဦးအတူတည်းခိုမှု စမ်းသပ်ရန်',
                    44 => 'အကြွေးတည်းခိုမှု၊ ငွေမသွင်းရသေး',
                    48 => 'မိသားစု၊ ကလေးနှင့် Extra bed ပါ',
                    default => self::NOTES[($number - 1) % count(self::NOTES)],
                },
                'payment_term' => $active && $number % 4 !== 2 ? 'credit' : 'immediate',
                'check_in_at' => $checkIn,
                'expected_check_out_at' => $expectedCheckOut,
                'check_out_at' => $checkOut,
                'room_charge' => $roomCharge,
                'created_by' => $manager->id,
                'created_at' => $checkIn,
                'updated_at' => $checkOut ?? $checkIn,
            ]);
            $stay->guests()->create(['name' => $stay->guest_name, 'gender' => $gender, 'phone' => $phone, 'type' => 'adult', 'is_primary' => true]);
            if (in_array($number, [43, 48])) {
                $stay->guests()->create(['name' => 'Demo companion · အတူတည်းခိုသူ', 'gender' => 'female', 'type' => 'adult', 'is_primary' => false]);
            }
            if ($number === 48) {
                $stay->guests()->create(['name' => 'Demo child · ကလေး', 'gender' => 'male', 'type' => 'child', 'age' => 8, 'is_primary' => false]);
            }
            $this->audit($manager, 'stay.check_in', $stay, $checkIn);

            $extra = $number % 8 === 0 ? $nights * 10000 : ($number % 4 === 0 ? 8000 : 0);
            if ($extra > 0) {
                $charge = $stay->charges()->create([
                    'description' => $number % 8 === 0 ? 'Demo · အပိုအိပ်ရာ' : 'Demo · အစားအသောက်',
                    'amount' => $extra,
                    'created_by' => $manager->id,
                    'created_at' => $checkIn->copy()->addHour(),
                    'updated_at' => $checkIn->copy()->addHour(),
                ]);
            }
            if ($number % 8 === 0) {
                $stay->extraBeds()->create(['charge_id' => $charge->id, 'quantity' => 1, 'nights' => $nights, 'rate' => 10000, 'returned_at' => $checkOut, 'created_by' => $manager->id]);
            }
            $total = $roomCharge + $extra;
            $paid = $active ? match ($number % 4) {
                0 => 0, 1 => intdiv($total, 3), 2 => $total, default => intdiv($total, 2)
            } : $total;
            if ($paid > 0) {
                $firstPayment = ! $active && $number % 3 === 0 ? intdiv($paid, 2) : $paid;
                $this->addPayment($stay, $manager, $firstPayment, $number, $checkIn->copy()->addMinutes(10));
                if ($firstPayment < $paid) {
                    $this->addPayment($stay, $manager, $paid - $firstPayment, $number + 1, $checkOut->copy()->subMinutes(10));
                }
            }
            if ($checkOut) {
                $this->audit($manager, 'stay.check_out', $stay, $checkOut);
            } else {
                $room->update(['status' => 'occupied']);
            }
            if ($number >= 41 && $number <= 45) {
                $this->addIdentityPreview($stay, $number);
            }
        }

        foreach (array_slice($rooms, 10, 10) as $index => $room) {
            if ($room->wasRecentlyCreated && ! $room->activeStay()->exists() && $room->status === 'available') {
                $status = match (true) {
                    $index < 4 => 'dirty',
                    $index < 7 => 'maintenance',
                    default => ['cleaning', 'cleaned', 'inspected'][$index - 7],
                };
                $room->update(['status' => $status, 'housekeeping_by' => $index >= 7 ? $manager->id : null,
                    'cleaning_started_at' => $index >= 7 ? now()->subHours(2) : null,
                    'cleaned_at' => $index >= 8 ? now()->subHour() : null,
                    'inspected_at' => $index === 9 ? now()->subMinutes(30) : null]);
                $this->audit($manager, 'room.'.$status, $room, now());
            }
        }
    }

    private function addPayment(Stay $stay, User $manager, int $amount, int $number, Carbon $paidAt): void
    {
        $payment = $stay->payments()->create([
            'amount' => $amount,
            'method' => ['cash', 'transfer', 'card'][$number % 3],
            'note' => 'Demo payment · နမူနာငွေပေးချေမှု',
            'reference' => 'DEMO-PAY-'.$stay->id.'-'.$number,
            'created_by' => $manager->id,
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ]);
        $this->audit($manager, 'payment.created', $payment, $paidAt);
        if ($number === 43) {
            $path = 'receipts/demo-receipt-'.$payment->id.'.pdf';
            Storage::disk('local')->put($path, $this->demoPdf(true));
            $payment->update(['receipt_path' => $path]);
        }
        if ($number === 41) {
            $reversal = $stay->payments()->create(['amount' => -1000, 'method' => $payment->method,
                'reversal_of_id' => $payment->id, 'note' => 'Demo reversal · ငွေပေးနည်းပြင်ခြင်း',
                'created_by' => $manager->id, 'created_at' => $paidAt, 'updated_at' => $paidAt]);
            $replacement = $stay->payments()->create(['amount' => 1000, 'method' => 'transfer',
                'reference' => 'DEMO-CORRECTED', 'note' => 'Demo replacement · ပြင်ဆင်ပြီးငွေစာရင်း',
                'created_by' => $manager->id, 'created_at' => $paidAt, 'updated_at' => $paidAt]);
            $this->audit($manager, 'payment.reversed', $reversal, $paidAt);
            $this->audit($manager, 'payment.created', $replacement, $paidAt);
        }
    }

    /** @param array<int, Room> $rooms */
    private function seedReservations(array $rooms, User $manager): void
    {
        $scenarios = [
            ['key' => 'today', 'name' => 'ယနေ့ရောက်မည့် Booking', 'room' => $rooms[20], 'arrival' => now()->subMinutes(30), 'departure' => now()->addDay(), 'status' => 'booked'],
            ['key' => 'future', 'name' => 'ရက်ပြောင်း၊ အခန်းပြောင်း စမ်းရန်', 'room' => $rooms[21], 'arrival' => today()->addDays(2)->setTime(14, 0), 'departure' => today()->addDays(4)->setTime(12, 0), 'status' => 'booked'],
            ['key' => 'family', 'name' => 'မိသားစုနှင့် Extra bed Booking', 'room' => $rooms[22], 'arrival' => today()->addDays(3)->setTime(14, 0), 'departure' => today()->addDays(5)->setTime(12, 0), 'status' => 'booked'],
            ['key' => 'cancelled', 'name' => 'ဖျက်သိမ်းထားသော Booking', 'room' => $rooms[23], 'arrival' => today()->subDays(3), 'departure' => today()->subDay(), 'status' => 'cancelled'],
            ['key' => 'no-show', 'name' => 'လာမတည်းသော Booking', 'room' => $rooms[24], 'arrival' => today()->subDays(2), 'departure' => today()->subDay(), 'status' => 'no_show'],
        ];
        foreach ([45 => 'checked_in', 40 => 'completed'] as $number => $status) {
            $stay = Stay::where('phone', sprintf('099900%05d', $number))->firstOrFail();
            $scenarios[] = ['key' => $status, 'name' => $status.' Booking', 'room' => $stay->room, 'arrival' => $stay->check_in_at, 'departure' => $stay->expected_check_out_at, 'status' => $status, 'stay' => $stay];
        }
        foreach ($scenarios as $index => $scenario) {
            $phone = $scenario['stay']->phone ?? sprintf('099901%05d', $index + 1);
            $existing = Reservation::where('phone', $phone)->first();
            if ($existing) {
                if ($existing->notes !== 'DEMO-WORKFLOW:'.$scenario['key']) {
                    throw new RuntimeException('Demo booking phone already belongs to another reservation: '.$phone);
                }

                continue;
            }
            $reservation = Reservation::create([
                'room_id' => $scenario['room']->id, 'guest_name' => 'Demo · '.$scenario['name'],
                'gender' => 'female', 'phone' => $phone, 'notes' => 'DEMO-WORKFLOW:'.$scenario['key'],
                'arrival_at' => $scenario['arrival'], 'departure_at' => $scenario['departure'],
                'room_charge' => $scenario['stay']->room_charge ?? $scenario['room']->rate * 2,
                'status' => $scenario['status'], 'created_by' => $manager->id,
                'companions' => $scenario['key'] === 'family' ? [['name' => 'Demo family adult', 'gender' => 'male', 'type' => 'adult'], ['name' => 'Demo family child', 'gender' => 'female', 'type' => 'child', 'age' => 7]] : null,
                'extra_beds' => $scenario['key'] === 'family' ? 1 : 0,
                'extra_bed_nights' => 2, 'extra_bed_rate' => $scenario['room']->extra_bed_rate,
            ]);
            if (isset($scenario['stay'])) {
                $reservation->update(['guest_name' => $scenario['stay']->guest_name, 'gender' => $scenario['stay']->gender]);
                $scenario['stay']->update(['reservation_id' => $reservation->id, 'source' => 'booking']);
            }
            $this->audit($manager, 'reservation.'.$scenario['status'], $reservation, now());
        }
    }

    private function seedExpenses(User $manager): void
    {
        $categories = ['supplies', 'maintenance', 'utilities', 'salary', 'other'];
        foreach (range(1, 12) as $number) {
            $description = sprintf('Demo expense %02d · %s', $number, [
                'သန့်ရှင်းရေးပစ္စည်း', 'အခန်းပြင်ဆင်ခြင်း', 'မီးဖိုး', 'ဝန်ထမ်းလစာ', 'အင်တာနက်ဖိုး', 'အိပ်ရာခင်း',
            ][($number - 1) % 6]);
            if (Expense::where('description', $description)->exists()) {
                continue;
            }
            $date = $number <= 4
                ? today()->startOfMonth()->addDays(min(($number - 1) * 2, today()->day - 1))
                : today()->subDays($number * 4);
            $recordedAt = $date->copy()->setTime(16, 0);
            if ($recordedAt->isFuture()) {
                $recordedAt = now();
            }
            $expense = Expense::create([
                'expense_date' => $date,
                'category' => $categories[($number - 1) % count($categories)],
                'description' => $description,
                'amount' => 12000 + $number * 2500,
                'created_by' => $manager->id,
                'created_at' => $recordedAt,
                'updated_at' => $recordedAt,
            ]);
            $this->audit($manager, 'expense.created', $expense, $expense->created_at);
        }
    }

    private function addIdentityPreview(Stay $stay, int $number): void
    {
        $path = "guest-identities/demo-identity-$number.pdf";
        Storage::disk('local')->put($path, $this->demoPdf());
        $stay->identityDocuments()->create(['path' => $path, 'stay_guest_id' => $stay->guests()->where('is_primary', true)->value('id')]);
    }

    private function demoPdf(bool $receipt = false): string
    {
        $label = $receipt ? 'DEMO RECEIPT - NOT A REAL PAYMENT' : 'DEMO IDENTITY - NOT A REAL DOCUMENT';
        $content = 'BT /F1 18 Tf 45 760 Td ('.$label.') Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($content)." >>\nstream\n$content\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n$object\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }

    private function audit(User $manager, string $action, Room|Stay|Expense|Reservation|Payment $subject, Carbon $at): void
    {
        AuditLog::create([
            'user_id' => $manager->id,
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->id,
            'before' => null,
            'after' => $subject->toArray(),
            'created_at' => $at,
        ]);
    }
}
