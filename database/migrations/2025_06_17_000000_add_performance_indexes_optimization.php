<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration untuk menambahkan indeks optimalisasi performa
 * 
 * Indeks yang ditambahkan:
 * - Composite indexes untuk query yang sering digunakan
 * - JSON indexes untuk filtering berdasarkan tahun
 * - Search indexes untuk pencarian teks
 * - Foreign key indexes yang hilang
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Patients table indexes
        Schema::table('patients', function (Blueprint $table) {
            // Composite index untuk filtering berdasarkan puskesmas dan gender
            if (!$this->indexExists('patients', 'idx_patients_puskesmas_gender')) {
                $table->index(['puskesmas_id', 'gender'], 'idx_patients_puskesmas_gender');
            }
            
            // Index untuk pencarian berdasarkan NIK
            if (!$this->indexExists('patients', 'idx_patients_nik')) {
                $table->index('nik', 'idx_patients_nik');
            }
            
            // Index untuk pencarian berdasarkan BPJS
            if (!$this->indexExists('patients', 'idx_patients_bpjs')) {
                $table->index('bpjs_number', 'idx_patients_bpjs');
            }
            
            // Index untuk pencarian berdasarkan nomor rekam medis
            if (!$this->indexExists('patients', 'idx_patients_medical_record')) {
                $table->index('medical_record_number', 'idx_patients_medical_record');
            }
            
            // Index untuk pencarian berdasarkan nomor telepon
            if (!$this->indexExists('patients', 'idx_patients_phone')) {
                $table->index('phone_number', 'idx_patients_phone');
            }
            
            // Composite index untuk filtering berdasarkan puskesmas dan umur
            if (!$this->indexExists('patients', 'idx_patients_puskesmas_age')) {
                $table->index(['puskesmas_id', 'age'], 'idx_patients_puskesmas_age');
            }
            
            // Index untuk birth_date (untuk kalkulasi umur)
            if (!$this->indexExists('patients', 'idx_patients_birth_date')) {
                $table->index('birth_date', 'idx_patients_birth_date');
            }
        });
        
        // 2. HT Examinations table indexes
        Schema::table('ht_examinations', function (Blueprint $table) {
            // Composite index untuk query statistik utama
            if (!$this->indexExists('ht_examinations', 'idx_ht_puskesmas_year_month')) {
                $table->index(['puskesmas_id', 'year', 'month'], 'idx_ht_puskesmas_year_month');
            }
            
            // Index untuk filtering berdasarkan patient dan tanggal
            if (!$this->indexExists('ht_examinations', 'idx_ht_patient_date')) {
                $table->index(['patient_id', 'examination_date'], 'idx_ht_patient_date');
            }
            
            // Index untuk filtering berdasarkan status kontrol
            if (!$this->indexExists('ht_examinations', 'idx_ht_puskesmas_controlled')) {
                $table->index(['puskesmas_id', 'is_controlled'], 'idx_ht_puskesmas_controlled');
            }
            
            // Index untuk filtering berdasarkan kunjungan pertama
            if (!$this->indexExists('ht_examinations', 'idx_ht_puskesmas_first_visit')) {
                $table->index(['puskesmas_id', 'is_first_visit_this_month'], 'idx_ht_puskesmas_first_visit');
            }
            
            // Index untuk filtering berdasarkan pasien standar
            if (!$this->indexExists('ht_examinations', 'idx_ht_puskesmas_standard')) {
                $table->index(['puskesmas_id', 'is_standard_patient'], 'idx_ht_puskesmas_standard');
            }
            
            // Index untuk filtering berdasarkan gender
            if (!$this->indexExists('ht_examinations', 'idx_ht_puskesmas_gender')) {
                $table->index(['puskesmas_id', 'patient_gender'], 'idx_ht_puskesmas_gender');
            }
            
            // Index untuk filtering berdasarkan status arsip
            if (!$this->indexExists('ht_examinations', 'idx_ht_puskesmas_archived')) {
                $table->index(['puskesmas_id', 'is_archived'], 'idx_ht_puskesmas_archived');
            }
            
            // Composite index untuk statistik bulanan
            if (!$this->indexExists('ht_examinations', 'idx_ht_monthly_stats')) {
                $table->index(['puskesmas_id', 'year', 'month', 'is_controlled'], 'idx_ht_monthly_stats');
            }
        });
        
        // 3. DM Examinations table indexes
        Schema::table('dm_examinations', function (Blueprint $table) {
            // Composite index untuk query statistik utama
            if (!$this->indexExists('dm_examinations', 'idx_dm_puskesmas_year_month')) {
                $table->index(['puskesmas_id', 'year', 'month'], 'idx_dm_puskesmas_year_month');
            }
            
            // Index untuk filtering berdasarkan patient dan tanggal
            if (!$this->indexExists('dm_examinations', 'idx_dm_patient_date')) {
                $table->index(['patient_id', 'examination_date'], 'idx_dm_patient_date');
            }
            
            // Index untuk filtering berdasarkan status kontrol
            if (!$this->indexExists('dm_examinations', 'idx_dm_puskesmas_controlled')) {
                $table->index(['puskesmas_id', 'is_controlled'], 'idx_dm_puskesmas_controlled');
            }
            
            // Index untuk filtering berdasarkan kunjungan pertama
            if (!$this->indexExists('dm_examinations', 'idx_dm_puskesmas_first_visit')) {
                $table->index(['puskesmas_id', 'is_first_visit_this_month'], 'idx_dm_puskesmas_first_visit');
            }
            
            // Index untuk filtering berdasarkan pasien standar
            if (!$this->indexExists('dm_examinations', 'idx_dm_puskesmas_standard')) {
                $table->index(['puskesmas_id', 'is_standard_patient'], 'idx_dm_puskesmas_standard');
            }
            
            // Index untuk filtering berdasarkan gender
            if (!$this->indexExists('dm_examinations', 'idx_dm_puskesmas_gender')) {
                $table->index(['puskesmas_id', 'patient_gender'], 'idx_dm_puskesmas_gender');
            }
            
            // Index untuk filtering berdasarkan status arsip
            if (!$this->indexExists('dm_examinations', 'idx_dm_puskesmas_archived')) {
                $table->index(['puskesmas_id', 'is_archived'], 'idx_dm_puskesmas_archived');
            }
            
            // Composite index untuk statistik bulanan
            if (!$this->indexExists('dm_examinations', 'idx_dm_monthly_stats')) {
                $table->index(['puskesmas_id', 'year', 'month', 'is_controlled'], 'idx_dm_monthly_stats');
            }
        });
        
        // 4. Monthly Statistics Cache table indexes (tambahan)
        Schema::table('monthly_statistics_cache', function (Blueprint $table) {
            // Index untuk query berdasarkan tahun saja
            if (!$this->indexExists('monthly_statistics_cache', 'idx_msc_disease_year_only')) {
                $table->index(['disease_type', 'year'], 'idx_msc_disease_year_only');
            }
            
            // Index untuk query berdasarkan puskesmas dan tahun
            if (!$this->indexExists('monthly_statistics_cache', 'idx_msc_puskesmas_year')) {
                $table->index(['puskesmas_id', 'year'], 'idx_msc_puskesmas_year');
            }
            
            // Index untuk query summary statistics
            if (!$this->indexExists('monthly_statistics_cache', 'idx_msc_disease_year_month')) {
                $table->index(['disease_type', 'year', 'month'], 'idx_msc_disease_year_month');
            }
        });
        
        // 5. Yearly Targets table indexes
        Schema::table('yearly_targets', function (Blueprint $table) {
            // Composite index untuk query target
            if (!$this->indexExists('yearly_targets', 'idx_yt_puskesmas_disease_year')) {
                $table->index(['puskesmas_id', 'disease_type', 'year'], 'idx_yt_puskesmas_disease_year');
            }
            
            // Index untuk query berdasarkan tahun dan jenis penyakit
            if (!$this->indexExists('yearly_targets', 'idx_yt_disease_year')) {
                $table->index(['disease_type', 'year'], 'idx_yt_disease_year');
            }
        });
        
        // 6. Users table indexes (jika belum ada)
        Schema::table('users', function (Blueprint $table) {
            // Index untuk query berdasarkan puskesmas
            if (!$this->indexExists('users', 'idx_users_puskesmas')) {
                $table->index('puskesmas_id', 'idx_users_puskesmas');
            }
            
            // Index untuk query berdasarkan role
            if (!$this->indexExists('users', 'idx_users_role')) {
                $table->index('role', 'idx_users_role');
            }
        });
        
        // 7. Add JSON indexes untuk MySQL 5.7+ (jika didukung)
        if ($this->supportsFunctionalIndexes()) {
            // JSON indexes untuk ht_years dan dm_years di patients table
            DB::statement('ALTER TABLE patients ADD INDEX idx_patients_ht_years_json ((CAST(ht_years AS JSON)))');
            DB::statement('ALTER TABLE patients ADD INDEX idx_patients_dm_years_json ((CAST(dm_years AS JSON)))');
        }
        
        // 8. Add full-text indexes untuk pencarian
        if ($this->supportsFullTextIndexes()) {
            Schema::table('patients', function (Blueprint $table) {
                if (!$this->indexExists('patients', 'idx_patients_fulltext_search')) {
                    $table->fullText(['name', 'address'], 'idx_patients_fulltext_search');
                }
            });
            
            Schema::table('puskesmas', function (Blueprint $table) {
                if (!$this->indexExists('puskesmas', 'idx_puskesmas_fulltext')) {
                    $table->fullText(['name'], 'idx_puskesmas_fulltext');
                }
            });
        }
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop JSON indexes
        if ($this->supportsFunctionalIndexes()) {
            DB::statement('ALTER TABLE patients DROP INDEX IF EXISTS idx_patients_ht_years_json');
            DB::statement('ALTER TABLE patients DROP INDEX IF EXISTS idx_patients_dm_years_json');
        }
        
        // Drop full-text indexes
        if ($this->supportsFullTextIndexes()) {
            Schema::table('patients', function (Blueprint $table) {
                if ($this->indexExists('patients', 'idx_patients_fulltext_search')) {
                    $table->dropFullText('idx_patients_fulltext_search');
                }
            });
            
            Schema::table('puskesmas', function (Blueprint $table) {
                if ($this->indexExists('puskesmas', 'idx_puskesmas_fulltext')) {
                    $table->dropFullText('idx_puskesmas_fulltext');
                }
            });
        }
        
        // Drop regular indexes
        Schema::table('patients', function (Blueprint $table) {
            if ($this->indexExists('patients', 'idx_patients_puskesmas_gender')) {
                $table->dropIndex('idx_patients_puskesmas_gender');
            }
            if ($this->indexExists('patients', 'idx_patients_nik')) {
                $table->dropIndex('idx_patients_nik');
            }
            if ($this->indexExists('patients', 'idx_patients_bpjs')) {
                $table->dropIndex('idx_patients_bpjs');
            }
            if ($this->indexExists('patients', 'idx_patients_medical_record')) {
                $table->dropIndex('idx_patients_medical_record');
            }
            if ($this->indexExists('patients', 'idx_patients_phone')) {
                $table->dropIndex('idx_patients_phone');
            }
            if ($this->indexExists('patients', 'idx_patients_puskesmas_age')) {
                $table->dropIndex('idx_patients_puskesmas_age');
            }
            if ($this->indexExists('patients', 'idx_patients_birth_date')) {
                $table->dropIndex('idx_patients_birth_date');
            }
        });
        
        Schema::table('ht_examinations', function (Blueprint $table) {
            if ($this->indexExists('ht_examinations', 'idx_ht_puskesmas_year_month')) {
                $table->dropIndex('idx_ht_puskesmas_year_month');
            }
            if ($this->indexExists('ht_examinations', 'idx_ht_patient_date')) {
                $table->dropIndex('idx_ht_patient_date');
            }
            if ($this->indexExists('ht_examinations', 'idx_ht_puskesmas_controlled')) {
                $table->dropIndex('idx_ht_puskesmas_controlled');
            }
            if ($this->indexExists('ht_examinations', 'idx_ht_puskesmas_first_visit')) {
                $table->dropIndex('idx_ht_puskesmas_first_visit');
            }
            if ($this->indexExists('ht_examinations', 'idx_ht_puskesmas_standard')) {
                $table->dropIndex('idx_ht_puskesmas_standard');
            }
            if ($this->indexExists('ht_examinations', 'idx_ht_puskesmas_gender')) {
                $table->dropIndex('idx_ht_puskesmas_gender');
            }
            if ($this->indexExists('ht_examinations', 'idx_ht_puskesmas_archived')) {
                $table->dropIndex('idx_ht_puskesmas_archived');
            }
            if ($this->indexExists('ht_examinations', 'idx_ht_monthly_stats')) {
                $table->dropIndex('idx_ht_monthly_stats');
            }
        });
        
        Schema::table('dm_examinations', function (Blueprint $table) {
            if ($this->indexExists('dm_examinations', 'idx_dm_puskesmas_year_month')) {
                $table->dropIndex('idx_dm_puskesmas_year_month');
            }
            if ($this->indexExists('dm_examinations', 'idx_dm_patient_date')) {
                $table->dropIndex('idx_dm_patient_date');
            }
            if ($this->indexExists('dm_examinations', 'idx_dm_puskesmas_controlled')) {
                $table->dropIndex('idx_dm_puskesmas_controlled');
            }
            if ($this->indexExists('dm_examinations', 'idx_dm_puskesmas_first_visit')) {
                $table->dropIndex('idx_dm_puskesmas_first_visit');
            }
            if ($this->indexExists('dm_examinations', 'idx_dm_puskesmas_standard')) {
                $table->dropIndex('idx_dm_puskesmas_standard');
            }
            if ($this->indexExists('dm_examinations', 'idx_dm_puskesmas_gender')) {
                $table->dropIndex('idx_dm_puskesmas_gender');
            }
            if ($this->indexExists('dm_examinations', 'idx_dm_puskesmas_archived')) {
                $table->dropIndex('idx_dm_puskesmas_archived');
            }
            if ($this->indexExists('dm_examinations', 'idx_dm_monthly_stats')) {
                $table->dropIndex('idx_dm_monthly_stats');
            }
        });
        
        Schema::table('monthly_statistics_cache', function (Blueprint $table) {
            if ($this->indexExists('monthly_statistics_cache', 'idx_msc_disease_year_only')) {
                $table->dropIndex('idx_msc_disease_year_only');
            }
            if ($this->indexExists('monthly_statistics_cache', 'idx_msc_puskesmas_year')) {
                $table->dropIndex('idx_msc_puskesmas_year');
            }
            if ($this->indexExists('monthly_statistics_cache', 'idx_msc_disease_year_month')) {
                $table->dropIndex('idx_msc_disease_year_month');
            }
        });
        
        Schema::table('yearly_targets', function (Blueprint $table) {
            if ($this->indexExists('yearly_targets', 'idx_yt_puskesmas_disease_year')) {
                $table->dropIndex('idx_yt_puskesmas_disease_year');
            }
            if ($this->indexExists('yearly_targets', 'idx_yt_disease_year')) {
                $table->dropIndex('idx_yt_disease_year');
            }
        });
        
        Schema::table('users', function (Blueprint $table) {
            if ($this->indexExists('users', 'idx_users_puskesmas')) {
                $table->dropIndex('idx_users_puskesmas');
            }
            if ($this->indexExists('users', 'idx_users_role')) {
                $table->dropIndex('idx_users_role');
            }
        });
    }
    
    /**
     * Check if database supports functional indexes (MySQL 8.0+)
     * MariaDB does not support functional indexes with CAST syntax
     */
    private function supportsFunctionalIndexes(): bool
    {
        try {
            $version = DB::select('SELECT VERSION() as version')[0]->version;
            
            // Check if it's MariaDB (not supported)
            if (stripos($version, 'mariadb') !== false) {
                return false;
            }
            
            // Check MySQL version
            return version_compare($version, '8.0.0', '>=');
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if database supports full-text indexes
     */
    private function supportsFullTextIndexes(): bool
    {
        try {
            $engine = DB::select("SHOW TABLE STATUS LIKE 'patients'")[0]->Engine ?? '';
            return in_array(strtolower($engine), ['myisam', 'innodb']);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if index exists on table
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = '{$index}'");
            return count($indexes) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
};