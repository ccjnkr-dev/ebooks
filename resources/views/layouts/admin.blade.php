
@extends('layouts.app')
@section('content')<div class="admin-shell"><aside class="admin-sidebar"><span class="eyebrow">THE CREATOR’S WORKSPACE</span><h2>Studio admin.</h2><nav aria-label="Administration">
@foreach(['dashboard'=>'Overview','products'=>'Content library','orders'=>'Orders & pickups','payments'=>'Payments','customers'=>'Customers','reports'=>'Reports','settings'=>'Store settings','audit'=>'Activity log','trash'=>'Deleted content'] as $route=>$label)<a class="{{ request()->routeIs('admin.'.$route)||($route==='products' && request()->routeIs('admin.products.*'))?'selected':'' }}" href="{{ route('admin.'.$route) }}">{{ $label }}</a>
@endforeach</nav><div class="admin-help"><x-icon name="lock"/><strong>Demo payment mode</strong><p>All transactions are simulated. No real payments are accepted.</p></div></aside><section class="admin-content">
@yield('admin-content')</section></div>
@endsection
