<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\Floor;
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
        if (! app()->environment(['local', 'testing', 'demo'])) {
            throw new RuntimeException('Demo data ကို local, testing သို့မဟုတ် demo environment တွင်သာ ထည့်နိုင်သည်။');
        }

        $manager = User::where('role', 'manager')->where('active', true)->first();
        if (! $manager) {
            throw new RuntimeException('Demo data မထည့်မီ ./bin/motel motel:create-manager ဖြင့် manager account ဖန်တီးပါ။');
        }

        DB::transaction(function () use ($manager): void {
            $rooms = $this->seedRooms($manager);
            $this->seedStays($rooms, $manager);
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
            $existingStay = Stay::where('phone', $phone)->first();
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
            $expectedCheckOut = $active ? $today->copy()->addDays($nights)->setTime(12, 0) : $checkOut;
            $roomCharge = $room->rate * $nights;
            $stay = Stay::create([
                'room_id' => $room->id,
                'active_room_id' => $active ? $room->id : null,
                'guest_name' => sprintf('Demo %02d · %s', $number, $name),
                'gender' => $gender,
                'phone' => $phone,
                'notes' => 'နမူနာအချက်အလက် · '.self::NOTES[($number - 1) % count(self::NOTES)],
                'check_in_at' => $checkIn,
                'expected_check_out_at' => $expectedCheckOut,
                'check_out_at' => $checkOut,
                'room_charge' => $roomCharge,
                'created_by' => $manager->id,
                'created_at' => $checkIn,
                'updated_at' => $checkOut ?? $checkIn,
            ]);
            $this->audit($manager, 'stay.check_in', $stay, $checkIn);

            $extra = $number % 4 === 0 ? 5000 + ($number % 3) * 3000 : 0;
            if ($extra > 0) {
                $stay->charges()->create([
                    'description' => $number % 8 === 0 ? 'Demo · အပိုအိပ်ရာ' : 'Demo · အစားအသောက်',
                    'amount' => $extra,
                    'created_by' => $manager->id,
                    'created_at' => $checkIn->copy()->addHour(),
                    'updated_at' => $checkIn->copy()->addHour(),
                ]);
            }
            $total = $roomCharge + $extra;
            $paid = $active ? match ($number % 4) {
                0 => 0, 1 => intdiv($total, 3), 2 => $total, default => intdiv($total, 2)
            } : $total;
            if ($paid > 0) {
                $firstPayment = ! $active && $number % 3 === 0 ? intdiv($paid, 2) : $paid;
                $this->addPayment($stay, $manager, $firstPayment, $number, $checkIn->copy()->addHours(2));
                if ($firstPayment < $paid) {
                    $this->addPayment($stay, $manager, $paid - $firstPayment, $number + 1, $checkOut->copy()->subHours(2));
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

        foreach (array_slice($rooms, 10, 7) as $index => $room) {
            if (! $room->activeStay()->exists() && $room->status === 'available') {
                $room->update(['status' => $index < 4 ? 'dirty' : 'maintenance']);
            }
        }
    }

    private function addPayment(Stay $stay, User $manager, int $amount, int $number, Carbon $paidAt): void
    {
        $stay->payments()->create([
            'amount' => $amount,
            'method' => ['cash', 'transfer', 'card'][$number % 3],
            'note' => 'Demo payment',
            'created_by' => $manager->id,
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ]);
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
        $stay->identityDocuments()->create(['path' => $path]);
    }

    private function demoPdf(): string
    {
        $content = 'BT /F1 24 Tf 45 760 Td (DEMO IDENTITY - NOT A REAL DOCUMENT) Tj ET';
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

    private function audit(User $manager, string $action, Room|Stay|Expense $subject, Carbon $at): void
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
