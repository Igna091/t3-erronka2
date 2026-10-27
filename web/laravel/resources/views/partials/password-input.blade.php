{{-- A password field with a Show button (account.js switches the type; without JavaScript the
     button stays hidden). $id, $name, $autocomplete, and optionally $describedby and $error. --}}
<div class="password-input">
    <input id="{{ $id }}" name="{{ $name }}" type="password" autocomplete="{{ $autocomplete }}" maxlength="128" required
        @if (! empty($describedby)) aria-describedby="{{ $describedby }}" @endif
        @if (! empty($error)) aria-invalid="true" @endif>
    <button class="password-input__toggle" type="button" data-password-toggle="{{ $id }}" aria-controls="{{ $id }}" hidden>Show</button>
</div>
