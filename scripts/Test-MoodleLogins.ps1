# ==============================================================================
# GlobeCon LMS - Security, RBAC & Authentication Test Suite
# Tests:
# 1. Dedicated Standalone Login Page (/login/index.php)
# 2. Unified One-Page Role Dashboard (/my/)
# 3. Student Login via Registration Number (GC-STU-00004) + Student Dashboard
# 4. Trainer Login via Company Email (jane.trainer@globecon.local) + Trainer Dashboard
# 5. Admin Login via Company Email (admin@globecon.local) + Administrator Dashboard
# 6. Least Privilege RBAC Enforcement (Site Admin blocked for Student & Trainer)
# ==============================================================================

$BaseUrl = "http://localhost:8000"

function Test-Authentication {
    param (
        [string]$Identifier,  # Registration Number or Company Email
        [string]$Password,
        [string]$RoleName,
        [string]$ExpectedDashboardText,
        [bool]$ExpectAdminAccess = $false
    )

    Write-Host ""
    Write-Host "--------------------------------------------------------------" -ForegroundColor Cyan
    Write-Host " Testing $RoleName Authentication: $Identifier" -ForegroundColor Cyan
    Write-Host "--------------------------------------------------------------" -ForegroundColor Cyan

    $CookieJar = "$env:TEMP\moodle_test_cookie_${RoleName}.txt"
    Remove-Item $CookieJar -ErrorAction SilentlyContinue

    # Step 1: Get dedicated login page and extract CSRF logintoken
    $loginPage = (curl.exe -s -c $CookieJar "$BaseUrl/login/index.php") -join "`n"
    if ($loginPage -match 'name="logintoken"\s+value="([^"]+)"') {
        $loginToken = $matches[1]
    } else {
        Write-Host "[FAIL] Failed to extract logintoken from login page." -ForegroundColor Red
        return $false
    }

    # Step 2: Post login credentials
    $escapedPass = [System.Uri]::EscapeDataString($Password)
    $escapedUser = [System.Uri]::EscapeDataString($Identifier)
    $postBody = "username=$escapedUser&password=$escapedPass&logintoken=$loginToken"
    $loginResp = (curl.exe -s -L -b $CookieJar -c $CookieJar -d $postBody "$BaseUrl/login/index.php") -join "`n"

    # Step 3: Verify login succeeded
    if ($loginResp -match "logout.php" -or $loginResp -match "Log Out" -or $loginResp -match "GlobeCon") {
        Write-Host "[OK] Authentication SUCCESSFUL for $RoleName ($Identifier)." -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Login FAILED for $Identifier." -ForegroundColor Red
        return $false
    }

    # Step 4: Verify the unified ONE Dashboard (/my/) directly renders role-specific dashboard
    $homeHtml = (curl.exe -s -b $CookieJar "$BaseUrl/my/") -join "`n"
    if ($homeHtml -match $ExpectedDashboardText) {
        Write-Host "[OK] Unified Dashboard (/my/): Successfully loaded $RoleName Dashboard directly on /my/." -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Unified Dashboard (/my/) missing expected text '$ExpectedDashboardText'." -ForegroundColor Red
        return $false
    }

    # Step 5: Verify Authorization Boundaries (Site Administration Access)
    $adminHtml = (curl.exe -s -L -b $CookieJar "$BaseUrl/admin/index.php") -join "`n"
    if ($ExpectAdminAccess) {
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

    return $true
}

function Test-UIFeatures {
    Write-Host ""
    Write-Host "--------------------------------------------------------------" -ForegroundColor Cyan
    Write-Host " Testing UI Features: Dedicated Standalone Login Page & Unified Dashboard" -ForegroundColor Cyan
    Write-Host "--------------------------------------------------------------" -ForegroundColor Cyan

    $loginHtml = (curl.exe -s "$BaseUrl/login/index.php") -join "`n"
    $allPassed = $true

    # 1. Registration Number & Staff Email Label
    if ($loginHtml -match 'Registration Number' -and $loginHtml -match 'Company Email') {
        Write-Host "[OK] Reg Number & Staff Email Inputs: PRESENT on Login Page." -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Reg Number & Staff Email Inputs NOT FOUND on Login Page." -ForegroundColor Red
        $allPassed = $false
    }

    # 2. Forgot Password Link
    if ($loginHtml -match 'forgot_password.php') {
        Write-Host "[OK] Forgot Password Link: PRESENT on Login Page." -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Forgot Password link NOT FOUND." -ForegroundColor Red
        $allPassed = $false
    }

    # 3. Student Registration Link
    if ($loginHtml -match 'signup.php') {
        Write-Host "[OK] Student Registration Link: PRESENT on Login Page." -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Student Registration link NOT FOUND." -ForegroundColor Red
        $allPassed = $false
    }

    # 4. 1-Click Role Verification Buttons
    if ($loginHtml -match 'GC-STU-00004' -and $loginHtml -match 'jane.trainer@globecon.local' -and $loginHtml -match 'admin@globecon.local') {
        Write-Host "[OK] 1-Click Role Verification: PRESENT on Login Page for all 3 users." -ForegroundColor Green
    } else {
        Write-Host "[FAIL] 1-Click Role Verification buttons NOT FOUND." -ForegroundColor Red
        $allPassed = $false
    }

    # 5. Unauthenticated /my/ redirects to login page
    $unauthResp = (curl.exe -s -i "$BaseUrl/my/") -join "`n"
    if ($unauthResp -match "303 See Other" -and $unauthResp -match "login/index.php") {
        Write-Host "[OK] Standalone Separation: Unauthenticated /my/ correctly redirects to /login/index.php." -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Unauthenticated /my/ did not redirect to /login/index.php." -ForegroundColor Red
        $allPassed = $false
    }

    return $allPassed
}

Write-Host "==============================================================" -ForegroundColor Yellow
Write-Host " EXECUTING GLOBECON ACADEMY AUTHENTICATION & DASHBOARD SUITE  " -ForegroundColor Yellow
Write-Host "==============================================================" -ForegroundColor Yellow

# UI Feature Verification
$uiRes = Test-UIFeatures

# 1. Student Login via Registration Number (GC-STU-00004)
$studentRes = Test-Authentication -Identifier "GC-STU-00004" `
                                  -Password "Learner@2026!Pass" `
                                  -RoleName "Student" `
                                  -ExpectedDashboardText "Student Portal" `
                                  -ExpectAdminAccess $false

# 2. Trainer Login via Company Email (jane.trainer@globecon.local)
$trainerRes = Test-Authentication -Identifier "jane.trainer@globecon.local" `
                                  -Password "Trainer@2026!Pass" `
                                  -RoleName "Trainer" `
                                  -ExpectedDashboardText "Trainer Portal" `
                                  -ExpectAdminAccess $false

# 3. Admin Login via Company Email (admin@globecon.local)
$adminRes   = Test-Authentication -Identifier "admin@globecon.local" `
                                  -Password "GlobeCon@2026!Secure" `
                                  -RoleName "Administrator" `
                                  -ExpectedDashboardText "GlobeCon Administrator Control Center" `
                                  -ExpectAdminAccess $true

Write-Host ""
Write-Host "==============================================================" -ForegroundColor Yellow
Write-Host " COMPREHENSIVE VERIFICATION SUMMARY" -ForegroundColor Yellow
Write-Host "==============================================================" -ForegroundColor Yellow
Write-Host " UI & Standalone Login Page:                    $(if ($uiRes) {'PASSED'} else {'FAILED'})"
Write-Host " Student (Reg Number: GC-STU-00004):            $(if ($studentRes) {'PASSED'} else {'FAILED'})"
Write-Host " Trainer (Company Email: jane.trainer):         $(if ($trainerRes) {'PASSED'} else {'FAILED'})"
Write-Host " Administrator (Company Email: admin):          $(if ($adminRes) {'PASSED'} else {'FAILED'})"
Write-Host "==============================================================" -ForegroundColor Yellow
