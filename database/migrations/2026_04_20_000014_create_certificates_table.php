<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->integer('id', false, true)->primary()->autoIncrement();
            $table->integer('user_id', false, true)->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->integer('partner_user_id', false, true)->nullable();
            $table->foreign('partner_user_id')->references('id')->on('users')->onDelete('set null');
            $table->integer('organization_id', false, true)->nullable();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            $table->string('certificate_number', 50)->unique();
            $table->text('file_url')->nullable();
            $table->timestamp('issued_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
