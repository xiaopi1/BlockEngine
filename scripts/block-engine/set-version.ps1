param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$')]
    [string]$Version
)

$ErrorActionPreference = 'Stop'
$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$targets = @(
    @{ Path = 'apps\app-frontend\package.json'; Pattern = '"version"\s*:\s*"[^"]+"'; Replacement = '"version": "' + $Version + '"' },
    @{ Path = 'apps\app\Cargo.toml'; Pattern = '(?m)^version\s*=\s*"[^"]+"'; Replacement = 'version = "' + $Version + '"' },
    @{ Path = 'packages\app-lib\Cargo.toml'; Pattern = '(?m)^version\s*=\s*"[^"]+"'; Replacement = 'version = "' + $Version + '"' }
)

foreach ($target in $targets) {
    $path = Join-Path $repositoryRoot $target.Path
    $content = [IO.File]::ReadAllText($path, [Text.UTF8Encoding]::new($false))
    $matcher = [Text.RegularExpressions.Regex]::new($target.Pattern)
    $updated = $matcher.Replace($content, $target.Replacement, 1)
    if ($updated -eq $content) {
        throw "Version field was not found in $($target.Path)"
    }
    [IO.File]::WriteAllText($path, $updated, [Text.UTF8Encoding]::new($false))
}

Write-Host "Block Engine version changed to $Version"
Write-Host 'Run cargo check or the Windows release build to refresh Cargo.lock.'
