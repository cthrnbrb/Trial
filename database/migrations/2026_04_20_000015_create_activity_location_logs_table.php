<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_location_logs', function (Blueprint $table) {
            $table->integer('id', false, true)->primary()->autoIncrement();
            $table->integer('activity_id', false, true);
            $table->foreign('activity_id')->references('id')->on('planting_activities')->onDelete('cascade');
            $table->integer('old_barangay_id', false, true)->nullable();
            $table->foreign('old_barangay_id')->references('id')->on('barangays')->onDelete('set null');
            $table->integer('new_barangay_id', false, true)->nullable();
            $table->foreign('new_barangay_id')->references('id')->on('barangays')->onDelete('set null');
            $table->decimal('old_lat', 10, 7)->nullable();
            $table->decimal('old_lng', 10, 7)->nullable();
            $table->decimal('new_lat', 10, 7)->nullable();
            $table->decimal('new_lng', 10, 7)->nullable();
            $table->text('remarks')->nullable();
            $table->integer('changed_by', false, true);
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('cascade');
            $table->timestamp('changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_location_logs');
    }
};
