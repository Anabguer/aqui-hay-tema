<?php
declare(strict_types=1);
require_once __DIR__ . '/src/dev_gate.php';
if (!aht_dev_enabled()) {
    http_response_code(403);
    echo 'Modo dev deshabilitado. Crear dev.local.php o AHT_DEV=1';
    exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Aquí Hay Tema — Laboratorio dev</title>
  <link rel="stylesheet" href="assets/css/app.css" />
  <style>
    .dev-lab { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 900px) { .dev-lab { grid-template-columns: 1fr; } }
    fieldset { border: 1px solid #444; margin: 0 0 1rem; padding: .75rem; }
    legend { padding: 0 .35rem; font-weight: 600; }
    .dev-lab label { display: block; margin: .35rem 0; }
    .dev-lab input[type="number"], .dev-lab select, .dev-lab input[type="text"] { max-width: 100%; }
    .inspect { max-height: 320px; overflow: auto; font-size: 12px; }
    .expr-strip { display: flex; flex-wrap: wrap; gap: .35rem; margin: .5rem 0; }
    .expr-strip button { font-size: 12px; }
    .expr-strip button.missing { opacity: .45; }
    .expr-strip button.active { outline: 2px solid var(--accent, #c9a227); }
    .expr-preview { width: 220px; height: 220px; object-fit: cover; background: #111; border: 1px solid #444; border-radius: 8px; }
    .expr-meta { font-size: 12px; color: #9a9aad; }
  </style>
</head>
<body>
  <header class="top-bar">
    <div>
      <h1>Laboratorio dev <span class="badge-provisional">NO producción</span></h1>
      <p class="status" aria-live="polite">Partida activa: <code id="partida-id">—</code></p>
    </div>
    <nav class="btn-row" aria-label="Navegación">
      <a class="btn" href="play.php">Jugar</a>
      <a class="btn" href="index.php">Landing</a>
    </nav>
  </header>

  <main class="layout" style="max-width: 1400px; grid-template-columns: 1fr;">
    <div class="dev-lab">
      <div>
        <fieldset>
          <legend>Partida</legend>
          <label for="sel-partida">Cargar partida existente</label>
          <select id="sel-partida" aria-describedby="partida-help"></select>
          <p id="partida-help" class="status">Selecciona y pulsa Cargar, o crea una nueva.</p>
          <div class="btn-row">
            <button type="button" id="btn-cargar-partida">Cargar seleccionada</button>
            <button type="button" id="btn-nueva" class="primary">Nueva partida dev</button>
            <button type="button" id="btn-borrar-partida">Borrar partida dev</button>
          </div>
          <label for="inp-seed">Seed (nueva partida)</label>
          <input type="text" id="inp-seed" placeholder="opcional, ej. qa-lunes-19h" />
        </fieldset>

        <fieldset>
          <legend>Reloj / time travel</legend>
          <div class="btn-row">
            <button type="button" id="btn-1h">+1 h</button>
            <button type="button" id="btn-4h">+4 h</button>
            <button type="button" id="btn-12h">+12 h</button>
            <button type="button" id="btn-1d">+1 día</button>
          </div>
          <label for="inp-dia">Ir a día pueblo</label>
          <input type="number" id="inp-dia" min="1" value="1" />
          <label for="inp-hora">Ir a hora (0–23)</label>
          <input type="number" id="inp-hora" min="0" max="23" value="19" />
          <div class="btn-row">
            <button type="button" id="btn-ir-a" class="primary">Saltar a fecha/hora</button>
            <button type="button" id="btn-sincronizar-enc">Sincronizar encuentros</button>
          </div>
        </fieldset>

        <fieldset>
          <legend>Residentes y vivienda</legend>
          <label for="sel-residente">Residente</label>
          <select id="sel-residente"></select>
          <div class="btn-row">
            <button type="button" id="btn-placeholder">Crear placeholder</button>
            <button type="button" id="btn-eliminar-ph">Eliminar placeholder</button>
          </div>
          <label for="sel-vivienda">Vivienda Bloque A</label>
          <select id="sel-vivienda">
            <?php for ($i = 1; $i <= 16; $i++): $id = 'A' . str_pad((string) $i, 2, '0', STR_PAD_LEFT); ?>
            <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($id) ?></option>
            <?php endfor; ?>
          </select>
          <div class="btn-row">
            <button type="button" id="btn-vivienda-inspect">Inspeccionar vivienda</button>
            <button type="button" id="btn-liberar">Liberar hueco</button>
          </div>
        </fieldset>

        <fieldset>
          <legend>QA visual / expresiones</legend>
          <p class="status">Estado interno ≠ cara. Esta tira mira assets <strong>sin</strong> disparar eventos de juego. El motor no genera PNG.</p>
          <label for="sel-pack-visual">Paquete gráfico</label>
          <select id="sel-pack-visual"></select>
          <p class="expr-meta" id="pack-meta">—</p>
          <div class="expr-strip" id="expr-strip" role="group" aria-label="Expresiones visuales"></div>
          <img id="expr-preview" class="expr-preview" alt="Vista previa de expresión" />
          <label for="sel-estado-emo">Estado emocional (placeholder, no fórmula)</label>
          <select id="sel-estado-emo"></select>
          <div class="btn-row">
            <button type="button" id="btn-vincular-pack">Vincular pack al residente</button>
            <button type="button" id="btn-forzar-estado">Forzar estado al residente</button>
            <button type="button" id="btn-limpiar-override">Quitar override de expresión</button>
          </div>
        </fieldset>

        <fieldset>
          <legend>Encuentros y relaciones</legend>
          <label for="sel-a">Participante A</label>
          <select id="sel-a"></select>
          <label for="sel-b">Participante B</label>
          <select id="sel-b"></select>
          <label for="sel-hora-enc">Hora encuentro</label>
          <select id="sel-hora-enc">
            <?php for ($h = 0; $h <= 23; $h++): ?>
            <option value="<?= $h ?>"<?= $h === 19 ? ' selected' : '' ?>><?= str_pad((string) $h, 2, '0', STR_PAD_LEFT) ?>:00</option>
            <?php endfor; ?>
          </select>
          <label for="sel-tipo-enc">Tipo</label>
          <select id="sel-tipo-enc">
            <option value="conocerse">Conocerse</option>
            <option value="amistad">Amistad</option>
            <option value="romantico">Romántico</option>
          </select>
          <div class="btn-row">
            <button type="button" id="btn-enc-programar">Programar encuentro</button>
            <button type="button" id="btn-enc-cancelar">Cancelar último activo</button>
            <button type="button" id="btn-enc-forzar">Forzar resolver</button>
          </div>
          <div class="btn-row">
            <button type="button" id="btn-rel-social">Modificar rel. social</button>
            <button type="button" id="btn-rel-romance">Modificar rel. romance</button>
          </div>
        </fieldset>

        <fieldset>
          <legend>Snapshots QA</legend>
          <label for="inp-snapshot">Nombre snapshot</label>
          <input type="text" id="inp-snapshot" value="antes_encuentro" />
          <div class="btn-row">
            <button type="button" id="btn-snap-guardar">Guardar snapshot</button>
            <button type="button" id="btn-snap-restaurar">Restaurar snapshot</button>
            <button type="button" id="btn-snap-listar">Listar snapshots</button>
          </div>
        </fieldset>

        <fieldset>
          <legend>Resets parciales</legend>
          <div class="btn-row">
            <button type="button" id="btn-reset-enc">Reset encuentros</button>
            <button type="button" id="btn-reset-rel">Reset relaciones</button>
            <button type="button" id="btn-reset-buzon">Reset buzón/diario</button>
          </div>
        </fieldset>

        <fieldset>
          <legend>Stress / persistencia</legend>
          <div class="btn-row">
            <button type="button" id="btn-stress100">Smoke 100 residentes</button>
            <button type="button" id="btn-guardar">Guardar</button>
            <button type="button" id="btn-recargar">Recargar</button>
            <button type="button" id="btn-copiar-json">Copiar JSON diagnóstico</button>
          </div>
        </fieldset>
      </div>

      <div>
        <fieldset>
          <legend>Inspecciones</legend>
          <div class="btn-row">
            <button type="button" id="btn-ins-residente">Ficha residente</button>
            <button type="button" id="btn-ins-agenda">Agenda día actual</button>
            <button type="button" id="btn-ins-rel">Relaciones</button>
            <button type="button" id="btn-ins-enc">Encuentros</button>
            <button type="button" id="btn-ins-rng">RNG / seed</button>
            <button type="button" id="btn-ins-audit">Audit / decision_log</button>
            <button type="button" id="btn-ins-buzon">Buzón</button>
            <button type="button" id="btn-ins-diario">Diario</button>
            <button type="button" id="btn-ins-mapa">Mapa presencia</button>
            <button type="button" id="btn-ins-coincidencias">Coincidencias NPC</button>
            <button type="button" id="btn-buzon-dev">Crear msg buzón dev</button>
          </div>
        </fieldset>

        <fieldset>
          <legend>DEV LAB — Discovery (sin asignar secretos)</legend>
          <label for="inp-disc-campo">Campo (ej. vida.hobby_principal)</label>
          <input type="text" id="inp-disc-campo" placeholder="vida.hobby_principal" value="vida.hobby_principal" />
          <div class="btn-row">
            <button type="button" id="btn-disc-campo">Inspeccionar visibilidad</button>
          </div>
          <pre class="inspect" id="disc-panel" aria-label="Proyección discovery"></pre>
        </fieldset>

        <fieldset>
          <legend>Calendario / eventos QA</legend>
          <label for="inp-cal-dia">Día calendario</label>
          <input type="number" id="inp-cal-dia" min="1" value="1" />
          <label for="inp-filtro-tipo-evento">Filtro tipo evento (exacto)</label>
          <input type="text" id="inp-filtro-tipo-evento" placeholder="ej. encuentro_programado" />
          <div class="btn-row">
            <button type="button" id="btn-calendario">Vista calendario día</button>
            <button type="button" id="btn-eventos">Inspector eventos</button>
            <button type="button" id="btn-diagnostico">Export diagnóstico</button>
            <button type="button" id="btn-simular-30">Simular 30 días</button>
            <button type="button" id="btn-catalogos">Inspeccionar catálogos</button>
            <button type="button" id="btn-diversidad">Analizador anti-clones</button>
          </div>
          <pre class="inspect" id="cal-panel" aria-label="Vista calendario"></pre>
        </fieldset>

        <fieldset>
          <legend>Log de acciones</legend>
          <pre class="inspect" id="log" tabindex="0" aria-label="Log de acciones dev"></pre>
        </fieldset>

        <fieldset>
          <legend>Estado completo (JSON)</legend>
          <pre class="inspect" id="inspect-panel" tabindex="0" aria-label="Estado JSON partida">Cargando…</pre>
        </fieldset>
      </div>
    </div>
  </main>

  <script src="assets/js/dev.js"></script>
</body>
</html>
