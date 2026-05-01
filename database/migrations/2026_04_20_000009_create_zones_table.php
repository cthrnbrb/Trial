<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->integer('id', false, true)->primary()->autoIncrement();
            $table->integer('barangay_id', false, true);
            $table->foreign('barangay_id')->references('id')->on('barangays')->onDelete('cascade');
            $table->string('name', 50);
            $table->geometry('boundary')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
