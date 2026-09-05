#Requires -Version 5.1
param([switch]$DryRun)
$ErrorActionPreference='Stop'
$ScriptDir=Split-Path -Parent $MyInvocation.MyCommand.Path
. (Join-Path $ScriptDir 'deploy_lib_aht.ps1')
$Ctx = Initialize-AhtDeployContext -ScriptDir $ScriptDir
$legacy = @(
 'assets/css/play-v3-ficha.css','assets/css/ficha-neni-ref-v1.css',
 'assets/css/play-v3-visual-review.css','assets/css/play-v3-visual-interior.css','assets/css/play-v3-visual-replica.css',
 'assets/css/play-v3-capas.css','assets/css/play-v3-capas-shell.css','assets/css/capas-ds.css',
 'assets/css/modals.css','assets/css/modal-core.css','assets/css/modal-skin.css','assets/css/modal-header.css',
 'assets/css/modal-responsive.css','assets/css/modal-catalog.css','assets/css/modal-ds.css',
 'assets/css/modals-secondary-unified.css','assets/css/modals-shell-lavanda-mobile.css','assets/css/modal-titles-aht.css',
 'assets/css/v4/screens-secondary.css','assets/css/play-v3-inicio-override.css','assets/css/app.css',
 'assets/css/play-v3-agenda.css','assets/css/play-v3-misiones.css','assets/css/play-v3-vida.css','assets/css/play-v3-notas-mapa.css',
 'assets/css/design-system/screens/inicio.css','assets/css/design-system/screens/inicio-mobile.css',
 'assets/css/design-system/screens/inicio-desktop.css','assets/css/design-system/screens/inicio-desktop-cromatica.css',
 'assets/css/design-system/screens/inicio-evento-pueblo-mobile.css','assets/css/design-system/screens/inicio-evento-pueblo-desktop.css',
 'assets/css/design-system/mensajitos-cartas-persona-v1.css','assets/css/design-system/mensajitos-carta-regalo-v1.css'
)
$cfg = Get-Content -LiteralPath $Ctx.CredsPath -Raw -Encoding UTF8 | ConvertFrom-Json
$winscp = Find-WinScpCom -JsonExplicitPath ([string]$cfg.HOSTALIA_WINSCP_PATH)
if (-not $winscp) { throw 'WinSCP.com no encontrado' }
$openLine = Get-WinScpOpenLine -Cfg $cfg
$lines = New-Object System.Collections.Generic.List[string]
$lines.Add($openLine)
$lines.Add('option batch on')
$lines.Add('option confirm off')
foreach ($rel in $legacy) {
  $remote = "$($Ctx.RemotePath)/$rel"
  if ($DryRun) { Write-Host "rm $remote" } else { $lines.Add("rm `"$remote`"") }
}
$lines.Add('exit')
if ($DryRun) { exit 0 }
$script = Join-Path $env:TEMP ('aht-rm-legacy-css-' + [guid]::NewGuid().ToString('N') + '.txt')
[IO.File]::WriteAllText($script, ($lines -join "`r`n") + "`r`n", (New-Object System.Text.UTF8Encoding $false))
& $winscp /ini=nul /script=$script
$code = $LASTEXITCODE
Remove-Item $script -Force -ErrorAction SilentlyContinue
if ($code -ne 0) { Write-Warning "WinSCP rm legacy codigo $code" }
Write-Host 'FTP legacy CSS delete ejecutado.'
