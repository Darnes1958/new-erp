#Requires -Version 5.1
<#
.SYNOPSIS
  Production deploy for Laravel/Filament on Windows IIS (Plesk).

.DESCRIPTION
  Root cause of "Access is denied" on bootstrap\cache\packages.php:
  IIS PHP workers keep packages.php / services.php open. Laravel's
  package:discover tries rename(tmp -> packages.php) and Windows blocks it.

  This script:
  1) maintenance mode
  2) stops the IIS site / app pool (releases file locks)
  3) clears bootstrap\cache safely
  4) composer install (NOT update)
  5) artisan optimize + filament:upgrade
  6) optional npm build
  7) starts site / app pool again

.EXAMPLE
  .\scripts\deploy-windows.ps1 -SiteName "alwaseetonly.ly" -AppPoolName "alwaseetonly.ly(domain)(4.0)(pool)"

.EXAMPLE
  .\scripts\deploy-windows.ps1 -SiteName "alwaseetonly.ly" -SkipNpm
#>

[CmdletBinding()]
param(
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path,
    [string]$SiteName = '',
    [string]$AppPoolName = '',
    [string]$PhpExe = '',
    [string]$ComposerExe = 'composer',
    [switch]$SkipNpm,
    [switch]$SkipComposer,
    [switch]$SkipStopIis
)

$ErrorActionPreference = 'Stop'

function Write-Step([string]$Message) {
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Assert-Admin {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        Write-Warning "Not running as Administrator. Stopping IIS / AppPool may fail. Run PowerShell as Admin if locks persist."
    }
}

function Resolve-Php {
    param([string]$Preferred)

    if ($Preferred -and (Test-Path $Preferred)) {
        return $Preferred
    }

    $candidates = @(
        'C:\Program Files (x86)\Plesk\Additional\PleskPHP83\php.exe',
        'C:\Program Files (x86)\Plesk\Additional\PleskPHP84\php.exe',
        'C:\Program Files (x86)\Plesk\Additional\PleskPHP82\php.exe'
    )

    foreach ($c in $candidates) {
        if (Test-Path $c) {
            return $c
        }
    }

    $fromPath = Get-Command php -ErrorAction SilentlyContinue
    if ($fromPath) {
        return $fromPath.Source
    }

    throw "PHP executable not found. Pass -PhpExe 'C:\...\php.exe'"
}

function Invoke-Artisan {
    param(
        [string]$Php,
        [string]$Root,
        [Parameter(ValueFromRemainingArguments = $true)]
        [string[]]$ArtisanArgs
    )

    Push-Location $Root
    try {
        & $Php artisan @ArtisanArgs
        if ($LASTEXITCODE -ne 0) {
            throw "artisan $($ArtisanArgs -join ' ') failed with exit code $LASTEXITCODE"
        }
    }
    finally {
        Pop-Location
    }
}

function Clear-BootstrapCache {
    param([string]$Root)

    $cacheDir = Join-Path $Root 'bootstrap\cache'
    if (-not (Test-Path $cacheDir)) {
        New-Item -ItemType Directory -Path $cacheDir | Out-Null
    }

    Get-ChildItem -Path $cacheDir -Force -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -ne '.gitignore' } |
        ForEach-Object {
            try {
                Remove-Item -LiteralPath $_.FullName -Force -Recurse -ErrorAction Stop
                Write-Host "  removed $($_.Name)"
            }
            catch {
                Write-Warning "  could not remove $($_.Name): $($_.Exception.Message)"
            }
        }

    # Ensure writable placeholder exists
    $keep = Join-Path $cacheDir '.gitignore'
    if (-not (Test-Path $keep)) {
        Set-Content -Path $keep -Value "*`n!.gitignore`n" -Encoding ascii
    }
}

function Stop-IisTargets {
    param([string]$Site, [string]$Pool)

    Import-Module WebAdministration -ErrorAction SilentlyContinue

    if ($Pool) {
        Write-Host "  Stopping AppPool [$Pool]..."
        Stop-WebAppPool -Name $Pool -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
    }

    if ($Site) {
        Write-Host "  Stopping Site [$Site]..."
        Stop-Website -Name $Site -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
    }

    # Kill leftover PHP CGI workers that may still lock cache files
    Get-Process php-cgi, php -ErrorAction SilentlyContinue |
        Where-Object { $_.Path -like '*PleskPHP*' -or $_.Path -like '*php*' } |
        ForEach-Object {
            try {
                Write-Host "  Stopping process $($_.Name) PID $($_.Id)"
                Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue
            }
            catch {}
        }

    Start-Sleep -Seconds 2
}

