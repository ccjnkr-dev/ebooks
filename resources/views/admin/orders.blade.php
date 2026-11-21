
@extends('layouts.admin')
@section('title','Orders & pickups')
@section('admin-content')<span class="eyebrow">FROM DISCOVERY TO COLLECTION</span><h1>Orders & pickups.</h1><form class="filter-bar"><select name="status" aria-label="Order status"><option value="">All statuses</option>
@foreach(['pending','paid','failed','cancelled','expired','refunded'] as $s)<option @selected(request('status')===$s)>{{ $s }}</option>
@endforeach</select><button class="button secondary">Filter orders</button></form>
@forelse($orders as $order)<div class="panel"><div class="section-heading"><div><a class="text-link" href="{{ route('orders.show',$order) }}">Order {{ substr($order->number,0,8) }}</a><p>{{ $order->user->name }} · {{ $order->created_at->format('M j, Y') }}</p></div><span class="badge {{ $order->status }}">{{ $order->status }}</span><strong>{{ $order->total_label }}</strong></div><p>{{ $order->items->map(fn($i)=>$i->title.' × '.$i->quantity)->join(' · ') }}</p><div class="button-row">
@if($order->status==='paid' && in_array($order->fulfillment_status,['awaiting','ready']))<form method="post" action="{{ route('admin.fulfillment',$order) }}">
@csrf @method('patch')<button class="button primary" name="fulfillment_status" value="{{ $order->fulfillment_status==='awaiting'?'ready':'collected' }}">{{ $order->fulfillment_status==='awaiting'?'Mark ready for pickup':'Mark collected' }}</button></form>
@endif @if($order->status==='paid' && $order->fulfillment_status!=='collected')<form method="post" action="{{ route('admin.refund',$order) }}" x-data @submit="if(!confirm('Simulate a full refund and revoke digital access?')) $event.preventDefault()">
@csrf<button class="button danger">Simulate full refund</button></form>
@endif @if($order->fulfillment_status)<span class="small muted">Pickup: {{ $order->fulfillment_status }}</span>
@endif</div></div>
@empty<div class="empty-state">No orders match this view.</div>
@endforelse{{ $orders->links() }}
@endsection
