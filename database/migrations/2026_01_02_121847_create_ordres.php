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
        Schema::create('license_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('name', ['MONTHLY', 'BIYEARLY', 'YEARLY', 'TRIAL'])->default('MONTHLY')->unique();
            $table->bigInteger('number_of_days')->nullable(false);
            $table->double('price')->nullable(false);
            $table->boolean('active')->nullable(false)->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index()->nullable(false);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->uuid('merchant_id')->index()->nullable(false);
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
            $table->uuid('license_type_id')->index()->nullable(false);
            $table->foreign('license_type_id')->references('id')->on('license_types')->onDelete('cascade');
            $table->double('amount')->nullable(false);
            $table->double('discount')->nullable(false);
            $table->double('final_amount')->nullable(false);
            $table->timestamp('placed_at')->nullable();
            $table->enum('status', ['PENDING', 'PLACED', 'COMPLETED', 'CANCELLED'])->default('PENDING');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('active')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_types');
        Schema::dropIfExists('orders');
    }
};
