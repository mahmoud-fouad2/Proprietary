# ZACO project diagnostics (PowerShell)
# Usage:
#   pwsh -File scripts/doctor.ps1
# Optional:
#   $env:ZACO_DOCTOR_OUT = "storage/doctor-report.txt"

[Diagnostics.CodeAnalysis.SuppressMessageAttribute('PSAvoidAssignmentToAutomaticVariable', '', Justification='PSScriptAnalyzer false-positive in this script.')]
param()

$ErrorActionPreference = 'Continue'

$root = Split-Path -Parent $PSScriptRoot
$out = $env:ZACO_DOCTOR_OUT
if ([string]::IsNullOrWhiteSpace($out)) {
  $out = Join-Path $root 'storage/doctor-report.txt'
}

# Ensure output folder exists
$outDir = Split-Path -Parent $out
if (!(Test-Path $outDir)) {
  New-Item -ItemType Directory -Force -Path $outDir | Out-Null
}

$lines = New-Object System.Collections.Generic.List[string]
function Add-Line([string]$s) { $lines.Add($s) | Out-Null }

Add-Line "ZACO Doctor Report"
Add-Line "================="
Add-Line ("Timestamp: " + (Get-Date).ToString('yyyy-MM-dd HH:mm:ss'))
Add-Line ("Root: " + $root)
Add-Line ""

# PHP availability
$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) {
  Add-Line "PHP: NOT FOUND in PATH"
  Add-Line "- Install PHP or run on the server where PHP is available."
  Add-Line ""
} else {
  Add-Line ("PHP: " + $php.Source)
  try {
    $ver = & php -v 2>&1
    Add-Line "php -v:"
    Add-Line $ver
  } catch {
    Add-Line "php -v failed."
  }
  Add-Line ""

  # Lint all PHP files
  Add-Line "PHP lint (php -l)"
  Add-Line "-----------------"
  $phpFiles = Get-ChildItem -Path $root -Recurse -File -Filter *.php |
    Where-Object { (-not [regex]::IsMatch($_.FullName, '\\vendor\\')) -and (-not [regex]::IsMatch($_.FullName, '\\storage\\')) }

  $lintErrors = 0
  foreach ($f in $phpFiles) {
    $res = & php -l $f.FullName 2>&1
    if ($LASTEXITCODE -ne 0) {
      $lintErrors++
      Add-Line ("FAIL: " + $f.FullName)
      Add-Line $res
      Add-Line ""
    }
  }
  if ($lintErrors -eq 0) {
    Add-Line "OK: No PHP syntax errors found."
  } else {
    Add-Line ("Found " + $lintErrors + " file(s) with syntax errors.")
  }
  Add-Line ""
}

# Quick static scans for common deprecated patterns
Add-Line "Static scan (common deprecated patterns)"
Add-Line "--------------------------------------"
$scanFiles = Get-ChildItem -Path (Join-Path $root 'app') -Recurse -File -Filter *.php -ErrorAction SilentlyContinue |
  Where-Object { (-not [regex]::IsMatch($_.FullName, '\\vendor\\')) -and (-not [regex]::IsMatch($_.FullName, '\\storage\\')) }

$patterns = @(
  'imagedestroy\(',
  '\bstrftime\b',
  '\butf8_encode\b',
  '\butf8_decode\b',
  '\beach\(',
  '\bcreate_function\(',
  '\bFILTER_SANITIZE_STRING\b',
  '\bmysql_\w+\('
)

foreach ($p in $patterns) {
  if (-not $scanFiles -or $scanFiles.Count -eq 0) { continue }
  $patternHits = Select-String -Path $scanFiles.FullName -Pattern $p -ErrorAction SilentlyContinue
  if ($patternHits) {
    Add-Line ("Pattern: " + $p)
    foreach ($m in $patternHits) {
      Add-Line ("- " + $m.Path + ":" + $m.LineNumber + "  " + $m.Line.Trim())
    }
    Add-Line ""
  }
}

$lines | Set-Content -Path $out -Encoding UTF8
Write-Host ("Wrote report to: " + $out)
