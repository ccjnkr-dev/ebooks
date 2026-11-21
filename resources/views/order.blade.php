
@extends('layouts.app')
@section('title','Order details')
@section('content')<section class="section narrow"><a class="breadcrumb" href="{{ route('orders') }}">My orders</a><h1>Your discovery details.</h1><div class="panel"><div class="section-heading"><h2>Order {{ substr($order->number,0,8) }}</h2><span class="badge {{ $order->status }}">{{ ucfirst($order->status) }}</span></div><p class="small muted">{{ $order->created_at->format('M j, Y · g:i a') }} · Simulated payment</p>
@foreach($order->items as $item)<div class="summary-row"><span>{{ $item->title }} × {{ $item->quantity }}<small class="block muted">{{ ucfirst($item->type) }}</small></span><strong>₱{{ number_format($item->unit_price*$item->quantity/100,2) }}</strong></div>
@endforeach<div class="summary-row total"><strong>Total</strong><strong>{{ $order->total_label }}</strong></div>
@if($order->status==='paid')<a class="button primary" href="{{ route('library') }}">Open my library</a>
@elseif($order->status==='pending')<a class="button primary" href="{{ route('payment',$order) }}">Continue demo payment</a>
@else<p>Your order is {{ $order->status }}. @if($order->status==='refunded')Digital access for this order has been revoked.
@endif</p><a class="button secondary" href="{{ route('catalog') }}">Back to the collection</a>
@endif @if($order->fulfillment_status && $order->status==='paid')<div class="pickup-panel"><h3>Painting pickup</h3><span class="badge">{{ ucfirst($order->fulfillment_status) }}</span><p class="preserve-lines">{{ $order->pickup_instructions }}</p><p class="small muted">Please wait until your order is marked ready before collecting.</p></div>
@endif</div></section>
@endsection
