<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\StoreController;
use App\Http\Middleware\EnsureActive;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureActive::class)->group(function (): void {
    Route::get('/', [StoreController::class, 'home'])->name('home');
    Route::get('/catalog', [StoreController::class, 'catalog'])->name('catalog');
    Route::get('/products/{product:slug}', [StoreController::class, 'product'])->name('product');
    Route::get('/preview/{product}', [MediaController::class, 'preview'])->middleware('throttle:120,1')->name('preview');
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [AuthController::class, 'form'])->name('login');
        Route::get('/register', fn () => app(AuthController::class)->form('register'))->name('register');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    });
    Route::get('/cart', [CheckoutController::class, 'cart'])->name('cart');
    Route::post('/cart/{product}', [CheckoutController::class, 'add'])->middleware('throttle:30,1')->name('cart.add');
    Route::delete('/cart/{product}', [CheckoutController::class, 'remove'])->name('cart.remove');
    Route::middleware('auth')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/account', fn () => view('account'))->name('account');
        Route::patch('/account', [AuthController::class, 'profile'])->name('account.update');
        Route::get('/library', [StoreController::class, 'library'])->name('library');
        Route::get('/orders', [StoreController::class, 'orders'])->name('orders');
        Route::get('/orders/{order}', [StoreController::class, 'order'])->name('orders.show');
        Route::post('/checkout', [CheckoutController::class, 'checkout'])->middleware('throttle:10,1')->name('checkout');
        Route::get('/payment/{order}', [CheckoutController::class, 'payment'])->name('payment');
        Route::post('/payment/{order}', [CheckoutController::class, 'confirm'])->middleware('throttle:20,1')->name('payment.confirm');
        Route::get('/library/{product}', [StoreController::class, 'reader'])->withTrashed()->name('reader');
        Route::get('/media/{product}', [MediaController::class, 'protectedMedia'])->middleware(['signed', 'throttle:180,1'])->name('media');
        Route::prefix('admin')->name('admin.')->middleware(EnsureAdmin::class)->group(function (): void {
            Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
            Route::get('/products', [AdminController::class, 'products'])->name('products');
            Route::get('/products/create', [AdminController::class, 'create'])->name('products.create');
            Route::post('/products', [AdminController::class, 'store'])->name('products.store');
            Route::get('/products/{product}/edit', [AdminController::class, 'edit'])->name('products.edit');
            Route::put('/products/{product}', [AdminController::class, 'update'])->name('products.update');
            Route::delete('/products/{product}', [AdminController::class, 'destroy'])->name('products.destroy');
            Route::get('/trash', [AdminController::class, 'trash'])->name('trash');
            Route::delete('/trash/{product}', [AdminController::class, 'purge'])->name('products.purge');
            Route::get('/orders', [AdminController::class, 'orders'])->name('orders');
            Route::patch('/orders/{order}/fulfillment', [AdminController::class, 'fulfillment'])->name('fulfillment');
            Route::post('/orders/{order}/refund', [AdminController::class, 'refund'])->name('refund');
            Route::get('/payments', [AdminController::class, 'payments'])->name('payments');
            Route::get('/customers', [AdminController::class, 'customers'])->name('customers');
            Route::patch('/customers/{user}', [AdminController::class, 'customer'])->name('customers.update');
            Route::get('/customers/{user}/access', [AdminController::class, 'access'])->name('access');
            Route::delete('/access/{entitlement}', [AdminController::class, 'revoke'])->name('access.revoke');
            Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
            Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
            Route::put('/settings', [AdminController::class, 'saveSettings'])->name('settings.save');
            Route::post('/categories', [AdminController::class, 'category'])->name('categories.store');
            Route::delete('/categories/{category}', [AdminController::class, 'deleteCategory'])->name('categories.destroy');
            Route::get('/audit', [AdminController::class, 'audit'])->name('audit');
        });
    });
});
