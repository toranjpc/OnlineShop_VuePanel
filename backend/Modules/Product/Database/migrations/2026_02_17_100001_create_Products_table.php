<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Product\Database\Seeders\productsSeeder;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('category_id');
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->tinyInteger('tax')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // MySQL partial unique is not supported; enforce uniqueness only when slug & description are set.
            $table->string('unique_title_slug_description', 767)
                ->nullable()
                ->storedAs("CASE WHEN slug IS NOT NULL AND description IS NOT NULL THEN CONCAT(title, CHAR(0), slug, CHAR(0), description) ELSE NULL END");
            $table->unique('unique_title_slug_description', 'idx_products_title_slug_description');
            $table->index('deleted_at', 'idx_products_deleted_at');
            $table->index('user_id', 'idx_products_user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
        Schema::create('product_stock', function (Blueprint $table) {
            $table->id();
            // $table->string('uuid');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->integer('quantity');
            $table->integer('stock')->default(0);

            $table->json('metadata')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at', 'idx_product_stock_deleted_at');
            // $table->index('uuid', 'idx_product_stock_uuid');
            $table->index('product_id', 'idx_product_stock_product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            // $table->string('uuid');
            $table->unsignedBigInteger('product_id');
            // $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('user_category_id')->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('limit_sale')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at', 'idx_product_prices_deleted_at');
            // $table->index('uuid', 'idx_product_prices_uuid');
            $table->index('product_id', 'idx_product_prices_product_id');
            // $table->index('user_id', 'idx_product_prices_user_id');
            $table->index('user_category_id', 'idx_product_prices_user_category_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('user_category_id')->references('id')->on('options')->onDelete('set null');
        });
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('option_id')->nullable();
            // $table->string('uuid');
            $table->string('title')->nullable();
            $table->json('metadata')->nullable();
            $table->enum('kind', ['category', 'warehouse', 'bank'])->default('category');
            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at', 'idx_product_options_deleted_at');
            $table->unique(['title', 'kind'], 'idx_product_options_title_kind');
            // $table->index('uuid', 'idx_product_options_uuid');
            $table->index('option_id', 'idx_product_options_option_id');
            $table->foreign('option_id')->references('id')->on('product_options')->onDelete('set null');
        });


        (new productsSeeder())->run();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('product_stock');
        Schema::dropIfExists('products');
    }
}
