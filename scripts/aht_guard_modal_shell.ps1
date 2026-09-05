#Requires -Version 5.1
# Alias: guard modal shell -> guard CSS architecture (fase 2)
& (Join-Path $PSScriptRoot 'aht_guard_css_architecture.ps1') @args
exit $LASTEXITCODE
