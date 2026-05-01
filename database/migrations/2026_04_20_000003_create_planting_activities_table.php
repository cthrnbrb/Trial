<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planting_activities', function (Blueprint $table) {
            $table->integer('id', false, true)->primary()->autoIncrement();
            $table->integer('organization_id', false, true);
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->integer('barangay_id', false, true)->nullable();
            $table->foreign('barangay_id')->references('id')->on('barangays')->onDelete('set null');
            $table->string('site_name', 50);
            $table->string('tree_species', 25);
            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();
            $table->integer('radius_meters')->nullable();
            $table->date('scheduled_date');
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->integer('deleted_by', false, true)->nullable();
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planting_activities');
    }
};
