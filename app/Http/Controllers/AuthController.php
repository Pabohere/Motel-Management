<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login()
    {
        return view('auth.login');
    }

    public function authenticate(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt([...$data, 'active' => true])) {
            return back()->withErrors(['email' => __('အီးမေးလ် သို့မဟုတ် စကားဝှက် မမှန်ပါ။')])->onlyInput('email');
        }
        $request->session()->regenerate();
        Audit::record('auth.login', $request->user());

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Audit::record('auth.logout', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function password()
    {
        return view('auth.password');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|current_password', 'password' => 'required|confirmed|min:12|max:128']);
        DB::transaction(function () use ($request, $data) {
            $request->user()->update(['password' => Hash::make($data['password']), 'remember_token' => null]);
            DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
            Audit::record('user.password_changed', $request->user());
        });
        $request->session()->regenerate();

        return back()->with('success', __('စကားဝှက် ပြောင်းပြီးပါပြီ။'));
    }
}
