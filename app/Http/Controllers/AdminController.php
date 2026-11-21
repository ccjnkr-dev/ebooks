<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Http\Requests\ProductRequest;
use App\Models\Asset;
use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Audit;
use App\Services\CommerceService;
use App\Services\SaveProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminController extends Controller
{
    public function dashboard(CommerceService $commerce): View
    {
        $commerce->expire();

        return view('admin.dashboard', ['paid' => Order::where('status', 'paid')->sum('total'), 'orders' => Order::count(), 'customers' => User::where('is_admin', false)->count(), 'products' => Product::count(), 'recent' => Order::with('user')->latest()->limit(6)->get(), 'failed' => Asset::where('status', 'failed')->count(), 'pending' => Order::where('status', 'paid')->whereIn('fulfillment_status', ['awaiting', 'ready'])->count()]);
    }

    public function products(Request $request): View
    {
        return view('admin.products', ['products' => Product::with(['asset', 'category', 'assets'])->when($request->query('q'), fn ($query, $v) => $query->where('title', 'like', '%'.mb_substr($v, 0, 100).'%'))->latest()->paginate(20)]);
    }

    public function create(): View
    {
        return view('admin.product-form', ['product' => new Product(['stock' => 1, 'price' => 10000, 'type' => 'book', 'status' => 'draft', 'accent' => 'violet']), 'categories' => Category::orderBy('name')->get()]);
    }

    public function edit(Request $request, Product $product): View|JsonResponse
    {
        $product->load(['asset', 'assets']);
        if ($request->expectsJson()) {
            return response()->json([
                'processing' => $product->assets->contains(fn (Asset $asset): bool => in_array($asset->status, ['pending', 'processing'], true)),
                'ready' => $product->ready,
                'status' => $product->status,
                'publication_requested' => $product->publication_requested,
            ]);
        }

        return view('admin.product-form', ['product' => $product, 'categories' => Category::orderBy('name')->get()]);
    }

    public function store(ProductRequest $request, SaveProduct $saveProduct): RedirectResponse
    {
        $product = $saveProduct->save($request->validated(), $request->file('content'));

        return redirect()->route('admin.products.edit', $product)->with('success', $this->savedMessage($product));
    }

    public function update(ProductRequest $request, Product $product, SaveProduct $saveProduct): RedirectResponse
    {
        $product = $saveProduct->save($request->validated(), $request->file('content'), $product);

        return redirect()->route('admin.products.edit', $product)->with('success', $this->savedMessage($product));
    }

    private function savedMessage(Product $product): string
    {
        if ($product->publication_requested) {
            return 'Content saved. It will publish automatically once its previews are ready.';
        }

        return 'Content saved.';
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->assets()->whereIn('status', ['pending', 'processing'])->exists()) {
            throw ValidationException::withMessages(['product' => 'Wait for media processing to finish before deleting this product.']);
        } $product->update(['status' => 'archived']);
        if ($product->orderItems()->exists()) {
            Audit::record('product.archived', (string) $product->id);

            return back()->with('success', 'Product archived. Purchased access and order history were preserved.');
        } $product->delete();
        Audit::record('product.deleted', (string) $product->id);

        return redirect()->route('admin.products')->with('success', 'Product deleted. Unreferenced files remain available for explicit cleanup.');
    }

    public function purge(Request $request, string $product): RedirectResponse
    {
        $trashedProduct = Product::withTrashed()->findOrFail($product);
        abort_unless($trashedProduct->trashed(), 422);
        if ($trashedProduct->orderItems()->exists() || $trashedProduct->assets()->whereIn('status', ['pending', 'processing'])->exists()) {
            throw ValidationException::withMessages(['product' => 'Purchased or processing content cannot be permanently deleted.']);
        }
        foreach ($trashedProduct->assets()->get() as $asset) {
            Storage::disk('local')->delete($asset->original_path);
            Storage::disk('local')->deleteDirectory('processed/'.$asset->id);
            $asset->delete();
        }
        $trashedProduct->forceDelete();
        Audit::record('product.purged', $product);

        return back()->with('success', 'Unreferenced product and its files permanently deleted.');
    }

    public function trash(): View
    {
        return view('admin.trash', ['products' => Product::onlyTrashed()->withCount('orderItems')->latest()->paginate(20)]);
    }

    public function orders(Request $request): View
    {
        $data = $request->validate(['status' => 'nullable|in:pending,paid,failed,cancelled,expired,refunded']);

        return view('admin.orders', ['orders' => Order::with(['user', 'payment', 'items'])->when($data['status'] ?? null, fn ($query, $s) => $query->where('status', $s))->latest()->paginate(20)]);
    }

    public function payments(): View
    {
        return view('admin.payments', ['payments' => Payment::with('order.user')->latest()->paginate(20)]);
    }

    public function refund(Order $order, PaymentGateway $gateway): RedirectResponse
    {
        $gateway->refund($order->payment()->firstOrFail());

        return back()->with('success', 'Simulated refund recorded and access revoked.');
    }

    public function fulfillment(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['fulfillment_status' => 'required|in:ready,collected']);
        DB::transaction(function () use ($data, $order): void {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $expected = $data['fulfillment_status'] === 'ready' ? 'awaiting' : 'ready';
            abort_unless($order->status === 'paid' && $order->fulfillment_status === $expected, 422);
            $order->update($data);
            Audit::record('pickup.'.$data['fulfillment_status'], $order->number);
        });

        return back()->with('success', 'Pickup status updated.');
    }

    public function customers(): View
    {
        return view('admin.customers', ['users' => User::withCount(['orders', 'entitlements'])->orderBy('name')->paginate(20)]);
    }

    public function customer(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['is_active' => 'required|boolean']);
        abort_if($user->is_admin || $user->id === auth()->id(), 422, 'Admin accounts cannot be disabled here.');
        $user->is_active = $request->boolean('is_active');
        $user->save();
        Audit::record('customer.updated', (string) $user->id, $data);

        return back()->with('success', 'Account access updated.');
    }

    public function revoke(Entitlement $entitlement): RedirectResponse
    {
        $entitlement->update(['revoked_at' => now()]);
        Audit::record('access.revoked', (string) $entitlement->id);

        return back()->with('success', 'Digital access revoked.');
    }

    public function access(User $user): View
    {
        return view('admin.access', ['user' => $user, 'entitlements' => $user->entitlements()->with('product')->get()]);
    }

    public function settings(): View
    {
        return view('admin.settings', ['categories' => Category::withCount('products')->orderBy('name')->get()]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['brand_name' => 'required|string|max:80', 'tagline' => 'required|string|max:160', 'watermark' => 'required|string|max:60|regex:/^[\x20-\x7E]+$/', 'pickup_instructions' => 'required|string|max:2000', 'contact_email' => 'required|email|max:255']);
        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        } Audit::record('settings.updated', 'store');

        return back()->with('success', 'Store settings updated. Existing painting samples keep their burned-in watermark; re-upload to regenerate them.');
    }

    public function category(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:60']);
        $slug = Str::slug($data['name']);
        if (! $slug || Category::where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'Use a unique category name containing letters or numbers.']);
        } Category::create(['name' => $data['name'], 'slug' => $slug]);
        Audit::record('category.created', $slug);

        return back()->with('success', 'Category added.');
    }

    public function deleteCategory(Category $category): RedirectResponse
    {
        $category->delete();
        Audit::record('category.deleted', (string) $category->id);

        return back()->with('success', 'Category removed. Products were retained.');
    }

    public function audit(): View
    {
        return view('admin.audit', ['events' => AuditEvent::with('user')->latest()->paginate(30)]);
    }

    public function reports(Request $request): View|StreamedResponse
    {
        $dates = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'export' => 'nullable|in:csv']);
        $from = $dates['from'] ?? now()->subDays(29)->toDateString();
        $to = $dates['to'] ?? now()->toDateString();
        $query = Order::whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to);
        $gross = (clone $query)->whereIn('status', ['paid', 'refunded'])->sum('total');
        $refunds = (clone $query)->where('status', 'refunded')->sum('total');
        if (($dates['export'] ?? '') === 'csv') {
            $orders = (clone $query)->orderBy('id');

            return response()->streamDownload(function () use ($orders): void {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Order', 'Date', 'Status', 'Amount PHP', 'Pickup'], ',', '"', '');
                foreach ($orders->cursor() as $order) {
                    fputcsv($out, [$order->number, $order->created_at->toDateString(), $order->status, number_format($order->total / 100, 2, '.', ''), $order->fulfillment_status], ',', '"', '');
                } fclose($out);
            }, 'simulated-sales-'.$from.'-'.$to.'.csv');
        }
        $daily = (clone $query)->where('status', 'paid')->selectRaw('date(created_at) as day, SUM(total) as total')->groupByRaw('date(created_at)')->orderBy('day')->get();
        $popular = OrderItem::query()->whereHas('order', fn ($query) => $query->where('status', 'paid')->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to))->selectRaw('title, SUM(quantity) as units, SUM(unit_price * quantity) as revenue')->groupBy('title')->orderByDesc('units')->limit(8)->get();

        return view('admin.reports', compact('from', 'to', 'gross', 'refunds', 'daily', 'popular') + ['net' => $gross - $refunds, 'count' => (clone $query)->count()]);
    }
}
