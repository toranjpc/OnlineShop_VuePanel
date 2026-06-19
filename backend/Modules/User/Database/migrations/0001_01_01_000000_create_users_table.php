<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Modules\User\Database\Seeders\UserSeeder;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('options', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('f_id')->nullable();
            $table->foreign('f_id')->references('id')->on('options')->nullOnDelete();

            $table->string('title', 100)->nullable();

            $table->enum('kind', ['job', 'Category',  'Plan'])->nullable(); //Category:[admin,editor,user] , UserGroup:[seller1,seller2,...]
            $table->unique(['title', 'f_id', 'kind']);

            $table->json('option')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();

            // Indexes برای جستجوی سریع‌تر
            $table->index('f_id'); // برای relation parent/child و query های where('f_id', 0)
            $table->index(['kind', 'status']); // ترکیبی برای جستجوی رایج (kind همیشه با status استفاده می‌شه)
        });

        Schema::create('extdatas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('f_id')->default(0);
            $table->unsignedBigInteger('m_id')->default(0)->nullable();
            $table->string('s_id')->default(0)->nullable();
            $table->string('title')->nullable();
            $table->string('kind')->nullable();
            $table->json('datas')->nullable();
            $table->tinyInteger('status')->default(0); // status=1 ارسال برای کاربر با ای دی  f_id , status=2  ارسال برای همه گروه های کاربری f_id
            $table->timestamps();

            // Indexes برای جستجوی سریع‌تر (بیشترین استفاده از طریق relation)
            $table->index('f_id'); // برای relation با users/options (بیشترین استفاده)
            $table->index('m_id'); // برای relation با users/options
            $table->index('s_id'); // برای relation با users/options
            $table->index(['f_id', 'kind']); // ترکیبی برای relation های رایج (category, extraData)
            $table->index(['f_id', 'kind', 'status']); // ترکیبی برای userPlan که هر سه رو چک می‌کنه
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('f_id')->nullable();
            $table->foreign('f_id')->references('id')->on('users')->nullOnDelete();

            $table->tinyInteger('sex')->default(1);
            $table->string('ircode')->nullable();
            $table->string('name')->nullable();
            $table->string('lastname')->nullable();
            // $table->string('alias')->nullable();
            $table->timestamp('birth')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('mobile')->unique()->nullable();
            $table->timestamp('mobile_verified_at')->nullable();

            $table->foreignId('job')->nullable()->constrained('options', 'id')->nullOnDelete();
            $table->json('per')->nullable();

            $table->json('datas')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->tinyInteger('is_accountable')->default(0);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Indexes برای جستجوی سریع‌تر
            $table->index('username'); // برای جستجو و login
            $table->index(['status', 'deleted_at']); // ترکیبی برای فیلتر کاربران فعال
            $table->index(['f_id', 'status']); // ترکیبی برای جستجوی کاربران زیرمجموعه
            // note: 'f_id' و 'job' foreign key ها خودشون index می‌سازن
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('mobile')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        (new UserSeeder())->run();
    }


    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('extdatas');
        Schema::dropIfExists('options');
        Schema::dropIfExists('users');
    }
};
