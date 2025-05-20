<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesForPerformanceOptimization extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Menambahkan indeks pada tabel monthly_statistics_cache
        Schema::table('monthly_statistics_cache', function (Blueprint $table) {
            $table->index(['puskesmas_id', 'disease_type', 'year', 'month'], 'msc_puskesmas_disease_year_month_idx');
            $table->index(['disease_type', 'year'], 'msc_disease_year_idx');
        });

        // Menambahkan indeks pada tabel yearly_targets
        Schema::table('yearly_targets', function (Blueprint $table) {
            $table->index(['puskesmas_id', 'disease_type', 'year'], 'yt_puskesmas_disease_year_idx');
        });

        // Menambahkan indeks pada tabel ht_examinations
        Schema::table('ht_examinations', function (Blueprint $table) {
            $table->index(['patient_id', 'year', 'month'], 'ht_patient_year_month_idx');
            $table->index(['puskesmas_id', 'year'], 'ht_puskesmas_year_idx');
        });

        // Menambahkan indeks pada tabel dm_examinations
        Schema::table('dm_examinations', function (Blueprint $table) {
            $table->index(['patient_id', 'year', 'month'], 'dm_patient_year_month_idx');
            $table->index(['puskesmas_id', 'year'], 'dm_puskesmas_year_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Menghapus indeks dari tabel monthly_statistics_cache
        Schema::table('monthly_statistics_cache', function (Blueprint $table) {
            $table->dropIndex('msc_puskesmas_disease_year_month_idx');
            $table->dropIndex('msc_disease_year_idx');
        });

        // Menghapus indeks dari tabel yearly_targets
        Schema::table('yearly_targets', function (Blueprint $table) {
            $table->dropIndex('yt_puskesmas_disease_year_idx');
        });

        // Menghapus indeks dari tabel ht_examinations
        Schema::table('ht_examinations', function (Blueprint $table) {
            $table->dropIndex('ht_patient_year_month_idx');
            $table->dropIndex('ht_puskesmas_year_idx');
        });

        // Menghapus indeks dari tabel dm_examinations
        Schema::table('dm_examinations', function (Blueprint $table) {
            $table->dropIndex('dm_patient_year_month_idx');
            $table->dropIndex('dm_puskesmas_year_idx');
        });
    }
}