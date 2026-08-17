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
      <h1>Aquí Hay Tema <span class="badge-provisional">UI provisional v0</span></h1>
      <p class="status" id="status-reloj">Cargando…</p>
      <p class="status" id="status-meta"></p>
    </div>
    <div class="btn-row">
      <a class="btn" href="index.php">Landing</a>
      <a class="btn" href="dev.php">Modo dev</a>
      <button type="button" id="btn-avanzar-1h">+1h</button>
      <button type="button" id="btn-guardar">Guardar</button>
      <button type="button" id="btn-nueva">Nueva partida</button>
    </div>
  </header>

  <div class="layout" style="max-width:1200px;grid-template-columns:1fr 1fr;">
    <section class="panel">
      <h2>Bloque A</h2>
      <div class="grid-bloque-a" id="grid-bloque-a"></div>
      <h2 style="margin-top:1rem">Mapa técnico</h2>
      <div id="mapa-panel" class="status">Cargando mapa…</div>
    </section>

    <aside class="panel">
      <h2>Proponer encuentro</h2>
      <p class="status">PLACEHOLDER — resultados y textos no finales.</p>
      <label>Residente A <select id="enc-a"></select></label><br />
      <label>Residente B <select id="enc-b"></select></label><br />
      <label>Tipo <select id="enc-tipo">
        <option value="conocerse">Conocerse</option>
        <option value="amistad">Amistad</option>
        <option value="romantico">Romántico</option>
      </select></label><br />
      <label>Hora <select id="enc-hora"></select></label><br />
      <button type="button" class="primary" id="btn-programar">Programar (cafetería)</button>
      <p class="status" id="enc-feedback"></p>

      <h2 style="margin-top:1rem">Ficha residente</h2>
      <div class="ficha" id="ficha-panel"><p class="empty">Selecciona un hueco ocupado.</p></div>

      <h2 style="margin-top:1rem">Buzón / Diario</h2>
      <p class="status" id="buzon-panel">Bandeja vacía (estructura lista).</p>
    </aside>
  </div>

  <script src="assets/js/play.js"></script>
</body>
</html>
