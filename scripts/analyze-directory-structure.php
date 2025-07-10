<?php

/**
 * Directory Structure Analyzer and Optimizer
 * 
 * This script analyzes the current directory structure and suggests
 * improvements based on modular naming standards.
 */

class DirectoryStructureAnalyzer
{
    private string $basePath;
    private array $analysis = [];
    private array $recommendations = [];
    private array $stats = [
        'total_files' => 0,
        'well_organized' => 0,
        'needs_reorganization' => 0,
        'suggested_moves' => 0
    ];

    /**
     * Recommended directory structure
     */
    private array $recommendedStructure = [
        'app/Services' => [
            'Statistics' => ['StatisticsService', 'DiseaseStatisticsService', 'OptimizedStatisticsService', 'RealTimeStatisticsService'],
            'Export' => ['ExportService'],
            'Profile' => ['ProfileService'],
            'System' => ['ArchiveService', 'SystemService'],
            'Core' => ['BaseService', 'CoreService']
        ],
        'app/Http/Controllers/API' => [
            'Admin' => ['AdminController', 'AdminDashboardController'],
            'Auth' => ['AuthController', 'LoginController', 'RegisterController'],
            'Puskesmas' => ['PuskesmasController', 'PuskesmasDataController'],
            'Shared' => ['SharedController', 'CommonController'],
            'System' => ['SystemController', 'ConfigController'],
            'User' => ['UserController', 'UserProfileController']
        ],
        'app/Http/Middleware' => [
            'Auth' => ['AuthMiddleware', 'JwtMiddleware'],
            'Cache' => ['CacheMiddleware', 'OptimizedCacheMiddleware'],
            'RateLimit' => ['RateLimitMiddleware', 'ThrottleMiddleware'],
            'Security' => ['SecurityMiddleware', 'CorsMiddleware']
        ],
        'app/Console/Commands' => [
            'Archive' => ['ArchiveExaminations'],
            'Statistics' => ['PopulateExaminationStats', 'RebuildStatisticsCache'],
            'System' => ['OptimizePerformanceCommand', 'ValidateNamingStandardsCommand'],
            'Setup' => ['CreateYearlyTargets', 'SetupNewYear']
        ],
        'app/Traits' => [
            'Validation' => ['ValidationTrait', 'FormValidationTrait'],
            'Calculation' => ['CalculationTrait', 'StatisticsTrait'],
            'Utility' => ['UtilityTrait', 'HelperTrait']
        ]
    ];

    /**
     * File categorization patterns
     */
    private array $categorizationPatterns = [
        'Statistics' => ['/statistic/i', '/stats/i', '/disease/i', '/calculation/i'],
        'Export' => ['/export/i', '/download/i', '/report/i'],
        'Profile' => ['/profile/i', '/user/i', '/account/i'],
        'System' => ['/system/i', '/config/i', '/setting/i', '/archive/i'],
        'Auth' => ['/auth/i', '/login/i', '/register/i', '/jwt/i'],
        'Admin' => ['/admin/i', '/dashboard/i', '/management/i'],
        'Cache' => ['/cache/i', '/redis/i', '/memory/i'],
        'Security' => ['/security/i', '/cors/i', '/csrf/i'],
        'Validation' => ['/validat/i', '/form/i', '/rule/i'],
        'Utility' => ['/util/i', '/helper/i', '/common/i']
    ];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Run complete analysis
     */
    public function analyze(): array
    {
        echo "🔍 Analyzing directory structure...\n\n";

        $this->analyzeServices();
        $this->analyzeControllers();
        $this->analyzeMiddleware();
        $this->analyzeCommands();
        $this->analyzeTraits();
        $this->analyzeRepositories();
        $this->analyzeFormatters();

        $this->generateRecommendations();
        $this->displayResults();

        return [
            'analysis' => $this->analysis,
            'recommendations' => $this->recommendations,
            'stats' => $this->stats
        ];
    }

    /**
     * Analyze Services directory
     */
    private function analyzeServices(): void
    {
        $servicesPath = $this->basePath . '/app/Services';
        $this->analyzeDirectory('Services', $servicesPath, 'Service');
    }

    /**
     * Analyze Controllers directory
     */
    private function analyzeControllers(): void
    {
        $controllersPath = $this->basePath . '/app/Http/Controllers';
        $this->analyzeDirectory('Controllers', $controllersPath, 'Controller');
    }

