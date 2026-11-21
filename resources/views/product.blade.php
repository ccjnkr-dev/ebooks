
@extends('layouts.app')
@section('title',$product->title)
@section('content')<section class="section product-detail"><div><a class="breadcrumb" href="{{ route('catalog',['type'=>$product->type]) }}">Collection / {{ ucfirst($product->type) }}</a><x-product-art :product="$product" class="detail-art"/><p class="muted small">{{ $product->type==='painting'?'Only reduced-resolution, watermarked samples are displayed.':'Read and listen in your online library after purchase.' }}</p></div><div class="detail-copy"><span class="eyebrow">{{ $product->category?->name ?? ucfirst($product->type) }}</span><h1>{{ $product->title }}</h1><p class="detail-creator">By {{ $product->creator }}</p><div class="detail-price">{{ $product->price_label }}<span>{{ $product->type==='painting'?'Original physical painting · Pickup only':'One-time purchase · Online access' }}</span></div><p class="description">{{ $product->description }}</p>
@if($product->details)<p class="detail-spec">{{ $product->details }}</p>
@endif
@if(!$product->ready)<div class="notice"><x-icon name="lock"/><div><strong>A glimpse of what’s coming.</strong><p>This is a sample listing. Media has not been uploaded yet, so previews and purchasing are unavailable.</p></div></div><button class="button primary full" disabled>Available after content upload</button>
@elseif($owned)<a class="button primary full" href="{{ route('reader',$product) }}">{{ $product->type==='book'?'Continue reading':'Listen to your track' }}</a>
@elseif($product->type==='painting' && $product->stock<1)<button class="button primary full" disabled>Sold out</button>
@else<form action="{{ route('cart.add',$product) }}" method="post">
@csrf<button class="button primary full">{{ $product->type==='painting'?'Collect this painting':'Add to bag' }}</button></form>
@endif
<div class="purchase-notes"><span><x-icon name="check"/>{{ $product->type==='painting'?'Collect in person when ready':'Access from your personal library' }}</span><span><x-icon name="lock"/>Demo checkout · No real charge</span></div></div></section>
@if($product->type!=='painting')<section class="preview-section section"><div class="section-heading"><div><span class="eyebrow">GET A LITTLE CLOSER</span><h2>{{ $product->type==='book'?'The first page.':'A little listening session.' }}</h2></div></div><div class="preview-box">
@if($product->ready)
@if($product->type==='book')<img class="book-page" src="{{ route('preview',$product) }}" alt="First page of {{ $product->title }}">
@else<div class="audio-preview"><x-icon name="music"/><h3>{{ $product->title }}</h3><p>Preview · Up to {{ config('commerce.preview_seconds') }} seconds</p><audio controls controlsList="nodownload" preload="none" src="{{ route('preview',$product) }}"></audio></div>
@endif
@else<div class="preview-placeholder"><x-icon :name="$product->type==='book'?'book':'music'"/><h3>Your preview will appear here.</h3><p>Awaiting the creator’s content upload.</p></div>
@endif<div class="preview-gate"><x-icon name="lock"/><h3>{{ $product->type==='book'?'Pay to read full book':'Pay to listen to the full track' }}</h3><p>Make this discovery part of your personal collection.</p>
@if($owned)<a class="button primary" href="{{ route('reader',$product) }}">Open in my library</a>
@endif</div></div></section>
@endif @endsection
