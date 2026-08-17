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
      <p class="status" id="status-reloj" aria-live="polite">Cargando…</p>
      <p class="status" id="status-meta"></p>
    </div>
    <nav class="btn-row" aria-label="Acciones de partida">
      <a class="btn" href="index.php">Landing</a>
      <a class="btn" href="dev.php">Modo dev</a>
      <button type="button" id="btn-avanzar-1h">+1h</button>
      <button type="button" id="btn-guardar">Guardar</button>
      <button type="button" id="btn-nueva">Nueva partida</button>
    </nav>
  </header>

  <main class="layout" style="max-width:1200px;grid-template-columns:1fr 1fr;">
    <section class="panel" aria-labelledby="bloque-a-title">
      <h2 id="bloque-a-title">Bloque A</h2>
      <div class="grid-bloque-a" id="grid-bloque-a" role="list" aria-label="Viviendas del Bloque A"></div>
      <h2 style="margin-top:1rem" id="mapa-title">Mapa técnico</h2>
      <div id="mapa-panel" class="status" aria-labelledby="mapa-title">Cargando mapa…</div>
    </section>

    <aside class="panel" aria-labelledby="encuentro-title">
      <h2 id="encuentro-title">Proponer encuentro</h2>
      <p class="status">PLACEHOLDER — resultados y textos no finales.</p>
      <form id="form-encuentro" onsubmit="return false;">
        <label for="enc-a">Residente A</label>
        <select id="enc-a" name="residente_a" required></select>
        <label for="enc-b">Residente B</label>
        <select id="enc-b" name="residente_b" required></select>
        <label for="enc-tipo">Tipo</label>
        <select id="enc-tipo" name="tipo">
          <option value="conocerse">Conocerse</option>
          <option value="amistad">Amistad</option>
          <option value="romantico">Romántico</option>
        </select>
        <label for="enc-hora">Hora</label>
        <select id="enc-hora" name="hora" required></select>
        <button type="button" class="primary" id="btn-programar">Programar (cafetería)</button>
      </form>
      <p class="status" id="enc-feedback" role="status" aria-live="polite"></p>

      <h2 style="margin-top:1rem" id="ficha-title">Ficha residente</h2>
      <div class="ficha" id="ficha-panel" aria-labelledby="ficha-title">
        <p class="empty">Selecciona un hueco ocupado.</p>
      </div>

      <h2 style="margin-top:1rem" id="buzon-title">Buzón / Diario</h2>
      <p class="status" id="buzon-panel" aria-labelledby="buzon-title">Bandeja vacía (estructura lista).</p>
    </aside>
  </main>

  <script src="assets/js/play.js"></script>
</body>
</html>
