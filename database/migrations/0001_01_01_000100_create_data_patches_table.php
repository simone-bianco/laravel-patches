<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sb_patches', function (Blueprint $table): void {
            $table->id();
            $table->string('patch');
            $table->integer('batch');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sb_patches');
    }
};
