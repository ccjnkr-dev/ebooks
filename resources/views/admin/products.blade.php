
@extends('layouts.admin')
@section('title','Content library')
@section('admin-content')<div class="section-heading"><div><span class="eyebrow">MAKE SOMETHING WORTH DISCOVERING</span><h1>Your content library.</h1></div><a class="button primary" href="{{ route('admin.products.create') }}">Add new content</a></div><form method="get" class="filter-bar"><input name="q" value="{{ request('q') }}" placeholder="Search content titles" aria-label="Search content"><button class="button secondary">Search</button></form><div class="panel table-wrap"><table><thead><tr><th>Creative work</th><th>Price</th><th>Publication</th><th>Media</th><th>Stock</th><th>Manage</th></tr></thead><tbody>
@forelse($products as $product)<tr><td><strong>{{ $product->title }}</strong><small class="block muted">{{ ucfirst($product->type) }} · {{ $product->creator }}</small></td><td>{{ $product->price_label }}</td><td><span class="badge {{ $product->status }}">{{ $product->status }}</span></td><td>{{ $product->assets->sortByDesc('id')->first()?->status ?? 'No upload' }}</td><td>{{ $product->type==='painting'?$product->stock:'Digital' }}</td><td><a class="text-link" href="{{ route('admin.products.edit',$product) }}">Edit & upload</a></td></tr>
@empty<tr><td colspan="6">No content yet. Add your first creative work.</td></tr>
@endforelse</tbody></table></div>{{ $products->links() }}
@endsection
