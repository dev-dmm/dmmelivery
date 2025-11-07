<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            $redirectRoute = ($user && $user->isSuperAdmin())
                ? route('super-admin.dashboard', absolute: false)
                : route('dashboard', absolute: false);
            return redirect()->intended($redirectRoute);
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
