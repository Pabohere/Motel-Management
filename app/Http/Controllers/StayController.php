<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Stay;
use App\Models\StayIdentityDocument;
use App\Services\Audit;
use App\Services\FrontDesk;
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
        $data = $request->validate(['q' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(['active', 'completed', 'archived'])]]);
        if (($data['status'] ?? null) === 'archived' && ! $request->user()->isManager()) {
            abort(403);
        }
        $stays = Stay::with(['room', 'charges', 'payments', 'identityDocuments'])
            ->when(($data['status'] ?? '') === 'archived', fn ($q) => $q->onlyTrashed())
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('guest_name', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%')))
            ->when(($data['status'] ?? '') === 'active', fn ($q) => $q->whereNull('check_out_at'))
            ->when(($data['status'] ?? '') === 'completed', fn ($q) => $q->whereNotNull('check_out_at'))
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
        $data = $request->validate([
            'room_id' => 'required|integer|exists:rooms,id', 'guest_name' => 'required|string|max:150', 'gender' => ['required', Rule::in(array_keys(Stay::GENDERS))], 'phone' => 'required|string|max:40', 'notes' => 'nullable|string|max:2000',
            'check_in_at' => 'required|date|before_or_equal:now', 'expected_check_out_at' => 'required|date|after:check_in_at|after:now',
            'room_charge' => 'required|integer|min:0|max:100000000', 'deposit' => 'required|integer|min:0|lte:room_charge',
            'method' => ['required', Rule::in(['cash', 'transfer', 'card'])],
            'identity_documents' => 'nullable|array|max:5',
            'identity_documents.*' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);
        $paths = $this->storeIdentityFiles($request);
        unset($data['identity_documents']);
        try {
            $stay = $desk->checkIn($data, $paths);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }

        return redirect()->route('stays.show', $stay)->with('success', __('Check-in လုပ်ပြီးပါပြီ။'));
    }

    public function show(Stay $stay)
    {
        $stay->load(['room', 'charges.creator', 'payments.creator', 'identityDocuments']);

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
        $paths = $this->storeIdentityFiles($request);
        unset($data['identity_documents']);
        try {
            DB::transaction(function () use ($stay, $data, $paths): void {
                $locked = Stay::lockForUpdate()->findOrFail($stay->id);
                if ($locked->check_out_at && ! auth()->user()->isManager()) {
                    throw ValidationException::withMessages(['operation' => __('ထွက်ပြီးသောဧည့်သည်ကို မန်နေဂျာသာ ပြင်နိုင်ပါသည်။')]);
                }
                if ($locked->identityDocuments()->count() + count($paths) > 5) {
                    throw ValidationException::withMessages(['identity_documents' => __('အထောက်အထားဖိုင် စုစုပေါင်း ၅ ဖိုင်အထိသာ တင်နိုင်ပါသည်။')]);
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
        $document ??= $stay->identityDocuments()->firstOrFail();
        abort_unless($document->stay_id === $stay->id && Storage::disk('local')->exists($document->path), 404);
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

    public function payment(Request $request, Stay $stay, FrontDesk $desk)
    {
        $data = $request->validate(['amount' => 'required|integer|min:1|max:100000000', 'method' => ['required', Rule::in(['cash', 'transfer', 'card'])], 'note' => 'nullable|string|max:255']);
        $desk->payment($stay, $data);

        return back()->with('success', __('လက်ခံရရှိငွေ မှတ်ပြီးပါပြီ။'));
    }

    public function checkOut(Stay $stay, FrontDesk $desk)
    {
        $desk->checkOut($stay);

        return back()->with('success', __('Check-out လုပ်ပြီးပါပြီ။ အခန်းကို သန့်ရှင်းရေးလုပ်ရန် လိုအပ်ပါသည်။'));
    }
}
