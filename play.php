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
      <button type="button" id="btn-guardar">Guardar</button>
      <button type="button" id="btn-nueva">Nueva partida</button>
    </div>
  </header>

  <div class="layout">
    <section class="panel">
      <h2>Bloque A — 16 viviendas</h2>
      <p class="status">Clic en residente para abrir ficha. Huecos libres = primer hueco disponible al incorporar.</p>
      <div class="grid-bloque-a" id="grid-bloque-a"></div>
    </section>

    <aside class="panel ficha" id="ficha-panel">
      <h2>Ficha residente</h2>
      <p class="empty">Selecciona un hueco ocupado.</p>
    </aside>
  </div>

  <script src="assets/js/play.js"></script>
</body>
</html>
