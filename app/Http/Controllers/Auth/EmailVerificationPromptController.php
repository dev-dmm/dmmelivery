<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function __invoke(Request $request): RedirectResponse|Response
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            $redirectRoute = ($user && $user->isSuperAdmin())
                ? route('super-admin.dashboard', absolute: false)
                : route('dashboard', absolute: false);
            return redirect()->intended($redirectRoute);
        }

        return Inertia::render('Auth/VerifyEmail', ['status' => session('status')]);
    }
}
