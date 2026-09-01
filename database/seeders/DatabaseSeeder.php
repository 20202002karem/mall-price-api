<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Price;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // DEVELOPMENT ONLY - Admin user للاختبار المحلي فقط
        $admin = User::factory()->create([
            'name' => 'Dev Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'), // DEVELOPMENT ONLY
        ]);

        // 3 تصنيفات
        $food = Category::create(['name' => 'مواد غذائية']);
        $cleaning = Category::create(['name' => 'مواد تنظيف']);
        $vegetables = Category::create(['name' => 'خضروات وفواكه']);

        // 5 منتجات
        $sugar = Product::create([
            'category_id' => $food->id,
            'name' => 'سكر أبيض',
            'barcode' => '6291041500213',
            'availability' => 'available',
        ]);

        $rice = Product::create([
            'category_id' => $food->id,
            'name' => 'أرز بسمتي',
            'barcode' => '6291041500220',
            'availability' => 'available',
        ]);

        Product::create([
            'category_id' => $cleaning->id,
            'name' => 'منظف أرضيات',
            'barcode' => '6291041500237',
            'availability' => 'available',
        ]);

        Product::create([
            'category_id' => $cleaning->id,
            'name' => 'صابون سائل',
            'barcode' => '6291041500244',
            'availability' => 'out_of_stock',
        ]);

        Product::create([
            'category_id' => $vegetables->id,
            'name' => 'طماطم',
            'barcode' => '6291041500251',
            'availability' => 'available',
        ]);

        // Price History للسكر: سعر قديم منتهي + سعر حالي
        Price::create([
            'product_id' => $sugar->id,
            'price' => 5.00,
            'currency' => 'ILS',
            'effective_from' => now()->subDays(10),
            'effective_until' => now()->subDay(),
            'updated_by' => $admin->id,
        ]);

        Price::create([
            'product_id' => $sugar->id,
            'price' => 6.00,
            'old_price' => 5.00,
            'currency' => 'ILS',
            'effective_from' => now()->subDay(),
            'effective_until' => null,
            'updated_by' => $admin->id,
        ]);

        // سعر حالي واحد فقط للأرز (بدون تاريخ)
        Price::create([
            'product_id' => $rice->id,
            'price' => 22.00,
            'currency' => 'ILS',
            'effective_from' => now(),
            'effective_until' => null,
            'updated_by' => $admin->id,
        ]);
    }
}
