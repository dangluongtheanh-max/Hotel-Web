# Script đóng gói mã nguồn nộp bài cho dự án SKY HOTEL (HotelWeb)
# Chạy từ thư mục gốc hoặc bất kỳ đâu:
#   powershell -ExecutionPolicy Bypass -File tools\dong-goi.ps1

param(
    [string]$OutputPath = ""
)

$ErrorActionPreference = "Stop"

$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$Timestamp = Get-Date -Format "yyyyMMdd-HHmmss"

if ([string]::IsNullOrWhiteSpace($OutputPath)) {
    $OutputPath = Join-Path $ProjectRoot "HotelWeb-nop-bai-$Timestamp.zip"
} elseif (-not [System.IO.Path]::IsPathRooted($OutputPath)) {
    $OutputPath = Join-Path (Get-Location).Path $OutputPath
}

if (Test-Path $OutputPath) {
    Remove-Item $OutputPath -Force
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$ExcludedDirNames = @(".git", ".agent", ".agents", "node_modules")
$ExcludedRelPaths = @("Backend/.env", "BAO-CAO-LOI-CHECKIN-FK.md")
$ExcludedPrefixPaths = @("Backend/scratch/")

function Should-Exclude([string]$RelPath) {
    $norm = $RelPath.Replace("\", "/").TrimStart("/")
    $segments = $norm.Split("/")

    # Loại trừ các thư mục theo tên segment (ở mọi cấp)
    for ($i = 0; $i -lt ($segments.Length - 1); $i++) {
        if ($ExcludedDirNames -contains $segments[$i]) {
            return $true
        }
    }
    if ($ExcludedDirNames -contains $segments[-1]) {
        return $true
    }

    foreach ($exact in $ExcludedRelPaths) {
        if ($norm -ieq $exact) {
            return $true
        }
    }

    foreach ($prefix in $ExcludedPrefixPaths) {
        if ($norm.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)) {
            return $true
        }
    }

    if ($norm.EndsWith(".zip", [System.StringComparison]::OrdinalIgnoreCase)) {
        return $true
    }

    return $false
}

$RootPrefixLen = $ProjectRoot.TrimEnd("\", "/").Length + 1
$AddedCount = 0
$SkippedCount = 0

$Zip = [System.IO.Compression.ZipFile]::Open($OutputPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    $Files = Get-ChildItem -Path $ProjectRoot -Recurse -File -Force
    foreach ($File in $Files) {
        $RelPath = $File.FullName.Substring($RootPrefixLen)
        if (Should-Exclude $RelPath) {
            $SkippedCount++
            continue
        }
        $EntryName = $RelPath.Replace("\", "/")
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $Zip,
            $File.FullName,
            $EntryName,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
        $AddedCount++
    }
}
finally {
    $Zip.Dispose()
}

Write-Host "Da tao file zip: $OutputPath"
Write-Host "So file da dong goi: $AddedCount | So file da loai tru: $SkippedCount"

