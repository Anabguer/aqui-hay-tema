<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Aquí Hay Tema — Jugar (UI provisional)</title>
  <link rel="stylesheet" href="assets/css/app.css" />
</head>
<body>
  <header class="top-bar">
    <div>
      <h1>Aquí Hay Tema <span class="badge-provisional">UI provisional · PLAYTEST_01</span></h1>
      <p class="status" id="status-reloj" aria-live="polite">Cargando…</p>
      <p class="status" id="status-meta"></p>
    </div>
    <nav class="btn-row partida-actions" aria-label="Acciones de partida">
      <a class="btn" href="index.php">Landing</a>
      <button type="button" id="btn-guardar">Guardar</button>
      <button type="button" id="btn-nueva">Nueva partida</button>
    </nav>
  </header>

  <main class="play-shell">
    <section class="panel play-summary" aria-labelledby="resumen-title">
      <h2 id="resumen-title">Resumen del día</h2>
      <div class="summary-grid" id="summary-grid">
        <div class="summary-card"><span class="label">Día / hora</span><strong id="sum-reloj">—</strong></div>
        <div class="summary-card"><span class="label">Residentes</span><strong id="sum-residentes">—</strong></div>
        <div class="summary-card"><span class="label">Encuentros hoy</span><strong id="sum-encuentros-hoy">—</strong><span class="mini-meta" id="sum-encuentros-activos"></span></div>
        <div class="summary-card"><span class="label">Buzón pendiente</span><strong id="sum-buzon">—</strong></div>
      </div>

      <div class="proximo-card" id="proximo-card">
        <div class="label">Próximo encuentro</div>
        <div id="proximo-cuerpo" class="proximo-cuerpo">No hay encuentros programados.</div>
      </div>

      <div class="reloj-toolbar" role="group" aria-label="Controles de reloj">
        <button type="button" id="btn-avanzar-1h">+1h</button>
        <button type="button" id="btn-avanzar-8h">+8h</button>
        <button type="button" class="primary" id="btn-proximo-encuentro">Ir al próximo encuentro</button>
      </div>

      <div class="avance-resumen" id="avance-resumen" hidden></div>
      <div class="resultado-panel" id="resultado-encuentro" hidden></div>
    </section>

    <div class="play-grid">
      <section class="panel" aria-labelledby="residentes-title">
        <h2 id="residentes-title">Residentes</h2>
        <p class="status">Selecciona desde la lista o desde una vivienda.</p>
        <div id="residentes-panel" class="residentes-list" role="list" aria-label="Lista de residentes"></div>

        <h2 style="margin-top:1rem" id="bloque-a-title">Bloque A</h2>
        <div class="grid-bloque-a" id="grid-bloque-a" role="list" aria-label="Viviendas del Bloque A"></div>
      </section>

      <section class="panel" aria-labelledby="centro-title">
        <h2 id="centro-title">Pueblo y actividad</h2>

        <div class="stack-section">
          <h3 id="mapa-title">Mapa / presencia NPC</h3>
          <div id="mapa-panel" class="status lugares-list" aria-labelledby="mapa-title">Cargando mapa…</div>
        </div>

        <div class="stack-section">
          <h3 id="encuentro-title">Proponer encuentro</h3>
          <p class="status">Elige participantes, hora compatible y lugar operativo.</p>
          <form id="form-encuentro" class="compact-form enc-form" onsubmit="return false;">
            <div class="form-row-2">
              <div>
                <label for="enc-a">Residente A</label>
                <select id="enc-a" name="residente_a" required></select>
              </div>
              <div>
                <label for="enc-b">Residente B</label>
                <select id="enc-b" name="residente_b" required></select>
              </div>
            </div>
            <p class="status form-hint" id="enc-participantes-hint" hidden></p>
            <label for="enc-tipo">Tipo</label>
            <select id="enc-tipo" name="tipo">
              <option value="conocerse">Conocerse</option>
              <option value="amistad">Amistad</option>
              <option value="romantico">Romántico</option>
            </select>
            <label for="enc-slot">Hora compatible</label>
            <select id="enc-slot" name="slot" required disabled>
              <option value="">Elige dos residentes distintos…</option>
            </select>
            <p class="status form-hint" id="enc-slots-hint">Calculando horas compatibles…</p>
            <label for="enc-lugar">Lugar</label>
            <select id="enc-lugar" name="lugar" required>
              <option value="">Cargando lugares…</option>
            </select>
            <button type="button" class="primary" id="btn-programar" disabled>Programar encuentro</button>
          </form>
          <p class="feedback" id="enc-feedback" role="status" aria-live="polite"></p>
        </div>

        <div class="stack-section">
          <h3>Encuentros</h3>
          <div class="encuentros-grid">
            <div>
              <div class="label">Programados / en curso</div>
              <div id="encuentros-activos" class="citas-list"></div>
            </div>
            <div>
              <div class="label">Finalizados / cancelados</div>
              <div id="encuentros-historial" class="citas-list"></div>
            </div>
          </div>
        </div>
      </section>

      <aside class="panel" aria-labelledby="ficha-title">
        <h2 id="ficha-title">Ficha visible</h2>
        <div class="ficha" id="ficha-panel" aria-labelledby="ficha-title">
          <p class="empty">Selecciona un residente.</p>
        </div>

        <div class="stack-section">
          <h3 id="relaciones-title">Relaciones</h3>
          <div id="relaciones-panel" class="citas-list" aria-labelledby="relaciones-title">Sin datos.</div>
        </div>

        <div class="stack-section">
          <h3 id="buzon-title">Buzón</h3>
          <div id="buzon-panel" class="citas-list" aria-labelledby="buzon-title">Bandeja vacía.</div>
        </div>

        <div class="stack-section">
          <h3 id="diario-title">Diario</h3>
          <div id="diario-panel" class="citas-list" aria-labelledby="diario-title">Sin entradas hoy.</div>
        </div>
      </aside>
    </div>
  </main>

  <script src="assets/js/play.js"></script>
</body>
</html>
