
@extends('layouts.app')
@section('title','Your bag')
@section('content')<section class="page-heading"><span class="eyebrow">GOOD FINDS, ALL TOGETHER</span><h1>Your collection bag.</h1></section><section class="section checkout-layout"><div>
@forelse($products as $product)<article class="cart-item"><a href="{{ route('product',$product->slug) }}"><x-product-art :product="$product"/></a><div><span class="eyebrow">{{ $product->type }}</span><h3>{{ $product->title }}</h3><p>{{ $product->creator }}</p><span class="muted small">Quantity: {{ $cart[$product->id] }} · {{ $product->ready?'Ready to collect':'Awaiting content upload' }}</span><form method="post" action="{{ route('cart.remove',$product) }}">
@csrf @method('delete')<button class="text-button">Remove</button></form></div><strong>{{ $product->price_label }}</strong></article>
@empty<div class="empty-state"><x-icon name="bag"/><h2>Room for a new favorite.</h2><p>Your bag is waiting for a little inspiration.</p><a class="button primary" href="{{ route('catalog') }}">Explore the collection</a></div>
@endforelse</div>
@if($products->count())<aside class="summary-card"><h2>A lovely little collection.</h2><div class="summary-row"><span>Subtotal</span><strong>₱{{ number_format($products->sum(fn($p)=>$p->price*$cart[$p->id])/100,2) }}</strong></div><div class="summary-row"><span>Delivery</span><span>{{ $products->contains('type','painting')?'Pickup only · Free':'Online access' }}</span></div><p class="small muted">Demo store. No actual money will be charged.</p><form method="post" action="{{ route('checkout') }}" class="stack-form">
@csrf<label class="checkbox-label"><input type="checkbox" name="accept_pickup" value="1" required><span>I understand that books and music are accessed online and physical paintings must be collected in person.</span></label><button class="button primary full">{{ auth()->check()?'Continue to demo checkout':'Sign in to check out' }}</button></form><p class="small muted">Painting stock is reserved for 15 minutes during checkout. Prices are confirmed by the server.</p></aside>
@endif</section>
@endsection
