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
        Schema::create('orders', function (Blueprint $table) {
            $table->string('g_number');
            $table->timestamp('date');
            $table->date('last_change_date');
            $table->string('supplier_article');
            $table->string('tech_size');
            $table->string('barcode');
            $table->decimal('total_price', 10, 2);
            $table->integer('discount_percent');
            $table->string('warehouse_name');
            $table->string('oblast');
            $table->integer('income_id');
            $table->string('odid');
            $table->bigInteger('nm_id');
            $table->string('subject');
            $table->string('category');
            $table->string('brand');
            $table->boolean('is_cancel');
            $table->timestamp('cancel_dt')->nullable();

            $table->primary(['g_number', 'nm_id', 'date', 'barcode', 'subject']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
