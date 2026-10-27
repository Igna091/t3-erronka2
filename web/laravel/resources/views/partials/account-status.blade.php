{{-- The message after a change: Fortify's status keys in words, our own messages as they are. --}}
@php($status = session('status'))
@php($words = [
    'password-updated' => 'Your password is changed, and every other browser is logged out. We’ve emailed you about it.',
    'profile-information-updated' => 'Your details are saved.',
])
@if (is_string($status) && ! in_array($status, ['two-step-done', 'verification-link-sent'], true))
    <p class="form-note form-note--done" role="status">{{ $words[$status] ?? $status }}</p>
@endif
