{{-- The account's photo, or its initials until there is one. $user is the logged-in user. --}}
@if ($user->profile_image && $user->hasVerifiedEmail())
    <img @class(['avatar', $class ?? null]) src="{{ route('account.photo') }}" alt="" width="34" height="34">
@else
    <span @class(['avatar', $class ?? null]) aria-hidden="true">{{ $user->initials() }}</span>
@endif
