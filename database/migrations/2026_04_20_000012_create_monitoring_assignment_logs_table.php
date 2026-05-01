<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_assignment_logs', function (Blueprint $table) {
            $table->integer('id', false, true)->primary()->autoIncrement();
            $table->char('assignment_id', 36);
            $table->foreign('assignment_id')->references('id')->on('monitoring_assignments')->onDelete('cascade');
            $table->integer('previous_staff_id', false, true)->nullable();
            $table->foreign('previous_staff_id')->references('id')->on('users')->onDelete('set null');
            $table->integer('new_staff_id', false, true)->nullable();
            $table->foreign('new_staff_id')->references('id')->on('users')->onDelete('set null');
            $table->text('remarks')->nullable();
            $table->integer('transferred_by', false, true);
            $table->foreign('transferred_by')->references('id')->on('users')->onDelete('cascade');
            $table->timestamp('transferred_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_assignment_logs');
    }
};
