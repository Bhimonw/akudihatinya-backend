<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class ValidateNamingStandardsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'naming:validate 
                            {--fix : Automatically fix naming violations where possible}
                            {--report : Generate detailed report}
                            {--path= : Specific path to validate}
                            {--strict : Use strict validation rules}';

    /**
     * The console command description.
     */
    protected $description = 'Validate and optionally fix naming convention violations according to MODULAR_NAMING_STANDARDS.md';

    /**
     * Validation results
     */
    private array $violations = [];
    private array $warnings = [];
    private array $fixed = [];
    private array $stats = [
        'files_checked' => 0,
        'violations_found' => 0,
        'warnings_found' => 0,
        'fixes_applied' => 0
    ];

    /**
     * Naming convention rules
     */
    private array $namingRules = [
        'services' => [
            'path' => 'app/Services',
            'suffix' => 'Service',
            'namespace_pattern' => 'App\\Services',
            'allowed_subnamespaces' => ['Statistics', 'Export', 'Profile', 'System', 'Core']
        ],
        'controllers' => [
            'path' => 'app/Http/Controllers',
            'suffix' => 'Controller',
            'namespace_pattern' => 'App\\Http\\Controllers',
            'allowed_subnamespaces' => ['API\\Admin', 'API\\Auth', 'API\\Puskesmas', 'API\\Shared', 'API\\System', 'API\\User']
        ],
        'middleware' => [
            'path' => 'app/Http/Middleware',
            'suffix' => 'Middleware',
            'namespace_pattern' => 'App\\Http\\Middleware',
            'allowed_subnamespaces' => ['Auth', 'Cache', 'RateLimit', 'Security']
        ],
        'commands' => [
            'path' => 'app/Console/Commands',
            'suffix' => 'Command',
            'namespace_pattern' => 'App\\Console\\Commands',
            'allowed_subnamespaces' => ['Archive', 'Statistics', 'System', 'Setup']
        ],
        'traits' => [
            'path' => 'app/Traits',
            'suffix' => 'Trait',
            'namespace_pattern' => 'App\\Traits',
            'allowed_subnamespaces' => ['Validation', 'Calculation', 'Utility']
        ],
        'repositories' => [
            'path' => 'app/Repositories',
            'suffix' => ['Repository', 'RepositoryInterface'],
            'namespace_pattern' => 'App\\Repositories',
            'allowed_subnamespaces' => []
        ],
        'formatters' => [
            'path' => 'app/Formatters',
            'suffix' => 'Formatter',
            'namespace_pattern' => 'App\\Formatters',
            'allowed_subnamespaces' => []
        ]
    ];

    /**
     * Forbidden method names
     */
    private array $forbiddenMethodNames = [
        'process', 'handle', 'execute', 'run', 'do', 'perform'
    ];

    /**
     * Forbidden variable names
     */
    private array $forbiddenVariableNames = [
        '$data', '$result', '$temp', '$x', '$y', '$z'
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Validating Naming Standards...');
        $this->newLine();

        $path = $this->option('path') ?? base_path();
        $strict = $this->option('strict');
        $fix = $this->option('fix');
        $report = $this->option('report');

        // Validate different types of files
        $this->validateServices($path, $strict);
        $this->validateControllers($path, $strict);
        $this->validateMiddleware($path, $strict);
        $this->validateCommands($path, $strict);
        $this->validateTraits($path, $strict);
        $this->validateRepositories($path, $strict);
        $this->validateFormatters($path, $strict);

        // Apply fixes if requested
        if ($fix) {
            $this->applyFixes();
        }

        // Generate report if requested
        if ($report) {
            $this->generateReport();
        }

        // Display summary
        $this->displaySummary();

        return $this->stats['violations_found'] > 0 ? 1 : 0;
    }

    /**
     * Validate services naming conventions
     */
    private function validateServices(string $basePath, bool $strict): void
    {
        $this->info('📁 Validating Services...');
        $this->validateFileType('services', $basePath, $strict);
    }

    /**
     * Validate controllers naming conventions
     */
    private function validateControllers(string $basePath, bool $strict): void
    {
        $this->info('📁 Validating Controllers...');
        $this->validateFileType('controllers', $basePath, $strict);
    }

    /**
     * Validate middleware naming conventions
     */
    private function validateMiddleware(string $basePath, bool $strict): void
    {
        $this->info('📁 Validating Middleware...');
        $this->validateFileType('middleware', $basePath, $strict);
    }

    /**
     * Validate commands naming conventions
     */
    private function validateCommands(string $basePath, bool $strict): void
    {
        $this->info('📁 Validating Commands...');
        $this->validateFileType('commands', $basePath, $strict);
    }

    /**
     * Validate traits naming conventions
     */
    private function validateTraits(string $basePath, bool $strict): void
    {
        $this->info('📁 Validating Traits...');
        $this->validateFileType('traits', $basePath, $strict);
    }

    /**
     * Validate repositories naming conventions
     */
    private function validateRepositories(string $basePath, bool $strict): void
    {
        $this->info('📁 Validating Repositories...');
        $this->validateFileType('repositories', $basePath, $strict);
    }

    /**
     * Validate formatters naming conventions
     */
    private function validateFormatters(string $basePath, bool $strict): void
    {
        $this->info('📁 Validating Formatters...');
        $this->validateFileType('formatters', $basePath, $strict);
    }

    /**
     * Validate specific file type
     */
    private function validateFileType(string $type, string $basePath, bool $strict): void
    {
        $rules = $this->namingRules[$type];
        $path = $basePath . '/' . $rules['path'];

        if (!File::exists($path)) {
            $this->warn("  ⚠️  Path not found: {$path}");
            return;
        }

        $finder = new Finder();
        $finder->files()->in($path)->name('*.php');

        foreach ($finder as $file) {
            $this->stats['files_checked']++;
            $this->validateFile($file, $rules, $type, $strict);
        }
    }

    /**
     * Validate individual file
     */
    private function validateFile($file, array $rules, string $type, bool $strict): void
    {
        $filename = $file->getFilename();
        $className = pathinfo($filename, PATHINFO_FILENAME);
        $content = $file->getContents();
        $relativePath = str_replace(base_path() . '/', '', $file->getPathname());

        // Check class name suffix
        $this->validateClassNameSuffix($className, $rules['suffix'], $relativePath, $type);

        // Check namespace
        $this->validateNamespace($content, $rules, $relativePath, $strict);

        // Check file organization
        $this->validateFileOrganization($file, $rules, $relativePath, $strict);

        // Check method names
        $this->validateMethodNames($content, $relativePath, $strict);

        // Check variable names
        if ($strict) {
            $this->validateVariableNames($content, $relativePath);
        }
    }

    /**
     * Validate class name suffix
     */
    private function validateClassNameSuffix(string $className, $expectedSuffix, string $filePath, string $type): void
    {
        $suffixes = is_array($expectedSuffix) ? $expectedSuffix : [$expectedSuffix];
        $hasValidSuffix = false;

        foreach ($suffixes as $suffix) {
            if (str_ends_with($className, $suffix)) {
                $hasValidSuffix = true;
                break;
            }
        }

        if (!$hasValidSuffix) {
            $this->addViolation(
                'naming_suffix',
                $filePath,
                "Class '{$className}' should end with: " . implode(' or ', $suffixes),
                [
                    'current_name' => $className,
                    'expected_suffixes' => $suffixes,
                    'type' => $type
                ]
            );
        }
    }

    /**
     * Validate namespace
     */
    private function validateNamespace(string $content, array $rules, string $filePath, bool $strict): void
    {
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);
            $expectedPattern = $rules['namespace_pattern'];

            if (!str_starts_with($namespace, $expectedPattern)) {
                $this->addViolation(
                    'namespace_pattern',
                    $filePath,
                    "Namespace '{$namespace}' should start with '{$expectedPattern}'",
                    [
                        'current_namespace' => $namespace,
                        'expected_pattern' => $expectedPattern
                    ]
                );
            }

            // Check subnamespace organization for strict mode
            if ($strict && !empty($rules['allowed_subnamespaces'])) {
                $this->validateSubnamespace($namespace, $rules, $filePath);
            }
        } else {
            $this->addViolation(
                'missing_namespace',
                $filePath,
                'File is missing namespace declaration'
            );
        }
    }

    /**
     * Validate subnamespace organization
     */
    private function validateSubnamespace(string $namespace, array $rules, string $filePath): void
    {
        $basePattern = $rules['namespace_pattern'];
        $allowedSubnamespaces = $rules['allowed_subnamespaces'];

        if ($namespace === $basePattern) {
            // File is in root namespace, check if it should be in a subnamespace
            $this->addWarning(
                'subnamespace_organization',
                $filePath,
                "Consider organizing into subnamespaces: " . implode(', ', $allowedSubnamespaces)
            );
        } else {
            // File is in a subnamespace, validate it's allowed
            $subnamespace = str_replace($basePattern . '\\', '', $namespace);
            $topLevelSubnamespace = explode('\\', $subnamespace)[0];

            if (!in_array($topLevelSubnamespace, $allowedSubnamespaces)) {
                $this->addViolation(
                    'invalid_subnamespace',
                    $filePath,
                    "Subnamespace '{$topLevelSubnamespace}' is not allowed. Allowed: " . implode(', ', $allowedSubnamespaces),
                    [
                        'current_subnamespace' => $topLevelSubnamespace,
                        'allowed_subnamespaces' => $allowedSubnamespaces
                    ]
                );
            }
        }
    }

    /**
     * Validate file organization
     */
    private function validateFileOrganization($file, array $rules, string $filePath, bool $strict): void
    {
        if (!$strict) {
            return;
        }

        $directory = $file->getPath();
        $filesInDirectory = count(glob($directory . '/*.php'));

        if ($filesInDirectory > 15) {
            $this->addWarning(
                'directory_too_large',
                $filePath,
                "Directory contains {$filesInDirectory} files. Consider splitting into subdirectories (max recommended: 15)"
            );
        }
    }

    /**
     * Validate method names
     */
    private function validateMethodNames(string $content, string $filePath, bool $strict): void
    {
        if (!$strict) {
            return;
        }

        // Find all method declarations
        preg_match_all('/(?:public|private|protected)\s+function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $content, $matches);

        foreach ($matches[1] as $methodName) {
            // Skip magic methods and constructors
            if (str_starts_with($methodName, '__') || $methodName === 'boot' || $methodName === 'register') {
                continue;
            }

            // Check for forbidden generic names
            if (in_array($methodName, $this->forbiddenMethodNames)) {
                $this->addViolation(
                    'generic_method_name',
                    $filePath,
                    "Method '{$methodName}' uses a generic name. Use descriptive, action-oriented names.",
                    [
                        'method_name' => $methodName,
                        'forbidden_names' => $this->forbiddenMethodNames
                    ]
                );
            }

            // Check minimum length
            if (strlen($methodName) < 4) {
                $this->addWarning(
                    'short_method_name',
                    $filePath,
                    "Method '{$methodName}' is very short. Consider using more descriptive names."
                );
            }
        }
    }

    /**
     * Validate variable names
     */
    private function validateVariableNames(string $content, string $filePath): void
    {
        // Find variable assignments (simplified pattern)
        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=/', $content, $matches);

        foreach ($matches[1] as $variableName) {
            $fullVariableName = '$' . $variableName;

            if (in_array($fullVariableName, $this->forbiddenVariableNames)) {
                $this->addWarning(
                    'generic_variable_name',
                    $filePath,
                    "Variable '{$fullVariableName}' uses a generic name. Use descriptive camelCase names."
                );
            }
        }
    }

    /**
     * Add violation
     */
    private function addViolation(string $type, string $file, string $message, array $context = []): void
    {
        $this->violations[] = [
            'type' => $type,
            'file' => $file,
            'message' => $message,
            'context' => $context
        ];
        $this->stats['violations_found']++;
        $this->error("  ❌ {$file}: {$message}");
    }

    /**
     * Add warning
     */
    private function addWarning(string $type, string $file, string $message): void
    {
        $this->warnings[] = [
            'type' => $type,
            'file' => $file,
            'message' => $message
        ];
        $this->stats['warnings_found']++;
        $this->warn("  ⚠️  {$file}: {$message}");
    }

    /**
     * Apply automatic fixes
     */
    private function applyFixes(): void
    {
        $this->info('\n🔧 Applying automatic fixes...');

        foreach ($this->violations as $violation) {
            if ($this->canAutoFix($violation)) {
                if ($this->applyFix($violation)) {
                    $this->fixed[] = $violation;
                    $this->stats['fixes_applied']++;
                    $this->info("  ✅ Fixed: {$violation['file']}");
                }
            }
        }
    }

    /**
     * Check if violation can be auto-fixed
     */
    private function canAutoFix(array $violation): bool
    {
        return in_array($violation['type'], [
            'naming_suffix',
            'namespace_pattern'
        ]);
    }

    /**
     * Apply individual fix
     */
    private function applyFix(array $violation): bool
    {
        $filePath = base_path($violation['file']);
        
        if (!File::exists($filePath)) {
            return false;
        }

        $content = File::get($filePath);
        $originalContent = $content;

        switch ($violation['type']) {
            case 'naming_suffix':
                $content = $this->fixClassNameSuffix($content, $violation['context']);
                break;
            case 'namespace_pattern':
                $content = $this->fixNamespace($content, $violation['context']);
                break;
        }

        if ($content !== $originalContent) {
            File::put($filePath, $content);
            return true;
        }

        return false;
    }

    /**
     * Fix class name suffix
     */
    private function fixClassNameSuffix(string $content, array $context): string
    {
        $currentName = $context['current_name'];
        $expectedSuffixes = $context['expected_suffixes'];
        $suffix = $expectedSuffixes[0]; // Use first suffix as default
        
        $newName = $currentName . $suffix;
        
        // Replace class declaration
        $content = preg_replace(
            '/class\s+' . preg_quote($currentName) . '\b/',
            'class ' . $newName,
            $content
        );
        
        return $content;
    }

    /**
     * Fix namespace
     */
    private function fixNamespace(string $content, array $context): string
    {
        $currentNamespace = $context['current_namespace'];
        $expectedPattern = $context['expected_pattern'];
        
        // This is a simplified fix - in practice, you'd need more sophisticated logic
        $content = str_replace(
            "namespace {$currentNamespace};",
            "namespace {$expectedPattern};",
            $content
        );
        
        return $content;
    }

    /**
     * Generate detailed report
     */
    private function generateReport(): void
    {
        $reportPath = storage_path('logs/naming-standards-report.json');
        
        $report = [
            'timestamp' => now()->toISOString(),
            'stats' => $this->stats,
            'violations' => $this->violations,
            'warnings' => $this->warnings,
            'fixes_applied' => $this->fixed
        ];
        
        File::put($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        $this->info("\n📊 Detailed report saved to: {$reportPath}");
    }

    /**
     * Display summary
     */
    private function displaySummary(): void
    {
        $this->newLine();
        $this->info('📊 Validation Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Files Checked', $this->stats['files_checked']],
                ['Violations Found', $this->stats['violations_found']],
                ['Warnings Found', $this->stats['warnings_found']],
                ['Fixes Applied', $this->stats['fixes_applied']]
            ]
        );

        if ($this->stats['violations_found'] === 0) {
            $this->info('\n✅ All naming standards are compliant!');
        } else {
            $this->error('\n❌ Naming standard violations found. Run with --fix to apply automatic fixes.');
            $this->info('\n💡 For detailed guidelines, see: MODULAR_NAMING_STANDARDS.md');
        }
    }
}