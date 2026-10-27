<?php

namespace App\Http\Controllers;

use App\Models\User;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Turning 2-step verification on and off, with Fortify's actions behind ordinary pages and forms.
 * The steps follow the design: the offer, the password, the QR code, the code, the recovery
 * codes, done. Changing it asks for the password again after config('auth.password_timeout').
 */
class TwoStepController extends Controller
{
    // Set once the code is confirmed, until the recovery codes are saved
    private const SHOWING_CODES = 'two_step.showing_codes';

    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->user($request);
        $session = $request->session();

        if ($session->get('status') === 'two-step-done') {
            return view('account.two-step', ['step' => 'done']);
        }
        if ($user->hasTwoStep()) {
            return $session->get(self::SHOWING_CODES)
                ? view('account.two-step', ['step' => 'codes', 'codes' => $user->recoveryCodes()])
                : redirect()->route('account.security');
        }
        if ($user->two_factor_secret !== null) {
            $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

            return view('account.two-step', [
                'step' => 'scan',
                'qr' => $this->qrCode($user),
                'key' => trim(chunk_split($secret, 4, ' ')),
            ]);
        }

        return view('account.two-step', [
            'step' => $request->query('step') === 'password' && ! $this->recentlyConfirmed($request) ? 'password' : 'intro',
            'confirmed' => $this->recentlyConfirmed($request),
        ]);
    }

    /**
     * "Turn it on": checks the password unless it was given in the last 15 minutes, then makes a
     * new secret, which only counts once a code from it is confirmed.
     */
    public function start(Request $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        if (! $this->recentlyConfirmed($request)) {
            $request->validate(
                ['password' => ['required', 'string', 'current_password:web']],
                ['password.current_password' => 'That isn’t your password.'],
            );
            $request->session()->passwordConfirmed();
        }

        $user = $this->user($request);
        if (! $user->hasTwoStep()) {
            $enable($user, true);
        }

        return redirect()->route('account.two-step');
    }

    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']], ['code.required' => 'Enter the 6-digit code from your app.']);

        try {
            $confirm($this->user($request), (string) preg_replace('/\D/', '', (string) $request->input('code')));
        } catch (ValidationException) {
            throw ValidationException::withMessages([
                'code' => 'That code doesn’t match. Check the app shows '.config('app.name').' and type its newest code.',
            ]);
        }
        $request->session()->put(self::SHOWING_CODES, true);

        return redirect()->route('account.two-step');
    }

    public function finish(Request $request): RedirectResponse
    {
        $request->validate(['saved' => ['accepted']], [
            'saved.accepted' => 'Save the codes and tick the box first. Without them, a lost phone locks you out until you visit us.',
        ]);
        $request->session()->forget(self::SHOWING_CODES);

        return redirect()->route('account.two-step')->with('status', 'two-step-done');
    }

    /**
     * Leaves the setup before the code is confirmed: the unused secret goes.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        if (! $user->hasTwoStep()) {
            $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null])->save();
        }

        return redirect()->route('account.security');
    }

    public function recoveryCodes(Request $request): View|RedirectResponse
    {
        $user = $this->user($request);

        return $user->hasTwoStep()
            ? view('account.recovery-codes', ['codes' => $user->recoveryCodes()])
            : redirect()->route('account.security');
    }

    public function regenerateRecoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        if (! $this->recentlyConfirmed($request)) {
            return $this->confirmPasswordThen('account.recovery-codes', $request);
        }
        $user = $this->user($request);
        if ($user->hasTwoStep()) {
            $generate($user);
        }

        return redirect()->route('account.recovery-codes')
            ->with('status', 'Here are your new recovery codes. The old ones don’t work any more.');
    }

    public function turnOff(Request $request): View|RedirectResponse
    {
        return $this->user($request)->hasTwoStep()
            ? view('account.turn-off-two-step')
            : redirect()->route('account.security');
    }

    /**
     * Takes the place of Fortify's route at the same address (routes/web.php): turning it off needs
     * a code from the app, or a recovery code, as well as the password.
     */
    public function disable(Request $request, DisableTwoFactorAuthentication $disable, TwoFactorAuthenticationProvider $provider): RedirectResponse
    {
        if (! $this->recentlyConfirmed($request)) {
            return $this->confirmPasswordThen('account.two-step.turn-off', $request);
        }
        $request->validate(['code' => ['required', 'string']], ['code.required' => 'Enter a code from your app, or a recovery code.']);

        $user = $this->user($request);
        $code = trim((string) $request->input('code'));
        $secret = Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);
        $valid = $provider->verify($secret, (string) preg_replace('/\D/', '', $code))
            || collect($user->recoveryCodes())->contains(fn (string $recovery) => hash_equals($recovery, $code));

        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'That code doesn’t match. Type the newest one from your app, or a recovery code.']);
        }
        $disable($user);

        return redirect()->route('account.security')->with('status', '2-step verification is off. We’ve emailed you about it.');
    }

    private function recentlyConfirmed(Request $request): bool
    {
        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);

        return time() - $confirmedAt < (int) config('auth.password_timeout', 900);
    }

    /**
     * To the password page, and afterwards back to a page (never to the form that was sent, which
     * Fortify would repeat as a GET).
     */
    private function confirmPasswordThen(string $route, Request $request): RedirectResponse
    {
        $request->session()->put('url.intended', route($route));

        return redirect()->route('password.confirm');
    }

    private function qrCode(User $user): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle(232, 1, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(14, 58, 47))),
            new SvgImageBackEnd,
        ));
        $svg = $writer->writeString($user->twoFactorQrCodeUrl());

        // Without the XML declaration, so it can sit in the page
        return trim(substr($svg, (int) strpos($svg, "\n") + 1));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
