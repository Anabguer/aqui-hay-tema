<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
$ahtBusterFile = __DIR__ . '/assets/aht-cache-buster.txt';
$ahtUi = 'v3-static';
if (is_file($ahtBusterFile)) {
    $ahtBusterRaw = trim((string) file_get_contents($ahtBusterFile));
    if ($ahtBusterRaw !== '') {
        $ahtUi = $ahtBusterRaw;
    }
}
$ahtPwaBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/play.php')), '/') . '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <meta name="theme-color" content="#2a2218"/>
  <meta name="mobile-web-app-capable" content="yes"/>
  <meta name="apple-mobile-web-app-capable" content="yes"/>
  <meta name="apple-mobile-web-app-status-bar-style" content="default"/>
  <meta name="apple-mobile-web-app-title" content="Aquí Hay Tema"/>
  <meta name="aht-ui" content="v3"/>
  <title>Aquí Hay Tema</title>
  <link rel="manifest" href="<?= htmlspecialchars($ahtPwaBase, ENT_QUOTES, 'UTF-8') ?>manifest.webmanifest"/>
  <link rel="icon" href="assets/brand/pwa-icon-192.png" type="image/png" sizes="192x192"/>
  <link rel="apple-touch-icon" href="assets/brand/logo-aht.png"/>
  <link rel="stylesheet" href="assets/css/play-v3.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-capas.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-app.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-shell-ui.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-shell-art.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-capas-shell.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-mensajitos.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-mapa-canonico.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-bloques-residencias.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-musica.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-audio.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-regalos.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-lab.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-responsive.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/design-system/tokens.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/design-system/components.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/design-system/screens/inicio-views.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/design-system/screens/inicio-mobile.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/design-system/screens/inicio-evento-pueblo-mobile.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/design-system/screens/modals.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/design-system/screens/capas-ds.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-cotilleos.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/design-system/cotilleos-scrapbook-v1.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-vecinos.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/design-system/vecinos-celdas-persona-v1.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-ficha.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-organizar.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/design-system/org-plan-scrapbook-v1.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-agenda.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-misiones.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-vida.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-enc-int.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-notas-mapa.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-tutorial-ds.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-avisos.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-desktop-shell.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-inicio-override.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-visual-review.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-visual-interior.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-visual-replica.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/design-system/legibilidad-global.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/design-system/screens/inicio-desktop.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/design-system/screens/inicio-evento-pueblo-desktop.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/design-system/mensajitos-cartas-persona-v1.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/design-system/ficha-neni-ref-v1.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/design-system/modal-titles-aht.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/design-system/modals-secondary-unified.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <style>
    .tutorial-pista {
      margin: 0; padding: .45rem .85rem; font-size: .88rem;
      font-family: Fraunces, Georgia, serif; font-style: italic;
      background: #f3e6cc; border-bottom: 2px solid #c9b8a0; color: #2a2218;
    }
        body.play-v3 .playtest-guia,
    body.play-v3 .playtest-cheats,
    body.play-v3 .tiempo-juego { display: none !important; }
    body.play-v3[data-tutorial-zona="buzon"] [data-open="buzon"] { outline: 2px solid #c45; }
    body.play-v3[data-tutorial-zona="vecinos"] [data-open="vecinos"] { outline: 2px solid #c45; }
    body.play-v3[data-tutorial-zona="organizar"] [data-open="organizar"] { outline: 2px solid #c45; }
    .taller-debug { display: none; max-width: 42rem; margin: .25rem .5rem; padding: .4rem .6rem; font: 12px/1.35 monospace; background: #1a1a1a; color: #cfc; white-space: pre-wrap; }
    body.play-v3[data-taller="1"] .taller-debug.is-on { display: block; }
    .taller strong.lab { color: #c45; }
    .playtest-cheats {
      display: flex; flex-wrap: wrap; gap: .55rem; align-items: center;
      margin: 0 .5rem .5rem; padding: .65rem .8rem;
      background: #2a2218; color: #f7f1e8;
      border: 3px solid #c45; border-radius: 6px;
      position: relative; z-index: 40;
    }
    .playtest-cheats .pc-label {
      font: 800 .85rem Nunito, "Segoe UI", sans-serif;
      letter-spacing: .04em; text-transform: uppercase; color: #f3b1c3;
      margin-right: .25rem;
    }
    .playtest-cheats button {
      border: 2px solid #f7f1e8; background: #c45; color: #fff;
      font: 800 1rem Nunito, "Segoe UI", sans-serif;
      padding: .55rem 1rem; cursor: pointer; border-radius: 4px;
      min-width: 5.5rem;
    }
    .playtest-cheats button:hover { background: #a8324a; }
    .playtest-cheats .pc-msg { font-size: .85rem; color: #eadfd4; margin-left: .35rem; }
    .playtest-guia {
      margin: .35rem .5rem .6rem;
      padding: .75rem 1rem;
      max-width: 52rem;
      background: #f7f1e8;
      border: 1px solid #c9b8a0;
      color: #2a2218;
      font: 14px/1.45 Georgia, "Times New Roman", serif;
    }
    .playtest-guia h2 { margin: 0 0 .35rem; font-size: 1.15rem; letter-spacing: .02em; }
    .playtest-guia h3 { margin: .7rem 0 .25rem; font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; color: #6a5848; }
    .playtest-guia ul { margin: .2rem 0 .4rem; padding-left: 1.1rem; }
    .playtest-guia li { margin: .15rem 0; }
    .playtest-guia .evento { background: #fff; border-left: 3px solid #c45; padding: .5rem .7rem; margin: .4rem 0; }
    .playtest-guia .evento strong { display: block; margin-bottom: .25rem; }
    .playtest-guia .pista { padding: .45rem .6rem; margin: .35rem 0; background: #efe6d8; }
    .playtest-guia .pista.ojo { border-left: 3px solid #c45; }
    .playtest-guia .pista.puedes { border-left: 3px solid #7a9e6a; }
    .playtest-guia .objs { list-style: none; padding-left: 0; }
    .playtest-guia .objs li { padding-left: 1.4rem; position: relative; }
    .playtest-guia .objs li::before { content: "?"; position: absolute; left: 0; }
    .playtest-guia .objs li.hecho::before { content: "?"; color: #4a7a3a; }
    .playtest-guia .objs li.hecho { color: #4a7a3a; }
    .playtest-guia details.debug-tec { margin-top: .6rem; font-family: ui-monospace, Consolas, monospace; font-size: 12px; color: #555; }
    .playtest-guia .meta-reloj { font-size: .85rem; color: #6a5848; margin: 0 0 .4rem; }
    .playtest-diag {
      margin-top: .75rem; border: 1px solid #8a7a66; background: #1b1814; color: #d8d0c4;
      border-radius: 4px; padding: .35rem .55rem;
    }
    .playtest-diag summary {
      cursor: pointer; font: 800 .8rem Nunito, "Segoe UI", sans-serif;
      color: #f3b1c3; letter-spacing: .03em;
    }
    .playtest-diag .diag-actions { display: flex; gap: .4rem; margin: .45rem 0; flex-wrap: wrap; }
    .playtest-diag .diag-actions button {
      border: 1px solid #c9b8a0; background: #2a2218; color: #f7f1e8;
      font: 700 .75rem Nunito, "Segoe UI", sans-serif; padding: .3rem .55rem; cursor: pointer;
    }
    .playtest-diag pre {
      margin: 0; max-height: 22rem; overflow: auto; white-space: pre-wrap;
      font: 11px/1.4 ui-monospace, Consolas, monospace; color: #cfc6b8;
    }
    .aht-debug-float { position: fixed; z-index: 90; left: 10px; bottom: 10px; }
    .aht-debug-toggle { border: 1px solid #8a7a66; background: #fff6c8; font: 700 .72rem Nunito,sans-serif; padding: .35rem .55rem; border-radius: 999px; cursor: pointer; opacity: .92; }
    .aht-debug-panel { position: absolute; left: 0; bottom: calc(100% + 6px); width: min(260px, 88vw); padding: .5rem; background: #fffdf6; border: 1px solid #8a7a66; display: flex; flex-wrap: wrap; gap: .3rem; box-shadow: 2px 3px 8px rgba(0,0,0,.12); }
    .aht-debug-panel[hidden] { display: none !important; }
    .aht-debug-title { width: 100%; margin: 0; font-size: .65rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #7a7164; }
    .aht-debug-panel button { border: 1px solid #8a7a66; background: #fff; font: inherit; font-size: .7rem; font-weight: 700; padding: .2rem .4rem; cursor: pointer; }
    .tut-caras {
      display: flex; justify-content: center; gap: .35rem; flex-wrap: wrap;
      margin: .45rem 0 .55rem; max-width: 100%; overflow: hidden;
    }
    .tut-caras .cara {
      width: 52px; height: 52px; flex: 0 0 52px; overflow: hidden;
      border-radius: 50%; border: 2px solid rgba(120,96,72,.35); background: #fff;
      display: inline-flex; align-items: center; justify-content: center;
    }
    .tut-caras img, .tut-caras .cara img {
      width: 52px; height: 52px; max-width: 52px; max-height: 52px;
      object-fit: cover; object-position: 50% 20%; transform: scale(1.1); transform-origin: 50% 14%; display: block; border-radius: 50%;
    }
    .caras-clip { display: flex; justify-content: center; gap: .35rem; margin-bottom: .35rem; flex-wrap: wrap; overflow: hidden; }
    .caras-clip .cara {
      width: 52px; height: 52px; flex: 0 0 52px; overflow: hidden;
      border-radius: 50%; border: 2px solid rgba(120,96,72,.35);
    }
    .caras-clip img, .caras-clip .cara img { width: 52px; height: 52px; max-width: 52px; max-height: 52px; border-radius: 50%; object-fit: cover; object-position: 50% 20%; transform: scale(1.1); transform-origin: 50% 14%; display: block; }
    .mision-accion {
      margin-top: .35rem; border: 1px solid #8a7a66; background: #fff6c8;
      font: inherit; font-size: .75rem; font-weight: 800; padding: .25rem .5rem; cursor: pointer;
    }
    .mision-pp { margin-bottom: .55rem; }
    .mision-pp.mision-bloqueada { opacity: .55; }
    .mision-pp-head { display: flex; align-items: center; gap: .45rem; margin-bottom: .2rem; }
    .mision-pp-tit { font-size: .92rem; }
    .mision-pp-texto { margin: 0; font-size: .82rem; line-height: 1.35; }
    .mision-bolita {
      width: 1.15rem; height: 1.15rem; flex: 0 0 1.15rem;
      border: 2px solid #5c4f42; border-radius: 50%; background: #fffef8;
      display: inline-flex; align-items: center; justify-content: center;
      box-shadow: 1px 1px 0 rgba(60,48,36,.15);
    }
    .mision-bolita.bloqueada { background: #ece6da; border-color: #a89a88; opacity: .7; }
    .mision-bolita.cumplida { background: #fff6c8; border-color: #5c4f42; }
    .mision-check {
      font-family: "Comic Sans MS", "Segoe Print", cursive;
      font-size: .95rem; font-weight: 900; color: #3d3228;
      transform: rotate(-8deg); line-height: 1;
    }
    .carta-msg.leida { opacity: .88; }
    .carta-msg.leida .cuerpo { color: #5a5248; }
    /* Retratos: rostro visible sin redise—ar capas */
    .capa-vecinos .vecino img,
    .vecino-celda img,
    .ficha-hero .cara img,
    .caras-clip img,
    .caras-clip .cara img,
    .tut-caras img,
    .tut-caras .cara img,
    .prox-caras img {
      object-fit: cover;
      object-position: 50% 20%;
      transform: scale(1.1);
      transform-origin: 50% 14%;
    }
    .caras-clip img, .caras-clip .cara img { width: 52px; height: 52px; border-radius: 50%; }
    .prox-caras { display: flex; gap: .35rem; margin-bottom: .35rem; }
    .prox-caras img, .prox-cara-ini {
      width: 40px; height: 40px; border-radius: 50%; border: 2px solid rgba(120,96,72,.35);
      display: inline-flex; align-items: center; justify-content: center; background: #fff;
      font-weight: 800; font-size: .9rem;
    }
    .celestine-nota .obj-vecinos-tit { color: #d0697a; }
    /* Lo que sabes: iconos peque—os alineados con la l—nea manuscrita */
    .capa-ficha .ficha-sabes-ico { font-size: .8em; margin-right: .28rem; }
      </style>
</head>
<body class="play-v3" data-ui="v3" data-debug="0">
  <div class="aht-debug-float" data-debug-float>
    <button type="button" class="aht-debug-toggle" data-debug-toggle aria-expanded="false" title="Herramientas DEBUG">?? DEBUG</button>
    <div class="aht-debug-panel" data-debug-panel hidden>
      <p class="aht-debug-title">DEBUG</p>
      <button type="button" id="btn-debug-lab-open" class="btn-open-lab">Abrir laboratorio</button>
      <button type="button" id="btn-debug-nueva">Nueva partida</button>
      <button type="button" id="btn-debug-guardar">Guardar</button>
      <button type="button" data-horas="1">+1h</button>
      <button type="button" data-horas="8">+8h</button>
      <button type="button" data-horas="24">+1 d—a</button>
      <button type="button" data-horas="72">+3 d—as</button>
      <button type="button" data-horas="168">+7 d—as</button>
      <button type="button" data-horas="720">+30 d—as</button>
      <button type="button" id="btn-debug-proximo">Ir al pr—ximo</button>
      <button type="button" id="btn-debug-copy" data-debug-copy>Copiar debug</button>
      <button type="button" id="btn-debug-download" data-debug-download>Descargar debug</button>
      <button type="button" id="btn-debug-copy-estado" data-debug-copy-estado>Copiar estado</button>
      <button type="button" id="btn-debug-parejas-crear">Crear parejas de prueba</button>
      <button type="button" id="btn-debug-parejas-quitar">Quitar parejas de prueba</button>
      <div class="aht-sfx-debug" aria-label="Prueba de efectos de sonido">
        <p class="aht-sfx-debug-title">Prueba de sonidos</p>
        <button type="button" data-aht-sfx="mensajito">Mensajito</button>
        <button type="button" data-aht-sfx="cotilleo">Cotilleo</button>
        <button type="button" data-aht-sfx="mision">Misi—n</button>
        <button type="button" data-aht-sfx="descubrimiento">Descubrimiento</button>
        <button type="button" data-aht-sfx="romance">Romance</button>
        <button type="button" data-aht-sfx="conflicto">Conflicto</button>
        <button type="button" data-aht-sfx="llegada">Llegada</button>
        <button type="button" data-aht-sfx="nuevo_dia">Nuevo d—a</button>
      </div>
      <span class="msg" data-debug-msg></span>
    </div>
  </div>
  <div class="play-lab-overlay" data-play-lab hidden>
    <div class="play-lab-shell" role="dialog" aria-label="Laboratorio DEBUG">
      <header class="play-lab-head">
        <h2>Laboratorio de playtest</h2>
        <span class="lab-reloj" data-lab-reloj></span>
        <button type="button" data-lab-close>Cerrar</button>
      </header>
      <nav class="play-lab-tabs" aria-label="Secciones">
        <button type="button" data-lab-tab="resumen" aria-selected="true">Resumen</button>
        <button type="button" data-lab-tab="vecinos">Vecinos</button>
        <button type="button" data-lab-tab="relaciones">Relaciones</button>
        <button type="button" data-lab-tab="historial">Historial</button>
        <button type="button" data-lab-tab="tecnico">Datos t—cnicos</button>
      </nav>
      <div class="play-lab-body">
        <section class="play-lab-panel" data-lab-panel="resumen" data-lab-resumen></section>
        <section class="play-lab-panel" data-lab-panel="vecinos" hidden>
          <div class="lab-select-row">
            <select data-lab-vecino-select aria-label="Vecino"></select>
          </div>
          <div data-lab-vecinos-chips style="margin-bottom:.6rem"></div>
          <div data-lab-vecino-detalle></div>
        </section>
        <section class="play-lab-panel" data-lab-panel="relaciones" hidden>
          <div class="lab-select-row">
            <select data-lab-par-a aria-label="Persona A"></select>
            <span>+</span>
            <select data-lab-par-b aria-label="Persona B"></select>
            <button type="button" data-lab-inspeccionar-par>Inspeccionar par</button>
          </div>
          <div data-lab-par-detalle></div>
        </section>
        <section class="play-lab-panel" data-lab-panel="historial" hidden>
          <div data-lab-cronologia></div>
        </section>
        <section class="play-lab-panel" data-lab-panel="tecnico" hidden>
          <p style="font-size:.78rem;color:#6a5d4f">Exportaci—n t—cnica completa (estado + historial de sesi—n DEBUG).</p>
          <div class="lab-export-bar">
            <button type="button" id="btn-lab-debug-export" data-debug-copy>Copiar debug completo</button>
            <button type="button" id="btn-lab-debug-estado" data-debug-copy-estado>Copiar solo estado</button>
            <button type="button" data-debug-download>Descargar debug</button>
          </div>
          <pre class="lab-json-pre" data-lab-tecnico-pre>(usa los botones de arriba)</pre>
        </section>
      </div>
    </div>
  </div>
  <p class="tutorial-pista" data-tutorial-pista hidden></p>
  <div class="playtest-cheats" data-playtest-cheats hidden>
    <span class="pc-label">Acelerar tiempo</span>
    <button type="button" data-horas="1">+1h</button>
    <button type="button" data-horas="8">+8h</button>
    <button type="button" data-horas="24">+1 d—a</button>
    <button type="button" id="btn-proximo-lab">Ir al pr—ximo</button>
    <span class="pc-msg" data-taller-msg-lab></span>
  </div>
  <aside class="playtest-guia" data-playtest-guia hidden>
    <h2 data-pg-titulo>PRUEBA DEL PUEBLO</h2>
    <p class="meta-reloj" data-pg-reloj></p>
    <h3>Ahora mismo</h3>
    <ul data-pg-ahora></ul>
    <h3>Qu— hacer ahora</h3>
    <ol data-pg-hacer></ol>
    <div class="evento" data-pg-evento hidden></div>
    <div data-pg-pistas></div>
    <h3>Objetivos de esta partida</h3>
    <ul class="objs" data-pg-objs></ul>
    <details class="playtest-diag" data-playtest-diag open>
      <summary>Registro t—cnico del playtest (copiar para ChatGPT / Carlos I)</summary>
      <div class="diag-actions">
        <button type="button" data-diag-copy>Copiar todo</button>
        <button type="button" data-diag-clear-ui>Limpiar vista</button>
      </div>
      <pre data-playtest-diag-log>(a—n no hay eventos)</pre>
    </details>
    <details class="debug-tec">
      <summary>Datos t—cnicos (resumen avance)</summary>
      <pre data-taller-debug hidden></pre>
    </details>
  </aside>
    <div class="game-shell">
    <div class="inicio-stage">
      <section class="inicio-mobile" data-inicio-view="mobile" aria-label="Inicio m&oacute;vil">
        <header class="game-top game-top-mock">
      <div class="brand-col">
        <h1 class="brand" aria-label="Aqu&iacute; Hay Tema">
          <span class="brand-heart brand-heart--lead" aria-hidden="true"></span>
          <span class="brand-text">AQU&Iacute; HAY TEMA</span>
          <span class="brand-heart" aria-hidden="true"></span>
        </h1>
      </div>
      <div class="top-meta-row">
        <p class="top-meta-line" data-top-meta-mobile></p>
        <div class="top-center">
        <div class="top-reloj">
          <div class="obj-dia" style="--rot:-2deg">
            <div class="obj-dia-placa">
              <span class="obj-dia-num" data-dia-num>&#8212;</span>
            </div>
            <div class="obj-dia-cuerpo">
              <span class="obj-dia-estacion" data-dia-estacion>Primavera</span>
              <span class="obj-dia-meta" data-dia-meta>&#8212;</span>
            </div>
            <span class="sr-only" data-fecha></span>
          </div>
          <div class="obj-hora" style="--rot:3deg" aria-label="Hora del pueblo">
            <span class="obj-hora-ico" aria-hidden="true"></span>
            <span class="obj-hora-val" data-hora>&#8212;</span>
          </div>
          <span class="es-noche" data-es-noche hidden>
            <svg class="es-noche-luna" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            <span class="es-noche-txt">Es de noche</span>
          </span>
          <button type="button" class="pasar-rato" data-pasar-rato title="Avanza el tiempo exactamente 1 hora" aria-label="Pasar el rato">
            <span class="pasar-rato-ico" aria-hidden="true">&#9654;</span>
            <span class="pasar-rato-txt">Pasar el rato</span>
          </button>
        </div>
        </div>
      </div>
      <button type="button" class="top-vida top-vida-btn" data-open="vida_pueblo" aria-label="Vida del pueblo, pulsa para m&aacute;s informaci&oacute;n">
        <span class="obj-vida-kicker">Vida del pueblo</span>
        <span class="top-vida-num" data-vida-num aria-hidden="true">0</span>
        <svg class="corazon-svg corazon-org" viewBox="0 0 58 52" aria-hidden="true">
          <defs>
            <clipPath id="corazon-clip-mob"><path d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/></clipPath>
            <linearGradient id="corazon-agua-grad-mob" x1="29" y1="52" x2="29" y2="0" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#d46278"/>
              <stop offset="55%" stop-color="#e57d90"/>
              <stop offset="100%" stop-color="#f0a8b6"/>
            </linearGradient>
          </defs>
          <path class="corazon-bg" d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/>
          <path class="corazon-fill-path" clip-path="url(#corazon-clip-mob)" fill="url(#corazon-agua-grad-mob)" d="" data-corazon-fill/>
          <path class="corazon-fill-surface" clip-path="url(#corazon-clip-mob)" fill="none" stroke="rgba(255,255,255,.62)" stroke-width="1.15" stroke-linecap="round" d="" data-corazon-surface/>
          <path class="corazon-stroke" fill="none" d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/>
        </svg>
        <span class="sr-only" data-vida-pct>0%</span>
      </button>
      <div class="control-audio" aria-label="Controles de audio">
        <button type="button" class="control-musica" data-musica-toggle aria-pressed="true" aria-label="Desactivar m&uacute;sica" title="Desactivar m&uacute;sica">
          <span class="control-musica-ico" aria-hidden="true">&#9834;</span>
        </button>
        <button type="button" class="control-efectos" data-efectos-toggle aria-pressed="true" aria-label="Desactivar efectos de sonido" title="Desactivar efectos de sonido">
          <span class="control-efectos-ico" aria-hidden="true">&#10022;</span>
        </button>
        <button type="button" class="control-inventario" data-open="inventario" aria-label="Abrir inventario" title="Inventario">
          <span class="control-inventario-ico" aria-hidden="true">&#127873;</span>
        </button>
      </div>
    </header>
        <div class="inicio-layout inicio-mobile-layout">
          <div class="inicio-chrome-left inicio-mobile-tiles">
            <section class="shell-grupo shell-grupo-buzon">
          <div class="mensajitos-wrap">
            <button type="button" class="obj-buzon" data-open="buzon" aria-label="Abrir mensajitos">
              <span class="game-left-tile-ico obj-buzon-ico-wrap" aria-hidden="true"><img class="obj-buzon-img" src="assets/play-v3/hud/sobre.png" alt="" width="72" height="58"/><span class="obj-buzon-badge" data-buzon-badge hidden>0</span></span>
              <span class="obj-buzon-txt game-left-tile-label">Mensajitos</span>
              <span class="obj-buzon-flecha" aria-hidden="true">&#8250;</span>
            </button>
          </div>
        </section>
            <section class="shell-grupo shell-grupo-resumen">
          <button type="button" class="obj-vecinos-resumen celestine-nota" data-open="vecinos" aria-label="Ver vecinos">
            <span class="libreta-kicker">Celestine apunta</span>
            <span class="obj-vecinos-preview game-left-tile-ico" data-vecinos-preview aria-hidden="true"></span>
            <span class="obj-vecinos-total-badge" data-vecinos-total-badge hidden></span>
            <div class="obj-vecinos-head">
              <span class="obj-vecinos-tit game-left-tile-label">VECINOS</span>
              <span class="obj-vecinos-poblacion game-left-tile-meta" data-vecinos-poblacion></span>
            </div>
            <div class="obj-vecinos-stats" data-resumen-stats></div>
          </button>
        </section>
            <section class="shell-grupo shell-grupo-planes">
<button type="button" class="obj-nuevo-plan obj-proximo-cta" data-open="organizar" aria-label="Crear plan">
              <span class="obj-nuevo-plan-ico" aria-hidden="true">+</span>
              <span class="obj-nuevo-plan-txt game-left-tile-label">PLAN</span>
            </button>
</section>
          </div>
        </div>
      </section>

<div class="inicio-map-host game-map-wrap">
        <div class="plan-notif" data-plan-notif hidden role="status" aria-live="polite">
          <button type="button" class="plan-notif-inner" data-plan-notif-btn>
            <span class="plan-notif-kicker">Plan confirmado</span>
            <span class="plan-notif-nombres" data-plan-notif-nombres></span>
            <span class="plan-notif-meta" data-plan-notif-meta></span>
          </button>
        </div>
  <div class="play-stage">
    <div class="play-root pc" data-pueblo="temprano" data-aforo="1">
      <div class="board-scroll">
        <div class="board-fit">
          <div class="mapa-canonico" data-mapa-canonico>
            <img class="mapa-canonico-bg" src="assets/play-v3/mapa_canonico.png" alt="Mapa del pueblo" width="618" height="404"/>
            <div class="mapa-zonas-layer" data-mapa-zonas></div>
          </div>
          <div class="edificios-layer" data-edificios-layer aria-hidden="true"></div>
          <aside class="selector nota-mapa ds-modal-sheet">
            <button type="button" class="cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
            <button type="button" class="nota-atras" data-consulta-atras hidden aria-label="Atr&aacute;s">? Atr&aacute;s</button>
            <p class="libreta-kicker">Un vistazo al lugar</p>
            <h3 data-s-tit></h3>
            <p class="cotilleo" data-s-coti></p>
            <div class="destinos" data-s-btns></div>
          </aside>
          <aside class="quien nota-mapa ds-modal-sheet">
            <button type="button" class="cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
            <button type="button" class="nota-atras" data-consulta-atras hidden aria-label="Atr&aacute;s">? Atr&aacute;s</button>
            <header class="quien-bloque quien-bloque--lugar">
              <h3 class="quien-lugar-tit" data-q-tit></h3>
              <p class="quien-horario" data-q-horario hidden></p>
            </header>
            <section class="quien-bloque quien-bloque--presencia">
              <p class="libreta-kicker quien-kicker">&#8212;Qui&#8212;n est&#8212;?</p>
              <p class="quien-vacio" data-q-sum hidden></p>
              <div class="quien-list quien-residentes" data-q-list></div>
            </section>
            <div class="quien-bloque quien-bloque--tema quien-tema" data-q-tema hidden></div>
            <div class="destinos" data-q-btns></div>
          </aside>
        </div>
      </div>
      <div class="mesa">
        <div class="mesa-papel">
          <span class="corazon" aria-label="Vida del pueblo">
            <span class="corazon-papel">
              <span class="corazon-fill" style="--fill:0%"></span>
              <span class="corazon-pct" data-vida-pct></span>
            </span>
          </span>
          <div class="dia">
            <div class="dow" data-dow>&#8212;</div>
            <div class="fecha" data-fecha></div>
            <div class="hora" data-hora>&#8212;</div>
          </div>
          <div class="tiempo-juego" aria-label="Avanzar el tiempo">
            <button type="button" class="tique-hora" data-horas="1" title="Avanzar una hora">+1 h</button>
            <button type="button" class="tique-dia" data-horas="24" title="Avanzar un d&#8212;a">+1 d&#8212;a</button>
          </div>
          <div class="hud-right">
            <div class="dinero" data-dinero>&#8212;</div>
            <button type="button" class="buzon" data-open="buzon" aria-label="Abrir mensajitos">
              <img src="assets/play-v3/hud/sobre.png" alt=""/>
              <span class="badge">0</span>
              <img class="lacre-hud" src="assets/play-v3/hud/lacre.png" alt="OJO"/>
            </button>
            <button type="button" class="btn-organizar-pc" data-open="organizar">Organizar</button>
            <button type="button" class="btn-nueva-mesa" id="btn-nueva-mesa" title="Empezar de cero">Nueva partida</button>
          </div>
        </div>
      </div>
      <nav class="dock">
        <button type="button" data-close class="is-on"><img src="assets/play-v3/dock/sello_pueblo.png" alt=""/>Pueblo</button>
        <button type="button" data-open="diario"><img src="assets/play-v3/dock/sello_diario.png" alt=""/>Diario</button>
        <button type="button" data-open="organizar"><img src="assets/play-v3/dock/sello_organizar.png" alt=""/>Organizar</button>
        <button type="button" data-open="vecinos"><img src="assets/play-v3/dock/sello_vecinos.png" alt=""/>Vecinos</button>
      </nav>
      <div class="velo" data-close></div>
      <p class="feedback-toast" data-toast></p>
      <aside class="tut-intro" data-tut-intro hidden aria-live="polite">
        <div class="tut-papel" data-tut-papel>
          <button type="button" class="cerrar tut-skip ds-modal-close" data-tut-skip aria-label="Saltar tutorial">Saltar</button>
          <div class="tut-papel-cabecera">
          <div class="tut-hero" data-tut-hero hidden></div>
          <h2 class="tut-titulo" data-tut-tit></h2>
          </div>
          <div class="tut-papel-cuerpo">
          <p class="tut-intro-line" data-tut-intro-line hidden></p>
          <p class="tut-intro-extra" data-tut-intro-extra hidden></p>
          <div class="tut-caras" data-tut-caras hidden></div>
          <p class="tut-bloques-pref" data-tut-bloques-pref hidden></p>
          <div class="tut-bloques" data-tut-bloques hidden></div>
          <div class="tut-tareas" data-tut-tareas hidden></div>
          <p class="tut-cierre" data-tut-cierre hidden></p>
          </div>
          <div class="tut-papel-pie">
            <div class="tut-pasos" data-tut-pasos></div>
            <div class="tut-acciones">
            <button type="button" class="cta ghost" data-tut-atras hidden>Atrás</button>
            <button type="button" class="cta tut-cta-final" data-tut-siguiente>Siguiente</button>
          </div>
            </div>
        </div>
      </aside>
      <aside class="tut-finale" data-tut-finale hidden aria-live="polite">
        <div class="tut-papel">
          <div class="tut-fin-hero" data-tut-fin-hero aria-hidden="true"></div>
          <h2 class="tut-fin-titulo" data-tut-fin-tit></h2>
          <p class="tut-fin-lead" data-tut-fin-lead></p>
          <hr class="tut-fin-rule" aria-hidden="true"/>
          <p class="tut-fin-rest" data-tut-fin-rest></p>
          <p class="tut-texto" data-tut-fin-texto hidden></p>
          <button type="button" class="cta tut-fin-cta" data-tut-fin-ok>Que empiece el tema</button>
        </div>
      </aside>
      <aside class="vida-derrota" data-vida-derrota hidden aria-live="assertive">
        <div class="tut-papel vida-derrota-papel">
          <p class="vida-derrota-ico" aria-hidden="true">&#128148;</p>
          <h2 class="ds-modal-tit ds-modal-tit--ink">Se nos va de las manos</h2>
          <p class="tut-texto">La vida del pueblo ha llegado a un punto cr&#8212;tico. Celestine no ha podido mantener el equilibrio.</p>
          <button type="button" class="cta" data-vida-derrota-ok>Entendido</button>
        </div>
      </aside>

      <aside class="capa capa-vecinos" aria-label="Vecinos del pueblo">
        <button type="button" class="cerrar vecinos-cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
        <header class="vecinos-cab">
          <div class="ds-modal-head vecinos-head">
            <h2 class="ds-modal-tit ds-modal-tit--ink">Vecinos del pueblo</h2>
            <span class="vecinos-cuenta-wrap" data-vec-cuenta-wrap><span class="vecinos-cuenta ds-pill ds-pill--pink" data-vecinos-count></span></span>
          </div>
        </header>
        <div class="vec-tabs" role="tablist" aria-label="Vecinos y relaciones">
          <button type="button" class="vec-tab is-on" data-vec-tab="vecinos" role="tab" aria-selected="true">VECINOS</button>
          <button type="button" class="vec-tab vec-tab--lavanda" data-vec-tab="relaciones" role="tab" aria-selected="false">RELACIONES</button>
        </div>
        <div class="vec-panel" data-vec-panel="vecinos">
          <div class="vec-busca-tira">
            <label class="vec-busca-wrap">
              <span class="vec-busca-ico" aria-hidden="true">&#8981;</span>
              <input type="search" class="vec-busca-inp" data-vec-busca placeholder="Buscar vecino..." autocomplete="off" spellcheck="false"/>
            </label>
          </div>
          <div class="vecinos-grid" data-vecinos-list></div>
          <p class="mensajitos-hint vecinos-hint">&#11088; Toca un vecino para ver su historia, estado y relaciones</p>
        </div>
        <div class="vec-panel" data-vec-panel="relaciones" hidden>
          <div class="vec-rel-filtros" data-vec-rel-filtros></div>
          <label class="vec-rel-dd-wrap">
            <select class="vec-rel-persona" data-vec-rel-persona aria-label="Filtrar por vecino"></select>
          </label>
          <div class="vec-rel-scroll capa-scroll" data-vec-rel-list></div>
          <p class="mensajitos-hint vec-rel-hint">&#128156; Las relaciones pueden cambiar con cada plan</p>
        </div>
      </aside>
      <aside class="capa capa-agenda agenda-modal ds-modal-sheet" aria-label="Planes de Celestine">
        <span class="agenda-pin agenda-pin-l" aria-hidden="true"></span>
        <button type="button" class="cerrar agenda-cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
        <header class="agenda-cab">
          <div class="ds-modal-head">
            <div class="ds-modal-head-row">
              <span class="ds-modal-icon ds-modal-icon--brown" aria-hidden="true">&#128197;</span>
              <h2 class="agenda-tit ds-modal-tit ds-modal-tit--brown">Planes</h2>
            </div>
            <p class="ds-modal-sub agenda-sub">Lo que est&#8212; por venir.</p>
          </div>
        </header>
        <div class="agenda-list capa-scroll" data-agenda-list></div>
      </aside>
      <aside class="capa capa-ficha ds-modal-sheet" aria-label="Ficha de vecino">
        <span class="ficha-tape ficha-tape-l" aria-hidden="true"></span>
        <span class="ficha-tape ficha-tape-r" aria-hidden="true"></span>
        <button type="button" class="cerrar ficha-cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
        <header class="ficha-top">
          <h2 class="ficha-tit ds-modal-tit ds-modal-tit--ink">Ficha de vecino</h2>
          <button type="button" class="ficha-volver" data-ficha-volver>&larr; VECINOS</button>
        </header>
        <section class="ficha-hero" aria-label="Perfil del vecino">
          <div class="ficha-cara-ring" data-ficha-cara-ring>
            <div class="ficha-cara" data-ficha-img></div>
          </div>
          <div class="ficha-hero-info">
            <div class="ficha-nombre-nav">
              <button type="button" class="ficha-nav ficha-nav-prev" data-ficha-nav-prev aria-label="Vecino anterior">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </button>
              <h3 class="ficha-nombre" data-ficha-nombre></h3>
              <button type="button" class="ficha-nav ficha-nav-next" data-ficha-nav-next aria-label="Vecino siguiente">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.5 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </button>
            </div>
            <p class="ficha-edad" data-ficha-edad hidden></p>
            <p class="ficha-trabajo" data-ficha-trabajo hidden></p>
            <p class="ficha-desde" data-ficha-desde></p>
            <div class="ficha-animo-row" data-ficha-animo-row>
              <div class="ficha-animo-pill" data-ficha-animo-pill>
                <span class="ficha-animo-ico" data-ficha-animo-ico aria-hidden="true"></span>
                <span class="ficha-animo-val" data-ficha-animo-text></span>
                <button type="button" class="ficha-animo-q" data-ficha-animo-q hidden aria-label="&iquest;Por qu&eacute; est&aacute; as&iacute;?">?</button>
              </div>
            </div>
          </div>
          <div class="ficha-hero-acciones" aria-label="Acciones con el vecino">
            <button type="button" class="ficha-btn-diario ficha-hero-btn" data-ficha-diario-btn>Diario</button>
            <button type="button" class="ficha-btn-org ficha-hero-btn" data-ficha-org>Nuevo plan</button>
            <button type="button" class="ficha-btn-regalo ficha-hero-btn" data-ficha-regalar>Regalar</button>
          </div>
        </section>
        <div class="ficha-body">
          <div class="ficha-rasgos-hobbies">
            <section class="ficha-seccion ficha-seccion-rasgos">
              <h4 class="ficha-seccion-tit">Rasgos</h4>
              <div class="ficha-seccion-body">
                <div class="ficha-rasgos" data-ficha-rasgos></div>
              </div>
            </section>
            <section class="ficha-seccion ficha-seccion-hobbies">
              <h4 class="ficha-seccion-tit">Hobbies</h4>
              <div class="ficha-seccion-body">
                <div class="ficha-hobbies" data-ficha-hobbies></div>
              </div>
            </section>
          </div>
          <div class="ficha-col ficha-col-detalles capa-scroll">
            <section class="ficha-seccion ficha-seccion-prefs" data-ficha-sabes hidden>
              <h4 class="ficha-seccion-tit ficha-seccion-tit-sm">Lo que sabes</h4>
              <div class="ficha-seccion-body ficha-seccion-body-prefs" data-ficha-sabes-body></div>
            </section>
            <section class="ficha-seccion">
              <h4 class="ficha-seccion-tit">Relaciones</h4>
              <div class="ficha-seccion-body">
                <div class="ficha-relaciones" data-ficha-relaciones></div>
              <button type="button" class="ficha-ver-mas" data-ficha-rel-mas hidden>Ver m&aacute;s relaciones</button>
              </div>
            </section>
            <section class="ficha-seccion">
              <h4 class="ficha-seccion-tit">Pr&oacute;ximos planes</h4>
              <div class="ficha-seccion-body">
                <div class="ficha-planes" data-ficha-planes></div>
              </div>
            </section>
            <section class="ficha-seccion ficha-seccion-aprecio" data-ficha-aprecio hidden>
              <h4 class="ficha-seccion-tit">C&Oacute;MO TE VE</h4>
              <div class="ficha-seccion-body">
                <p class="ficha-aprecio" data-ficha-aprecio-texto></p>
              </div>
            </section>
          </div>
        </div>
        <div class="ficha-rel-overlay" data-ficha-rel-overlay hidden>
          <div class="ficha-rel-modal" role="dialog" aria-label="Relaciones del vecino">
            <span class="ficha-tape ficha-tape-l" aria-hidden="true"></span>
            <span class="ficha-tape ficha-tape-r" aria-hidden="true"></span>
            <button type="button" class="cerrar ficha-cerrar ds-modal-close" data-ficha-rel-close aria-label="Cerrar">X</button>
            <header class="ficha-rel-top">
              <div class="ds-modal-head">
                <div class="ds-modal-head-row">
                  <span class="ds-modal-icon ds-modal-icon--pink" aria-hidden="true">&#128149;</span>
                  <h3 class="ficha-rel-modal-tit ds-modal-tit ds-modal-tit--pink" data-ficha-rel-modal-tit>Relaciones</h3>
                </div>
                <p class="ds-modal-sub ficha-rel-modal-sub">Qui&eacute;n le cae bien (o mal)</p>
              </div>
            </header>
            <div class="ficha-rel-scroll capa-scroll" data-ficha-rel-list></div>
          </div>
        </div>
        <div class="ficha-rel-overlay" data-animo-overlay hidden>
          <div class="ficha-rel-modal ficha-modal-animo" role="dialog" aria-label="&iquest;Por qu&eacute; est&aacute; as&iacute;?">
            <button type="button" class="cerrar ficha-cerrar ds-modal-close" data-animo-close aria-label="Cerrar">X</button>
            <div class="ficha-diario-scroll capa-scroll" data-animo-body></div>
          </div>
        </div>
      </aside>
      <aside class="capa capa-ficha-diario ds-modal-sheet" aria-label="Diario del vecino">
        <span class="ficha-tape ficha-tape-r fdi-tape-r" aria-hidden="true"></span>
        <button type="button" class="cerrar ficha-cerrar ds-modal-close" data-diario-vecino-close aria-label="Cerrar">X</button>
        <header class="fdi-top">
          <button type="button" class="fdi-volver" data-diario-volver>&larr; FICHA</button>
          <div class="fdi-hero" data-diario-hero></div>
          <label class="fdi-busca-wrap">
            <span class="fdi-busca-ico" aria-hidden="true">&#8981;</span>
            <input type="search" class="fdi-busca-inp" data-diario-busca placeholder="Buscar en su historia..." autocomplete="off" spellcheck="false"/>
          </label>
          <div class="fdi-filtros" role="tablist" aria-label="Filtrar entradas">
            <button type="button" class="fdi-filt is-on" data-diario-filt="todo" role="tab" aria-selected="true">Todo</button>
            <button type="button" class="fdi-filt" data-diario-filt="planes" role="tab" aria-selected="false">Planes</button>
            <button type="button" class="fdi-filt" data-diario-filt="relaciones" role="tab" aria-selected="false">Relaciones</button>
            <button type="button" class="fdi-filt" data-diario-filt="cambios" role="tab" aria-selected="false">Cambios</button>
          </div>
          <button type="button" class="fdi-orden" data-diario-orden aria-label="Ordenar">&#9783; M&aacute;s reciente</button>
        </header>
        <div class="fdi-scroll capa-scroll ficha-diario-scroll" data-diario-list></div>
      </aside>
      <aside class="capa capa-misiones mis-modal-papel ds-modal-sheet" aria-label="Misiones de hoy">
        <span class="ficha-tape ficha-tape-l mis-tape-tl" aria-hidden="true"></span>
        <span class="ficha-tape ficha-tape-r mis-tape-tr" aria-hidden="true"></span>
        <span class="mis-pin mis-pin-l" aria-hidden="true"></span>
        <button type="button" class="cerrar mis-cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
        <header class="mis-top">
          <div class="ds-modal-head">
            <div class="ds-modal-head-row">
              <span class="ds-modal-icon ds-modal-icon--brown" aria-hidden="true">&#9733;</span>
              <h2 class="mis-tit ds-modal-tit ds-modal-tit--brown">Hoy en el pueblo</h2>
            </div>
          </div>
          <p class="mis-sub mini" data-misiones-teaser>&#8212;</p>
        </header>
        <div class="mis-body capa-scroll misiones-body" data-misiones-list></div>
      </aside>

      <aside class="capa capa-parejas par-modal-papel ds-modal-sheet" aria-label="Parejas del pueblo">
        <span class="ficha-tape ficha-tape-l par-tape-tl" aria-hidden="true"></span>
        <span class="ficha-tape ficha-tape-r par-tape-tr" aria-hidden="true"></span>
        <button type="button" class="cerrar par-cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
        <header class="par-top">
          <div class="ds-modal-head">
            <div class="ds-modal-head-row">
              <span class="ds-modal-icon ds-modal-icon--pink" aria-hidden="true">&#9829;</span>
              <h2 class="par-tit ds-modal-tit ds-modal-tit--pink">Parejas</h2>
            </div>
          </div>
          <p class="par-sub mini" data-parejas-teaser">&mdash;</p>
        </header>
        <div class="par-body capa-scroll" data-parejas-modal-list></div>
      </aside>
      <aside class="capa capa-vida-pueblo vida-modal-papel ds-modal-sheet" aria-label="Vida del pueblo" role="dialog" aria-modal="true">
        <button type="button" class="cerrar vida-cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
        <header class="vida-top">
          <p class="vida-modal-ico" aria-hidden="true">&#127793;</p>
          <h2 class="vida-tit ds-modal-tit ds-modal-tit--pink">Vida del pueblo</h2>
          <p class="vida-valor" data-vida-modal-valor>&#8212; / 100</p>
          <div class="vida-valor-bar" data-vida-modal-bar hidden><span style="width:0%"></span></div>
          <p class="vida-estado-pista mini" data-vida-modal-estado hidden></p>
        </header>
        <div class="vida-body capa-scroll">
          <div class="vida-copy">
            <p>Esto no es decoraci&#8212;n, aunque lo parezca.</p>
            <p>Tus vecinos tienen una peligrosa tendencia a complicarse la vida y, por alg&#8212;n motivo, ahora son responsabilidad tuya.</p>
            <p>Haz que las cosas salgan bien y el coraz&#8212;n subir&#8212;. D&#8212;jalos a su suerte demasiado tiempo y&#8212; bueno, procura que esto no llegue a 0.</p>
          </div>
          <p class="vida-latido mini">&#8212;Llegas a 100? ?? Hay latido.<br>S&#8212;, conseguir que este pueblo funcione tiene premio. Incre&#8212;ble, pero cierto.</p>
        </div>
      </aside>
      <aside class="capa capa-buzon ds-modal-sheet" aria-label="Mensajitos">
        <button type="button" class="cerrar mensajitos-cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
        <header class="mensajitos-cab">
          <div class="ds-modal-head">
            <div class="ds-modal-head-row">
              <h2 class="ds-modal-tit ds-modal-tit--ink">Mensajitos</h2>
            </div>
          </div>
          <div class="mensajitos-tabs" role="tablist" aria-label="Filtrar mensajitos">
            <button type="button" class="mensajitos-tab is-on" data-buzon-tab="nuevos" role="tab" aria-selected="true">NUEVOS <span class="mensajitos-tab-badge" data-buzon-tab-count hidden></span></button>
            <button type="button" class="mensajitos-tab" data-buzon-tab="todos" role="tab" aria-selected="false">TODOS</button>
          </div>
        </header>
        <div class="mensajitos-toolbar">
          <button type="button" class="mensajitos-leer-todos" data-buzon-leer-todos hidden>
            <span class="mensajitos-leer-todos-box" aria-hidden="true"></span>
            <span class="mensajitos-leer-todos-txt">Marcar todo como le&iacute;do</span>
          </button>
        </div>
        <div data-buzon-list></div>
        <p class="mensajitos-hint">&#11088; Abrir mensajitos puede desbloquear planes y cotilleos</p>
      </aside>
      <aside class="capa capa-inventario inv-modal-papel ds-modal-sheet" aria-label="Inventario de Celestine">
        <span class="ficha-tape ficha-tape-l inv-tape-tl" aria-hidden="true"></span>
        <span class="ficha-tape ficha-tape-r inv-tape-tr" aria-hidden="true"></span>
        <button type="button" class="cerrar inv-cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
        <header class="inv-cab">
          <div class="ds-modal-head">
            <div class="ds-modal-head-row">
              <span class="ds-modal-icon ds-modal-icon--brown" aria-hidden="true">&#127890;</span>
              <h2 class="inv-tit ds-modal-tit ds-modal-tit--brown">Inventario</h2>
            </div>
            <p class="inv-sub ds-modal-sub" data-inv-sub>Detalles guardados para regalar a los vecinos.</p>
          </div>
        </header>
        <div class="inv-body capa-scroll">
          <div class="inv-lista" data-inv-lista></div>
          <div class="inv-regalo" data-inv-regalo hidden>
            <p class="inv-regalo-titulo">Regalar <strong data-inv-objeto-nombre></strong> a&hellip;</p>
            <div class="inv-vecinos" data-inv-vecinos></div>
            <div class="inv-acciones">
              <button type="button" class="inv-entregar" data-inv-entregar disabled>Regalar</button>
              <button type="button" class="inv-cancelar" data-inv-cancelar>Cancelar</button>
            </div>
          </div>
          <p class="inv-feedback" data-inv-feedback hidden aria-live="polite"></p>
        </div>
      </aside>

      <aside class="capa capa-ajustes ajust-modal-papel ds-modal-sheet" aria-label="Ajustes">
        <span class="ficha-tape ficha-tape-l ajust-tape-tl" aria-hidden="true"></span>
        <span class="ficha-tape ficha-tape-r ajust-tape-tr" aria-hidden="true"></span>
        <button type="button" class="cerrar ajustes-cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
        <header class="ajustes-top">
          <div class="ds-modal-head">
            <div class="ds-modal-head-row">
              <span class="ds-modal-icon ds-modal-icon--brown" aria-hidden="true">&#9881;</span>
              <h2 class="ds-modal-tit ds-modal-tit--ink">Ajustes</h2>
            </div>
            <p class="ds-modal-sub">Sonido, tutorial y partida</p>
          </div>
        </header>
        <div class="ajustes-body capa-scroll">
          <button type="button" class="ajustes-link" data-ajustes-tut>&iquest;C&oacute;mo se juega?</button>
          <section class="ajustes-grupo" aria-label="M&uacute;sica de fondo">
            <div class="ajustes-grupo-head">
              <span class="ajustes-grupo-tit">M&uacute;sica de fondo</span>
              <button type="button" class="ajustes-toggle" data-musica-toggle aria-pressed="true">
                <span class="ajustes-toggle-track" aria-hidden="true"><span class="ajustes-toggle-knob"></span></span>
              </button>
            </div>
            <label class="ajustes-vol">
              <span class="ajustes-vol-lbl">Volumen</span>
              <input type="range" class="ajustes-range" min="0" max="100" value="22" data-musica-vol aria-label="Volumen de m&uacute;sica"/>
            </label>
          </section>
          <section class="ajustes-grupo" aria-label="Efectos de sonido">
            <div class="ajustes-grupo-head">
              <span class="ajustes-grupo-tit">Efectos de sonido</span>
              <button type="button" class="ajustes-toggle" data-efectos-toggle aria-pressed="true">
                <span class="ajustes-toggle-track" aria-hidden="true"><span class="ajustes-toggle-knob"></span></span>
              </button>
            </div>
            <label class="ajustes-vol">
              <span class="ajustes-vol-lbl">Volumen</span>
              <input type="range" class="ajustes-range" min="0" max="100" value="55" data-sfx-vol aria-label="Volumen de efectos"/>
            </label>
          </section>
          <section class="ajustes-grupo ajustes-diag" aria-label="Diagn&oacute;stico">
            <div class="ajustes-grupo-head">
              <span class="ajustes-grupo-tit">Diagn&oacute;stico</span>
            </div>
            <p class="ajustes-diag-hint">Herramienta t&eacute;cnica para copiar o guardar el estado de depuraci&oacute;n.</p>
            <div class="ajustes-diag-actions">
              <button type="button" class="ajustes-diag-btn" data-ajustes-debug-copy>Copiar debug</button>
              <button type="button" class="ajustes-diag-btn" data-ajustes-debug-download>Descargar debug</button>
            </div>
            <p class="ajustes-diag-feedback" data-ajustes-debug-feedback hidden aria-live="polite"></p>
          </section>
          <button type="button" class="ajustes-reiniciar" data-ajustes-reiniciar>Reiniciar partida</button>
        </div>
      </aside>

      <aside class="capa capa-diario coti-modal-papel" aria-label="Cotilleos">
        <button type="button" class="cerrar coti-cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
        <header class="coti-top">
          <div class="ds-modal-head">
            <div class="ds-modal-head-row">
              <span class="ds-modal-icon ds-modal-icon--lavender" aria-hidden="true">&#128226;</span>
              <h2 class="coti-tit ds-modal-tit ds-modal-tit--lavender">Cotilleos</h2>
              <span class="coti-badge" data-coti-count hidden></span>
            </div>
            <p class="ds-modal-sub">Lo &uacute;ltimo que corre por el pueblo</p>
          </div>
          <div class="coti-filtros" data-coti-filtros role="group" aria-label="Filtrar por tipo" hidden></div>
        </header>
        <div class="coti-body capa-scroll">
          <div class="coti-list" data-coti-list></div>
        </div>
        <footer class="coti-pie">
          <p class="coti-pie-hint"><span class="coti-pie-ico" aria-hidden="true">&#128161;</span> Estos rumores cambian con el tiempo. Contin&uacute;a conociendo a los vecinos para descubrir m&aacute;s.</p>
        </footer>
      </aside>
      <aside class="capa capa-organizar org-plan-papel ds-modal-sheet" aria-label="Nuevo plan">
        <span class="ficha-tape ficha-tape-l org-tape-tl" aria-hidden="true"></span>
        <span class="ficha-tape ficha-tape-r org-tape-tr" aria-hidden="true"></span>
        <span class="org-pin org-pin-l" aria-hidden="true"></span>
        <button type="button" class="cerrar org-cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
        <header class="org-top">
          <div class="ds-modal-head">
            <div class="ds-modal-head-row">
              <h2 class="org-tit ds-modal-tit ds-modal-tit--ink">Nuevo plan</h2>
            </div>
          </div>
          <div class="org-modo-toggle" data-org-modo-toggle aria-label="Modo del plan">
            <span class="org-modo-pill" data-org-modo-solo>Solo</span>
            <span class="org-modo-pill" data-org-modo-pareja>Acompa&ntilde;ado</span>
          </div>
          <p class="org-modo-estado sr-only" data-org-modo-estado hidden></p>
          <p class="org-aviso" data-org-aviso hidden></p>
        </header>
        <div class="org-body capa-scroll">
          <section class="ficha-seccion org-seccion org-seccion--quienes">
            <div class="org-seccion-head">
              <div class="org-seccion-head-row">
                <h4 class="ficha-seccion-tit">&iquest;Qui&eacute;nes van?</h4>
                <span class="org-vecinos-contador" data-org-vecinos-contador hidden></span>
              </div>
              <p class="org-seccion-meta org-picker-hint" data-org-picker-hint>Elige hasta 2 vecinos.</p>
            </div>
            <div class="ficha-seccion-body">
              <div class="org-busca-wrap">
                <span class="org-busca-ico" aria-hidden="true"></span>
                <input type="search" class="org-busca" data-org-busca placeholder="Buscar vecino&hellip;" autocomplete="off" aria-label="Buscar vecino"/>
                <span class="org-busca-todos" data-org-mostrar-todos role="button" tabindex="0" hidden>mostrar todos</span>
              </div>
              <div class="org-picker-strip capa-scroll" data-org-picker></div>
            </div>
          </section>
          <section class="ficha-seccion org-seccion org-seccion--que">
            <h4 class="ficha-seccion-tit">&iquest;Qu&eacute; har&aacute;n?</h4>
            <div class="ficha-seccion-body">
              <div class="org-tipos" data-org-tipos></div>
            </div>
          </section>
          <section class="ficha-seccion org-seccion org-seccion--donde">
            <h4 class="ficha-seccion-tit">&iquest;D&oacute;nde?</h4>
            <div class="ficha-seccion-body org-donde-fila">
              <div class="org-dd org-dd--lugar" data-org-dd-lugar></div>
              <select class="org-select org-select-native" data-org-lugar hidden tabindex="-1" aria-hidden="true"></select>
              <p class="org-lugar-horario mini" data-org-lugar-horario hidden></p>
            </div>
          </section>
          <section class="ficha-seccion org-seccion org-seccion--cuando">
            <h4 class="ficha-seccion-tit">&iquest;Cu&aacute;ndo?</h4>
            <div class="ficha-seccion-body">
              <div class="org-cuando">
                <div class="org-cuando-campo org-cuando-campo--dia">
                  <span class="org-cuando-ico org-cuando-ico--dia" aria-hidden="true"></span>
                  <div class="org-dd org-dd--dia" data-org-dd-dia></div>
                </div>
                <div class="org-cuando-campo org-cuando-campo--hora">
                  <span class="org-cuando-ico org-cuando-ico--hora" aria-hidden="true"></span>
                  <div class="org-dd org-dd--hora" data-org-dd-hora></div>
                </div>
                <select class="org-select org-select-native" data-org-dia hidden tabindex="-1" aria-hidden="true"></select>
                <select class="org-select org-select-native" data-org-hora hidden tabindex="-1" aria-hidden="true"></select>
              </div>
              <p class="org-horas-hint mini" data-org-horas-hint hidden></p>
            </div>
          </section>
        </div>
        <footer class="org-footer">
          <button type="button" class="org-crear" data-org-go>
            <span class="org-crear-tape org-crear-tape-l" aria-hidden="true"></span>
            <span class="org-crear-tape org-crear-tape-r" aria-hidden="true"></span>
            <span class="org-crear-txt">Crear plan</span>
          </button>
        </footer>
      </aside>
    </div>
  </div>

    <nav class="play-bottom-nav" aria-label="Accesos r&aacute;pidos">
      <button type="button" class="play-bottom-nav-btn" data-open="ajustes">
        <span class="play-bottom-nav-ico" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.26.604.852.997 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        <span class="play-bottom-nav-txt">Ajustes</span>
      </button>
      <button type="button" class="play-bottom-nav-btn" data-open="inventario">
        <span class="play-bottom-nav-ico play-bottom-nav-ico--inv" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false"><path d="M8 7V6a4 4 0 0 1 8 0v1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M6 7h12l-1.2 12.2a2 2 0 0 1-2 1.8H9.2a2 2 0 0 1-2-1.8L6 7Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.5 11v4M14.5 11v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          <span class="play-bottom-nav-badge" data-inv-nav-badge hidden>0</span>
        </span>
        <span class="play-bottom-nav-txt">Inventario</span>
      </button>
      <button type="button" class="play-bottom-nav-btn" data-open="vecinos">
        <span class="play-bottom-nav-ico" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false"><path d="M16 11a3 3 0 1 0-6 0 3 3 0 0 0 6 0Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M5.5 19.5c.6-2.5 2.8-4 6.5-4s5.9 1.5 6.5 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M18.5 11.2a2.6 2.6 0 1 0-1.8-1.8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M20.8 18.2c-.4-1.8-1.7-3-3.8-3.3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M5.5 11.2a2.6 2.6 0 1 1 1.8-1.8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M3.2 18.2c.4-1.8 1.7-3 3.8-3.3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        </span>
        <span class="play-bottom-nav-txt">Vecinos</span>
      </button>
      <button type="button" class="play-bottom-nav-btn" data-open="relaciones">
        <span class="play-bottom-nav-ico" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false"><path d="M12 20.5s-6.5-4.2-6.5-8.4C5.5 9.2 8.1 7 11 7c1.6 0 2.7.7 3.5 1.6.8-.9 1.9-1.6 3.5-1.6 2.9 0 5.5 2.2 5.5 5.1 0 4.2-6.5 8.4-6.5 8.4Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
        </span>
        <span class="play-bottom-nav-txt">Relaciones</span>
      </button>
    </nav>
      </div>

<section class="inicio-desktop" data-inicio-view="desktop" aria-label="Inicio escritorio">
        <header class="game-top">
      <div class="brand-col">
        <h1 class="brand" aria-label="Aqu&iacute; Hay Tema">
          <span class="brand-text">AQU&Iacute; HAY TEMA</span>
          <span class="brand-heart" aria-hidden="true"></span>
        </h1>
        <p class="top-meta-line" data-top-meta-mobile></p>
        <button type="button" class="btn-guia" data-tut-reopen hidden>&iquest;C&oacute;mo va esto?</button>
      </div>
      <div class="top-center">
        <div class="top-reloj">
          <div class="obj-dia" style="--rot:-2deg">
            <div class="obj-dia-placa">
              <span class="obj-dia-num" data-dia-num>&#8212;</span>
            </div>
            <div class="obj-dia-cuerpo">
              <span class="obj-dia-estacion" data-dia-estacion>Primavera</span>
              <span class="obj-dia-meta" data-dia-meta>&#8212;</span>
            </div>
            <span class="sr-only" data-fecha></span>
          </div>
          <div class="obj-hora" style="--rot:3deg" aria-label="Hora del pueblo">
            <span class="obj-hora-ico" aria-hidden="true"></span>
            <span class="obj-hora-val" data-hora>&#8212;</span>
          </div>
          <span class="es-noche" data-es-noche hidden>
            <svg class="es-noche-luna" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            <span class="es-noche-txt">Es de noche</span>
          </span>
          <button type="button" class="pasar-rato" data-pasar-rato title="Avanza el tiempo exactamente 1 hora">
            <span class="pasar-rato-ico" aria-hidden="true">&#9654;</span>
            <span class="pasar-rato-txt">Pasar el rato</span>
          </button>
        </div>
      </div>
      <button type="button" class="top-vida top-vida-btn" data-open="vida_pueblo" aria-label="Vida del pueblo, pulsa para m&aacute;s informaci&oacute;n">
        <span class="obj-vida-kicker">Vida del pueblo</span>
        <svg class="corazon-svg corazon-org" viewBox="0 0 58 52" aria-hidden="true">
          <defs>
            <clipPath id="corazon-clip-desk"><path d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/></clipPath>
            <linearGradient id="corazon-agua-grad-desk" x1="29" y1="52" x2="29" y2="0" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#d46278"/>
              <stop offset="55%" stop-color="#e57d90"/>
              <stop offset="100%" stop-color="#f0a8b6"/>
            </linearGradient>
          </defs>
          <path class="corazon-bg" d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/>
          <path class="corazon-fill-path" clip-path="url(#corazon-clip-desk)" fill="url(#corazon-agua-grad-desk)" d="" data-corazon-fill/>
          <path class="corazon-fill-surface" clip-path="url(#corazon-clip-desk)" fill="none" stroke="rgba(255,255,255,.62)" stroke-width="1.15" stroke-linecap="round" d="" data-corazon-surface/>
          <path class="corazon-stroke" fill="none" d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/>
        </svg>
        <span class="sr-only" data-vida-pct>0%</span>
      </button>
      <div class="control-audio" aria-label="Controles de audio">
        <button type="button" class="control-musica" data-musica-toggle aria-pressed="true" aria-label="Desactivar m&uacute;sica" title="Desactivar m&uacute;sica">
          <span class="control-musica-ico" aria-hidden="true">&#9834;</span>
        </button>
        <button type="button" class="control-efectos" data-efectos-toggle aria-pressed="true" aria-label="Desactivar efectos de sonido" title="Desactivar efectos de sonido">
          <span class="control-efectos-ico" aria-hidden="true">&#10022;</span>
        </button>
        <button type="button" class="control-inventario" data-open="inventario" aria-label="Abrir inventario" title="Inventario">
          <span class="control-inventario-ico" aria-hidden="true">&#127873;</span>
        </button>
      </div>
    </header>
        <div class="inicio-layout inicio-desktop-layout">
          <aside class="inicio-chrome-left inicio-desktop-left">
            <section class="shell-grupo shell-grupo-buzon">
          <div class="mensajitos-wrap">
            <button type="button" class="obj-buzon" data-open="buzon" aria-label="Abrir mensajitos">
              <span class="game-left-tile-ico obj-buzon-ico-wrap" aria-hidden="true"><img class="obj-buzon-img" src="assets/play-v3/hud/sobre.png" alt="" width="72" height="58"/></span>
              <span class="obj-buzon-txt game-left-tile-label">Mensajitos</span>
              <span class="obj-buzon-badge" data-buzon-badge hidden>0</span>
              <span class="obj-buzon-flecha" aria-hidden="true">&#8250;</span>
            </button>
          </div>
        </section>
            <section class="shell-grupo shell-grupo-resumen">
          <button type="button" class="obj-vecinos-resumen celestine-nota" data-open="vecinos" aria-label="Ver vecinos">
            <span class="libreta-kicker">Celestine apunta</span>
            <span class="obj-vecinos-preview game-left-tile-ico" data-vecinos-preview aria-hidden="true"></span>
            <div class="obj-vecinos-head">
              <span class="obj-vecinos-tit game-left-tile-label">VECINOS</span>
              <span class="obj-vecinos-poblacion game-left-tile-meta" data-vecinos-poblacion></span>
            </div>
            <div class="obj-vecinos-stats" data-resumen-stats></div>
          </button>
        </section>
            <section class="shell-grupo shell-grupo-cotilleo-par">
          <button type="button" class="obj-cotilleo obj-cotilleo-par obj-cotilleo-compact" data-open="diario" aria-label="Abrir cotilleo del pueblo">
            <span class="obj-cotilleo-ico" aria-hidden="true"></span>
            <span class="obj-cotilleo-cuerpo">
              <span class="obj-cotilleo-badges">
                <span class="obj-cotilleo-tit">COTILLEOS</span>
                <span class="obj-cotilleo-badge" data-cotilleo-badge hidden></span>
              </span>
              <span class="obj-cotilleo-txt" data-cotilleo-teaser>Hoy est&aacute;n sospechosamente tranquilos&hellip;</span>
            </span>
            <span class="obj-cotilleo-flecha" aria-hidden="true">&#8250;</span>
          </button>
        </section>
            <section class="shell-grupo shell-grupo-misiones-par" data-inicio-misiones>
          <div class="obj-misiones-papel" aria-label="Misiones de hoy">
            <span class="mision-tape mision-tape-tl" aria-hidden="true"></span>
            <span class="mision-tape mision-tape-tr" aria-hidden="true"></span>
            <span class="mision-tape mision-tape-bl" aria-hidden="true"></span>
            <span class="mision-tape mision-tape-br" aria-hidden="true"></span>
            <span class="obj-misiones-papel-tit">MISIONES</span>
            <div class="obj-misiones-strip" data-misiones-strip></div>
          </div>
        </section>
          </aside>
          <aside class="inicio-chrome-right inicio-desktop-right">
            <aside class="inicio-proximo-evento" data-proximo-evento-slot hidden aria-label="Pr&oacute;ximo evento">
        <div class="inicio-evento-card inicio-evento-libreta" data-proximo-evento-card role="status">
          <span class="inicio-evento-tag" data-proximo-evento-tag aria-hidden="true">
            <span class="inicio-evento-tag-txt" data-proximo-evento-tag-txt>Evento del pueblo</span>
          </span>
          <span class="inicio-evento-main">
            <span class="inicio-evento-ico" data-proximo-evento-ico aria-hidden="true"></span>
            <span class="inicio-evento-body">
              <span class="inicio-evento-tit" data-proximo-evento-tit></span>
              <span class="inicio-evento-meta" data-proximo-evento-meta></span>
            </span>
          </span>
          <button type="button" class="inicio-evento-cta" data-proximo-evento-cta hidden>
            <span class="inicio-evento-cta-txt" data-proximo-evento-cta-txt>¿Qui&eacute;n va?</span>
            <span class="inicio-evento-cta-spark" aria-hidden="true"></span>
          </button>
        </div>
      </aside>
            <section class="shell-grupo encursos-movil" data-encursos-block aria-label="Planes en curso ahora">
          <header class="enc-mov-cab plan-seccion-cab">
            <svg class="plan-seccion-ico enc-mov-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 0 1 13.3-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M20 12a8 8 0 0 1-13.3 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M16.5 3.5V8h-4.5M7.5 20.5V16H12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <h3 class="enc-mov-tit">PLANES EN CURSO<span class="plan-seccion-cnt" data-encursos-count hidden aria-hidden="true"></span></h3>
            <span class="plan-seccion-rule" aria-hidden="true"></span>
            <button type="button" class="plan-seccion-ver" data-open="agenda">VER TODOS &#8250;</button>
          </header>
          <div class="enc-mov-shell" data-encursos-shell hidden aria-hidden="true">
            <button type="button" class="enc-mov-nav-btn enc-mov-nav-prev" data-enc-mov-prev aria-label="Plan anterior" hidden>
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 7l-5 5 5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <div class="enc-mov-track" data-encursos-track></div>
            <button type="button" class="enc-mov-nav-btn enc-mov-nav-next" data-enc-mov-next aria-label="Plan siguiente" hidden>
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 7l5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
          </div>
        </section>
            <section class="shell-grupo proxplanes-movil" data-proxplanes-block aria-label="Pr&oacute;ximos planes programados">
          <header class="pp-mov-cab plan-seccion-cab">
            <svg class="plan-seccion-ico pp-mov-ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="3.2" y="5" width="17.6" height="15.4" rx="2.4" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M3.4 9.6h17.2" stroke="currentColor" stroke-width="1.7"/><path d="M8.2 3.2v3.4M15.8 3.2v3.4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
            <h3 class="pp-mov-tit">PR&Oacute;XIMOS PLANES<span class="plan-seccion-cnt" data-proxplanes-count hidden aria-hidden="true"></span></h3>
            <span class="plan-seccion-rule" aria-hidden="true"></span>
            <button type="button" class="plan-seccion-ver" data-open="agenda">VER TODOS &#8250;</button>
          </header>
          <div class="pp-mov-shell" data-proxplanes-shell hidden aria-hidden="true">
            <button type="button" class="pp-mov-nav-btn pp-mov-nav-prev" data-pp-mov-prev aria-label="Planes anteriores" hidden>
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 7l-5 5 5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <div class="pp-mov-track" data-proxplanes-track></div>
            <button type="button" class="pp-mov-nav-btn pp-mov-nav-next" data-pp-mov-next aria-label="Siguientes planes" hidden>
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 7l5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
          </div>
        </section>
            <section class="shell-grupo shell-grupo-planes">
<button type="button" class="obj-nuevo-plan obj-proximo-cta obj-nuevo-plan-horiz" data-open="organizar" aria-label="Crear plan">
              <span class="obj-nuevo-plan-ico" aria-hidden="true">+</span>
              <span class="obj-nuevo-plan-txt">Crear plan</span>
            </button>
</section>
            <section class="shell-grupo shell-grupo-parejas" data-inicio-parejas>
          <span class="zona-tit zona-tit-parejas">PAREJAS</span>
          <div class="obj-parejas-list" data-parejas-strip></div>
        </section>
          </aside>
        </div>
      </section>

      <section class="inicio-mobile inicio-mobile-feed" data-inicio-view="mobile" aria-label="Inicio m&oacute;vil feed">
        <div class="inicio-chrome-right inicio-mobile-feed-inner">
            <aside class="inicio-proximo-evento" data-proximo-evento-slot hidden aria-label="Pr&oacute;ximo evento">
        <div class="inicio-evento-card inicio-evento-libreta" data-proximo-evento-card role="status">
          <span class="inicio-evento-tag" data-proximo-evento-tag aria-hidden="true">
            <span class="inicio-evento-tag-txt" data-proximo-evento-tag-txt>Evento del pueblo</span>
          </span>
          <span class="inicio-evento-main">
            <span class="inicio-evento-ico" data-proximo-evento-ico aria-hidden="true"></span>
            <span class="inicio-evento-body">
              <span class="inicio-evento-tit" data-proximo-evento-tit></span>
              <span class="inicio-evento-meta" data-proximo-evento-meta></span>
            </span>
          </span>
          <button type="button" class="inicio-evento-cta" data-proximo-evento-cta hidden>
            <span class="inicio-evento-cta-txt" data-proximo-evento-cta-txt>¿Qui&eacute;n va?</span>
            <span class="inicio-evento-cta-spark" aria-hidden="true"></span>
          </button>
        </div>
      </aside>
            <section class="shell-grupo shell-grupo-cotilleo-par">
          <button type="button" class="obj-cotilleo obj-cotilleo-par obj-cotilleo-compact" data-open="diario" aria-label="Abrir cotilleo del pueblo">
            <span class="obj-cotilleo-ico" aria-hidden="true"></span>
            <span class="obj-cotilleo-cuerpo">
              <span class="obj-cotilleo-badges">
                <span class="obj-cotilleo-tit">COTILLEOS</span>
                <span class="obj-cotilleo-badge" data-cotilleo-badge hidden></span>
              </span>
              <span class="obj-cotilleo-txt" data-cotilleo-teaser>Hoy est&aacute;n sospechosamente tranquilos&hellip;</span>
            </span>
            <span class="obj-cotilleo-flecha" aria-hidden="true">&#8250;</span>
          </button>
        </section>
            <section class="shell-grupo planes-movil-unif" data-planes-unif-block aria-label="Planes">
          <div class="planes-unif-body">
            <aside class="planes-unif-spine" aria-label="Planes">
              <svg class="planes-unif-spine-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 0 1 13.3-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M20 12a8 8 0 0 1-13.3 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M16.5 3.5V8h-4.5M7.5 20.5V16H12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <h3 class="planes-unif-spine-tit">PLANES</h3>
              <div class="planes-unif-spine-badges" data-planes-unif-badges hidden aria-live="polite"></div>
            </aside>
            <div class="planes-unif-track-wrap">
              <div class="planes-unif-track" data-planes-unif-track></div>
              <button type="button" class="planes-unif-more" data-planes-unif-more hidden aria-label="Ver m&aacute;s planes">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 7l5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </button>
            </div>
          </div>
        </section>
            <section class="shell-grupo inicio-mp-duo" data-inicio-mp-duo aria-label="Misiones y parejas">
          <button type="button" class="inicio-mp-card inicio-mp-card--mis" data-open="misiones" aria-label="Ver misiones de hoy">
            <div class="inicio-mp-cuerpo">
              <span class="inicio-mp-ico inicio-mp-ico--mis" aria-hidden="true"></span>
              <div class="inicio-mp-main">
                <span class="inicio-mp-tit" data-misiones-tit-corta>MISIONES</span>
                <span class="inicio-mp-resumen" data-misiones-resumen-corta></span>
                <span class="inicio-mp-progreso" data-misiones-progreso aria-hidden="true"></span>
              </div>
            </div>
            <span class="inicio-mp-pie"><span class="inicio-mp-ver">VER &#8250;</span></span>
          </button>
          <button type="button" class="inicio-mp-card inicio-mp-card--par" data-open="parejas" aria-label="Ver parejas del pueblo">
            <div class="inicio-mp-cuerpo">
              <span class="inicio-mp-tit" data-parejas-tit-corta>PAREJAS</span>
              <div class="inicio-mp-par-mid">
                <div class="inicio-mp-par-izq">
                  <span class="inicio-mp-ico inicio-mp-ico--par" aria-hidden="true"></span>
                  <span class="inicio-mp-resumen" data-parejas-resumen-corta></span>
                </div>
                <span class="inicio-mp-par-faces" data-parejas-preview-faces aria-hidden="true"></span>
              </div>
            </div>
            <span class="inicio-mp-pie"><span class="inicio-mp-ver">VER &#8250;</span></span>
          </button>
        </section>
        </div>
      </section>

    </div>
  </div>  <script src="assets/js/lab-audit.js?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="assets/js/play-v3-audio.js?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="assets/js/hobby-icons.js?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="assets/js/play-v3.js?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="assets/js/play-v3-lab.js?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"></script>
  <script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('<?= htmlspecialchars($ahtPwaBase, ENT_QUOTES, 'UTF-8') ?>sw.js', { scope: '<?= htmlspecialchars($ahtPwaBase, ENT_QUOTES, 'UTF-8') ?>' })
        .catch(function (err) { console.warn('PWA service worker:', err); });
    });
  }
  </script>
<script>if(/^(mobile|desktop)$/.test(new URLSearchParams(location.search).get('design')||'')){var l=document.createElement('link');l.rel='stylesheet';l.href='dev/inicio-design-mode.css';document.head.appendChild(l);var s=document.createElement('script');s.src='dev/inicio-design-mode.js';document.body.appendChild(s)}</script>
</body>
</html>