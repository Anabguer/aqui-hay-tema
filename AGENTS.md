# AHT — Reglas para Agentes

## CANÓNICO

- **Carpeta:** `W:\juegos\aqui-hay-tema`
- **Branch:** `deploy/integrated` (remota) / `main/canonical` (local tracking)
- **Por defecto TODOS los agentes trabajan aquí.**

## Worktrees

- Solo crear worktree temporal si existe concurrencia real.
- Si se crea:
  - Usar carpeta TEMP (`C:\Users\agl03\AppData\Local\Temp\`)
  - Terminar tarea
  - Integrar en `main/canonical`
  - Verificar `git status` clean
  - `git worktree remove`
  - No dejarlo abandonado
- **Nunca crear otro worktree permanente.**

## Commits

- Commits canónicos van a `main/canonical` → push a `origin/deploy/integrated`.
- NO push de WIPs antiguos por inercia.
- NO force push.
- NO modificar gameplay, diseño, saves, o desplegar producción desde limpiezas de repo.

## Backups

- Snapshot de seguridad: `git tag backup/pre-<contexto>-<timestamp> origin/deploy/integrated`
- Manifiestos fuera del repo: `W:\juegos\_AHT_CONSOLIDACION_<timestamp>\`
- Archivo de preservación: `W:\juegos\_AHT_ARCHIVO\`

## CSS / UI / modales

- **OBLIGATORIO:** antes de cualquier cambio en CSS, UI de play, modales o orden de hojas en `play.php`, leer `docs/ARQUITECTURA_CSS.md` y respetar el contrato V4.
- **CERO `!important`:** no existe en la arquitectura AHT (ni en hojas CSS ni inline en `play.php`). Si parece necesario, corregir autoridad/cascada — no introducir excepciones.
- Tras tocar frame o links: `scripts/aht_guard_css_architecture.ps1` y `node tests/css_architecture_test.js`.
