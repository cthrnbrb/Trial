<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->integer('id', false, true)->primary()->autoIncrement();
            $table->integer('activity_id', false, true);
            $table->foreign('activity_id')->references('id')->on('planting_activities')->onDelete('cascade');
            $table->integer('user_id', false, true);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->enum('attendance', ['present', 'absent']);
            $table->integer('tree_id', false, true);
            $table->foreign('tree_id')->references('id')->on('trees')->onDelete('cascade');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
