<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete(); // لا نسمح بحذف تصنيف تابعة له منتجات

            $table->string('name')->index(); // للبحث بالاسم
            $table->string('barcode')->nullable()->unique();
            $table->string('sku')->nullable()->index();
            $table->string('brand')->nullable();
            $table->string('image')->nullable(); // path فقط
            $table->text('description')->nullable();

            $table->string('availability')->default('unknown');

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
