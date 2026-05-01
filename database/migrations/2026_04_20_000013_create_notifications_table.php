<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->integer('id', false, true)->primary()->autoIncrement();
            $table->integer('user_id', false, true);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('title', 100);
            $table->text('message');
            $table->enum('type', [
                'activity_reminder',
                'activity_rescheduled',
                'activity_cancelled',
                'monitoring_schedule',
                'monitoring_reassigned',
                'tree_update_reminder',
                'certificate_ready'
            ]);
            $table->enum('role_target', ['admin', 'staff', 'couple']);
            $table->boolean('is_read')->default(false);
            $table->integer('related_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
