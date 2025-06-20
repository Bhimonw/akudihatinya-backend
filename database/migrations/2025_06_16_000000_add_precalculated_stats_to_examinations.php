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
        // Add pre-calculated statistics to HT examinations
        Schema::table('ht_examinations', function (Blueprint $table) {
            $table->boolean('is_controlled')->nullable()->after('diastolic');
            $table->boolean('is_first_visit_this_month')->default(false)->after('is_controlled');
            $table->boolean('is_standard_patient')->default(false)->after('is_first_visit_this_month');
            $table->string('patient_gender', 10)->nullable()->after('is_standard_patient');

            // Index untuk performa query
            $table->index(['puskesmas_id', 'year', 'month', 'is_first_visit_this_month'], 'idx_ht_stats_query');
            $table->index(['is_controlled', 'is_standard_patient'], 'idx_ht_controlled_standard');
        });

        // Add pre-calculated statistics to DM examinations
        Schema::table('dm_examinations', function (Blueprint $table) {
            $table->boolean('is_controlled')->nullable()->after('result');
            $table->boolean('is_first_visit_this_month')->default(false)->after('is_controlled');
            $table->boolean('is_standard_patient')->default(false)->after('is_first_visit_this_month');
            $table->string('patient_gender', 10)->nullable()->after('is_standard_patient');

            // Index untuk performa query
            $table->index(['puskesmas_id', 'year', 'month', 'is_first_visit_this_month'], 'idx_dm_stats_query');
            $table->index(['is_controlled', 'is_standard_patient'], 'idx_dm_controlled_standard');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ht_examinations', function (Blueprint $table) {
            $table->dropIndex('idx_ht_stats_query');
            $table->dropIndex('idx_ht_controlled_standard');
            $table->dropColumn([
                'is_controlled',
                'is_first_visit_this_month',
                'is_standard_patient',
                'patient_gender'
            ]);
        });

        Schema::table('dm_examinations', function (Blueprint $table) {
            $table->dropIndex('idx_dm_stats_query');
            $table->dropIndex('idx_dm_controlled_standard');
            $table->dropColumn([
                'is_controlled',
                'is_first_visit_this_month',
                'is_standard_patient',
                'patient_gender'
            ]);
        });
    }
};
