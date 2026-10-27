{{-- The picture check (App\Support\Captcha). Each load of the picture replaces its answer;
     "New picture" reloads only the picture with JavaScript, the whole page without. --}}
<div class="captcha" role="group" aria-labelledby="captcha-title">
    <p class="captcha__title" id="captcha-title">Type the characters in the picture</p>
    @if (! empty($why))
        <p class="field__help">{{ $why }}</p>
    @endif
    <div class="captcha__picture">
        <img src="{{ route('captcha') }}?{{ \Illuminate\Support\Str::random(8) }}" width="200" height="64" alt="Picture with 5 characters to type" data-captcha>
        <a class="btn btn--outline btn--small" href="{{ url()->current() }}" data-captcha-refresh>
            <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 6.5A5.5 5.5 0 1 0 13 11"/><path d="M14 2.5v4h-4"/></svg>
            New picture
        </a>
    </div>
    <div class="field">
        <label class="visually-hidden" for="captcha">Characters in the picture</label>
        <input class="captcha__input" id="captcha" name="captcha" type="text" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="10" required @error('captcha') aria-invalid="true" aria-describedby="captcha-error" @enderror>
        @error('captcha')<p class="field__error" id="captcha-error">{{ $message }}</p>@enderror
    </div>
    <p class="field__help">Can’t read it? Call us on <a href="tel:{{ config('pharmacy.phone.dial') }}">{{ config('pharmacy.phone.display') }}</a> and we’ll help you.</p>
</div>
