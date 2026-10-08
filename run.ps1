param(
    [ValidateRange(1024, 65535)]
    [int]$Port = 8017,
    [string]$PhpPath = ''
)

$ErrorActionPreference = 'Stop'
$projectRoot = $PSScriptRoot
if (-not $PhpPath) {
    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCommand) { $PhpPath = $phpCommand.Source }
    elseif (Test-Path -LiteralPath 'C:\xampp\php\php.exe') { $PhpPath = 'C:\xampp\php\php.exe' }
    else { throw 'PHP 8.2+ tidak ditemukan. Gunakan .\run.ps1 -PhpPath C:\lokasi\php.exe' }
}
if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'vendor\autoload.php'))) {
    throw 'Jalankan composer install terlebih dahulu. Lihat README.md.'
}
if (-not (Test-Path -LiteralPath (Join-Path $projectRoot '.env'))) {
    throw 'Konfigurasi .env belum tersedia. Ikuti pemasangan awal di README.md.'
}

$phpArguments = @('-d', 'max_execution_time=0', '-d', 'upload_max_filesize=5M', '-d', 'post_max_size=20M')
$opcachePath = Join-Path (Split-Path -Parent $PhpPath) 'ext\php_opcache.dll'
$hasOpcache = & $PhpPath -r "echo extension_loaded('Zend OPcache') ? 'yes' : 'no';"
if ($hasOpcache -ne 'yes' -and (Test-Path -LiteralPath $opcachePath)) {
    $phpArguments += @('-d', "zend_extension=$opcachePath")
}
$phpArguments += @('-d', 'opcache.enable_cli=1', '-d', 'opcache.validate_timestamps=1', '-d', 'opcache.revalidate_freq=0')
$routerPath = Join-Path $projectRoot 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
Write-Host "SD Ceria Nusantara: http://127.0.0.1:$Port/login"
Write-Host 'Hentikan server dengan Ctrl+C.'
Push-Location (Join-Path $projectRoot 'public')
try { & $PhpPath @phpArguments -S "127.0.0.1:$Port" $routerPath }
finally { Pop-Location }
