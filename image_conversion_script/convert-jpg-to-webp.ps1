# convert-jpg-to-webp.ps1
# Converts JPG/JPEG files to WEBP using ImageMagick.
# Reads source directories from input-directories.txt.
# Creates a log file for every run.

$InputFile = Join-Path $PSScriptRoot "input-directories.txt"

# Create logs folder
$LogFolder = Join-Path $PSScriptRoot "logs"

if (!(Test-Path $LogFolder)) {
    New-Item -ItemType Directory -Path $LogFolder | Out-Null
}

# Create timestamped log file
$Timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$LogFile = Join-Path $LogFolder "webp-conversion-$Timestamp.log"

# Logging function
function Write-Log {
    param (
        [string]$Message,
        [string]$Level = "INFO",
        [string]$Color = "White"
    )

    $Time = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $LogMessage = "[$Time] [$Level] $Message"

    # Show on screen
    Write-Host $LogMessage -ForegroundColor $Color

    # Write to log file
    Add-Content -Path $LogFile -Value $LogMessage
}

Write-Log "Starting JPG to WEBP conversion script." "INFO" "Cyan"
Write-Log "Log file created at: $LogFile" "INFO" "Cyan"

if (!(Test-Path $InputFile)) {
    Write-Log "input-directories.txt not found in script folder." "ERROR" "Red"
    Write-Log "Create input-directories.txt and add one directory path per line." "ERROR" "Red"
    Pause
    exit
}

# Check if ImageMagick is available
$MagickExists = Get-Command magick -ErrorAction SilentlyContinue

if (!$MagickExists) {
    Write-Log "ImageMagick 'magick' command not found." "ERROR" "Red"
    Write-Log "Install ImageMagick or make sure it is added to PATH." "ERROR" "Red"
    Pause
    exit
}

$Directories = Get-Content $InputFile | Where-Object {
    $_.Trim() -ne "" -and !$_.Trim().StartsWith("#")
}

$TotalFiles = 0
$ConvertedFiles = 0
$SkippedFiles = 0
$FailedFiles = 0

foreach ($Directory in $Directories) {
    $Directory = $Directory.Trim().Trim('"')

    if (!(Test-Path $Directory)) {
        Write-Log "Directory not found: $Directory" "WARNING" "Yellow"
        continue
    }

    Write-Log "Scanning directory: $Directory" "INFO" "Cyan"

    $JpgFiles = Get-ChildItem -Path $Directory -Recurse -File -Include *.jpg, *.jpeg

    if ($JpgFiles.Count -eq 0) {
        Write-Log "No JPG/JPEG files found in: $Directory" "INFO" "Yellow"
        continue
    }

    foreach ($File in $JpgFiles) {
        $TotalFiles++

        $OutputFile = Join-Path $File.DirectoryName ($File.BaseName + ".webp")

        if (Test-Path $OutputFile) {
            $SkippedFiles++
            Write-Log "Skipped existing WEBP: $OutputFile" "SKIPPED" "DarkYellow"
            continue
        }

        Write-Log "Converting: $($File.FullName)" "INFO" "White"

        magick "$($File.FullName)" -quality 60 "$OutputFile"

        if ($LASTEXITCODE -eq 0 -and (Test-Path $OutputFile)) {
            $ConvertedFiles++
            Write-Log "Created: $OutputFile" "SUCCESS" "Green"
        } else {
            $FailedFiles++
            Write-Log "Failed to convert: $($File.FullName)" "ERROR" "Red"
        }
    }
}

Write-Log "Conversion completed." "INFO" "Green"
Write-Log "Total JPG/JPEG files found: $TotalFiles" "SUMMARY" "Cyan"
Write-Log "Converted files: $ConvertedFiles" "SUMMARY" "Green"
Write-Log "Skipped files: $SkippedFiles" "SUMMARY" "Yellow"
Write-Log "Failed files: $FailedFiles" "SUMMARY" "Red"
Write-Log "Final log saved at: $LogFile" "INFO" "Cyan"

Pause