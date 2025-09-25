<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_patches', function (Blueprint $table) {
            $table->id();
            $table->string('patch');
            $table->integer('batch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_patches');
    }
};
