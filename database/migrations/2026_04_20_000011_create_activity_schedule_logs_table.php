<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_schedule_logs', function (Blueprint $table) {
            $table->integer('id', false, true)->primary()->autoIncrement();
            $table->integer('activity_id', false, true);
            $table->foreign('activity_id')->references('id')->on('planting_activities')->onDelete('cascade');
            $table->date('old_date')->nullable();
            $table->date('new_date')->nullable();
            $table->text('remarks')->nullable();
            $table->integer('changed_by', false, true);
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('cascade');
            $table->timestamp('changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_schedule_logs');
    }
};
