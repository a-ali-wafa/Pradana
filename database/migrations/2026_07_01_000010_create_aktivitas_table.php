<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aktivitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('aksi', 255);
            $table->nullableMorphs('subjek');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aktivitas');
    }
};
