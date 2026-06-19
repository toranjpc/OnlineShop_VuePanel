<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('f_id')->nullable();
            $table->foreign('f_id')->references('id')->on('users')->nullOnDelete();

            $table->string('shenase_meli')->unique();
            $table->string('name')->nullable();
            $table->string('last_name')->nullable();
            $table->date('registrationDate')->nullable();
            $table->string('registrationTypeTitle')->nullable();
            $table->date('lastCompanyNewsDate')->nullable();
            $table->date('NewsDateFrom')->nullable();
            $table->string('shomare_sabt')->nullable();
            $table->string('code_eghtesadi')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('webSite')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes - only essential ones for performance
            // Primary search fields (most frequently queried)
            // Note: shenase_meli already has unique index (from unique constraint)
            $table->index('name', 'idx_customers_name');
            $table->index('last_name', 'idx_customers_last_name');
            $table->index('mobile', 'idx_customers_mobile');
            $table->index('phone', 'idx_customers_phone');
            $table->index('email', 'idx_customers_email');
            $table->index('province', 'idx_customers_province');
            $table->index('city', 'idx_customers_city');
            $table->index('postal_code', 'idx_customers_postal_code');
            $table->index('registrationDate', 'idx_customers_registrationDate');
            $table->index('lastCompanyNewsDate', 'idx_customers_lastCompanyNewsDate');
            $table->index('shomare_sabt', 'idx_customers_shomare_sabt');
            $table->index('code_eghtesadi', 'idx_customers_code_eghtesadi');
            $table->index('deleted_at', 'idx_customers_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customers');
    }
}
