
@extends('layouts.app')
@section('title','My account')
@section('content')<section class="section narrow"><span class="eyebrow">YOUR SPACE</span><h1>Account settings.</h1><div class="panel"><p>Signed in as <strong>{{ auth()->user()->email }}</strong></p><form method="post" action="{{ route('account.update') }}" class="stack-form">
@csrf @method('patch')<label>Your name<input name="name" value="{{ old('name',auth()->user()->name) }}" required autocomplete="name"></label><label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label><label>New password<input type="password" name="password" required minlength="10" autocomplete="new-password"></label><p class="small muted">At least 10 characters, including letters and numbers.</p><label>Confirm new password<input type="password" name="password_confirmation" required autocomplete="new-password"></label><button class="button primary">Save profile and password</button></form></div><form method="post" action="{{ route('logout') }}">
@csrf<button class="text-button">Sign out of my account</button></form></section>
@endsection
