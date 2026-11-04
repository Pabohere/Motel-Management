<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Room;
use App\Models\Stay;
use App\Models\StayIdentityDocument;
use App\Services\Audit;
use App\Services\FrontDesk;
use App\Services\RoomAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StayController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(['active', 'completed', 'archived'])], 'source' => ['nullable', Rule::in(['walk_in', 'booking'])], 'attention' => ['nullable', Rule::in(['departures', 'overdue'])]]);
        if (($data['status'] ?? null) === 'archived' && ! $request->user()->isManager()) {
            abort(403);
        }
        $stays = Stay::with(['room', 'identityDocuments'])->withFinancialTotals()
            ->when(($data['status'] ?? '') === 'archived', fn ($q) => $q->onlyTrashed())
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('guest_name', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%')->orWhere('id', ltrim($term, '#'))->orWhereHas('room', fn ($room) => $room->where('number', 'like', '%'.$term.'%'))))
            ->when($data['source'] ?? null, fn ($q, $source) => $q->where('source', $source))
            ->when(($data['status'] ?? '') === 'active', fn ($q) => $q->whereNull('check_out_at'))
            ->when(($data['status'] ?? '') === 'completed', fn ($q) => $q->whereNotNull('check_out_at'))
            ->when(($data['attention'] ?? '') === 'departures', fn ($q) => $q->whereNull('check_out_at')->where('expected_check_out_at', '>=', now())->where('expected_check_out_at', '<', today()->addDay()))
            ->when(($data['attention'] ?? '') === 'overdue', fn ($q) => $q->whereNull('check_out_at')->where('expected_check_out_at', '<', now()))
            ->latest('check_in_at')->paginate(20)->withQueryString();

        return view('stays.index', compact('stays'));
    }

    public function create(Request $request)
    {
        $rooms = Room::with(['floor', 'roomType'])->where('status', 'available')->orderBy('number')->get();
        $selectedRoom = $rooms->firstWhere('id', (int) $request->query('room'));

        return view('stays.create', compact('rooms', 'selectedRoom'));
    }

    public function store(Request $request, FrontDesk $desk)
    {
        $request->merge(['payment_term' => $request->input('payment_term') ?? 'immediate']);
        $data = $request->validate([
            'request_key' => 'nullable|uuid', 'room_id' => 'required|integer|exists:rooms,id', 'guest_name' => 'required|string|max:150', 'gender' => ['required', Rule::in(array_keys(Stay::GENDERS))], 'phone' => 'required|string|max:40', 'notes' => 'nullable|string|max:2000',
            'check_in_at' => 'required|date|before_or_equal:now', 'expected_check_out_at' => 'required|date|after:check_in_at|after:now',
            'room_charge' => 'required|integer|min:0|max:100000000', 'deposit' => 'required|integer|min:0|lte:room_charge',
            'method' => ['required', Rule::in(['cash', 'transfer', 'card'])],
            'payment_term' => ['required', Rule::in(['immediate', 'credit'])],
            'payment_receipt' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'identity_documents' => 'nullable|array|max:5',
            'identity_documents.*' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);
        abort_if($request->hasFile('identity_documents') && ! $request->user()->canUploadDocuments(), 403);
        $data['identity_hashes'] = array_map(fn ($file) => hash_file('sha256', $file->getRealPath()), $request->file('identity_documents', []));
        $data['receipt_hash'] = $request->hasFile('payment_receipt') ? hash_file('sha256', $request->file('payment_receipt')->getRealPath()) : null;
        $paths = [];
        $receiptPath = null;
        try {
            $paths = $this->storeIdentityFiles($request);
            $receiptPath = $this->storeReceipt($request, (int) $data['deposit'], $data['method']);
            unset($data['identity_documents'], $data['payment_receipt']);
            $data['receipt_path'] = $receiptPath;
            $stay = $desk->checkIn($data, $paths);
            $usedPaths = $stay->identityDocuments()->pluck('path')->all();
            Storage::disk('local')->delete(array_diff($paths, $usedPaths));
            if ($receiptPath && ! $stay->payments()->where('receipt_path', $receiptPath)->exists()) {
                Storage::disk('local')->delete($receiptPath);
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            if ($receiptPath) {
                Storage::disk('local')->delete($receiptPath);
            }
            throw $exception;
        }

        return redirect()->route('stays.show', $stay)->with('success', __('Check-in လုပ်ပြီးပါပြီ။'));
    }

    public function show(Stay $stay)
    {
        $stay->load(['room', 'charges.creator', 'payments.creator', 'payments.reversals', 'identityDocuments']);

        return view('stays.show', compact('stay'));
    }

    public function edit(Stay $stay)
    {
        if ($stay->check_out_at && ! auth()->user()->isManager()) {
            abort(403);
        }
        $stay->load('identityDocuments');

        return view('stays.edit', compact('stay'));
    }

    public function update(Request $request, Stay $stay)
    {
        if ($stay->check_out_at && ! $request->user()->isManager()) {
            throw ValidationException::withMessages(['operation' => __('ထွက်ပြီးသောဧည့်သည်ကို မန်နေဂျာသာ ပြင်နိုင်ပါသည်။')]);
        }
        $data = $request->validate([
            'guest_name' => 'required|string|max:150', 'gender' => ['required', Rule::in(array_keys(Stay::GENDERS))], 'phone' => 'required|string|max:40', 'notes' => 'nullable|string|max:2000',
            'expected_check_out_at' => $stay->check_out_at
                ? 'required|date|after:'.$stay->check_in_at->toDateTimeString()
                : 'required|date|after:'.$stay->check_in_at->toDateTimeString().'|after:now',
            'identity_documents' => 'nullable|array|max:5',
            'identity_documents.*' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);
        abort_if($request->hasFile('identity_documents') && ! $request->user()->canUploadDocuments(), 403);
        $paths = $this->storeIdentityFiles($request);
        unset($data['identity_documents']);
        try {
            DB::transaction(function () use ($stay, $data, $paths): void {
                $room = Room::lockForUpdate()->findOrFail($stay->room_id);
                $locked = Stay::lockForUpdate()->findOrFail($stay->id);
                if ($locked->check_out_at && ! auth()->user()->isManager()) {
                    throw ValidationException::withMessages(['operation' => __('ထွက်ပြီးသောဧည့်သည်ကို မန်နေဂျာသာ ပြင်နိုင်ပါသည်။')]);
                }
                if ($locked->identityDocuments()->count() + count($paths) > 5) {
                    throw ValidationException::withMessages(['identity_documents' => __('အထောက်အထားဖိုင် စုစုပေါင်း ၅ ဖိုင်အထိသာ တင်နိုင်ပါသည်။')]);
                }
                if (! $locked->check_out_at) {
                    app(RoomAvailability::class)->ensureNoReservationOverlap($room, $locked->check_in_at->toDateTimeString(), $data['expected_check_out_at'], $locked->reservation_id, 'expected_check_out_at');
                }
                $before = $locked->toArray();
                $locked->update($data);
                Audit::record('stay.updated', $locked, $before, $locked->toArray());
                foreach ($paths as $path) {
                    $document = $locked->identityDocuments()->create(['path' => $path]);
                    Audit::record('stay.identity_added', $document, null, $document->toArray());
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }

        return redirect()->route('stays.show', $stay)->with('success', __('ဧည့်သည်အချက်အလက် ပြင်ပြီးပါပြီ။'));
    }

    public function destroy(Stay $stay)
    {
        DB::transaction(function () use ($stay): void {
            $locked = Stay::lockForUpdate()->findOrFail($stay->id);
            if (! $locked->check_out_at) {
                throw ValidationException::withMessages(['operation' => __('တည်းခိုနေသောဧည့်သည်ကို ဖျက်၍မရပါ။ Check-out အရင်လုပ်ပါ။')]);
            }
            $before = $locked->toArray();
            $locked->delete();
            Audit::record('stay.archived', $locked, $before, $locked->toArray());
        });

        return redirect()->route('stays.index')->with('success', __('ဧည့်သည်မှတ်တမ်းကို archive ထဲရွှေ့ပြီးပါပြီ။ ပြန်ယူနိုင်ပါသည်။'));
    }

    public function restore(Stay $stay)
    {
        abort_unless($stay->trashed(), 404);
        DB::transaction(function () use ($stay): void {
            $locked = Stay::withTrashed()->lockForUpdate()->findOrFail($stay->id);
            $before = $locked->toArray();
            $locked->restore();
            Audit::record('stay.restored', $locked, $before, $locked->toArray());
        });

        return redirect()->route('stays.index', ['status' => 'archived'])->with('success', __('ဧည့်သည်မှတ်တမ်းကို ပြန်ယူပြီးပါပြီ။'));
    }

    public function charge(Request $request, Stay $stay, FrontDesk $desk)
    {
        $data = $request->validate(['description' => 'required|string|max:255', 'amount' => 'required|integer|min:1|max:100000000']);
        $desk->charge($stay, $data);

        return back()->with('success', __('အပိုကျသင့်ငွေ ထည့်ပြီးပါပြီ။'));
    }

    public function identityDocument(Stay $stay, ?StayIdentityDocument $document = null): StreamedResponse
    {
        abort_unless(auth()->user()->canViewDocuments(), 403);
        $document ??= $stay->identityDocuments()->firstOrFail();
        abort_unless(! $document->trashed() && $document->stay_id === $stay->id && Storage::disk('local')->exists($document->path), 404);
        Audit::record('document.viewed', $document, null, ['stay_id' => $stay->id]);
        $extension = strtolower(pathinfo($document->path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            default => abort(404),
        };

        return Storage::disk('local')->response($document->path, 'identity-'.$document->id.'.'.$extension, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    public function paymentReceipt(Stay $stay, Payment $payment): StreamedResponse
    {
        abort_unless($payment->stay_id === $stay->id && $payment->receipt_path && Storage::disk('local')->exists($payment->receipt_path), 404);
        Audit::record('receipt.viewed', $payment, null, ['stay_id' => $stay->id]);
        $extension = strtolower(pathinfo($payment->receipt_path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', default => abort(404),
        };

        return Storage::disk('local')->response($payment->receipt_path, 'receipt-'.$payment->id.'.'.$extension, [
            'Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    private function storeIdentityFiles(Request $request): array
    {
        $paths = [];
        try {
            foreach ($request->file('identity_documents', []) as $file) {
                $path = $file->store('guest-identities', 'local');
                if (! $path) {
                    throw new RuntimeException(__('အထောက်အထားဖိုင် သိမ်း၍မရပါ။'));
                }
                $paths[] = $path;
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }

        return $paths;
    }

    private function storeReceipt(Request $request, int $amount, string $method): ?string
    {
        if (! $request->hasFile('payment_receipt')) {
            return null;
        }
        if ($method !== 'transfer' || $amount === 0) {
            throw ValidationException::withMessages(['payment_receipt' => __('ငွေလွှဲပြီးသော ပေးငွေအတွက်သာ ပြေစာတင်နိုင်ပါသည်။')]);
        }
        $path = $request->file('payment_receipt')->store('payment-receipts', 'local');
        if (! $path) {
            throw new RuntimeException(__('ငွေလွှဲပြေစာ သိမ်း၍မရပါ။'));
        }

        return $path;
    }

    public function payment(Request $request, Stay $stay, FrontDesk $desk)
    {
        $data = $request->validate(['amount' => 'required|integer|min:1|max:100000000', 'method' => ['required', Rule::in(['cash', 'transfer', 'card'])], 'note' => 'nullable|string|max:255', 'reference' => 'nullable|string|max:120', 'request_key' => 'nullable|uuid', 'payment_receipt' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $data['receipt_hash'] = $request->hasFile('payment_receipt') ? hash_file('sha256', $request->file('payment_receipt')->getRealPath()) : null;
        $receiptPath = $this->storeReceipt($request, (int) $data['amount'], $data['method']);
        unset($data['payment_receipt']);
        try {
            $recorded = $desk->payment($stay, [...$data, 'receipt_path' => $receiptPath]);
            if ($receiptPath && $recorded->receipt_path !== $receiptPath) {
                Storage::disk('local')->delete($receiptPath);
            }
        } catch (Throwable $exception) {
            if ($receiptPath) {
                Storage::disk('local')->delete($receiptPath);
            }
            throw $exception;
        }

        return back()->with('success', __('လက်ခံရရှိငွေ မှတ်ပြီးပါပြီ။'));
    }

    public function reversePayment(Request $request, Stay $stay, Payment $payment, FrontDesk $desk)
    {
        $desk->reversePayment($stay, $payment, $request->only(['amount', 'reason', 'request_key']));

        return back()->with('success', __('Payment reversed. Review the outstanding balance before recording a replacement.'));
    }

    public function deleteIdentity(Stay $stay, StayIdentityDocument $document)
    {
        abort_unless(auth()->user()->isManager(), 403);
        abort_unless($document->stay_id === $stay->id, 404);
        DB::transaction(function () use ($document, $stay): void {
            $locked = StayIdentityDocument::lockForUpdate()->findOrFail($document->id);
            $locked->delete();
            Audit::record('document.deleted', $locked, null, ['stay_id' => $stay->id, 'deleted_by' => auth()->id()]);
        }, 3);
        if (! Storage::disk('local')->delete($document->path)) {
            Audit::record('document.deletion_failed', $document);

            return back()->withErrors(['operation' => __('File access was removed, but storage cleanup failed. Contact the manager.')]);
        }

        return back()->with('success', __('Document deleted. The stay record has been preserved.'));
    }

    public function deleteReceipt(Stay $stay, Payment $payment)
    {
        abort_unless(auth()->user()->isManager(), 403);
        abort_unless($payment->stay_id === $stay->id && $payment->receipt_path, 404);
        $path = null;
        DB::transaction(function () use ($payment, $stay, &$path): void {
            $locked = Payment::lockForUpdate()->findOrFail($payment->id);
            abort_unless($locked->receipt_path, 404);
            $path = $locked->receipt_path;
            $locked->update(['receipt_path' => null]);
            Audit::record('receipt.deleted', $locked, null, ['stay_id' => $stay->id]);
        }, 3);
        if (! Storage::disk('local')->delete($path)) {
            Audit::record('receipt.deletion_failed', $payment);

            return back()->withErrors(['operation' => __('File access was removed, but storage cleanup failed. Contact the manager.')]);
        }

        return back()->with('success', __('Receipt image deleted. The payment record has been preserved.'));
    }

    public function checkOut(Stay $stay, FrontDesk $desk)
    {
        $desk->checkOut($stay);

        return back()->with('success', __('Check-out လုပ်ပြီးပါပြီ။ အခန်းကို သန့်ရှင်းရေးလုပ်ရန် လိုအပ်ပါသည်။'));
    }
}
