<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="description" content="Discover independent books, music, and original paintings at {{ \App\Models\Setting::read('brand_name','Muse & Folio') }}."><title>
@yield('title','Discover something that stays with you') · {{ \App\Models\Setting::read('brand_name','Muse & Folio') }}</title>
@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body>
@php($brand=\App\Models\Setting::read('brand_name','Muse & Folio'))
<a class="skip-link" href="#main">Skip to content</a>
<div class="announcement">A home for independent stories, sounds & original art <span>Thoughtfully made. Personally discovered.</span></div>
<header class="site-header" x-data="{open:false}">
 <a class="brand" href="{{ route('home') }}"><span class="brand-mark" aria-hidden="true">m<span>f</span></span>{{ $brand }}<small>THE CREATIVE COLLECTION</small></a>
 <nav class="desktop-nav" aria-label="Main navigation"><a class="{{ request()->routeIs('home')?'active':'' }}" href="{{ route('home') }}">Discover</a><a href="{{ route('catalog',['type'=>'book']) }}">Books</a><a href="{{ route('catalog',['type'=>'music']) }}">Music</a><a href="{{ route('catalog',['type'=>'painting']) }}">Paintings</a></nav>
 <div class="header-actions">
@auth<a class="quiet-link" href="{{ route('library') }}">My library</a>
@if(auth()->user()->is_admin)<a class="quiet-link" href="{{ route('admin.dashboard') }}">Studio admin</a>
@endif<a class="account-icon" href="{{ route('account') }}" aria-label="My account"><x-icon name="user"/></a>
@else<a class="quiet-link" href="{{ route('login') }}">Sign in</a>
@endauth<a class="bag-link" href="{{ route('cart') }}"><x-icon name="bag"/><span class="bag-label">Bag</span><span class="bag-count">{{ array_sum(session('cart',[])) }}</span></a><button class="mobile-toggle" @click="open=!open" :aria-expanded="open" aria-controls="mobile-nav" aria-label="Toggle navigation"><x-icon name="menu"/></button></div>
 <nav id="mobile-nav" class="mobile-nav" x-show="open" x-cloak aria-label="Mobile navigation"><a href="{{ route('catalog') }}">All collections</a><a href="{{ route('catalog',['type'=>'book']) }}">Books</a><a href="{{ route('catalog',['type'=>'music']) }}">Music</a><a href="{{ route('catalog',['type'=>'painting']) }}">Paintings</a>
@auth<a href="{{ route('library') }}">My library</a>
@if(auth()->user()->is_admin)<a href="{{ route('admin.dashboard') }}">Studio admin</a>
@endif
@endauth</nav>
</header>
@if(session('success'))<div class="flash success" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())<div class="flash error" role="alert"><strong>Please check the following:</strong><ul>
@foreach($errors->all() as $error)<li>{{ $error }}</li>
@endforeach</ul></div>
@endif
<main id="main">
@yield('content')</main>
<footer class="site-footer"><div><a class="footer-brand" href="{{ route('home') }}">{{ $brand }}</a><p>{{ \App\Models\Setting::read('tagline','For the stories, sounds, and art that stay with you.') }}</p></div><div class="footer-links"><a href="{{ route('catalog') }}">Explore the collection</a><a href="mailto:{{ \App\Models\Setting::read('contact_email','hello@example.test') }}">Get in touch</a>
@auth<a href="{{ route('orders') }}">My orders</a>
@endauth</div><div class="footer-bottom"><span>© {{ date('Y') }} {{ $brand }}. A little room for creativity.</span><span>Demo store · No real payments</span></div></footer>
</body></html>
