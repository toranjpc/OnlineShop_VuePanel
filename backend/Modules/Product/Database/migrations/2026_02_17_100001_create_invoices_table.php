<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number');
            $table->string('invoice_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->dateTime('pay_date');
            $table->string('pay_trace')->nullable();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('tax')->default(0);
            $table->enum('kind', ['sale', 'purchase', 'performa', 'return'])->default('sale'); //, 'transference'
            $table->tinyInteger('status')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at', 'idx_invoices_deleted_at');

            $table->index('customer_id', 'idx_invoices_customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');

            $table->index('user_id', 'idx_invoices_user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');



            $table->index('invoice_number', 'idx_invoices_invoice_number');
            $table->index('pay_date', 'idx_invoices_pay_date');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();

            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->unsignedBigInteger('amount');
            $table->unsignedInteger('number')->nullable();
            $table->unsignedInteger('subtotal')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at');
        });

        // سند دریافت / پرداخت — هر ردیف یک جابجایی پول از payment به receive
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // ثبت‌کننده
            $table->unsignedBigInteger('invoice_id')->nullable(); // ارتباط اختیاری با فاکتور

            // طرف دریافت‌کننده (bank → product_options, user → users, customer → customers, account → users با is_accountable)
            $table->enum('receive_type', ['bank', 'user', 'customer', 'account']);
            $table->unsignedBigInteger('receive_id');

            // طرف پرداخت‌کننده
            $table->enum('payment_type', ['bank', 'user', 'customer', 'account']);
            $table->unsignedBigInteger('payment_id');

            $table->unsignedBigInteger('amount');
            $table->dateTime('pay_date');
            $table->string('pay_trace')->nullable();
            $table->text('description')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at', 'idx_credits_deleted_at');
            $table->index('user_id', 'idx_credits_user_id');
            $table->index('invoice_id', 'idx_credits_invoice_id');
            $table->index('pay_date', 'idx_credits_pay_date');
            $table->index(['receive_type', 'receive_id'], 'idx_credits_receive');
            $table->index(['payment_type', 'payment_id'], 'idx_credits_payment');

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('credits');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
}
