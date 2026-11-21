
@extends('layouts.app')
@section('title',$product->title)
@section('content')<section class="section reader-shell"><div class="section-heading"><div><a class="breadcrumb" href="{{ route('library') }}">My library</a><h1>{{ $product->title }}</h1><p>{{ $product->creator }}</p></div><span class="badge">Personal online access</span></div>
@php($media=\Illuminate\Support\Facades\URL::temporarySignedRoute('media',now()->addMinutes(15),['product'=>$product->id,'uid'=>auth()->id(),'page'=>$page]))
@if($product->type==='book')<div class="reader-toolbar"><span>Page {{ $page }} of {{ $count }}</span><div class="button-row">
@if($page>1)<a class="button secondary" href="{{ route('reader',[$product,'page'=>$page-1]) }}">Previous page</a>
@endif @if($page<$count)<a class="button primary" href="{{ route('reader',[$product,'page'=>$page+1]) }}">Next page</a>
@endif</div><form method="get"><label class="inline-label">Go to page <input type="number" name="page" value="{{ $page }}" min="1" max="{{ $count }}" aria-label="Page number"></label><button class="button secondary">Go</button></form></div><div class="reader-page"><img src="{{ $media }}" alt="Page {{ $page }} of {{ $product->title }}"></div>
@else<div class="listening-room"><x-product-art :product="$product"/><div><span class="eyebrow">MAKE A LITTLE SPACE TO LISTEN</span><h2>{{ $product->title }}</h2><p>{{ $product->creator }}</p><audio controls controlsList="nodownload" preload="metadata" src="{{ $media }}"></audio><p class="small muted">If playback access expires, refresh this page to continue.</p></div></div>
@endif<p class="muted small">Licensed to your account for personal online use. Please respect the creator’s work.</p></section>
@endsection
