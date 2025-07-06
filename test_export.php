<?php

// Simple test to check if Laravel server is running and routes work
function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_VERBOSE, false);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'response' => $response,
        'http_code' => $httpCode,
        'content_type' => $contentType,
        'error' => $error
    ];
}

echo "=== Laravel Server Test ===\n";

// Test 1: Check if server is running
echo "1. Testing server base...\n";
$result = makeRequest('http://localhost:8000');
if ($result['error']) {
    echo "   ERROR: {$result['error']}\n";
    exit(1);
}
echo "   HTTP {$result['http_code']} - Content-Type: {$result['content_type']}\n";

// Test 2: Check route list command
echo "\n2. Checking available routes...\n";
exec('php artisan route:list --path=api/auth 2>&1', $output, $returnCode);
if ($returnCode === 0) {
    echo "   Routes found:\n";
    foreach ($output as $line) {
        if (strpos($line, 'api/auth') !== false) {
            echo "   $line\n";
        }
    }
} else {
    echo "   Error getting routes: " . implode("\n", $output) . "\n";
}

// Test 3: Try login with POST
echo "\n3. Testing login endpoint...\n";
$loginData = json_encode([
    'username' => 'admin',
    'password' => 'password'
]);

$result = makeRequest(
    'http://localhost:8000/api/auth/login',
    'POST',
    $loginData,
    [
        'Content-Type: application/json',
        'Accept: application/json'
    ]
);

echo "   HTTP {$result['http_code']}\n";
echo "   Content-Type: {$result['content_type']}\n";

if ($result['error']) {
    echo "   CURL Error: {$result['error']}\n";
} else {
    echo "   Response: " . substr($result['response'], 0, 300) . "...\n";
    
    // Try to decode JSON
    $json = json_decode($result['response'], true);
    if ($json) {
        echo "   JSON Response: " . print_r($json, true) . "\n";
    }
}

echo "\n=== Test Complete ===\n";