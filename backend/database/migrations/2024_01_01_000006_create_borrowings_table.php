<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->date('borrow_date');
            $table->date('return_date');
            $table->date('actual_return_date')->nullable();
            $table->text('purpose')->nullable();
            $table->enum('status', ['pending', 'approved', 'borrowed', 'returned', 'overdue', 'cancelled'])->default('pending');
            $table->decimal('daily_fine_rate', 10, 2)->default(10.00);
            $table->integer('days_late')->default(0);
            $table->decimal('total_fine', 10, 2)->default(0.00);
            $table->boolean('overdue_flag')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowings');
    }
};
