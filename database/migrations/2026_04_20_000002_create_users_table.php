<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->integer('id', false, true)->primary()->autoIncrement();
            $table->string('email', 50)->unique();
            $table->string('password', 60);

            $table->string('first_name', 50);
            $table->string('middle_name', 50)->nullable();
            $table->string('last_name', 50);
            $table->string('contact_number', 11);
            $table->text('address');
            $table->text('photo')->nullable();
            $table->string('or_number', 50)->nullable();

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
        Schema::dropIfExists('users');
    }
};
