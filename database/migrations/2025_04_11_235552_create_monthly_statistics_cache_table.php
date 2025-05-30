<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_statistics_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puskesmas_id')->constrained()->cascadeOnDelete();
            $table->enum('disease_type', ['ht', 'dm']);
            $table->integer('year');
            $table->integer('month');
            $table->integer('male_count')->default(0);
            $table->integer('female_count')->default(0);
            $table->integer('total_count')->default(0);
            $table->integer('standard_count')->default(0);
            $table->integer('non_standard_count')->default(0);
            $table->decimal('standard_percentage', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['puskesmas_id', 'disease_type', 'year', 'month'], 'monthly_stats_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_statistics_cache');
    }
};
