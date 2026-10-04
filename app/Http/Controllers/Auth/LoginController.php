<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\BackgroundImage;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    public function show()
    {
        $loginBackgroundUrl = null;
        $selectedBackground = SystemSetting::get('login_background');

        if ($selectedBackground !== 'default') {
            $background = BackgroundImage::query()->find($selectedBackground);

            if ($background && str_starts_with($background->file_path, 'backgrounds/') && Storage::disk('public')->exists($background->file_path)) {
                $loginBackgroundUrl = asset('storage/' . $background->file_path);
            }
        }

        $systemBranding = SystemSetting::branding();

        return view('auth.login', compact('loginBackgroundUrl', 'systemBranding'));
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->only('username'));
        }

        $credentials = $validator->validated();
        $loginField = filter_var($credentials['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (Auth::attempt([$loginField => trim($credentials['username']), 'password' => $credentials['password']])) {
            $request->session()->regenerate();

            $user = Auth::user();
            AuditLogger::log('Login', 'System', "({$user->username}) logged in");

            return redirect()->intended(route('dashboard'))->with('success', 'You are now logged in');
        }

        return back()->withErrors(['username' => 'Invalid username or password'])->withInput($request->only('username'));
    }

    public function logout(Request $request)
    {
        if ($user = Auth::user()) {
            AuditLogger::log('Logout', 'System', "({$user->username}) logged out");
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
