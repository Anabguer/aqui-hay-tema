# Checklist pre-playtest 01

Auditoría antes de que Neni juegue PLAYTEST_01. No es estética final.

## 1. Suite y errores

- [x] `php tests/run_all.php` — **2026-08-18, exit 0** (incluye `playtest_01_config_test.php` y smoke)
- [x] `php tests/playtest_01_config_test.php` — Rocío + QA Valid, sin B/C
- [x] Sin `console.log` / `var_dump` / `display_errors` en código de play
- [x] Rutas de play relativas (`assets/`, `api/index.php`) — válidas bajo cualquier subcarpeta del juego

## 2. Saves

- [x] Partidas en `data/partidas/` (escritura PHP; listado HTTP denegado)
- [x] `data/partidas/.htaccess` → `Require all denied`
- [x] Carpeta versionada con `.gitkeep`; JSON de partidas no van a git

## 3. DEV / debug

- [x] `dev.php` exige `aht_dev_enabled()` (`src/dev.local.php` o `AHT_DEV=1`)
- [x] APIs `dev.*` y `reloj.ir_a` pasan por `requireDev()` → 403 si el gate está off
- [x] Enlace «Modo dev» **quitado** de `play.php` e `index.php` (recorrido Neni)
- [x] `dev.php` sigue existiendo para diagnóstico **si** el gate está activo
- [x] No copiar `dev.local.php` a ningún despliegue público (está en `.gitignore`)

## 4. Placeholders e identidades

- [x] Config `playtest_01`: Rocío real + **QA Valid** (NO CANON)
- [x] No se crean B/C ni I02/I10 para rellenar
- [x] Badge `UI provisional · PLAYTEST_01`
- [x] PNG de revisión Dani 06b **no** están en git (gitignore `assets/personajes/revision/**/*.png`)
- [x] No se añaden evidencias Pamela/Dani al playtest

## 5. Qué no se ha añadido

- [x] No hay panel nuevo de relaciones
- [x] No hay economía / compatibilidad real / narrativa nueva
- [x] No hay residentes nuevos de diseño
- [x] No hay secretos nuevos ni visual definitivo
- [x] No se ha “arreglado” la UX por intuición (solo gate, config, checklist, protección HTTP)

## 6. Responsive (manual, Neni en PC + móvil)

- [ ] Header usable; sin scroll horizontal obvio
- [ ] Reloj +1h / +8h / próximo bajo el resumen
- [ ] Mapa: badges sin hover; detalle al toque
- [ ] Programar encuentro y Ver resultado con botones grandes
- [ ] Ficha / roster comprensibles en una columna

## 7. Despliegue (no ejecutado sin decisión)

- [x] **No** se ha copiado el juego encima de `W:\juegos\aqui-hay-tema` (puerta de catálogo)
- [x] **No** se ha tocado `catalog/games.json` de otros juegos
- [ ] Neni confirma URL/ruta pública (ver `docs/PLAYTEST_01.md` y la entrega)

## 8. Diagnóstico si algo falla

Neni **no** usa DEV en el playtest.

Si peta o se bloquea:

1. Copiar la línea de estado del header (`schema v2 · part_…`).
2. Decir qué pulsó y qué vio.
3. Carlos, en máquina con gate DEV: `dev.php` → exportar diagnóstico (`dev.diagnostico.export`): `partida_id`, seed, schema, reloj, eventos recientes.

## 9. Cómo reiniciar

En `play.php`: **Nueva partida** (confirma). Crea id nuevo con config `playtest_01` y seed `playtest-01`.
