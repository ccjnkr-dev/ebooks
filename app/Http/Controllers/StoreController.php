<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class StoreController extends Controller
{
    public function home(): View
    {
        return view('home', ['products' => Product::with(['asset', 'category'])->where('status', 'published')->orderByDesc('featured')->latest()->limit(6)->get()]);
    }

    public function catalog(Request $request): View
    {
        $filter = $request->validate(['type' => 'nullable|in:book,music,painting', 'q' => 'nullable|string|max:100', 'category' => 'nullable|integer', 'sort' => 'nullable|in:newest,price_low,price_high']);
        $query = Product::with(['asset', 'category'])->where('status', 'published')->when($filter['type'] ?? null, fn ($query, $v) => $query->where('type', $v))->when($filter['q'] ?? null, fn ($query, $v) => $query->where(fn ($query) => $query->where('title', 'like', '%'.$v.'%')->orWhere('creator', 'like', '%'.$v.'%')))->when($filter['category'] ?? null, fn ($query, $v) => $query->where('category_id', $v));
        match ($filter['sort'] ?? 'newest') {
            'price_low' => $query->orderBy('price'), 'price_high' => $query->orderByDesc('price'), default => $query->latest()
        };

        return view('catalog', ['products' => $query->paginate(12)->withQueryString(), 'categories' => Category::orderBy('name')->get()]);
    }

    public function product(Product $product): View
    {
        abort_unless($product->status === 'published', 404);
        $product->load(['asset', 'category']);

        return view('product', ['product' => $product, 'owned' => auth()->user()?->owns($product) ?? false]);
    }

    public function library(Request $request): View
    {
        return view('library', ['entitlements' => $request->user()->entitlements()->whereNull('revoked_at')->with('product.asset')->latest()->get()]);
    }

    public function orders(Request $request): View
    {
        return view('orders', ['orders' => $request->user()->orders()->with(['items', 'payment'])->latest()->paginate(15)]);
    }

    public function order(Order $order): View
    {
        Gate::authorize('view', $order);
        $order->load(['items', 'payment']);

        return view('order', ['order' => $order]);
    }

    public function reader(Request $request, Product $product): View
    {
        Gate::authorize('consume', $product);
        $product->load('asset');
        abort_unless($product->ready, 404);
        $entry = $request->user()->entitlements()->where('product_id', $product->id)->firstOrFail();
        $count = count($product->asset->manifest['pages'] ?? []);
        $page = max(1, min($count, (int) $request->query('page', $entry->progress)));
        if ($product->type === 'book') {
            $entry->update(['progress' => $page]);
        }

        return view('reader', compact('product', 'page', 'count'));
    }
}
