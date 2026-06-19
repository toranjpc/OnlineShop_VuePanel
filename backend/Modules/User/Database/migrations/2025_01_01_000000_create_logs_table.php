<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            
            // کاربری که این عمل رو انجام داده
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            
            // نوع عمل (login, logout, create, update, delete, etc.)
            $table->string('action', 50)->index();
            
            // مدلی که رویش عمل انجام شده (users, options, extdatas, etc.)
            $table->string('model', 100)->nullable()->index();
            
            // آی‌دی رکوردی که تغییر کرده (nullable برای action هایی مثل login)
            $table->unsignedBigInteger('model_id')->nullable()->index();
            
            // اطلاعات اضافی به صورت JSON
            $table->json('data')->nullable();
            
            // اطلاعات درخواست
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('method', 10)->nullable(); // GET, POST, PUT, DELETE, etc.
            $table->string('url')->nullable();
            
            $table->timestamps();
            
            // Index برای جستجوی سریع‌تر
            $table->index(['user_id', 'created_at']);
            $table->index(['model', 'model_id']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
