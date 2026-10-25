<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', ['users' => User::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'email' => 'required|email|max:255|unique:users', 'password' => 'required|string|min:12|max:128|confirmed', 'position' => 'nullable|string|max:120', 'biography' => 'nullable|string|max:4000', 'photo' => $this->photoRules(), 'role' => ['required', Rule::in(['manager', 'staff'])]]);
        $photoPath = $this->storePhoto($request);
        unset($data['photo']);
        try {
            DB::transaction(function () use ($data, $photoPath): void {
                $data['password'] = Hash::make($data['password']);
                $user = User::create([...$data, 'photo_path' => $photoPath]);
                Audit::record('user.created', $user, null, $user->only(['name', 'email', 'role', 'active', 'position', 'biography', 'photo_path']));
            });
        } catch (Throwable $exception) {
            if ($photoPath) {
                Storage::disk('local')->delete($photoPath);
            }
            throw $exception;
        }

        return back()->with('success', __('ဝန်ထမ်းအကောင့် ထည့်ပြီးပါပြီ။'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'position' => 'nullable|string|max:120', 'biography' => 'nullable|string|max:4000', 'photo' => $this->photoRules(), 'remove_photo' => 'nullable|boolean', 'role' => ['required', Rule::in(['manager', 'staff'])], 'active' => 'required|boolean']);
        if ($request->hasFile('photo') && $request->boolean('remove_photo')) {
            throw ValidationException::withMessages(['photo' => __('ဓာတ်ပုံအသစ်တင်ခြင်းနှင့် ဖယ်ရှားခြင်းကို တစ်ပြိုင်တည်း မရွေးပါနှင့်။')]);
        }
        $photoPath = $this->storePhoto($request);
        unset($data['photo'], $data['remove_photo']);
        $previousPhoto = null;
        try {
            DB::transaction(function () use ($request, $user, $data, $photoPath, &$previousPhoto): void {
                // Lock the manager set so simultaneous changes cannot remove the last active manager.
                $managers = User::where('role', 'manager')->where('active', true)->lockForUpdate()->get();
                $locked = User::lockForUpdate()->findOrFail($user->id);
                if ($user->id === $request->user()->id && (! $data['active'] || $data['role'] !== 'manager')) {
                    throw ValidationException::withMessages(['operation' => __('ကိုယ့်မန်နေဂျာအကောင့်ကို ပိတ်ခြင်း/အဆင့်လျှော့ခြင်း မပြုနိုင်ပါ။')]);
                }
                if ($locked->isManager() && $locked->active && (! $data['active'] || $data['role'] !== 'manager') && $managers->count() <= 1) {
                    throw ValidationException::withMessages(['operation' => __('အနည်းဆုံး မန်နေဂျာတစ်ဦး ရှိရပါမည်။')]);
                }
                $before = $locked->only(['name', 'role', 'active', 'position', 'biography', 'photo_path']);
                if ($photoPath || $request->boolean('remove_photo')) {
                    $previousPhoto = $locked->photo_path;
                    $data['photo_path'] = $photoPath;
                }
                $locked->update($data);
                if (! $locked->active || $before['role'] !== $locked->role) {
                    DB::table('sessions')->where('user_id', $locked->id)->delete();
                }
                Audit::record('user.updated', $locked, $before, $locked->only(['name', 'role', 'active', 'position', 'biography', 'photo_path']));
            });
        } catch (Throwable $exception) {
            if ($photoPath) {
                Storage::disk('local')->delete($photoPath);
            }
            throw $exception;
        }
        if ($previousPhoto) {
            Storage::disk('local')->delete($previousPhoto);
        }

        return back()->with('success', __('ဝန်ထမ်းအကောင့် ပြင်ပြီးပါပြီ။'));
    }

    public function photo(User $user): StreamedResponse
    {
        abort_unless($user->photo_path && Storage::disk('local')->exists($user->photo_path), 404);

        return Storage::disk('local')->response($user->photo_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function photoRules(): array
    {
        return ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'];
    }

    private function storePhoto(Request $request): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }
        $path = $request->file('photo')->store('staff-photos', 'local');
        if (! $path) {
            throw ValidationException::withMessages(['photo' => __('ဓာတ်ပုံသိမ်းခြင်း မအောင်မြင်ပါ။ ပြန်ကြိုးစားပါ။')]);
        }

        return $path;
    }
}