    /**
     * Analyze Middleware directory
     */
    private function analyzeMiddleware(): void
    {
        $middlewarePath = $this->basePath . '/app/Http/Middleware';
        $this->analyzeDirectory('Middleware', $middlewarePath, 'Middleware');
    }

    /**
     * Analyze Commands directory
     */
    private function analyzeCommands(): void
    {
        $commandsPath = $this->basePath . '/app/Console/Commands';
        $this->analyzeDirectory('Commands', $commandsPath, 'Command');
    }

    /**
     * Analyze Traits directory
     */
    private function analyzeTraits(): void
    {
        $traitsPath = $this->basePath . '/app/Traits';
        $this->analyzeDirectory('Traits', $traitsPath, 'Trait');
    }

    /**
     * Analyze Repositories directory
     */
    private function analyzeRepositories(): void
    {
        $repositoriesPath = $this->basePath . '/app/Repositories';
        $this->analyzeDirectory('Repositories', $repositoriesPath, 'Repository');
    }

    /**
     * Analyze Formatters directory
     */
    private function analyzeFormatters(): void
    {
        $formattersPath = $this->basePath . '/app/Formatters';
        $this->analyzeDirectory('Formatters', $formattersPath, 'Formatter');
    }

    /**
     * Analyze specific directory
     */
    private function analyzeDirectory(string $type, string $path, string $expectedSuffix): void
    {
        if (!is_dir($path)) {
            $this->analysis[$type] = [
                'status' => 'missing',
                'message' => "Directory {$path} does not exist"
            ];
            return;
        }

        $files = $this->getPhpFiles($path);
        $this->stats['total_files'] += count($files);

        $analysis = [
            'path' => $path,
            'total_files' => count($files),
            'subdirectories' => $this->getSubdirectories($path),
            'files' => [],
            'organization_score' => 0,
            'recommendations' => []
        ];

        foreach ($files as $file) {
            $fileAnalysis = $this->analyzeFile($file, $expectedSuffix, $type);
            $analysis['files'][] = $fileAnalysis;

            if ($fileAnalysis['well_organized']) {
                $this->stats['well_organized']++;
            } else {
                $this->stats['needs_reorganization']++;
            }
        }

        // Calculate organization score
        $analysis['organization_score'] = $this->calculateOrganizationScore($analysis, $type);

        // Generate directory-specific recommendations
        $analysis['recommendations'] = $this->generateDirectoryRecommendations($analysis, $type);

        $this->analysis[$type] = $analysis;
    }

    /**
     * Analyze individual file
     */
    private function analyzeFile(string $filePath, string $expectedSuffix, string $type): array
    {
        $filename = basename($filePath, '.php');
        $relativePath = str_replace($this->basePath . '/', '', $filePath);
        $content = file_get_contents($filePath);

        $analysis = [
            'filename' => $filename,
            'path' => $relativePath,
            'has_correct_suffix' => str_ends_with($filename, $expectedSuffix),
            'suggested_category' => $this->suggestCategory($filename, $content),
            'current_namespace' => $this->extractNamespace($content),
            'well_organized' => false,
            'issues' => [],
            'suggestions' => []
        ];

        // Check naming conventions
        if (!$analysis['has_correct_suffix']) {
            $analysis['issues'][] = "Missing '{$expectedSuffix}' suffix";
            $analysis['suggestions'][] = "Rename to '{$filename}{$expectedSuffix}'";
        }

        // Check if file is in appropriate subdirectory
        $suggestedCategory = $analysis['suggested_category'];
        $currentDir = dirname($relativePath);
        $expectedDir = $this->getExpectedDirectory($type, $suggestedCategory);

        if ($suggestedCategory && $expectedDir && !str_contains($currentDir, $suggestedCategory)) {
            $analysis['issues'][] = "File should be in '{$suggestedCategory}' subdirectory";
            $analysis['suggestions'][] = "Move to '{$expectedDir}'";
            $this->stats['suggested_moves']++;
        }

        // Determine if well organized
        $analysis['well_organized'] = empty($analysis['issues']);

        return $analysis;
    }

