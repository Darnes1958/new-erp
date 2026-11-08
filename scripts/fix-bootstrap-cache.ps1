#Requires -Version 5.1
<#
.SYNOPSIS
  Emergency fix when composer package:discover fails with Access denied on packages.php

.EXAMPLE
  .\scripts\fix-bootstrap-cache.ps1 -AppPoolName "alwaseetonly.ly(domain)(4.0)(pool)"
#>

[CmdletBinding()]
param(
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path,
    [string]$SiteName = '',
    [string]$AppPoolName = '',
    [string]$PhpExe = 'C:\Program Files (x86)\Plesk\Additional\PleskPHP83\php.exe'
)

$ErrorActionPreference = 'Stop'
Import-Module WebAdministration -ErrorAction SilentlyContinue

Write-Host "Stopping IIS targets to unlock bootstrap\cache..."
if ($AppPoolName) { Stop-WebAppPool -Name $AppPoolName -ErrorAction SilentlyContinue }
if ($SiteName) { Stop-Website -Name $SiteName -ErrorAction SilentlyContinue }
Start-Sleep -Seconds 2

Get-Process php-cgi -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 1

$cache = Join-Path $ProjectRoot 'bootstrap\cache'
Get-ChildItem $cache -Force |
    Where-Object { $_.Name -ne '.gitignore' } |
    Remove-Item -Force -Recurse -ErrorAction SilentlyContinue

Write-Host "Cleared $cache"
Write-Host "Running package:discover..."

Push-Location $ProjectRoot
try {
    & $PhpExe artisan package:discover --ansi
    if ($LASTEXITCODE -ne 0) { throw "package:discover failed" }
    & $PhpExe artisan filament:upgrade
    & $PhpExe artisan optimize:clear
    & $PhpExe artisan optimize
}
finally {
    Pop-Location
}

if ($AppPoolName) { Start-WebAppPool -Name $AppPoolName -ErrorAction SilentlyContinue }
if ($SiteName) { Start-Website -Name $SiteName -ErrorAction SilentlyContinue }

Write-Host "Done." -ForegroundColor Green
