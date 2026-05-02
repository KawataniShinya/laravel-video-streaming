<?php

namespace App\Http\Controllers;

use App\UseCase\GoogleLoginUseCase;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleLoginController extends Controller
{
    public function __construct(
        private readonly GoogleLoginUseCase $googleLoginUseCase,
    ) {
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $socialiteUser = Socialite::driver('google')->user();
            $user = $this->googleLoginUseCase->findUserByEmail($socialiteUser->email);

            if (!$user) {
                return redirect()->route('login')->withErrors([
                    'email' => 'This Google account is not registered.',
                ]);
            }

            Auth::login($user);

            return redirect()->intended('dashboard');
        } catch (Exception $e) {
            Log::error($e);

            return redirect()->route('login')->withErrors([
                'email' => 'Google authentication failed.',
            ]);
        }
    }
}