    /**
     * Suggest category for file based on name and content
     */
    private function suggestCategory(string $filename, string $content): ?string
    {
        foreach ($this->categorizationPatterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $filename) || preg_match($pattern, $content)) {
                    return $category;
                }
            }
        }

        return null;
    }

    /**
     * Extract namespace from file content
     */
    private function extractNamespace(string $content): ?string
    {
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Get expected directory for type and category
     */
    private function getExpectedDirectory(string $type, ?string $category): ?string
    {
        if (!$category) {
            return null;
        }

        $typeMapping = [
            'Services' => 'app/Services',
            'Controllers' => 'app/Http/Controllers/API',
            'Middleware' => 'app/Http/Middleware',
            'Commands' => 'app/Console/Commands',
            'Traits' => 'app/Traits'
        ];

        $basePath = $typeMapping[$type] ?? null;
        return $basePath ? "{$basePath}/{$category}" : null;
    }

    /**
     * Get PHP files in directory
     */
    private function getPhpFiles(string $path): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Get subdirectories
     */
    private function getSubdirectories(string $path): array
    {
        $subdirs = [];
        $items = scandir($path);

        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..' && is_dir($path . '/' . $item)) {
                $subdirs[] = $item;
            }
        }

        return $subdirs;
    }

    /**
     * Calculate organization score for directory
     */
    private function calculateOrganizationScore(array $analysis, string $type): float
    {
        $totalFiles = $analysis['total_files'];
        if ($totalFiles === 0) {
            return 100.0;
        }

        $wellOrganizedFiles = array_filter($analysis['files'], fn($file) => $file['well_organized']);
        $score = (count($wellOrganizedFiles) / $totalFiles) * 100;

        // Bonus points for having subdirectories when recommended
        if (count($analysis['subdirectories']) > 0 && $totalFiles > 5) {
            $score += 10;
        }

        // Penalty for too many files in root directory
        $rootFiles = array_filter($analysis['files'], function($file) {
            return !str_contains($file['path'], '/');
        });
        
        if (count($rootFiles) > 10) {
            $score -= 20;
        }

        return min(100.0, max(0.0, $score));
    }

    /**
     * Generate directory-specific recommendations
     */
    private function generateDirectoryRecommendations(array $analysis, string $type): array
    {
        $recommendations = [];

        // Too many files in root directory
        $rootFiles = array_filter($analysis['files'], function($file) {
            return !str_contains(dirname($file['path']), '/');
        });

        if (count($rootFiles) > 10) {
            $recommendations[] = [
                'type' => 'organization',
                'priority' => 'high',
                'message' => 'Consider organizing files into subdirectories',
                'details' => 'Root directory has ' . count($rootFiles) . ' files (recommended max: 10)'
            ];
        }

        // Missing recommended subdirectories
        $recommendedSubdirs = $this->recommendedStructure["app/{$type}"] ?? [];
        $existingSubdirs = $analysis['subdirectories'];

        foreach ($recommendedSubdirs as $subdir => $expectedFiles) {
            if (!in_array($subdir, $existingSubdirs)) {
                $recommendations[] = [
                    'type' => 'structure',
                    'priority' => 'medium',
                    'message' => "Consider creating '{$subdir}' subdirectory",
                    'details' => 'For organizing: ' . implode(', ', $expectedFiles)
                ];
            }
        }

        // Files that should be moved
        $filesToMove = array_filter($analysis['files'], function($file) {
            return !empty($file['suggestions']) && 
                   str_contains(implode(' ', $file['suggestions']), 'Move to');
        });

        if (!empty($filesToMove)) {
            $recommendations[] = [
                'type' => 'reorganization',
                'priority' => 'medium',
                'message' => count($filesToMove) . ' files should be reorganized',
                'details' => 'Files need to be moved to appropriate subdirectories'
            ];
        }

        return $recommendations;
    }

    /**
     * Generate overall recommendations
     */
    private function generateRecommendations(): void
    {
        $this->recommendations = [
            'immediate_actions' => [],
            'structural_improvements' => [],
            'long_term_goals' => []
        ];

        foreach ($this->analysis as $type => $analysis) {
            if ($analysis['organization_score'] < 70) {
                $this->recommendations['immediate_actions'][] = [
                    'type' => $type,
                    'action' => 'Reorganize directory structure',
                    'score' => $analysis['organization_score'],
                    'priority' => $analysis['organization_score'] < 50 ? 'critical' : 'high'
                ];
            }

            foreach ($analysis['recommendations'] ?? [] as $rec) {
                if ($rec['priority'] === 'high') {
                    $this->recommendations['immediate_actions'][] = [
                        'type' => $type,
                        'action' => $rec['message'],
                        'details' => $rec['details'] ?? ''
                    ];
                } elseif ($rec['priority'] === 'medium') {
                    $this->recommendations['structural_improvements'][] = [
                        'type' => $type,
                        'action' => $rec['message'],
                        'details' => $rec['details'] ?? ''
                    ];
                }
            }
        }

        // Long-term goals
        $this->recommendations['long_term_goals'] = [
            'Implement automated directory structure validation',
            'Set up pre-commit hooks for naming standards',
            'Create documentation for new developers',
            'Establish code review guidelines for structure'
        ];
    }

    /**
     * Display analysis results
     */
    private function displayResults(): void
    {
        echo "\n📊 Directory Structure Analysis Results\n";
        echo str_repeat('=', 50) . "\n\n";

        // Overall statistics
        echo "📈 Overall Statistics:\n";
        echo "  Total Files: {$this->stats['total_files']}\n";
        echo "  Well Organized: {$this->stats['well_organized']}\n";
        echo "  Need Reorganization: {$this->stats['needs_reorganization']}\n";
        echo "  Suggested Moves: {$this->stats['suggested_moves']}\n\n";

        // Directory scores
        echo "📁 Directory Organization Scores:\n";
        foreach ($this->analysis as $type => $analysis) {
            $score = $analysis['organization_score'] ?? 0;
            $emoji = $score >= 80 ? '✅' : ($score >= 60 ? '⚠️' : '❌');
            echo "  {$emoji} {$type}: {$score}%\n";
        }
        echo "\n";

        // Immediate actions
        if (!empty($this->recommendations['immediate_actions'])) {
            echo "🚨 Immediate Actions Required:\n";
            foreach ($this->recommendations['immediate_actions'] as $action) {
                $priority = $action['priority'] ?? 'medium';
                $emoji = $priority === 'critical' ? '🔥' : '⚠️';
                echo "  {$emoji} [{$action['type']}] {$action['action']}\n";
                if (!empty($action['details'])) {
                    echo "     Details: {$action['details']}\n";
                }
            }
            echo "\n";
        }

        // Structural improvements
        if (!empty($this->recommendations['structural_improvements'])) {
            echo "🔧 Structural Improvements:\n";
            foreach ($this->recommendations['structural_improvements'] as $improvement) {
                echo "  💡 [{$improvement['type']}] {$improvement['action']}\n";
                if (!empty($improvement['details'])) {
                    echo "     Details: {$improvement['details']}\n";
                }
            }
            echo "\n";
        }

        // Long-term goals
        echo "🎯 Long-term Goals:\n";
        foreach ($this->recommendations['long_term_goals'] as $goal) {
            echo "  📋 {$goal}\n";
        }
        echo "\n";

        // Generate migration script suggestion
        echo "💡 Next Steps:\n";
        echo "  1. Review the analysis results above\n";
        echo "  2. Run: php migrate-modular-naming.php --dry-run\n";
        echo "  3. Run: php artisan naming:validate --report\n";
        echo "  4. Apply fixes: php migrate-modular-naming.php --execute\n";
        echo "  5. Validate: php artisan naming:validate --strict\n\n";
    }

    /**
     * Export analysis to JSON
     */
    public function exportToJson(string $outputPath): void
    {
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'stats' => $this->stats,
            'analysis' => $this->analysis,
            'recommendations' => $this->recommendations
        ];

        file_put_contents($outputPath, json_encode($data, JSON_PRETTY_PRINT));
        echo "📄 Analysis exported to: {$outputPath}\n";
    }
}

// Run the analysis
if (php_sapi_name() === 'cli') {
    $basePath = dirname(__DIR__);
    $analyzer = new DirectoryStructureAnalyzer($basePath);
    
    $results = $analyzer->analyze();
    
    // Export results if requested
    if (isset($argv[1]) && $argv[1] === '--export') {
        $outputPath = $argv[2] ?? 'directory-analysis.json';
        $analyzer->exportToJson($outputPath);
    }
}