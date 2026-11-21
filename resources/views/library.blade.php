
@extends('layouts.app')
@section('title','My library')
@section('content')<section class="page-heading"><span class="eyebrow">YOUR OWN LITTLE WORLD</span><h1>Welcome to your library.</h1><p>Stories to return to. Sounds to get lost in.</p><div class="button-row"><a class="text-link" href="{{ route('orders') }}">My orders</a><a class="text-link" href="{{ route('account') }}">Account settings</a></div></section><section class="section"><div class="product-grid">
@forelse($entitlements as $entry)<article class="product-card"><x-product-art :product="$entry->product"/><div class="card-meta"><span>{{ ucfirst($entry->product->type) }}</span><span>In your collection</span></div><h3>{{ $entry->product->title }}</h3><p class="creator">{{ $entry->product->creator }}</p><a class="button primary" href="{{ route('reader',$entry->product) }}">{{ $entry->product->type==='book'?'Continue reading':'Listen now' }}</a></article>
@empty<div class="empty-state"><x-icon name="book"/><h2>A good collection starts with curiosity.</h2><p>Your purchased books and music will find their home here.</p><a class="button primary" href="{{ route('catalog') }}">Find your first favorite</a></div>
@endforelse</div></section>
@endsection
