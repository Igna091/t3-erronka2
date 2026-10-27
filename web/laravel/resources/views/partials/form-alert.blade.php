{{-- A message about the whole form, such as a wrong password: $message, and optionally $title. --}}
<div class="form-alert" role="alert">
    <svg width="22" height="22" viewBox="0 0 22 22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="9.5"/><path d="M11 6.5v5.5"/><path d="M11 15.5v0.01"/></svg>
    <p>@if (! empty($title))<strong>{{ $title }}</strong> @endif{{ $message }}</p>
</div>
