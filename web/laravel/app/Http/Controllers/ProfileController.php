<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ProfilePhoto;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Name, email and the profile photo, which helps the pharmacy recognise a customer at the counter.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.profile', ['user' => $this->user($request)]);
    }

    public function storePhoto(Request $request): RedirectResponse
    {
        $request->validate(
            ['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048']],
            [
                'photo.required' => 'Choose a photo first.',
                'photo.mimes' => 'Use a JPEG, PNG or WebP photo.',
                'photo.max' => 'That photo is over 2 MB. Use a smaller one.',
            ],
        );
        $photo = $request->file('photo');
        abort_unless($photo instanceof UploadedFile, 422);
        ProfilePhoto::store($this->user($request), $photo);

        return redirect()->route('account.profile')->with('status', 'Your photo is saved.');
    }

    public function destroyPhoto(Request $request): RedirectResponse
    {
        ProfilePhoto::delete($this->user($request));

        return redirect()->route('account.profile')->with('status', 'Your photo is removed.');
    }

    /**
     * The owner's own photo, never cached by a shared proxy.
     */
    public function photo(Request $request): Response
    {
        $path = $this->user($request)->profile_image;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return response((string) Storage::disk('local')->get($path), 200, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
