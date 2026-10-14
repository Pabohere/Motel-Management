<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'email' => 'required|email|max:255|unique:users', 'password' => 'required|string|min:12|max:128|confirmed', 'role' => ['required', Rule::in(['manager', 'staff'])]]);
        DB::transaction(function () use ($data) {
            $data['password'] = Hash::make($data['password']);
            $user = User::create($data);
            Audit::record('user.created', $user, null, $user->only(['name', 'email', 'role', 'active']));
        });

        return back()->with('success', __('ဝန်ထမ်းအကောင့် ထည့်ပြီးပါပြီ။'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'role' => ['required', Rule::in(['manager', 'staff'])], 'active' => 'required|boolean']);
        DB::transaction(function () use ($request, $user, $data) {
            // Lock the manager set so simultaneous changes cannot remove the last active manager.
            $managers = User::where('role', 'manager')->where('active', true)->lockForUpdate()->get();
            $locked = User::lockForUpdate()->findOrFail($user->id);
            if ($user->id === $request->user()->id && (! $data['active'] || $data['role'] !== 'manager')) {
                throw ValidationException::withMessages(['operation' => __('ကိုယ့်မန်နေဂျာအကောင့်ကို ပိတ်ခြင်း/အဆင့်လျှော့ခြင်း မပြုနိုင်ပါ။')]);
            }
            if ($locked->isManager() && $locked->active && (! $data['active'] || $data['role'] !== 'manager') && $managers->count() <= 1) {
                throw ValidationException::withMessages(['operation' => __('အနည်းဆုံး မန်နေဂျာတစ်ဦး ရှိရပါမည်။')]);
            }
            $before = $locked->only(['name', 'role', 'active']);
            $locked->update($data);
            if (! $locked->active || $before['role'] !== $locked->role) {
                DB::table('sessions')->where('user_id', $locked->id)->delete();
            }
            Audit::record('user.updated', $locked, $before, $locked->only(['name', 'role', 'active']));
        });

        return back()->with('success', __('ဝန်ထမ်းအကောင့် ပြင်ပြီးပါပြီ။'));
    }
}