function Start-IisTargets {
    param([string]$Site, [string]$Pool)

    Import-Module WebAdministration -ErrorAction SilentlyContinue

    if ($Pool) {
        Write-Host "  Starting AppPool [$Pool]..."
        Start-WebAppPool -Name $Pool -ErrorAction SilentlyContinue
    }

    if ($Site) {
        Write-Host "  Starting Site [$Site]..."
        Start-Website -Name $Site -ErrorAction SilentlyContinue
    }
}

# ---------------------------------------------------------------------------
Assert-Admin
$php = Resolve-Php -Preferred $PhpExe

Write-Host "Project : $ProjectRoot"
Write-Host "PHP     : $php"
Write-Host "Site    : $(if ($SiteName) { $SiteName } else { '(not set)' })"
Write-Host "AppPool : $(if ($AppPoolName) { $AppPoolName } else { '(not set)' })"

if (-not (Test-Path (Join-Path $ProjectRoot 'artisan'))) {
    throw "artisan not found in $ProjectRoot"
}

Write-Step "Maintenance mode ON"
try {
    Invoke-Artisan -Php $php -Root $ProjectRoot down --retry=60
}
catch {
    Write-Warning "Could not enter maintenance mode (continuing): $($_.Exception.Message)"
}

if (-not $SkipStopIis) {
    Write-Step "Stop IIS to release file locks on bootstrap\cache"
    if (-not $SiteName -and -not $AppPoolName) {
        Write-Warning "No -SiteName / -AppPoolName provided. Skipping IIS stop. If rename fails, re-run with those params as Admin."
    }
    else {
        Stop-IisTargets -Site $SiteName -Pool $AppPoolName
    }
}

Write-Step "Clear bootstrap\cache (packages.php / services.php / *.tmp)"
Clear-BootstrapCache -Root $ProjectRoot

if (-not $SkipComposer) {
    Write-Step "composer install --no-dev (do NOT use composer update on production)"
    Push-Location $ProjectRoot
    try {
        & $ComposerExe install --no-dev --optimize-autoloader --no-interaction
        if ($LASTEXITCODE -ne 0) {
            throw "composer install failed with exit code $LASTEXITCODE"
        }
    }
    finally {
        Pop-Location
    }
}
else {
    Write-Step "Skip composer (manual)"
    Write-Step "package:discover + filament:upgrade"
    Invoke-Artisan -Php $php -Root $ProjectRoot package:discover --ansi
    Invoke-Artisan -Php $php -Root $ProjectRoot filament:upgrade
}

Write-Step "Clear runtime caches then optimize"
Invoke-Artisan -Php $php -Root $ProjectRoot optimize:clear
Invoke-Artisan -Php $php -Root $ProjectRoot view:clear
Invoke-Artisan -Php $php -Root $ProjectRoot optimize
Invoke-Artisan -Php $php -Root $ProjectRoot filament:cache-components

if (-not $SkipNpm) {
    Write-Step "npm ci / npm run build"
    Push-Location $ProjectRoot
    try {
        if (Test-Path 'package-lock.json') {
            npm ci --ignore-scripts
        }
        else {
            npm install --ignore-scripts
        }
        if ($LASTEXITCODE -ne 0) { throw "npm install/ci failed" }

        npm run build
        if ($LASTEXITCODE -ne 0) { throw "npm run build failed" }
    }
    finally {
        Pop-Location
    }
}

if (-not $SkipStopIis) {
    Write-Step "Start IIS"
    Start-IisTargets -Site $SiteName -Pool $AppPoolName
}

Write-Step "Maintenance mode OFF"
try {
    Invoke-Artisan -Php $php -Root $ProjectRoot up
}
catch {
    Write-Warning "Could not leave maintenance mode: $($_.Exception.Message)"
}

Write-Host ""
Write-Host "Deploy finished successfully." -ForegroundColor Green
Write-Host "If Livewire still freezes: hard-refresh browser (Ctrl+F5) and confirm public/build assets were uploaded."
