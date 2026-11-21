
@extends('layouts.app')
@section('title','My orders')
@section('content')<section class="page-heading"><span class="eyebrow">YOUR DISCOVERIES, COLLECTED</span><h1>My orders.</h1></section><section class="section">
@forelse($orders as $order)<a class="order-list-item" href="{{ route('orders.show',$order) }}"><div><strong>{{ $order->items->pluck('title')->join(', ') }}</strong><p>{{ $order->created_at->format('M j, Y') }} · {{ substr($order->number,0,8) }}</p></div><span class="badge {{ $order->status }}">{{ ucfirst($order->status) }}</span><strong>{{ $order->total_label }}</strong></a>
@empty<div class="empty-state"><h2>Your story starts here.</h2><p>You haven’t placed an order yet.</p><a class="button primary" href="{{ route('catalog') }}">Explore the collection</a></div>
@endforelse{{ $orders->links() }}</section>
@endsection
