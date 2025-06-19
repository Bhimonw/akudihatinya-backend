<?php

// Debug endpoint untuk test upload tanpa middleware
// Simpan file ini di public/ folder dan akses via browser

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$response = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown',
    'has_files' => !empty($_FILES),
    'files_count' => count($_FILES),
    'post_data' => $_POST,
    'files_data' => [],
    'php_config' => [
        'file_uploads' => ini_get('file_uploads') ? 'ON' : 'OFF',
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_file_uploads' => ini_get('max_file_uploads')
    ]
];

// Process uploaded files
foreach ($_FILES as $key => $file) {
    $fileInfo = [
        'field_name' => $key,
        'original_name' => $file['name'],
        'size' => $file['size'],
        'type' => $file['type'],
        'tmp_name' => $file['tmp_name'],
        'error' => $file['error'],
        'error_message' => ''
    ];
    
    // Get error message
    $errors = [
        UPLOAD_ERR_OK => 'No error',
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    
    $fileInfo['error_message'] = $errors[$file['error']] ?? 'Unknown error';
    
    // Get image info if it's an image
    if ($file['error'] === UPLOAD_ERR_OK && $file['tmp_name']) {
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo) {
            $fileInfo['image_info'] = [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'mime_type' => $imageInfo['mime'],
                'extension' => image_type_to_extension($imageInfo[2])
            ];
        }
        
        // Check if file exists and is readable
        $fileInfo['tmp_file_exists'] = file_exists($file['tmp_name']);
        $fileInfo['tmp_file_readable'] = is_readable($file['tmp_name']);
    }
    
    $response['files_data'][] = $fileInfo;
}

// Log to file for debugging
$logFile = __DIR__ . '/debug_upload.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . json_encode($response, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

echo json_encode($response, JSON_PRETTY_PRINT);