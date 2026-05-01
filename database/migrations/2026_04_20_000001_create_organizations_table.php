<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->integer('id', false, true)->primary()->autoIncrement();
            $table->string('org_name', 50);
            $table->string('president_first_name', 50);
            $table->string('president_middle_name', 50)->nullable();
            $table->string('president_last_name', 50);
            $table->string('president_email', 50)->unique();
            $table->string('organization_code', 6)->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
