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
        Schema::create('lab_inventory', function (Blueprint $table) {
            $table->id();
            $table->enum('lab', ['chemistry', 'physics']);
            $table->enum('category', ['general', 'pasco']);
            $table->string('item_no', 20)->nullable();
            $table->string('description', 255);
            $table->string('size', 100)->nullable();
            $table->integer('quantity')->default(0);
            $table->string('uom', 50)->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('storage_location', 100)->nullable();
            $table->text('condition_remarks')->nullable();
            $table->integer('qty_sent_iligan')->default(0);
            $table->integer('qty_sent_butuan')->default(0);
            $table->string('current_campus_location', 100)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['lab', 'category']);
            $table->index('description');
            $table->index('storage_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_inventory');
    }
};
