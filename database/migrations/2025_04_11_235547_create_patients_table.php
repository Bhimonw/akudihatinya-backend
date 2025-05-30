<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puskesmas_id')->constrained('puskesmas')->onDelete('cascade');
            $table->string('nik', 16)->nullable();
            $table->string('bpjs_number', 13)->nullable();
            $table->string('medical_record_number');
            $table->string('name');
            $table->text('address')->nullable();
            $table->enum('gender', ['male', 'female']);
            $table->date('birth_date')->nullable();
            $table->integer('age')->nullable();
            $table->json('ht_years')->nullable();
            $table->json('dm_years')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
