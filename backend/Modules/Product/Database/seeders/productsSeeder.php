<?php

namespace Modules\Product\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Product\Models\ProductOption;
use Modules\Product\Models\ProductPrice;
use Modules\Product\Models\ProductStock;
use Modules\Product\Models\Product;

class productsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProductOption::create([
            'title' => 'همه دسته بندی ها',
            'metadata' => [],
            'kind' => 'category',
        ]);
        ProductOption::create([
            'title' => 'انبار مرکزی',
            'metadata' => [],
            'kind' => 'warehouse',
        ]);
    }
}
