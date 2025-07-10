<?php

/**
 * Script untuk migrasi penamaan modular
 * Menerapkan standar penamaan yang konsisten sesuai MODULAR_NAMING_STANDARDS.md
 */

class ModularNamingMigrator
{
    private string $basePath;
    private array $migrations = [];
    private array $errors = [];
    private bool $dryRun;

    public function __construct(string $basePath, bool $dryRun = true)
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->dryRun = $dryRun;
    }

    /**
     * Jalankan migrasi penamaan modular
     */
    public function migrate(): void
    {
        echo "🚀 Starting Modular Naming Migration...\n";
        echo "Mode: " . ($this->dryRun ? "DRY RUN" : "LIVE") . "\n\n";

        // Phase 1: Rename files untuk konsistensi
        $this->renameCommandFiles();
        $this->renameMiddlewareFiles();
        
        // Phase 2: Reorganize services by domain
        $this->reorganizeServices();
        
        // Phase 3: Update namespaces dan imports
        $this->updateNamespaces();
        
        // Phase 4: Update service provider registrations
        $this->updateServiceProviders();
        
        $this->printSummary();
    }

    /**
     * Rename command files untuk menambahkan suffix "Command"
     */
    private function renameCommandFiles(): void
    {
        echo "📁 Phase 1a: Renaming Command Files...\n";
        
        $commandsPath = $this->basePath . '/app/Console/Commands';
        $renames = [
            'ArchiveExaminations.php' => 'ArchiveExaminationsCommand.php',
            'CreateYearlyTargets.php' => 'CreateYearlyTargetsCommand.php',
            'PopulateExaminationStats.php' => 'PopulateExaminationStatsCommand.php',
            'RebuildStatisticsCache.php' => 'RebuildStatisticsCacheCommand.php',
            'SetupNewYear.php' => 'SetupNewYearCommand.php',
        ];

        foreach ($renames as $oldName => $newName) {
            $oldPath = $commandsPath . '/' . $oldName;
            $newPath = $commandsPath . '/' . $newName;
            
            if (file_exists($oldPath)) {
                $this->addMigration('rename_file', $oldPath, $newPath);
                
                // Update class name dalam file
                $this->addMigration('update_class_name', $oldPath, [
                    'old_class' => pathinfo($oldName, PATHINFO_FILENAME),
                    'new_class' => pathinfo($newName, PATHINFO_FILENAME)
                ]);
            }
        }
    }

    /**
     * Rename middleware files untuk menambahkan suffix "Middleware"
     */
    private function renameMiddlewareFiles(): void
    {
        echo "📁 Phase 1b: Renaming Middleware Files...\n";
        
        $middlewarePath = $this->basePath . '/app/Http/Middleware';
        $renames = [
            'AdminOrPuskesmas.php' => 'AdminOrPuskesmasMiddleware.php',
            'CheckUserRole.php' => 'CheckUserRoleMiddleware.php',
            'HasRole.php' => 'HasRoleMiddleware.php',
            'IsAdmin.php' => 'IsAdminMiddleware.php',
            'IsPuskesmas.php' => 'IsPuskesmasMiddleware.php',
            'FileUploadRateLimit.php' => 'FileUploadRateLimitMiddleware.php',
        ];

        foreach ($renames as $oldName => $newName) {
            $oldPath = $middlewarePath . '/' . $oldName;
            $newPath = $middlewarePath . '/' . $newName;
            
            if (file_exists($oldPath)) {
                $this->addMigration('rename_file', $oldPath, $newPath);
                
                // Update class name dalam file
                $this->addMigration('update_class_name', $oldPath, [
                    'old_class' => pathinfo($oldName, PATHINFO_FILENAME),
                    'new_class' => pathinfo($newName, PATHINFO_FILENAME)
                ]);
            }
        }
    }

    /**
     * Reorganize services berdasarkan domain
     */
    private function reorganizeServices(): void
    {
        echo "📁 Phase 2: Reorganizing Services by Domain...\n";
        
        $servicesPath = $this->basePath . '/app/Services';
        
        // Create domain directories
        $domains = [
            'Statistics' => [
                'StatisticsService.php',
                'StatisticsDataService.php',
                'StatisticsCacheService.php',
                'StatisticsAdminService.php',
                'RealTimeStatisticsService.php',
                'OptimizedStatisticsService.php',
                'DiseaseStatisticsService.php'
            ],
            'Export' => [
                'ExportService.php',
                'StatisticsExportService.php',
                'PuskesmasExportService.php',
                'PdfService.php'
            ],
            'Profile' => [
                'ProfileUpdateService.php',
                'ProfilePictureService.php'
            ],
            'System' => [
                'ArchiveService.php',
                'FileUploadService.php',
                'MonitoringReportService.php',
                'NewYearSetupService.php'
            ]
        ];

        foreach ($domains as $domain => $services) {
            $domainPath = $servicesPath . '/' . $domain;
            $this->addMigration('create_directory', $domainPath);
            
            foreach ($services as $service) {
                $oldPath = $servicesPath . '/' . $service;
                $newPath = $domainPath . '/' . $service;
                
                if (file_exists($oldPath)) {
                    $this->addMigration('move_file', $oldPath, $newPath);
                    
                    // Update namespace
                    $this->addMigration('update_namespace', $newPath, [
                        'old_namespace' => 'App\\Services',
                        'new_namespace' => 'App\\Services\\' . $domain
                    ]);
                }
            }
        }
    }

    /**
     * Update namespaces dan imports di seluruh codebase
     */
    private function updateNamespaces(): void
    {
        echo "📁 Phase 3: Updating Namespaces and Imports...\n";
        
        // Files yang perlu diupdate import statements
        $filesToUpdate = [
            $this->basePath . '/app/Providers',
            $this->basePath . '/app/Http/Controllers',
            $this->basePath . '/config',
            $this->basePath . '/routes'
        ];

        foreach ($filesToUpdate as $path) {
            $this->addMigration('update_imports_in_directory', $path);
        }
    }

    /**
     * Update service provider registrations
     */
    private function updateServiceProviders(): void
    {
        echo "📁 Phase 4: Updating Service Provider Registrations...\n";
        
        $providers = [
            $this->basePath . '/app/Providers/AppServiceProvider.php',
            $this->basePath . '/app/Providers/RepositoryServiceProvider.php',
            $this->basePath . '/config/app.php'
        ];

        foreach ($providers as $provider) {
            if (file_exists($provider)) {
                $this->addMigration('update_service_bindings', $provider);
            }
        }
    }

    /**
     * Add migration task
     */
    private function addMigration(string $type, string $source, $target = null): void
    {
        $this->migrations[] = [
            'type' => $type,
            'source' => $source,
            'target' => $target,
            'status' => 'pending'
        ];
        
        echo "  ✓ Queued: {$type} - " . basename($source) . "\n";
    }

    /**
     * Execute migrations
     */
    public function execute(): void
    {
        if ($this->dryRun) {
            echo "\n⚠️  DRY RUN MODE - No actual changes will be made\n";
            return;
        }

        echo "\n🔧 Executing migrations...\n";
        
        foreach ($this->migrations as &$migration) {
            try {
                $this->executeMigration($migration);
                $migration['status'] = 'completed';
                echo "  ✅ {$migration['type']}: " . basename($migration['source']) . "\n";
            } catch (Exception $e) {
                $migration['status'] = 'failed';
                $migration['error'] = $e->getMessage();
                $this->errors[] = $migration;
                echo "  ❌ {$migration['type']}: " . basename($migration['source']) . " - {$e->getMessage()}\n";
            }
        }
    }

    /**
     * Execute single migration
     */
    private function executeMigration(array $migration): void
    {
        switch ($migration['type']) {
            case 'rename_file':
                rename($migration['source'], $migration['target']);
                break;
                
            case 'move_file':
                rename($migration['source'], $migration['target']);
                break;
                
            case 'create_directory':
                if (!is_dir($migration['source'])) {
                    mkdir($migration['source'], 0755, true);
                }
                break;
                
            case 'update_class_name':
                $this->updateClassNameInFile($migration['source'], $migration['target']);
                break;
                
            case 'update_namespace':
                $this->updateNamespaceInFile($migration['source'], $migration['target']);
                break;
                
            case 'update_imports_in_directory':
                $this->updateImportsInDirectory($migration['source']);
                break;
                
            case 'update_service_bindings':
                $this->updateServiceBindingsInFile($migration['source']);
                break;
        }
    }

    /**
     * Update class name dalam file
     */
    private function updateClassNameInFile(string $filePath, array $data): void
    {
        $content = file_get_contents($filePath);
        $content = str_replace(
            "class {$data['old_class']}",
            "class {$data['new_class']}",
            $content
        );
        file_put_contents($filePath, $content);
    }

    /**
     * Update namespace dalam file
     */
    private function updateNamespaceInFile(string $filePath, array $data): void
    {
        $content = file_get_contents($filePath);
        $content = str_replace(
            "namespace {$data['old_namespace']};",
            "namespace {$data['new_namespace']};",
            $content
        );
        file_put_contents($filePath, $content);
    }

    /**
     * Update imports dalam directory
     */
    private function updateImportsInDirectory(string $dirPath): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath)
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $this->updateImportsInFile($file->getPathname());
            }
        }
    }

    /**
     * Update imports dalam single file
     */
    private function updateImportsInFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        
        // Update service imports
        $serviceUpdates = [
            'App\\Services\\StatisticsService' => 'App\\Services\\Statistics\\StatisticsService',
            'App\\Services\\StatisticsDataService' => 'App\\Services\\Statistics\\StatisticsDataService',
            'App\\Services\\ExportService' => 'App\\Services\\Export\\ExportService',
            'App\\Services\\ProfileUpdateService' => 'App\\Services\\Profile\\ProfileUpdateService',
            // Add more mappings as needed
        ];
        
        foreach ($serviceUpdates as $old => $new) {
            $content = str_replace($old, $new, $content);
        }
        
        file_put_contents($filePath, $content);
    }

    /**
     * Update service bindings dalam file
     */
    private function updateServiceBindingsInFile(string $filePath): void
    {
        // Implementation for updating service provider bindings
        // This would update the service container bindings to use new namespaces
    }

    /**
     * Print migration summary
     */
    private function printSummary(): void
    {
        echo "\n📊 Migration Summary:\n";
        echo "Total migrations: " . count($this->migrations) . "\n";
        
        if (!$this->dryRun) {
            $completed = array_filter($this->migrations, fn($m) => $m['status'] === 'completed');
            $failed = array_filter($this->migrations, fn($m) => $m['status'] === 'failed');
            
            echo "Completed: " . count($completed) . "\n";
            echo "Failed: " . count($failed) . "\n";
            
            if (!empty($this->errors)) {
                echo "\n❌ Errors:\n";
                foreach ($this->errors as $error) {
                    echo "  - {$error['type']}: {$error['source']} - {$error['error']}\n";
                }
            }
        }
        
        echo "\n✅ Migration planning completed!\n";
        
        if ($this->dryRun) {
            echo "\n💡 To execute the migration, run with --execute flag\n";
        }
    }

    /**
     * Generate rollback script
     */
    public function generateRollbackScript(): void
    {
        $rollbackScript = "<?php\n\n";
        $rollbackScript .= "// Rollback script for modular naming migration\n\n";
        
        foreach (array_reverse($this->migrations) as $migration) {
            switch ($migration['type']) {
                case 'rename_file':
                case 'move_file':
                    $rollbackScript .= "rename('{$migration['target']}', '{$migration['source']}');\n";
                    break;
                case 'create_directory':
                    $rollbackScript .= "rmdir('{$migration['source']}');\n";
                    break;
            }
        }
        
        file_put_contents($this->basePath . '/scripts/rollback-modular-naming.php', $rollbackScript);
        echo "\n📝 Rollback script generated: scripts/rollback-modular-naming.php\n";
    }
}

// CLI Interface
if (php_sapi_name() === 'cli') {
    $options = getopt('', ['execute', 'rollback', 'help']);
    
    if (isset($options['help'])) {
        echo "Modular Naming Migration Tool\n\n";
        echo "Usage:\n";
        echo "  php migrate-modular-naming.php [options]\n\n";
        echo "Options:\n";
        echo "  --execute    Execute the migration (default is dry run)\n";
        echo "  --rollback   Generate rollback script\n";
        echo "  --help       Show this help message\n\n";
        exit(0);
    }
    
    $basePath = dirname(__DIR__);
    $dryRun = !isset($options['execute']);
    
    $migrator = new ModularNamingMigrator($basePath, $dryRun);
    $migrator->migrate();
    
    if (!$dryRun) {
        $migrator->execute();
    }
    
    if (isset($options['rollback'])) {
        $migrator->generateRollbackScript();
    }
}