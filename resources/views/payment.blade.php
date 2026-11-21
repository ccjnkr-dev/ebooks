
@extends('layouts.app')
@section('title','Demo checkout')
@section('content')<section class="section narrow"><span class="eyebrow">SIMULATED PAYMENT</span><h1>One step closer.</h1><div class="notice"><x-icon name="lock"/><div><strong>This checkout is a demonstration.</strong><p>No payment information is collected and no money changes hands.</p></div></div><div class="panel"><h2>Order summary</h2>
@foreach($order->items as $item)<div class="summary-row"><span>{{ $item->title }} × {{ $item->quantity }}</span><span>₱{{ number_format($item->unit_price*$item->quantity/100,2) }}</span></div>
@endforeach<div class="summary-row total"><strong>Total</strong><strong>{{ $order->total_label }}</strong></div><p class="muted small">Reference {{ $order->payment->reference }}<br>Reservation expires {{ $order->expires_at->format('M j, g:i a') }} ({{ config('app.timezone') }})</p>
@if($order->status==='pending')<form method="post" action="{{ route('payment.confirm',$order) }}" class="stack-form">
@csrf<button class="button primary full" name="outcome" value="success">Simulate successful payment</button><div class="button-row"><button class="button secondary" name="outcome" value="failure">Simulate failure</button><button class="button secondary" name="outcome" value="cancel">Cancel checkout</button></div></form>
@else<p>This checkout is {{ $order->status }}.</p><a class="button secondary" href="{{ route('orders.show',$order) }}">View order</a>
@endif</div></section>
@endsection
