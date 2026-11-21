
@extends('layouts.admin')
@section('title','Store settings')
@section('admin-content')<span class="eyebrow">MAKE IT YOUR OWN</span><h1>The details that make a brand.</h1><div class="panel"><h2>Store identity & pickup</h2><form method="post" action="{{ route('admin.settings.save') }}" class="stack-form">
@csrf @method('put')
@foreach(['brand_name'=>'Brand name','tagline'=>'Store tagline','contact_email'=>'Contact email','watermark'=>'Watermark text (ASCII characters)'] as $key=>$label)<label>{{ $label }}<input name="{{ $key }}" value="{{ old($key,\App\Models\Setting::read($key)) }}" required @if($key==='contact_email')type="email"
@endif></label>
@endforeach<label>Pickup instructions<textarea name="pickup_instructions" rows="4" required>{{ old('pickup_instructions',\App\Models\Setting::read('pickup_instructions')) }}</textarea></label><p class="small muted">Pickup instructions are saved with each new order. Existing painting samples keep their burned-in watermark until re-uploaded.</p><div><button class="button primary">Save store settings</button></div></form></div><div class="panel"><h2>Collection categories</h2><form method="post" action="{{ route('admin.categories.store') }}" class="filter-bar">
@csrf<input name="name" required maxlength="60" placeholder="New category name" aria-label="New category name"><button class="button secondary">Add category</button></form>
@foreach($categories as $category)<div class="summary-row"><span>{{ $category->name }} <small class="muted">({{ $category->products_count }} works)</small></span><form method="post" action="{{ route('admin.categories.destroy',$category) }}">
@csrf @method('delete')<button class="text-button">Remove</button></form></div>
@endforeach</div>
@endsection
