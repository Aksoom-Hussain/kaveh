<?php

namespace Kaveh\Server\Http\Controllers;

use Kaveh\Server\Models\Organization;
use Kaveh\Server\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('kaveh::auth.login');
    }

    public function showRegister(): View
    {
        return view('kaveh::auth.register');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid credentials'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('kaveh.dashboard'));
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'organization' => ['required', 'string', 'max:120'],
        ]);

        $userModel = config('kaveh.server.user_model', \App\Models\User::class);

        $user = $userModel::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $org = Organization::query()->create([
            'name' => $data['organization'],
            'slug' => Str::slug($data['organization']).'-'.Str::lower(Str::random(4)),
        ]);
        $org->users()->attach($user->id, ['role' => 'owner']);

        Project::query()->create([
            'organization_id' => $org->id,
            'name' => 'Default',
            'slug' => 'default',
        ]);

        Auth::login($user);

        return redirect()->route('kaveh.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('kaveh.login');
    }
}
