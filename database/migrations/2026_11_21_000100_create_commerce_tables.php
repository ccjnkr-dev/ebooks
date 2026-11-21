<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_active')->default(true);
        });
        Schema::create('categories', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type');
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('creator');
            $t->text('description');
            $t->unsignedInteger('price');
            $t->unsignedInteger('stock')->default(1);
            $t->string('status')->default('draft');
            $t->boolean('featured')->default(false);
            $t->string('accent')->default('violet');
            $t->string('details')->nullable();
            $t->unsignedBigInteger('active_asset_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('assets', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('product_id')->constrained();
            $t->string('original_path');
            $t->string('original_name');
            $t->string('status')->default('pending');
            $t->json('manifest')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t): void {
            $t->id();
            $t->uuid('number')->unique();
            $t->foreignId('user_id')->constrained();
            $t->string('status')->default('pending');
            $t->unsignedInteger('total');
            $t->string('currency', 3)->default('PHP');
            $t->string('fulfillment_status')->nullable();
            $t->text('pickup_instructions')->nullable();
            $t->timestamp('expires_at');
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_id')->constrained();
            $t->foreignId('product_id')->constrained();
            $t->string('title');
            $t->string('type');
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('unit_price');
            $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained();
            $t->string('gateway');
            $t->uuid('reference')->unique();
            $t->string('status')->default('pending');
            $t->unsignedInteger('amount');
            $t->string('currency', 3)->default('PHP');
            $t->timestamps();
        });
        Schema::create('entitlements', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('product_id')->constrained();
            $t->foreignId('order_id')->constrained();
            $t->unsignedInteger('progress')->default(1);
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'product_id']);
        });
        Schema::create('stock_reservations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_id')->constrained();
            $t->foreignId('product_id')->constrained();
            $t->unsignedInteger('quantity');
            $t->string('status')->default('held');
            $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t): void {
            $t->string('key')->primary();
            $t->text('value');
        });
        Schema::create('audit_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('action');
            $t->string('subject');
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_events', 'settings', 'stock_reservations', 'entitlements', 'payments', 'order_items', 'orders', 'assets', 'products', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['is_admin', 'is_active']));
    }
};
