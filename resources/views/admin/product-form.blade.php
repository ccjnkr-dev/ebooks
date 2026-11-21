@extends('layouts.admin')
@section('title', $product->exists ? 'Edit content' : 'Add content')
@section('admin-content')
@php
    $selectedType = old('type', $product->type);
    $latestAsset = $product->exists ? $product->assets->sortByDesc('id')->first() : null;
    $processing = $latestAsset && in_array($latestAsset->status, ['pending', 'processing'], true);
    $formConfig = ['type' => $selectedType, 'processing' => (bool) $processing, 'statusUrl' => $product->exists ? route('admin.products.edit', $product) : null];
@endphp
<a class="breadcrumb" href="{{ route('admin.products') }}">Content library</a>
<h1>{{ $product->exists ? 'A little fine-tuning.' : 'Make room for something new.' }}</h1>
<div class="panel">
    <form method="post" enctype="multipart/form-data"
          action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}"
          class="stack-form" x-data="contentForm(@js($formConfig))" @input="dirty = true" @change="dirty = true">
        @csrf
        @if($product->exists)
            @method('put')
        @endif
        <div class="form-grid">
            <label>Title
                <input required name="title" value="{{ old('title', $product->title) }}" maxlength="160">
            </label>
            <label>Creator / artist
                <input required name="creator" value="{{ old('creator', $product->creator) }}" maxlength="120">
            </label>
            <label>Content type
                <select name="type" x-model="type">
                    @foreach(['book' => 'Online book (PDF)', 'music' => 'Music (MP3)', 'painting' => 'Physical painting (JPG/PNG sample)'] as $value => $label)
                        <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Category
                <select name="category_id">
                    <option value="">Uncategorized</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Price (PHP)
                <input required type="number" name="price" min="1" max="1000000" step="0.01" value="{{ old('price', number_format($product->price / 100, 2, '.', '')) }}">
            </label>
            <label x-show="type === 'painting'" :hidden="type !== 'painting'" @if($selectedType !== 'painting') hidden @endif>
                Available painting stock
                <input type="number" name="stock" min="0" max="10000" value="{{ old('stock', $product->stock) }}"
                       :disabled="type !== 'painting'" :required="type === 'painting'" @disabled($selectedType !== 'painting')>
                <small class="muted">Excludes stock held by pending checkouts.</small>
            </label>
            <label>Status
                <select name="status" aria-describedby="publication-help">
                    @foreach(['draft', 'published', 'archived'] as $value)
                        <option value="{{ $value }}" @selected(old('status', $product->publication_requested ? 'published' : $product->status) === $value)>{{ ucfirst($value) }}</option>
                    @endforeach
                </select>
            </label>
            <label>Cover accent
                <select name="accent">
                    @foreach(['violet', 'rose', 'teal', 'amber'] as $value)
                        <option value="{{ $value }}" @selected(old('accent', $product->accent) === $value)>{{ ucfirst($value) }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <p id="publication-help" class="small muted">Choose Published and save once. New uploads publish automatically after their previews are prepared.</p>
        <label>Description
            <textarea required name="description" rows="5" maxlength="10000">{{ old('description', $product->description) }}</textarea>
        </label>
        <label>Details (pages, genre, dimensions, medium)
            <input name="details" maxlength="200" value="{{ old('details', $product->details) }}">
        </label>
        <label class="checkbox-label">
            <input name="featured" type="checkbox" value="1" @checked(old('featured', $product->featured))>
            Feature in the discovery edit
        </label>
        <div>
            <span class="eyebrow">CONTENT FILE</span>
            <label class="stack-form">Content file
                <input type="file" name="content" aria-describedby="content-help"
                       accept="{{ match($selectedType) { 'book' => '.pdf', 'music' => '.mp3', default => '.jpg,.jpeg,.png' } }}"
                       :accept="fileAccept">
            </label>
            <p id="content-help" class="small muted" x-text="fileHelp">{{ match($selectedType) { 'book' => 'PDF: up to 50 MB and 500 pages. The first page becomes the public preview.', 'music' => 'MP3: up to 100 MB. A separate 30-second preview is created automatically.', default => 'JPG/PNG: up to 20 MB and 40 megapixels. A watermark is burned into the sample.' } }}</p>
            @if($latestAsset)
                <p class="small">Current file: <strong>{{ $latestAsset->original_name }}</strong>. Leave the file field empty to keep it.</p>
                @if($latestAsset->status === 'failed')
                    <div class="flash error" role="alert">
                        <strong>The current file could not be prepared.</strong>
                        <p>Choose a replacement, or use Save content to retry this file.</p>
                        <pre class="processing-error">{{ $latestAsset->error }}</pre>
                    </div>
                @endif
            @endif
            @if($processing)
                <div class="notice" role="status">
                    <x-icon name="lock"/>
                    <div>
                        <strong>{{ $product->publication_requested ? 'Preparing your content for publication.' : 'Preparing your content previews.' }}</strong>
                        <p>{{ $product->publication_requested ? 'It will publish automatically when ready. You do not need to save again.' : 'You can choose Published and save now, or keep the content as a draft.' }}</p>
                        <p class="small muted" x-show="processingFinished" x-cloak>Preparation has finished. Your unsaved edits are preserved; save them when ready.</p>
                    </div>
                </div>
            @elseif($product->ready)
                <p class="small"><span class="badge published">Previews ready</span> {{ $product->status === 'published' ? 'This content is published.' : 'Choose Published to make this content available.' }}</p>
            @endif
            <p class="small muted">A replacement becomes active only after it is prepared successfully. Original files remain private.</p>
        </div>
        <div><button class="button primary" type="submit">Save content</button></div>
    </form>
</div>
@if($product->exists)
    <div class="danger-panel">
        <h2>Remove from the collection</h2>
        <p>Purchased works are archived, retaining customer access. Unpurchased works move to deleted content for optional permanent cleanup.</p>
        <form method="post" action="{{ route('admin.products.destroy', $product) }}" x-data @submit="if (!confirm('Remove this product from the storefront?')) $event.preventDefault()">
            @csrf
            @method('delete')
            <button class="button danger">Archive or delete content</button>
        </form>
    </div>
@endif
@endsection
