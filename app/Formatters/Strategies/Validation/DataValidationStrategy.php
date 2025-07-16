<?php

namespace App\Formatters\Strategies\Validation;

use App\Formatters\Strategies\BaseFormatterStrategy;

/**
 * Strategy untuk validasi data
 * Menangani semua validasi input data, parameter, dan konsistensi data
 */
class DataValidationStrategy extends BaseFormatterStrategy
{
    protected string $name = 'DataValidationStrategy';

    protected array $defaultOptions = [
        'validation_type' => 'comprehensive', // comprehensive, basic, strict
        'check_data_consistency' => true,
        'check_required_fields' => true,
        'check_data_types' => true,
        'check_value_ranges' => true,
        'check_business_rules' => true,
        'allow_empty_data' => false,
        'required_fields' => [],
        'optional_fields' => [],
        'field_types' => [],
        'value_ranges' => [],
        'business_rules' => []
    ];

    /**
     * {@inheritdoc}
     */
    public function execute(array $data, array $options = []): array
    {
        $options = $this->mergeOptions($options);
        $this->validate($data, $options);

        $this->log('Starting data validation', [
            'data_count' => count($data),
            'validation_type' => $options['validation_type']
        ]);

        try {
            $validationResults = [];
            $errors = [];
            $warnings = [];
            $validItems = [];
            $invalidItems = [];

            foreach ($data as $index => $item) {
                $itemResult = $this->validateSingleItem($item, $index, $options);
                $validationResults[] = $itemResult;

                if ($itemResult['is_valid']) {
                    $validItems[] = $item;
                } else {
                    $invalidItems[] = [
                        'index' => $index,
                        'item' => $item,
                        'errors' => $itemResult['errors']
                    ];
                    $errors = array_merge($errors, $itemResult['errors']);
                }

                if (!empty($itemResult['warnings'])) {
                    $warnings = array_merge($warnings, $itemResult['warnings']);
                }
            }

            // Perform cross-item validations
            if ($options['check_data_consistency']) {
                $consistencyResults = $this->validateDataConsistency($data, $options);
                $errors = array_merge($errors, $consistencyResults['errors']);
                $warnings = array_merge($warnings, $consistencyResults['warnings']);
            }

            $isOverallValid = empty($errors) && (count($validItems) > 0 || $options['allow_empty_data']);

            return [
                'success' => true,
                'is_valid' => $isOverallValid,
                'validation_type' => $options['validation_type'],
                'summary' => [
                    'total_items' => count($data),
                    'valid_items' => count($validItems),
                    'invalid_items' => count($invalidItems),
                    'error_count' => count($errors),
                    'warning_count' => count($warnings)
                ],
                'results' => $validationResults,
                'errors' => $errors,
                'warnings' => $warnings,
                'valid_data' => $validItems,
                'invalid_data' => $invalidItems,
                'metadata' => [
                    'validated_at' => now()->toISOString(),
                    'validation_rules_applied' => $this->getAppliedRules($options)
                ]
            ];

        } catch (\Exception $e) {
            $this->log('Data validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \RuntimeException('Data validation failed: ' . $e->getMessage());
        }
    }

    /**
     * Validate single data item
     *
     * @param array $item Data item
     * @param int $index Item index
     * @param array $options Validation options
     * @return array Validation result
     */
    private function validateSingleItem(array $item, int $index, array $options): array
    {
        $errors = [];
        $warnings = [];
        $isValid = true;

        // Check required fields
        if ($options['check_required_fields']) {
            $fieldErrors = $this->validateRequiredFields($item, $index, $options);
            $errors = array_merge($errors, $fieldErrors);
        }

        // Check data types
        if ($options['check_data_types']) {
            $typeErrors = $this->validateDataTypes($item, $index, $options);
            $errors = array_merge($errors, $typeErrors);
        }

        // Check value ranges
        if ($options['check_value_ranges']) {
            $rangeResults = $this->validateValueRanges($item, $index, $options);
            $errors = array_merge($errors, $rangeResults['errors']);
            $warnings = array_merge($warnings, $rangeResults['warnings']);
        }

        // Check business rules
        if ($options['check_business_rules']) {
            $businessResults = $this->validateBusinessRules($item, $index, $options);
            $errors = array_merge($errors, $businessResults['errors']);
            $warnings = array_merge($warnings, $businessResults['warnings']);
        }

        // Validate specific data structures
        $structureResults = $this->validateDataStructures($item, $index, $options);
        $errors = array_merge($errors, $structureResults['errors']);
        $warnings = array_merge($warnings, $structureResults['warnings']);

        $isValid = empty($errors);

        return [
            'index' => $index,
            'is_valid' => $isValid,
            'errors' => $errors,
            'warnings' => $warnings,
            'validated_fields' => array_keys($item)
        ];
    }

    /**
     * Validate required fields
     *
     * @param array $item Data item
     * @param int $index Item index
     * @param array $options Validation options
     * @return array Errors
     */
    private function validateRequiredFields(array $item, int $index, array $options): array
    {
        $errors = [];
        $requiredFields = $options['required_fields'] ?? $this->getDefaultRequiredFields();

        foreach ($requiredFields as $field) {
            if (!isset($item[$field]) || $item[$field] === null || $item[$field] === '') {
                $errors[] = [
                    'type' => 'missing_required_field',
                    'field' => $field,
                    'index' => $index,
                    'message' => "Field '{$field}' is required but missing or empty"
                ];
            }
        }

        return $errors;
    }

    /**
     * Validate data types
     *
     * @param array $item Data item
     * @param int $index Item index
     * @param array $options Validation options
     * @return array Errors
     */
    private function validateDataTypes(array $item, int $index, array $options): array
    {
        $errors = [];
        $fieldTypes = $options['field_types'] ?? $this->getDefaultFieldTypes();

        foreach ($fieldTypes as $field => $expectedType) {
            if (!isset($item[$field])) {
                continue; // Skip if field doesn't exist (handled by required field validation)
            }

            $value = $item[$field];
            $actualType = gettype($value);
            $isValidType = $this->checkDataType($value, $expectedType);

            if (!$isValidType) {
                $errors[] = [
                    'type' => 'invalid_data_type',
                    'field' => $field,
                    'index' => $index,
                    'expected_type' => $expectedType,
                    'actual_type' => $actualType,
                    'value' => $value,
                    'message' => "Field '{$field}' expected type '{$expectedType}' but got '{$actualType}'"
                ];
            }
        }

        return $errors;
    }

    /**
     * Validate value ranges
     *
     * @param array $item Data item
     * @param int $index Item index
     * @param array $options Validation options
     * @return array Results with errors and warnings
     */
    private function validateValueRanges(array $item, int $index, array $options): array
    {
        $errors = [];
        $warnings = [];
        $valueRanges = $options['value_ranges'] ?? $this->getDefaultValueRanges();

        foreach ($valueRanges as $field => $range) {
            if (!isset($item[$field])) {
                continue;
            }

            $value = $item[$field];
            $rangeResult = $this->checkValueRange($value, $range, $field, $index);

            if ($rangeResult['type'] === 'error') {
                $errors[] = $rangeResult;
            } elseif ($rangeResult['type'] === 'warning') {
                $warnings[] = $rangeResult;
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate business rules
     *
     * @param array $item Data item
     * @param int $index Item index
     * @param array $options Validation options
     * @return array Results with errors and warnings
     */
    private function validateBusinessRules(array $item, int $index, array $options): array
    {
        $errors = [];
        $warnings = [];

        // Validate Puskesmas data
        $puskesmasResults = $this->validatePuskesmasData($item, $index);
        $errors = array_merge($errors, $puskesmasResults['errors']);
        $warnings = array_merge($warnings, $puskesmasResults['warnings']);

        // Validate disease data (HT/DM)
        $diseaseResults = $this->validateDiseaseData($item, $index);
        $errors = array_merge($errors, $diseaseResults['errors']);
        $warnings = array_merge($warnings, $diseaseResults['warnings']);

        // Validate target and achievement data
        $targetResults = $this->validateTargetData($item, $index);
        $errors = array_merge($errors, $targetResults['errors']);
        $warnings = array_merge($warnings, $targetResults['warnings']);

        // Validate date and time data
        $dateResults = $this->validateDateData($item, $index);
        $errors = array_merge($errors, $dateResults['errors']);
        $warnings = array_merge($warnings, $dateResults['warnings']);

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate data structures (HT/DM data format)
     *
     * @param array $item Data item
     * @param int $index Item index
     * @param array $options Validation options
     * @return array Results with errors and warnings
     */
    private function validateDataStructures(array $item, int $index, array $options): array
    {
        $errors = [];
        $warnings = [];

        // Validate HT data structure
        if (isset($item['ht']) || isset($item['ht_data'])) {
            $htData = $item['ht'] ?? $item['ht_data'] ?? [];
            $htResults = $this->validateDiseaseDataStructure($htData, 'ht', $index);
            $errors = array_merge($errors, $htResults['errors']);
            $warnings = array_merge($warnings, $htResults['warnings']);
        }

        // Validate DM data structure
        if (isset($item['dm']) || isset($item['dm_data'])) {
            $dmData = $item['dm'] ?? $item['dm_data'] ?? [];
            $dmResults = $this->validateDiseaseDataStructure($dmData, 'dm', $index);
            $errors = array_merge($errors, $dmResults['errors']);
            $warnings = array_merge($warnings, $dmResults['warnings']);
        }

        // Validate monthly data structure if present
        if (isset($item['monthly_data'])) {
            $monthlyResults = $this->validateMonthlyDataStructure($item['monthly_data'], $index);
            $errors = array_merge($errors, $monthlyResults['errors']);
            $warnings = array_merge($warnings, $monthlyResults['warnings']);
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate data consistency across items
     *
     * @param array $data All data items
     * @param array $options Validation options
     * @return array Results with errors and warnings
     */
    private function validateDataConsistency(array $data, array $options): array
    {
        $errors = [];
        $warnings = [];

        if (empty($data)) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Check for duplicate puskesmas IDs
        $duplicateResults = $this->checkDuplicatePuskesmas($data);
        $errors = array_merge($errors, $duplicateResults['errors']);
        $warnings = array_merge($warnings, $duplicateResults['warnings']);

        // Check data format consistency
        $formatResults = $this->checkDataFormatConsistency($data);
        $warnings = array_merge($warnings, $formatResults['warnings']);

        // Check target consistency
        $targetResults = $this->checkTargetConsistency($data);
        $warnings = array_merge($warnings, $targetResults['warnings']);

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Check data type
     *
     * @param mixed $value Value to check
     * @param string $expectedType Expected type
     * @return bool Is valid type
     */
    private function checkDataType($value, string $expectedType): bool
    {
        switch ($expectedType) {
            case 'string':
                return is_string($value);
            case 'integer':
            case 'int':
                return is_int($value) || (is_string($value) && ctype_digit($value));
            case 'float':
            case 'double':
                return is_float($value) || is_int($value) || (is_string($value) && is_numeric($value));
            case 'numeric':
                return is_numeric($value);
            case 'boolean':
            case 'bool':
                return is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true);
            case 'array':
                return is_array($value);
            case 'object':
                return is_object($value) || is_array($value);
            case 'null':
                return is_null($value);
            default:
                return true; // Unknown type, assume valid
        }
    }

    /**
     * Check value range
     *
     * @param mixed $value Value to check
     * @param array $range Range configuration
     * @param string $field Field name
     * @param int $index Item index
     * @return array Result
     */
    private function checkValueRange($value, array $range, string $field, int $index): array
    {
        if (!is_numeric($value)) {
            return [
                'type' => 'error',
                'field' => $field,
                'index' => $index,
                'value' => $value,
                'message' => "Field '{$field}' must be numeric for range validation"
            ];
        }

        $numericValue = (float) $value;

        // Check minimum value
        if (isset($range['min']) && $numericValue < $range['min']) {
            $severity = $range['min_severity'] ?? 'error';
            return [
                'type' => $severity,
                'field' => $field,
                'index' => $index,
                'value' => $value,
                'min' => $range['min'],
                'message' => "Field '{$field}' value {$value} is below minimum {$range['min']}"
            ];
        }

        // Check maximum value
        if (isset($range['max']) && $numericValue > $range['max']) {
            $severity = $range['max_severity'] ?? 'error';
            return [
                'type' => $severity,
                'field' => $field,
                'index' => $index,
                'value' => $value,
                'max' => $range['max'],
                'message' => "Field '{$field}' value {$value} is above maximum {$range['max']}"
            ];
        }

        // Check warning thresholds
        if (isset($range['warn_min']) && $numericValue < $range['warn_min']) {
            return [
                'type' => 'warning',
                'field' => $field,
                'index' => $index,
                'value' => $value,
                'warn_min' => $range['warn_min'],
                'message' => "Field '{$field}' value {$value} is below recommended minimum {$range['warn_min']}"
            ];
        }

        if (isset($range['warn_max']) && $numericValue > $range['warn_max']) {
            return [
                'type' => 'warning',
                'field' => $field,
                'index' => $index,
                'value' => $value,
                'warn_max' => $range['warn_max'],
                'message' => "Field '{$field}' value {$value} is above recommended maximum {$range['warn_max']}"
            ];
        }

        return ['type' => 'valid'];
    }

    /**
     * Validate Puskesmas data
     *
     * @param array $item Data item
     * @param int $index Item index
     * @return array Results
     */
    private function validatePuskesmasData(array $item, int $index): array
    {
        $errors = [];
        $warnings = [];

        // Check Puskesmas ID
        $puskesmasId = $item['puskesmas_id'] ?? $item['id'] ?? null;
        if ($puskesmasId !== null) {
            if (!is_numeric($puskesmasId) || $puskesmasId <= 0) {
                $errors[] = [
                    'type' => 'invalid_puskesmas_id',
                    'field' => 'puskesmas_id',
                    'index' => $index,
                    'value' => $puskesmasId,
                    'message' => 'Puskesmas ID must be a positive integer'
                ];
            }
        }

        // Check Puskesmas name
        $puskesmasName = $item['puskesmas_name'] ?? $item['nama_puskesmas'] ?? null;
        if ($puskesmasName !== null) {
            if (!is_string($puskesmasName) || trim($puskesmasName) === '') {
                $errors[] = [
                    'type' => 'invalid_puskesmas_name',
                    'field' => 'puskesmas_name',
                    'index' => $index,
                    'value' => $puskesmasName,
                    'message' => 'Puskesmas name must be a non-empty string'
                ];
            } elseif (strlen(trim($puskesmasName)) < 3) {
                $warnings[] = [
                    'type' => 'short_puskesmas_name',
                    'field' => 'puskesmas_name',
                    'index' => $index,
                    'value' => $puskesmasName,
                    'message' => 'Puskesmas name is very short (less than 3 characters)'
                ];
            }
        }

        // Check Puskesmas code
        $puskesmasCode = $item['code'] ?? $item['kode_puskesmas'] ?? null;
        if ($puskesmasCode !== null) {
            if (!is_string($puskesmasCode) || !preg_match('/^[A-Z0-9]{2,10}$/', $puskesmasCode)) {
                $warnings[] = [
                    'type' => 'invalid_puskesmas_code_format',
                    'field' => 'code',
                    'index' => $index,
                    'value' => $puskesmasCode,
                    'message' => 'Puskesmas code should be 2-10 alphanumeric characters'
                ];
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate disease data (HT/DM)
     *
     * @param array $item Data item
     * @param int $index Item index
     * @return array Results
     */
    private function validateDiseaseData(array $item, int $index): array
    {
        $errors = [];
        $warnings = [];

        // Check if at least one disease type is present
        $hasHt = isset($item['ht']) || isset($item['ht_data']);
        $hasDm = isset($item['dm']) || isset($item['dm_data']);

        if (!$hasHt && !$hasDm) {
            $warnings[] = [
                'type' => 'no_disease_data',
                'index' => $index,
                'message' => 'No HT or DM data found in item'
            ];
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate target data
     *
     * @param array $item Data item
     * @param int $index Item index
     * @return array Results
     */
    private function validateTargetData(array $item, int $index): array
    {
        $errors = [];
        $warnings = [];

        // Validate HT targets
        if (isset($item['ht']) || isset($item['ht_data'])) {
            $htData = $item['ht'] ?? $item['ht_data'] ?? [];
            $htResults = $this->validateDiseaseTargets($htData, 'ht', $index);
            $errors = array_merge($errors, $htResults['errors']);
            $warnings = array_merge($warnings, $htResults['warnings']);
        }

        // Validate DM targets
        if (isset($item['dm']) || isset($item['dm_data'])) {
            $dmData = $item['dm'] ?? $item['dm_data'] ?? [];
            $dmResults = $this->validateDiseaseTargets($dmData, 'dm', $index);
            $errors = array_merge($errors, $dmResults['errors']);
            $warnings = array_merge($warnings, $dmResults['warnings']);
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate disease targets
     *
     * @param array $diseaseData Disease data
     * @param string $diseaseType Disease type (ht/dm)
     * @param int $index Item index
     * @return array Results
     */
    private function validateDiseaseTargets(array $diseaseData, string $diseaseType, int $index): array
    {
        $errors = [];
        $warnings = [];

        $target = $diseaseData['target'] ?? 0;
        $totalPatients = $diseaseData['total_patients'] ?? $diseaseData['total'] ?? 0;
        $standardPatients = $diseaseData['standard_patients'] ?? $diseaseData['standard'] ?? 0;

        // Validate target
        if ($target < 0) {
            $errors[] = [
                'type' => 'negative_target',
                'field' => $diseaseType . '.target',
                'index' => $index,
                'value' => $target,
                'message' => "Target for {$diseaseType} cannot be negative"
            ];
        }

        // Validate total patients
        if ($totalPatients < 0) {
            $errors[] = [
                'type' => 'negative_total_patients',
                'field' => $diseaseType . '.total_patients',
                'index' => $index,
                'value' => $totalPatients,
                'message' => "Total patients for {$diseaseType} cannot be negative"
            ];
        }

        // Validate standard patients
        if ($standardPatients < 0) {
            $errors[] = [
                'type' => 'negative_standard_patients',
                'field' => $diseaseType . '.standard_patients',
                'index' => $index,
                'value' => $standardPatients,
                'message' => "Standard patients for {$diseaseType} cannot be negative"
            ];
        }

        // Business rule: standard patients should not exceed total patients
        if ($standardPatients > $totalPatients) {
            $errors[] = [
                'type' => 'standard_exceeds_total',
                'field' => $diseaseType . '.standard_patients',
                'index' => $index,
                'standard' => $standardPatients,
                'total' => $totalPatients,
                'message' => "Standard patients ({$standardPatients}) cannot exceed total patients ({$totalPatients}) for {$diseaseType}"
            ];
        }

        // Warning: total patients significantly exceeds target
        if ($target > 0 && $totalPatients > ($target * 2)) {
            $warnings[] = [
                'type' => 'total_exceeds_target_significantly',
                'field' => $diseaseType . '.total_patients',
                'index' => $index,
                'total' => $totalPatients,
                'target' => $target,
                'message' => "Total patients ({$totalPatients}) significantly exceeds target ({$target}) for {$diseaseType}"
            ];
        }

        // Warning: very low achievement
        if ($target > 0 && $totalPatients < ($target * 0.1)) {
            $warnings[] = [
                'type' => 'very_low_achievement',
                'field' => $diseaseType . '.total_patients',
                'index' => $index,
                'total' => $totalPatients,
                'target' => $target,
                'achievement' => round(($totalPatients / $target) * 100, 2),
                'message' => "Very low achievement for {$diseaseType}: " . round(($totalPatients / $target) * 100, 2) . '%'
            ];
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate date data
     *
     * @param array $item Data item
     * @param int $index Item index
     * @return array Results
     */
    private function validateDateData(array $item, int $index): array
    {
        $errors = [];
        $warnings = [];

        // Validate year
        if (isset($item['year'])) {
            $year = $item['year'];
            if (!is_numeric($year) || $year < 2020 || $year > (date('Y') + 1)) {
                $errors[] = [
                    'type' => 'invalid_year',
                    'field' => 'year',
                    'index' => $index,
                    'value' => $year,
                    'message' => 'Year must be between 2020 and ' . (date('Y') + 1)
                ];
            }
        }

        // Validate month
        if (isset($item['month'])) {
            $month = $item['month'];
            if (!is_numeric($month) || $month < 1 || $month > 12) {
                $errors[] = [
                    'type' => 'invalid_month',
                    'field' => 'month',
                    'index' => $index,
                    'value' => $month,
                    'message' => 'Month must be between 1 and 12'
                ];
            }
        }

        // Validate quarter
        if (isset($item['quarter'])) {
            $quarter = $item['quarter'];
            if (!is_numeric($quarter) || $quarter < 1 || $quarter > 4) {
                $errors[] = [
                    'type' => 'invalid_quarter',
                    'field' => 'quarter',
                    'index' => $index,
                    'value' => $quarter,
                    'message' => 'Quarter must be between 1 and 4'
                ];
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate disease data structure
     *
     * @param array $diseaseData Disease data
     * @param string $diseaseType Disease type
     * @param int $index Item index
     * @return array Results
     */
    private function validateDiseaseDataStructure(array $diseaseData, string $diseaseType, int $index): array
    {
        $errors = [];
        $warnings = [];

        $expectedFields = ['target', 'total_patients', 'standard_patients'];
        $alternativeFields = [
            'total_patients' => ['total'],
            'standard_patients' => ['standard']
        ];

        foreach ($expectedFields as $field) {
            $hasField = isset($diseaseData[$field]);
            $hasAlternative = false;

            if (!$hasField && isset($alternativeFields[$field])) {
                foreach ($alternativeFields[$field] as $altField) {
                    if (isset($diseaseData[$altField])) {
                        $hasAlternative = true;
                        break;
                    }
                }
            }

            if (!$hasField && !$hasAlternative) {
                $warnings[] = [
                    'type' => 'missing_disease_field',
                    'field' => $diseaseType . '.' . $field,
                    'index' => $index,
                    'disease_type' => $diseaseType,
                    'message' => "Missing field '{$field}' in {$diseaseType} data"
                ];
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate monthly data structure
     *
     * @param array $monthlyData Monthly data
     * @param int $index Item index
     * @return array Results
     */
    private function validateMonthlyDataStructure(array $monthlyData, int $index): array
    {
        $errors = [];
        $warnings = [];

        if (!is_array($monthlyData)) {
            $errors[] = [
                'type' => 'invalid_monthly_data_structure',
                'field' => 'monthly_data',
                'index' => $index,
                'message' => 'Monthly data must be an array'
            ];
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        foreach ($monthlyData as $month => $data) {
            if (!is_numeric($month) || $month < 1 || $month > 12) {
                $warnings[] = [
                    'type' => 'invalid_month_key',
                    'field' => 'monthly_data',
                    'index' => $index,
                    'month' => $month,
                    'message' => "Invalid month key '{$month}' in monthly data"
                ];
                continue;
            }

            if (!is_array($data)) {
                $warnings[] = [
                    'type' => 'invalid_month_data_structure',
                    'field' => 'monthly_data.' . $month,
                    'index' => $index,
                    'month' => $month,
                    'message' => "Data for month {$month} must be an array"
                ];
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Check for duplicate Puskesmas
     *
     * @param array $data All data
     * @return array Results
     */
    private function checkDuplicatePuskesmas(array $data): array
    {
        $errors = [];
        $warnings = [];
        $seenIds = [];
        $seenNames = [];

        foreach ($data as $index => $item) {
            $puskesmasId = $item['puskesmas_id'] ?? $item['id'] ?? null;
            $puskesmasName = $item['puskesmas_name'] ?? $item['nama_puskesmas'] ?? null;

            // Check duplicate IDs
            if ($puskesmasId !== null) {
                if (isset($seenIds[$puskesmasId])) {
                    $errors[] = [
                        'type' => 'duplicate_puskesmas_id',
                        'field' => 'puskesmas_id',
                        'index' => $index,
                        'duplicate_index' => $seenIds[$puskesmasId],
                        'value' => $puskesmasId,
                        'message' => "Duplicate Puskesmas ID {$puskesmasId} found at indices {$seenIds[$puskesmasId]} and {$index}"
                    ];
                } else {
                    $seenIds[$puskesmasId] = $index;
                }
            }

            // Check duplicate names
            if ($puskesmasName !== null) {
                $normalizedName = strtolower(trim($puskesmasName));
                if (isset($seenNames[$normalizedName])) {
                    $warnings[] = [
                        'type' => 'duplicate_puskesmas_name',
                        'field' => 'puskesmas_name',
                        'index' => $index,
                        'duplicate_index' => $seenNames[$normalizedName],
                        'value' => $puskesmasName,
                        'message' => "Duplicate Puskesmas name '{$puskesmasName}' found at indices {$seenNames[$normalizedName]} and {$index}"
                    ];
                } else {
                    $seenNames[$normalizedName] = $index;
                }
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Check data format consistency
     *
     * @param array $data All data
     * @return array Results
     */
    private function checkDataFormatConsistency(array $data): array
    {
        $warnings = [];

        if (empty($data)) {
            return ['warnings' => $warnings];
        }

        // Check field name consistency
        $firstItem = reset($data);
        $expectedFields = array_keys($firstItem);

        foreach ($data as $index => $item) {
            $itemFields = array_keys($item);
            $missingFields = array_diff($expectedFields, $itemFields);
            $extraFields = array_diff($itemFields, $expectedFields);

            if (!empty($missingFields)) {
                $warnings[] = [
                    'type' => 'inconsistent_fields_missing',
                    'index' => $index,
                    'missing_fields' => $missingFields,
                    'message' => 'Item missing fields: ' . implode(', ', $missingFields)
                ];
            }

            if (!empty($extraFields)) {
                $warnings[] = [
                    'type' => 'inconsistent_fields_extra',
                    'index' => $index,
                    'extra_fields' => $extraFields,
                    'message' => 'Item has extra fields: ' . implode(', ', $extraFields)
                ];
            }
        }

        return ['warnings' => $warnings];
    }

    /**
     * Check target consistency
     *
     * @param array $data All data
     * @return array Results
     */
    private function checkTargetConsistency(array $data): array
    {
        $warnings = [];
        $htTargets = [];
        $dmTargets = [];

        // Collect all targets
        foreach ($data as $index => $item) {
            if (isset($item['ht']['target']) || isset($item['ht_data']['target'])) {
                $htTarget = $item['ht']['target'] ?? $item['ht_data']['target'] ?? 0;
                $htTargets[$index] = $htTarget;
            }

            if (isset($item['dm']['target']) || isset($item['dm_data']['target'])) {
                $dmTarget = $item['dm']['target'] ?? $item['dm_data']['target'] ?? 0;
                $dmTargets[$index] = $dmTarget;
            }
        }

        // Check for unusual target variations
        if (count($htTargets) > 1) {
            $uniqueHtTargets = array_unique($htTargets);
            if (count($uniqueHtTargets) > 3) {
                $warnings[] = [
                    'type' => 'inconsistent_ht_targets',
                    'message' => 'HT targets vary significantly across Puskesmas (' . count($uniqueHtTargets) . ' different values)',
                    'unique_targets' => $uniqueHtTargets
                ];
            }
        }

        if (count($dmTargets) > 1) {
            $uniqueDmTargets = array_unique($dmTargets);
            if (count($uniqueDmTargets) > 3) {
                $warnings[] = [
                    'type' => 'inconsistent_dm_targets',
                    'message' => 'DM targets vary significantly across Puskesmas (' . count($uniqueDmTargets) . ' different values)',
                    'unique_targets' => $uniqueDmTargets
                ];
            }
        }

        return ['warnings' => $warnings];
    }

    /**
     * Get default required fields
     *
     * @return array Required fields
     */
    private function getDefaultRequiredFields(): array
    {
        return [
            'puskesmas_id',
            'puskesmas_name'
        ];
    }

    /**
     * Get default field types
     *
     * @return array Field types
     */
    private function getDefaultFieldTypes(): array
    {
        return [
            'puskesmas_id' => 'integer',
            'puskesmas_name' => 'string',
            'code' => 'string',
            'year' => 'integer',
            'month' => 'integer',
            'quarter' => 'integer'
        ];
    }

    /**
     * Get default value ranges
     *
     * @return array Value ranges
     */
    private function getDefaultValueRanges(): array
    {
        return [
            'year' => [
                'min' => 2020,
                'max' => date('Y') + 1,
                'min_severity' => 'error',
                'max_severity' => 'error'
            ],
            'month' => [
                'min' => 1,
                'max' => 12,
                'min_severity' => 'error',
                'max_severity' => 'error'
            ],
            'quarter' => [
                'min' => 1,
                'max' => 4,
                'min_severity' => 'error',
                'max_severity' => 'error'
            ]
        ];
    }

    /**
     * Get applied validation rules
     *
     * @param array $options Validation options
     * @return array Applied rules
     */
    private function getAppliedRules(array $options): array
    {
        $rules = [];

        if ($options['check_required_fields']) {
            $rules[] = 'required_fields';
        }
        if ($options['check_data_types']) {
            $rules[] = 'data_types';
        }
        if ($options['check_value_ranges']) {
            $rules[] = 'value_ranges';
        }
        if ($options['check_business_rules']) {
            $rules[] = 'business_rules';
        }
        if ($options['check_data_consistency']) {
            $rules[] = 'data_consistency';
        }

        return $rules;
    }

    /**
     * Validate specific disease type
     *
     * @param string $diseaseType Disease type
     * @return bool Is valid
     */
    public function validateDiseaseType(string $diseaseType): bool
    {
        return in_array($diseaseType, ['ht', 'dm', 'all']);
    }

    /**
     * Validate year range
     *
     * @param int $year Year
     * @return bool Is valid
     */
    public function validateYear(int $year): bool
    {
        return $year >= 2020 && $year <= (date('Y') + 1);
    }

    /**
     * Validate month range
     *
     * @param int $month Month
     * @return bool Is valid
     */
    public function validateMonth(int $month): bool
    {
        return $month >= 1 && $month <= 12;
    }

    /**
     * Validate quarter range
     *
     * @param int $quarter Quarter
     * @return bool Is valid
     */
    public function validateQuarter(int $quarter): bool
    {
        return $quarter >= 1 && $quarter <= 4;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $data, array $options = []): bool
    {
        parent::validate($data, $options);

        // Validate validation type
        $validTypes = ['comprehensive', 'basic', 'strict'];
        if (isset($options['validation_type']) && !in_array($options['validation_type'], $validTypes)) {
            throw new \InvalidArgumentException('Invalid validation type. Must be one of: ' . implode(', ', $validTypes));
        }

        // Validate required fields if provided
        if (isset($options['required_fields']) && !is_array($options['required_fields'])) {
            throw new \InvalidArgumentException('Required fields must be an array');
        }

        // Validate field types if provided
        if (isset($options['field_types']) && !is_array($options['field_types'])) {
            throw new \InvalidArgumentException('Field types must be an array');
        }

        // Validate value ranges if provided
        if (isset($options['value_ranges']) && !is_array($options['value_ranges'])) {
            throw new \InvalidArgumentException('Value ranges must be an array');
        }

        return true;
    }
}