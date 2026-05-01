<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_records', function (Blueprint $table) {
            $table->integer('id', false, true)->primary()->autoIncrement();
            $table->integer('tree_id', false, true);
            $table->foreign('tree_id')->references('id')->on('trees')->onDelete('cascade');
            $table->char('assignment_id', 36)->nullable();
            $table->foreign('assignment_id')->references('id')->on('monitoring_assignments')->onDelete('set null');
            $table->integer('couple_user_id', false, true)->nullable();
            $table->foreign('couple_user_id')->references('id')->on('users')->onDelete('set null');
            $table->text('photo')->nullable();
            $table->enum('status', ['alive', 'dead']);
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_records');
    }
};
