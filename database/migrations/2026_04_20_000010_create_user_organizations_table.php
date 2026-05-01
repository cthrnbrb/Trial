<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_organizations', function (Blueprint $table) {
            $table->integer('id', false, true)->primary()->autoIncrement();
            $table->integer('user_id', false, true);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->integer('organization_id', false, true);
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->enum('role', ['admin', 'monitoring staff', 'organization', 'couple']);
            $table->timestamp('joined_at')->nullable();
            $table->unique(['user_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_organizations');
    }
};
