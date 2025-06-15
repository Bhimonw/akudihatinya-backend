# Test script for puskesmas export endpoint with authentication

# Step 1: Login to get token
$loginUri = 'http://localhost:8000/api/login'
$loginData = @{
    username = 'admin'
    password = 'password'
} | ConvertTo-Json

$loginHeaders = @{
    'Content-Type' = 'application/json'
    'Accept'       = 'application/json'
}

Write-Host "Step 1: Logging in..."
try {
    $loginResponse = Invoke-WebRequest -Uri $loginUri -Method POST -Body $loginData -Headers $loginHeaders
    $responseObj = $loginResponse.Content | ConvertFrom-Json
    $token = $responseObj.access_token
    Write-Host "Login successful, token obtained: $($token.Substring(0, 20))..."
    
    # Step 2: Test export endpoint
    Write-Host "Step 2: Testing export endpoint..."
    $exportUri = 'http://localhost:8000/api/statistics/export?table_type=puskesmas&year=2025&disease_type=dm&format=excel'
    $exportHeaders = @{
        'Authorization' = "Bearer $token"
        'Accept'        = 'application/json'
    }
    
    $exportResponse = Invoke-WebRequest -Uri $exportUri -Method GET -Headers $exportHeaders
    Write-Host "Export Success - Status: $($exportResponse.StatusCode)"
    Write-Host "Content-Type: $($exportResponse.Headers['Content-Type'])"
    
    if ($exportResponse.Headers['Content-Length']) {
        Write-Host "Content-Length: $($exportResponse.Headers['Content-Length'])"
    }
    
    # Check if it's an Excel file
    if ($exportResponse.Headers['Content-Type'] -like '*excel*' -or $exportResponse.Headers['Content-Type'] -like '*spreadsheet*') {
        Write-Host "SUCCESS: Excel file returned"
    }
    else {
        Write-Host "WARNING: Not an Excel file. Content-Type: $($exportResponse.Headers['Content-Type'])"
        Write-Host "Response content (first 500 chars): $($exportResponse.Content.Substring(0, [Math]::Min(500, $exportResponse.Content.Length)))"
    }
    
}
catch {
    Write-Host "Error: $($_.Exception.Message)"
    if ($_.Exception.Response) {
        Write-Host "Status Code: $($_.Exception.Response.StatusCode)"
        try {
            $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
            $responseBody = $reader.ReadToEnd()
            Write-Host "Response Body: $responseBody"
        }
        catch {
            Write-Host "Could not read response body"
        }
    }
}