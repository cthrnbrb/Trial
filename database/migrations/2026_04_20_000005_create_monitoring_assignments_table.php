<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_assignments', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->integer('activity_id', false, true);
            $table->foreign('activity_id')->references('id')->on('planting_activities')->onDelete('cascade');
            $table->integer('staff_id', false, true);
            $table->foreign('staff_id')->references('id')->on('users')->onDelete('cascade');
            $table->integer('target_year');
            $table->integer('target_quarter');
            $table->date('scheduled_date');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['activity_id', 'staff_id', 'target_year', 'target_quarter'], 'monitoring_assignments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_assignments');
    }
};
