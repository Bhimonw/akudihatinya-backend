<?php

// Authentication routes
require __DIR__ . '/api/auth.php';

// User management routes
require __DIR__ . '/api/users.php';

// Profile management routes
require __DIR__ . '/api/profile.php';

// Dashboard routes
require __DIR__ . '/api/dashboard.php';

// Statistics routes (data retrieval)
require __DIR__ . '/api/statistics.php';

// Export and reporting routes
require __DIR__ . '/api/exports.php';

// Patient management routes
require __DIR__ . '/api/patients.php';

// Examination routes (HT & DM)
require __DIR__ . '/api/examinations.php';

// Admin-specific routes (yearly targets)
require __DIR__ . '/api/admin.php';
