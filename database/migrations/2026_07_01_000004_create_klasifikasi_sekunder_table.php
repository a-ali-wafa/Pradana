<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('klasifikasi_sekunder', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klasifikasi_primer_id')->constrained('klasifikasi_primer')->cascadeOnDelete();
            $table->string('kode', 5);
            $table->string('nama', 100);
            $table->timestamps();
            $table->unique(['klasifikasi_primer_id', 'kode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('klasifikasi_sekunder');
    }
};
