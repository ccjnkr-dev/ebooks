
@extends('layouts.admin')
@section('title','Deleted content')
@section('admin-content')<span class="eyebrow">CONTENT CLEANUP</span><h1>Deleted content.</h1><p class="muted">Only content without order references or active processing can be permanently removed.</p><div class="panel table-wrap"><table><thead><tr><th>Work</th><th>Order references</th><th>Action</th></tr></thead><tbody>
@forelse($products as $product)<tr><td>{{ $product->title }}</td><td>{{ $product->order_items_count }}</td><td><form method="post" action="{{ route('admin.products.purge',$product->id) }}" x-data @submit="if(!confirm('Permanently delete this unreferenced work and all of its files?')) $event.preventDefault()">
@csrf @method('delete')<button class="button danger">Delete permanently</button></form></td></tr>
@empty<tr><td colspan="3">No deleted content.</td></tr>
@endforelse</tbody></table></div>{{ $products->links() }}
@endsection
