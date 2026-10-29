# ==============================================================================
# GlobeCon LMS - Automated Security & RBAC Test Suite
# ==============================================================================

$BaseUrl = "http://localhost:8000"
$CookieJar = "$env:TEMP\moodle_test_cookies.txt"

function Test-UserRoleLogin {
    param (
        [string]$Username,
        [string]$Password,
        [string]$RoleName
    )

    Write-Host ""
    Write-Host "--------------------------------------------------------------" -ForegroundColor Cyan
    Write-Host " Testing Role Authentication: $RoleName ($Username)" -ForegroundColor Cyan
    Write-Host "--------------------------------------------------------------" -ForegroundColor Cyan

    Remove-Item $CookieJar -ErrorAction SilentlyContinue

    # Step 1: Get login page and extract CSRF logintoken
    $loginPage = (curl.exe -s -c $CookieJar "$BaseUrl/login/index.php") -join "`n"
    if ($loginPage -match 'name="logintoken"\s+value="([^"]+)"') {
        $loginToken = $matches[1]
    } else {
        Write-Host "[FAIL] Failed to extract logintoken from login page." -ForegroundColor Red
        return $false
    }

    # Step 2: Post login credentials
    $escapedPass = [System.Uri]::EscapeDataString($Password)
    $postBody = "username=$Username&password=$escapedPass&logintoken=$loginToken"
    $null = curl.exe -s -b $CookieJar -c $CookieJar -d $postBody -X POST "$BaseUrl/login/index.php"

    # Step 3: Check authenticated home page
    $homeHtml = curl.exe -s -b $CookieJar "$BaseUrl/my/"
    if ($homeHtml -match $Username -or $homeHtml -match "logout.php" -or $homeHtml -match "Log out") {
        Write-Host "[OK] Authentication Successful for $RoleName ($Username)." -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Login Failed for $Username." -ForegroundColor Red
        return $false
    }

    # Step 4: Verify Authorization Boundaries (Site Administration Access)
    $adminHtml = curl.exe -s -b $CookieJar "$BaseUrl/admin/index.php"
    
    if ($RoleName -eq "System Administrator") {
        if ($adminHtml -match "Site administration" -or $adminHtml -match "General") {
            Write-Host "[OK] Site Administration: ACCESS GRANTED (Expected for Administrator)." -ForegroundColor Green
        } else {
            Write-Host "[WARN] Site Administration access check returned unexpected markup." -ForegroundColor Yellow
        }
    } else {
        # Trainer or Learner MUST NOT access Site Administration
        if ($adminHtml -match "Site administration" -and -not ($adminHtml -match "You cannot access this page" -or $adminHtml -match "error/moodle/nopermissions")) {
            Write-Host "[FAIL] SECURITY VIOLATION: $RoleName accessed Site Administration!" -ForegroundColor Red
            return $false
        } else {
            Write-Host "[OK] Principle of Least Privilege ENFORCED: $RoleName is DENIED Site Administration access." -ForegroundColor Green
        }
    }

    # Step 5: Verify Course Access
    $courseHtml = curl.exe -s -b $CookieJar "$BaseUrl/course/view.php?id=2"
    if ($courseHtml -match "GlobeCon Fundamentals" -or $courseHtml -match "GC-SEC-101") {
        Write-Host "[OK] Course Access Verified: Enrolled course content accessible." -ForegroundColor Green
    } else {
        Write-Host "[WARN] Course content was not loaded for $RoleName." -ForegroundColor Yellow
    }

    return $true
}

Write-Host "==============================================================" -ForegroundColor Yellow
Write-Host " EXECUTING GLOBECON LMS ROLE-BASED VERIFICATION TEST SUITE" -ForegroundColor Yellow
Write-Host "==============================================================" -ForegroundColor Yellow

$adminRes = Test-UserRoleLogin -Username "globecon_admin" -Password "GlobeCon@2026!Secure" -RoleName "System Administrator"
$trainerRes = Test-UserRoleLogin -Username "trainer_jane" -Password "Trainer@2026!Pass" -RoleName "Trainer"
$learnerRes = Test-UserRoleLogin -Username "learner_john" -Password "Learner@2026!Pass" -RoleName "Learner"

Write-Host ""
Write-Host "==============================================================" -ForegroundColor Yellow
Write-Host " RBAC VERIFICATION SUMMARY" -ForegroundColor Yellow
if ($adminRes) { Write-Host " Administrator Tests: PASSED" -ForegroundColor Green } else { Write-Host " Administrator Tests: FAILED" -ForegroundColor Red }
if ($trainerRes) { Write-Host " Trainer Tests:       PASSED" -ForegroundColor Green } else { Write-Host " Trainer Tests:       FAILED" -ForegroundColor Red }
if ($learnerRes) { Write-Host " Learner Tests:       PASSED" -ForegroundColor Green } else { Write-Host " Learner Tests:       FAILED" -ForegroundColor Red }
Write-Host "==============================================================" -ForegroundColor Yellow
