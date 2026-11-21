
@extends('layouts.app')
@section('title',$mode==='register'?'Join the collection':'Welcome back')
@section('content')<section class="auth-shell"><div class="auth-story"><span class="eyebrow">A LITTLE ROOM FOR WHAT YOU LOVE</span><h1>Your next favorite<br>is waiting.</h1><p>Collect the stories, sounds, and art<br>that make your world a little richer.</p><span class="auth-star">✳</span></div><div class="auth-form"><span class="eyebrow">{{ mb_strtoupper(\App\Models\Setting::read('brand_name','Muse & Folio')) }} COMMUNITY</span><h2>{{ $mode==='register'?'Make yourself at home.':'Good to see you again.' }}</h2><p>{{ $mode==='register'?'Create an account to start your collection.':'Sign in to revisit your collection.' }}</p><form method="post" action="{{ $mode==='register'?route('register'):route('login') }}" class="stack-form">
@csrf @if($mode==='register')<label>Your name<input required name="name" value="{{ old('name') }}" maxlength="100" autocomplete="name"></label>
@endif<label>Email address<input required type="email" name="email" value="{{ old('email') }}" autocomplete="email"></label><label>Password<input required type="password" name="password" minlength="{{ $mode==='register'?10:1 }}" autocomplete="{{ $mode==='register'?'new-password':'current-password' }}"></label>
@if($mode==='register')<p class="small muted">At least 10 characters, including letters and numbers.</p><label>Confirm password<input required type="password" name="password_confirmation" autocomplete="new-password"></label>
@else<label class="checkbox-label"><input type="checkbox" name="remember" value="1">Keep me signed in</label>
@endif<button class="button primary full">{{ $mode==='register'?'Create my account':'Sign in' }}</button></form><p class="auth-switch">{{ $mode==='register'?'Already part of the collection?':'New around here?' }} <a href="{{ $mode==='register'?route('login'):route('register') }}">{{ $mode==='register'?'Sign in':'Create an account' }}</a></p></div></section>
@endsection
