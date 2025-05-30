<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateDatabase extends Command
{
    protected $signature = 'db:create {name}';
    protected $description = 'Create a new MySQL database';

    public function handle()
    {
        try {
            $dbname = $this->argument('name');
            $charset = config('database.connections.mysql.charset', 'utf8mb4');
            $collation = config('database.connections.mysql.collation', 'utf8mb4_unicode_ci');

            $query = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET $charset COLLATE $collation;";
            DB::statement($query);

            $this->info("Database '$dbname' created successfully");
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
