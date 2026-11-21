
@extends('layouts.app')
@section('title','The collection')
@section('content')<section class="page-heading"><span class="eyebrow">CURATED FOR THE CURIOUS</span><h1>{{ match(request('type')) {'book'=>'Between the pages.','music'=>'Find your frequency.','painting'=>'Art to come home to.',default=>'The collection.'} }}</h1><p>Independent stories, sounds, and original art. Find what speaks to you.</p></section><section class="section catalog-section"><nav class="collection-tabs" aria-label="Collection type">
@foreach([''=>'All discoveries','book'=>'Books','music'=>'Music','painting'=>'Paintings'] as $type=>$label)<a class="{{ request('type','')===$type?'selected':'' }}" href="{{ route('catalog',$type?['type'=>$type]:[]) }}">{{ $label }}</a>
@endforeach</nav><form class="filter-bar" method="get"><input type="hidden" name="type" value="{{ request('type') }}"><label class="search-field"><x-icon name="search"/><input name="q" value="{{ request('q') }}" placeholder="Search a title or creator" aria-label="Search products"></label><label><span class="sr-only">Category</span><select name="category"><option value="">All categories</option>
@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category')==$category->id)>{{ $category->name }}</option>
@endforeach</select></label><label><span class="sr-only">Sort</span><select name="sort">
@foreach(['newest'=>'Newest discoveries','price_low'=>'Price: low to high','price_high'=>'Price: high to low'] as $value=>$label)<option value="{{ $value }}" @selected(request('sort','newest')===$value)>{{ $label }}</option>
@endforeach</select></label><button class="button secondary">Apply</button></form><p class="results-label">{{ $products->total() }} discoveries <span>Demo listings · Media awaiting upload</span></p><div class="product-grid">
@forelse($products as $product)<x-product-card :product="$product"/>
@empty<div class="empty-state"><x-icon name="search"/><h2>No discoveries just yet.</h2><p>Try another search or explore all collections.</p><a class="button secondary" href="{{ route('catalog') }}">Clear filters</a></div>
@endforelse</div><div class="pagination">{{ $products->links() }}</div></section>
@endsection
