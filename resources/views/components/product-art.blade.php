
@props(['product','hero'=>false])
<div {{ $attributes->merge(['class'=>'product-art '.$product->type.'-art tone-'.$product->accent]) }}>
@if($product->type==='painting' && $product->ready)
 <img src="{{ route('preview',$product) }}" alt="Watermarked sample of {{ $product->title }}" loading="lazy">
@elseif($product->type==='book')
 <div class="book-object"><div class="book-author">{{ $product->creator }}</div><div class="book-title">{{ $product->title }}</div><div class="cover-illustration"><i></i><i></i><i></i></div><span class="book-imprint">A {{ mb_strtoupper(\App\Models\Setting::read('brand_name','Muse & Folio')) }} SELECTION</span></div>
@elseif($product->type==='music')
 <div class="record-disc"><span></span></div><div class="record-sleeve"><span class="sleeve-caption">{{ $product->creator }}</span><span class="sleeve-title">{{ $product->title }}</span><div class="sound-lines">
@for($i=0;$i<15;$i++)<i style="--wave:{{ 20+(($i*37)%70) }}%"></i>
@endfor</div><small>INDEPENDENT SOUNDS</small></div>
@else
 <div class="painting-frame"><div class="painting-placeholder"><span>ARTWORK<br>SAMPLE</span><small>Awaiting artist upload</small></div></div>
@endif
@if(!$hero)<span class="art-tag">{{ $product->type==='painting'?'Original artwork':($product->type==='music'?'Listen online':'Read online') }}</span>
@endif
</div>
