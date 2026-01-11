<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleLoginController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $socialiteUser = Socialite::driver('google')->user();
            $email = $socialiteUser->email;

            $user = User::firstOrCreate(['email' => $email], [
                'name' => $socialiteUser->name,
            ]);

            Auth::login($user);

            return redirect()->intended('dashboard');
        } catch (Exception $e) {
            Log::error($e);
            throw $e;
        }
    }
}
