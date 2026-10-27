<?php

namespace App\Http\Controllers;

use App\Models\AccountEvent;
use App\Models\User;
use App\Support\UserSessions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The account area: the overview (dashboard), Security and the browsers logged in.
 */
class AccountController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $this->user($request);

        return view('account.dashboard', [
            'user' => $user,
            'orders' => $user->orders()->with('items')->latest('id')->limit(3)->get(),
        ]);
    }

    public function security(Request $request): View
    {
        $user = $this->user($request);

        return view('account.security', [
            'user' => $user,
            'codesLeft' => $user->hasTwoStep() ? count($user->recoveryCodes()) : 0,
            'sessions' => UserSessions::forUser($user, $request->session()->getId()),
            'events' => $user->accountEvents()->latest('id')->limit(10)->get(),
        ]);
    }

    public function password(): View
    {
        return view('account.password');
    }

    public function endSession(Request $request, string $session): RedirectResponse
    {
        $ended = UserSessions::end($this->user($request), $session, $request->session()->getId());

        return redirect()->route('account.security')
            ->with('status', $ended ? 'That browser is logged out.' : 'That browser was already logged out.');
    }

    public function endOtherSessions(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        UserSessions::endOthers($user, $request->session()->getId());
        AccountEvent::record($user, 'sessions_ended');

        return redirect()->route('account.security')->with('status', 'Every other browser is logged out.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
