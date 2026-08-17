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
  <title>Aquí Hay Tema — Modo dev</title>
  <link rel="stylesheet" href="assets/css/app.css" />
</head>
<body>
  <header class="top-bar">
    <div>
      <h1>Harness dev <span class="badge-provisional">no producción</span></h1>
      <p class="status">Partida: <code id="partida-id">—</code></p>
    </div>
    <div class="btn-row">
      <a class="btn" href="play.php">Jugar</a>
      <a class="btn" href="index.php">Landing</a>
    </div>
  </header>

  <div class="layout" style="max-width: 1400px; grid-template-columns: 1fr;">
    <div class="dev-grid">
      <section class="panel">
        <h2>Reloj</h2>
        <div class="btn-row">
          <button type="button" id="btn-1h">+1 hora</button>
          <button type="button" id="btn-4h">+4 horas</button>
          <button type="button" class="primary" id="btn-1d">+1 día</button>
        </div>

        <h2 style="margin-top:1rem">Residentes / vivienda</h2>
        <div class="btn-row">
          <button type="button" id="btn-placeholder">Crear placeholder dev</button>
        </div>
        <p class="status">Liberar vivienda:</p>
        <select id="sel-vivienda">
          <?php for ($i = 1; $i <= 16; $i++): $id = 'A' . str_pad((string) $i, 2, '0', STR_PAD_LEFT); ?>
          <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($id) ?></option>
          <?php endfor; ?>
        </select>
        <button type="button" id="btn-liberar">Liberar</button>

        <h2 style="margin-top:1rem">Citas y relaciones</h2>
        <label>Residente A <select id="sel-a"></select></label><br />
        <label>Residente B <select id="sel-b"></select></label><br />
        <label>Hora <select id="sel-hora">
          <?php for ($h = 8; $h <= 22; $h++): ?>
          <option value="<?= $h ?>"><?= str_pad((string) $h, 2, '0', STR_PAD_LEFT) ?>:00</option>
          <?php endfor; ?>
        </select></label>
        <div class="btn-row">
          <button type="button" id="btn-cita">Programar cita (cafetería)</button>
          <button type="button" id="btn-rel-social">Rel. social</button>
          <button type="button" id="btn-rel-romance">Rel. romance (dev)</button>
        </div>

        <h2 style="margin-top:1rem">Persistencia</h2>
        <div class="btn-row">
          <button type="button" id="btn-guardar">Guardar</button>
          <button type="button" id="btn-recargar">Recargar</button>
          <button type="button" id="btn-nueva">Nueva partida</button>
        </div>
      </section>

      <section class="panel">
        <h2>Log</h2>
        <pre class="inspect" id="log"></pre>
      </section>
    </div>

    <section class="panel" style="margin-top:1rem">
      <h2>Estado completo (JSON)</h2>
      <pre class="inspect" id="inspect">Cargando…</pre>
    </section>
  </div>

  <script src="assets/js/dev.js"></script>
</body>
</html>
