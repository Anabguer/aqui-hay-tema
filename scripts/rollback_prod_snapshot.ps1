#Requires -Version 5.1
<#
.SYNOPSIS
  Restaura bytes exactos desde snapshot GOLDEN PROD.
  Por defecto DRY-RUN. Usar -Execute para subir a produccion (requiere autorizacion).
#>
param(
    [Parameter(Mandatory)]
    [string]$Manifest,

    [Parameter(Mandatory)]
    [string[]]$Files,

    [switch]$Execute,

    [string]$LogFile
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'deploy_lib_aht.ps1')
. (Join-Path $PSScriptRoot 'aht_golden_prod_lib.ps1')

$Ctx = Initialize-AhtDeployContext -ScriptDir $PSScriptRoot

if (-not $LogFile) {
    $ts = Get-Date -Format 'yyyyMMdd-HHmmss'
    $LogFile = Join-Path $Ctx.LogsDir "rollback-prod-snapshot-$ts.log"
}

$manifestPath = Resolve-Path -LiteralPath $Manifest
$snapshotRoot = Split-Path -Parent $manifestPath.Path
$filesRoot = Join-Path $snapshotRoot 'files'
if (-not (Test-Path -LiteralPath $filesRoot)) {
    throw "No existe carpeta files/ junto al manifest: $filesRoot"
}

Write-AhtGoldenProdLog -LogFile $LogFile -Message "=== ROLLBACK PROD SNAPSHOT $(if ($Execute) { 'EXECUTE' } else { 'DRY-RUN' }) ===" -ToHost
Write-AhtGoldenProdLog -LogFile $LogFile -Message "Manifest: $($manifestPath.Path)" -ToHost

$integrity = Test-AhtGoldenProdManifestIntegrity -ManifestPath $manifestPath.Path -FilesRoot $filesRoot
if (-not $integrity.Ok) {
    foreach ($err in $integrity.Errors) {
        Write-AhtGoldenProdLog -LogFile $LogFile -Message "INTEGRIDAD FAIL: $err" -ToHost
    }
    throw 'Manifest o snapshot corrupto; abortando.'
}
Write-AhtGoldenProdLog -LogFile $LogFile -Message 'OK: manifest y SHA256 del snapshot validados' -ToHost

$manifestMap = @{}
foreach ($entry in $integrity.Manifest.files) {
    $manifestMap[[string]$entry.path] = $entry
}

$normalized = @($Files | ForEach-Object { $_.Replace('\', '/').TrimStart('/') } | Where-Object { $_ })
if ($normalized.Count -eq 0) {
    throw 'Lista de archivos vacia.'
}

$plan = New-Object 'System.Collections.Generic.List[object]'
$rejected = New-Object 'System.Collections.Generic.List[string]'

foreach ($rel in $normalized) {
    if (-not $manifestMap.ContainsKey($rel)) {
        $rejected.Add($rel)
        continue
    }
    $entry = $manifestMap[$rel]
    $local = Join-Path $filesRoot ($rel -replace '/', '\')
    if (-not (Test-Path -LiteralPath $local -PathType Leaf)) {
        throw "Archivo en manifest pero ausente en snapshot: $rel"
    }
    $hash = Get-AhtFileSha256 -Path $local
    if ($hash -ne [string]$entry.sha256) {
        throw "Hash corrupto en snapshot para $rel (esperado $($entry.sha256), actual $hash)"
    }
    $plan.Add([ordered]@{
        Rel = $rel
        Local = $local
        Remote = "$($Ctx.RemotePath)/$rel"
        Bytes = [int64]$entry.bytes
        Sha256 = $hash
    })
}

if ($rejected.Count -gt 0) {
    foreach ($r in $rejected) {
        Write-AhtGoldenProdLog -LogFile $LogFile -Message "RECHAZADO (no en manifest): $r" -ToHost
    }
    throw "Se rechazaron $($rejected.Count) archivo(s) no presentes en manifest."
}

Write-AhtGoldenProdLog -LogFile $LogFile -Message "Plan de restauracion ($($plan.Count) archivos):" -ToHost
foreach ($item in $plan) {
    Write-AhtGoldenProdLog -LogFile $LogFile -Message "  $($item.Rel) ($($item.Bytes) B) -> $($item.Remote)" -ToHost
}

if (-not $Execute) {
    Write-AhtGoldenProdLog -LogFile $LogFile -Message 'DRY-RUN completado. Sin cambios en produccion.' -ToHost
    exit 0
}

$uploadList = [System.Collections.Generic.List[object]]::new()
foreach ($item in $plan) {
    [void]$uploadList.Add(@{
        Local = [string]$item.Local
        Remote = [string]$item.Remote
        Rel = [string]$item.Rel
    })
}

$winscpLog = Join-Path (Split-Path -Parent $LogFile) ("winscp-rollback-" + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.log')
$upload = Invoke-AhtWinScpUpload -Ctx $Ctx -Files $uploadList -WinScpLogPath $winscpLog
if (-not $upload.Ok) {
    Write-AhtGoldenProdLog -LogFile $LogFile -Message "ERROR WinSCP upload code=$($upload.Code)" -ToHost
    exit $upload.Code
}

Write-AhtGoldenProdLog -LogFile $LogFile -Message "OK: restaurados $($upload.Count) archivos en produccion." -ToHost
exit 0
