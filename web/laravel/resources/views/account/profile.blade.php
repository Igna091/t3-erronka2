@extends('layouts.account')

@section('title', 'Profile and photo · '.config('app.name'))

@section('header')
    @include('partials.header')
@endsection

@section('main')
    <div class="account-layout wrap">
        @include('partials.account-nav')

        <div class="account-content account-content--narrow">
            @include('partials.account-status')
            <div class="account-title">
                <h1>Profile and photo</h1>
            </div>

            <section class="panel-card" aria-labelledby="photo-title">
                <h2 id="photo-title">Profile photo</h2>
                <div class="photo-row">
                    @include('partials.avatar', ['user' => $user, 'class' => 'avatar--large'])
                    <p class="muted">So we recognise you at the counter when you collect a prescription. Only the pharmacy team sees it, and we keep a square copy of it, without the photo’s location or other details.</p>
                </div>
                <form class="auth-form" method="post" action="{{ route('account.photo.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="field">
                        <label for="photo">{{ $user->profile_image ? 'Choose a new photo' : 'Choose a photo' }}</label>
                        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required aria-describedby="photo-help @error('photo') photo-error @enderror" @error('photo') aria-invalid="true" @enderror>
                        <p class="field__help" id="photo-help">JPEG, PNG or WebP, up to 2 MB. A photo of your face, like on an ID card.</p>
                        @error('photo')<p class="field__error" id="photo-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="actions">
                        <button class="btn btn--primary btn--small" type="submit">Save the photo</button>
                    </div>
                </form>
                @if ($user->profile_image)
                    <form method="post" action="{{ route('account.photo.destroy') }}">
                        @csrf
                        @method('delete')
                        <button class="link-button" type="submit">Remove the photo</button>
                    </form>
                @endif
            </section>

            <section class="panel-card" aria-labelledby="details-title">
                <h2 id="details-title">Your details</h2>
                <form class="auth-form" method="post" action="{{ route('user-profile-information.update') }}">
                    @csrf
                    @method('put')
                    <div class="field">
                        <label for="name">Full name</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" autocomplete="name" maxlength="100" required @if ($errors->updateProfileInformation->has('name')) aria-invalid="true" @endif>
                        @if ($errors->updateProfileInformation->has('name'))<p class="field__error">{{ $errors->updateProfileInformation->first('name') }}</p>@endif
                    </div>
                    <div class="field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" autocomplete="email" maxlength="255" required aria-describedby="email-help" @if ($errors->updateProfileInformation->has('email')) aria-invalid="true" @endif>
                        <p class="field__help" id="email-help">A new email has to be confirmed with a link we send to it.</p>
                        @if ($errors->updateProfileInformation->has('email'))<p class="field__error">{{ $errors->updateProfileInformation->first('email') }}</p>@endif
                    </div>
                    <div class="actions">
                        <button class="btn btn--primary btn--small" type="submit">Save</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
@endsection
