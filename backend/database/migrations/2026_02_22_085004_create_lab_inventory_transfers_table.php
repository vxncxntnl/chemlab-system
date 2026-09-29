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
        Schema::create('lab_inventory_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('lab_inventory')->onDelete('cascade');
            $table->enum('campus', ['iligan', 'butuan']);
            $table->integer('quantity');
            $table->date('transfer_date');
            $table->string('received_by', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['inventory_id', 'campus']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_inventory_transfers');
    }
};
