<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
$ahtUi = 'v3-20260822mapa2-ficha-v3';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <meta name="aht-ui" content="v3"/>
  <title>Aquí Hay Tema</title>
  <link rel="icon" href="cover.svg" type="image/svg+xml"/>
  <link rel="stylesheet" href="assets/css/play-v3.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-capas.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-app.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-shell-ui.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-shell-art.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-capas-shell.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-mensajitos.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-mapa-canonico.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-bloques-residencias.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
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
    .playtest-guia .objs li::before { content: "☐"; position: absolute; left: 0; }
    .playtest-guia .objs li.hecho::before { content: "☑"; color: #4a7a3a; }
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
      object-fit: cover; object-position: 50% 12%; display: block; border-radius: 50%;
    }
    .caras-clip { display: flex; justify-content: center; gap: .35rem; margin-bottom: .35rem; flex-wrap: wrap; overflow: hidden; }
    .caras-clip .cara {
      width: 52px; height: 52px; flex: 0 0 52px; overflow: hidden;
      border-radius: 50%; border: 2px solid rgba(120,96,72,.35);
    }
    .caras-clip img, .caras-clip .cara img { width: 52px; height: 52px; max-width: 52px; max-height: 52px; border-radius: 50%; object-fit: cover; object-position: 50% 12%; display: block; }
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
    /* Retratos: rostro visible sin rediseñar capas */
    .capa-vecinos .vecino img,
    .vecino-celda img,
    .ficha-hero .cara img,
    .caras-clip img,
    .caras-clip .cara img,
    .tut-caras img,
    .tut-caras .cara img,
    .prox-caras img {
      object-fit: cover;
      object-position: 50% 12%;
    }
    .caras-clip img, .caras-clip .cara img { width: 52px; height: 52px; border-radius: 50%; }
    .prox-caras { display: flex; gap: .35rem; margin-bottom: .35rem; }
    .prox-caras img, .prox-cara-ini {
      width: 40px; height: 40px; border-radius: 50%; border: 2px solid rgba(120,96,72,.35);
      display: inline-flex; align-items: center; justify-content: center; background: #fff;
      font-weight: 800; font-size: .9rem;
    }
    .celestine-nota .obj-vecinos-tit { color: #d0697a; }
      </style>
</head>
<body class="play-v3" data-ui="v3" data-debug="0">
  <div class="aht-debug-float" data-debug-float>
    <button type="button" class="aht-debug-toggle" data-debug-toggle aria-expanded="false" title="Herramientas DEBUG">🧪 DEBUG</button>
    <div class="aht-debug-panel" data-debug-panel hidden>
      <p class="aht-debug-title">DEBUG</p>
      <button type="button" id="btn-debug-nueva">Nueva partida</button>
      <button type="button" id="btn-debug-guardar">Guardar</button>
      <button type="button" data-horas="1">+1h</button>
      <button type="button" data-horas="8">+8h</button>
      <button type="button" data-horas="24">+1 día</button>
      <button type="button" data-horas="72">+3 días</button>
      <button type="button" id="btn-debug-proximo">Ir al próximo</button>
      <button type="button" id="btn-debug-copy">Copiar debug</button>
      <button type="button" id="btn-debug-copy-estado">Copiar estado</button>
      <button type="button" id="btn-debug-parejas-crear">Crear parejas de prueba</button>
      <button type="button" id="btn-debug-parejas-quitar">Quitar parejas de prueba</button>
      <span class="msg" data-debug-msg></span>
    </div>
  </div>
  <p class="tutorial-pista" data-tutorial-pista hidden></p>
  <div class="playtest-cheats" data-playtest-cheats hidden>
    <span class="pc-label">Acelerar tiempo</span>
    <button type="button" data-horas="1">+1h</button>
    <button type="button" data-horas="8">+8h</button>
    <button type="button" data-horas="24">+1 día</button>
    <button type="button" id="btn-proximo-lab">Ir al próximo</button>
    <span class="pc-msg" data-taller-msg-lab></span>
  </div>
  <aside class="playtest-guia" data-playtest-guia hidden>
    <h2 data-pg-titulo>PRUEBA DEL PUEBLO</h2>
    <p class="meta-reloj" data-pg-reloj></p>
    <h3>Ahora mismo</h3>
    <ul data-pg-ahora></ul>
    <h3>Qué hacer ahora</h3>
    <ol data-pg-hacer></ol>
    <div class="evento" data-pg-evento hidden></div>
    <div data-pg-pistas></div>
    <h3>Objetivos de esta partida</h3>
    <ul class="objs" data-pg-objs></ul>
    <details class="playtest-diag" data-playtest-diag open>
      <summary>Registro técnico del playtest (copiar para ChatGPT / Carlos I)</summary>
      <div class="diag-actions">
        <button type="button" data-diag-copy>Copiar todo</button>
        <button type="button" data-diag-clear-ui>Limpiar vista</button>
      </div>
      <pre data-playtest-diag-log>(aún no hay eventos)</pre>
    </details>
    <details class="debug-tec">
      <summary>Datos técnicos (resumen avance)</summary>
      <pre data-taller-debug hidden></pre>
    </details>
  </aside>
  <div class="game-shell">
    <header class="game-top">
      <div class="brand-col">
        <h1 class="brand" aria-label="Aquí Hay Tema">
          <span class="brand-text">AQUÍ HAY TEMA</span>
          <span class="brand-heart" aria-hidden="true"></span>
        </h1>
        <button type="button" class="btn-guia" data-tut-reopen hidden>¿Cómo va esto?</button>
      </div>
      <div class="top-center">
        <div class="top-reloj">
          <div class="obj-dia" style="--rot:-2deg">
            <div class="obj-dia-placa">
              <span class="obj-dia-num" data-dia-num>DÍA ·</span>
            </div>
            <div class="obj-dia-cuerpo">
              <span class="obj-dia-estacion" data-dia-estacion>Primavera</span>
              <span class="obj-dia-meta" data-dia-meta>·</span>
            </div>
            <span class="sr-only" data-fecha></span>
          </div>
          <div class="obj-hora" style="--rot:3deg" aria-label="Hora del pueblo">
            <span class="obj-hora-ico" aria-hidden="true"></span>
            <span class="obj-hora-val" data-hora>—</span>
          </div>
        </div>
      </div>
      <div class="top-vida" aria-label="Vida del pueblo">
        <span class="obj-vida-kicker">Vida del pueblo</span>
        <svg class="corazon-svg corazon-org" viewBox="0 0 58 52" aria-hidden="true">
          <path class="corazon-bg" d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/>
          <clipPath id="corazon-clip"><path d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/></clipPath>
          <rect class="corazon-fill-rect" clip-path="url(#corazon-clip)" x="0" y="0" width="58" height="52" data-corazon-fill/>
          <path class="corazon-stroke" fill="none" d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/>
        </svg>
        <span class="sr-only" data-vida-pct>0%</span>
      </div>
    </header>
    <div class="game-main">
      <aside class="game-left zona-actividad">
        <section class="shell-grupo shell-grupo-buzon">
          <button type="button" class="obj-buzon" data-open="buzon" aria-label="Abrir mensajitos">
            <span class="obj-buzon-badge" data-buzon-badge hidden>0</span>
            <img class="obj-buzon-img" src="assets/play-v3/hud/sobre.png" alt="" width="72" height="58"/>
            <span class="obj-buzon-txt">MENSAJITOS</span>
          </button>
        </section>
        <section class="shell-grupo shell-grupo-resumen">
          <button type="button" class="obj-vecinos-resumen celestine-nota" data-open="vecinos" aria-label="Ver vecinos">
            <span class="libreta-kicker">Celestine apunta</span>
            <span class="obj-vecinos-tit">Vecinos</span>
            <div class="obj-vecinos-stats" data-resumen-stats></div>
          </button>
        </section>
        <section class="shell-grupo shell-grupo-planes">
          <div class="obj-proximo obj-proximo-polaroid">
            <span class="obj-proximo-tit">Próximo plan</span>
            <div class="obj-proximo-body" data-proximo-plan><p class="obj-proximo-vacio">Nada en agenda. Sospechoso.</p></div>
            <button type="button" class="obj-nuevo-plan obj-proximo-cta" data-open="organizar" aria-label="Nuevo plan">
              <span class="obj-nuevo-plan-ico" aria-hidden="true">+</span>
              <span class="obj-nuevo-plan-txt">NUEVO PLAN</span>
            </button>
            <button type="button" class="obj-ver-planes" data-open="agenda" aria-label="Ver todos los planes">
              <span class="obj-ver-planes-txt">ver todos los planes</span>
            </button>
          </div>
        </section>

      </aside>
      <div class="game-map-wrap">
        <div class="plan-notif" data-plan-notif hidden role="status" aria-live="polite">
          <button type="button" class="plan-notif-inner" data-plan-notif-btn>
            <span class="plan-notif-kicker">Plan confirmado</span>
            <span class="plan-notif-nombres" data-plan-notif-nombres></span>
            <span class="plan-notif-meta" data-plan-notif-meta></span>
          </button>
        </div>
  <div class="play-stage">
    <div class="play-root pc" data-pueblo="temprano" data-diario="hoy" data-aforo="1">
      <div class="board-scroll">
        <div class="board-fit">
          <div class="mapa-canonico" data-mapa-canonico>
            <img class="mapa-canonico-bg" src="assets/play-v3/mapa_canonico.png" alt="Mapa del pueblo" width="618" height="404"/>
            <div class="mapa-zonas-layer" data-mapa-zonas></div>
          </div>
          <div class="edificios-layer" data-edificios-layer aria-hidden="true"></div>
          <aside class="selector nota-mapa">
            <button type="button" class="cerrar" data-close aria-label="Cerrar">×</button>
            <p class="libreta-kicker">Un vistazo al lugar</p>
            <h3 data-s-tit></h3>
            <p class="cotilleo" data-s-coti></p>
            <div class="destinos" data-s-btns></div>
          </aside>
          <aside class="quien nota-mapa">
            <button type="button" class="cerrar" data-close aria-label="Cerrar">×</button>
            <p class="libreta-kicker">Quién está</p>
            <h3 data-q-tit></h3>
            <p class="quien-sum" data-q-sum></p>
            <div class="quien-list" data-q-list></div>
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
            <div class="dow" data-dow>—</div>
            <div class="fecha" data-fecha></div>
            <div class="hora" data-hora>—</div>
          </div>
          <div class="tiempo-juego" aria-label="Avanzar el tiempo">
            <button type="button" class="tique-hora" data-horas="1" title="Avanzar una hora">+1 h</button>
            <button type="button" class="tique-dia" data-horas="24" title="Avanzar un día">+1 día</button>
          </div>
          <div class="hud-right">
            <div class="dinero" data-dinero>—</div>
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
        <div class="tut-papel">
          <button type="button" class="cerrar tut-skip" data-tut-skip aria-label="Saltar tutorial">Saltar</button>
          <p class="libreta-kicker">Primeros pasos</p>
          <h2 data-tut-tit></h2>
          <p class="tut-texto" data-tut-texto></p>
          <div class="tut-caras" data-tut-caras hidden></div>
          <div class="tut-pasos" data-tut-pasos></div>
          <div class="tut-acciones">
            <button type="button" class="cta ghost" data-tut-atras hidden>Atrás</button>
            <button type="button" class="cta" data-tut-siguiente>Siguiente</button>
          </div>
        </div>
      </aside>
      <aside class="tut-finale" data-tut-finale hidden aria-live="polite">
        <div class="tut-papel">
          <h2 data-tut-fin-tit></h2>
          <p class="tut-texto" data-tut-fin-texto></p>
          <button type="button" class="cta" data-tut-fin-ok>Que empiece el tema</button>
        </div>
      </aside>

      <aside class="capa capa-vecinos" aria-label="Vecinos del pueblo">
        <span class="vecinos-pin vecinos-pin-l" aria-hidden="true"></span>
        <span class="vecinos-pin vecinos-pin-r" aria-hidden="true"></span>
        <button type="button" class="cerrar vecinos-cerrar" data-close aria-label="Cerrar">cerrar</button>
        <header class="vecinos-cab">
          <h2>Vecinos del pueblo</h2>
          <span class="vecinos-cuenta" data-vecinos-count></span>
        </header>
        <div class="vec-busca-tira">
          <label class="vec-busca-wrap">
            <span class="vec-busca-ico" aria-hidden="true">⌕</span>
            <input type="search" class="vec-busca-inp" data-vec-busca placeholder="¿Dónde está?" autocomplete="off" spellcheck="false"/>
          </label>
        </div>
        <div class="vecinos-grid" data-vecinos-list></div>
        <p class="vecinos-pie">Haz clic en un vecino para ver su ficha.</p>
      </aside>
      <aside class="capa capa-ficha" aria-label="Ficha de vecino">
        <span class="ficha-tape ficha-tape-l" aria-hidden="true"></span>
        <span class="ficha-tape ficha-tape-r" aria-hidden="true"></span>
        <button type="button" class="cerrar ficha-cerrar" data-close aria-label="Cerrar">cerrar</button>
        <header class="ficha-top">
          <button type="button" class="ficha-volver" data-ficha-volver>← Volver a vecinos</button>
          <h2 class="ficha-tit">Ficha de vecino</h2>
        </header>
        <div class="ficha-body">
          <div class="ficha-col ficha-col-perfil">
            <div class="ficha-cara-ring">
              <div class="ficha-cara" data-ficha-img></div>
            </div>
            <h3 class="ficha-nombre" data-ficha-nombre></h3>
            <p class="ficha-desde" data-ficha-desde></p>
            <div class="ficha-animo-row">
              <p class="ficha-animo-line">
                <span class="ficha-animo-label">Ánimo:</span>
                <span class="ficha-animo-val" data-ficha-animo-text></span>
              </p>
              <span class="ficha-animo-ico" data-ficha-animo-ico aria-hidden="true"></span>
            </div>
            <div class="ficha-rasgos" data-ficha-rasgos></div>
            <button type="button" class="ficha-btn-msg" data-ficha-msg disabled>Enviar mensaje</button>
          </div>
          <div class="ficha-col ficha-col-detalles capa-scroll">
            <section class="ficha-seccion">
              <h4 class="ficha-seccion-tit">Le gusta</h4>
              <div class="ficha-chips ficha-gusta" data-ficha-gusta></div>
            </section>
            <section class="ficha-seccion">
              <h4 class="ficha-seccion-tit">No le gusta</h4>
              <div class="ficha-nogusta" data-ficha-nogusta></div>
            </section>
            <section class="ficha-seccion">
              <h4 class="ficha-seccion-tit">Relaciones</h4>
              <div class="ficha-relaciones" data-ficha-relaciones></div>
              <button type="button" class="ficha-ver-mas" data-ficha-rel-mas hidden>Ver más relaciones</button>
            </section>
            <section class="ficha-seccion">
              <h4 class="ficha-seccion-tit">Próximos planes</h4>
              <div class="ficha-planes" data-ficha-planes></div>
            </section>
            <button type="button" class="ficha-btn-org" data-ficha-org>+ Organizar plan</button>
          </div>
        </div>
        <div class="ficha-rel-overlay" data-ficha-rel-overlay hidden>
          <div class="ficha-rel-modal" role="dialog" aria-label="Relaciones del vecino">
            <span class="ficha-tape ficha-tape-l" aria-hidden="true"></span>
            <span class="ficha-tape ficha-tape-r" aria-hidden="true"></span>
            <button type="button" class="cerrar ficha-cerrar" data-ficha-rel-close aria-label="Cerrar">cerrar</button>
            <h3 class="ficha-rel-modal-tit" data-ficha-rel-modal-tit>Relaciones</h3>
            <div class="ficha-rel-scroll capa-scroll" data-ficha-rel-list></div>
          </div>
        </div>
      </aside>
      <aside class="capa capa-buzon">
        <button type="button" class="cerrar capa-cerrar-pestaña" data-close>cerrar</button>
        <p class="libreta-kicker">Correo del pueblo</p>
        <h2 class="mensajitos-tit">Mensajitos</h2>
        <div data-buzon-list></div>
      </aside>
      <aside class="capa capa-diario">
        <button type="button" class="cerrar capa-cerrar-pestaña" data-close>cerrar</button>
        <header class="masthead">
          <img src="assets/play-v3/capas/masthead_cotilleo.png" alt=""/>
          <h2>El Cotilleo</h2>
          <p class="edicion">Diario del pueblo</p>
        </header>
        <div class="tabs-papel">
          <button type="button" class="is-on" data-diario-tab="hoy">Hoy</button>
          <button type="button" data-diario-tab="ayer">Ayer</button>
          <button type="button" class="trapos" data-diario-tab="viejos">Trapos viejos</button>
        </div>
        <div class="dia-block dia-hoy collage" data-coti-hoy></div>
        <div class="dia-block dia-ayer collage" data-coti-ayer></div>
        <div class="dia-block dia-viejos collage" data-coti-viejos></div>
      </aside>
      <aside class="capa capa-misiones">
        <button type="button" class="cerrar capa-cerrar-pestaña" data-close>cerrar</button>
        <p class="libreta-kicker">Libreta de Celestine</p>
        <h2>Hoy en el pueblo</h2>
        <p class="mini">Pequeños objetivos sociales. Opcionales.</p>
        <div data-misiones-list></div>
      </aside>
      <aside class="capa capa-organizar">
        <button type="button" class="cerrar capa-cerrar-pestaña" data-close>cerrar</button>
        <p class="libreta-kicker">Libreta de Celestine</p>
        <h2>Organizar un plan</h2>
        <p class="mini">Tú propones. Ellas viven.</p>
        <div class="org-row" data-org-modo-row>
          <label>Tipo de plan</label>
          <button type="button" class="chip is-on" data-org-modo="pareja">Con alguien</button>
          <button type="button" class="chip" data-org-modo="solo">Por su cuenta</button>
        </div>
        <div class="caras-clip" data-org-caras hidden></div>
        <div class="org-row"><label>¿Con quién?</label><select data-org-a></select></div>
        <div class="org-row" data-org-row-b><label>¿Y con quién más?</label><select data-org-b></select></div>
        <p><strong>¿Qué plan?</strong></p>
        <div class="chips" data-org-tipos></div>
        <div class="org-row"><label>¿Dónde?</label><select data-org-lugar></select></div>
        <div class="org-row"><label>¿Qué día?</label><select data-org-dia></select></div>
        <div class="org-row"><label>¿A qué hora?</label><select data-org-hora></select></div>
        <button type="button" class="cta" data-org-go>Proponer</button>
      </aside>
      <aside class="capa capa-agenda">
        <button type="button" class="cerrar capa-cerrar-pestaña" data-close>cerrar</button>
        <p class="libreta-kicker">Libreta de Celestine</p>
        <h2>Planes</h2>
        <p class="mini">Lo que está por venir. Solo consulta.</p>
        <div class="agenda-list" data-agenda-list></div>
      </aside>
    </div>
  </div>
      </div>
      <aside class="game-right zona-personas">
        <section class="shell-grupo shell-grupo-misiones-par">
          <button type="button" class="obj-misiones-papel" data-open="misiones" aria-label="Ver misiones de hoy">
            <span class="mision-tape mision-tape-tl" aria-hidden="true"></span>
            <span class="mision-tape mision-tape-tr" aria-hidden="true"></span>
            <span class="mision-tape mision-tape-bl" aria-hidden="true"></span>
            <span class="mision-tape mision-tape-br" aria-hidden="true"></span>
            <span class="obj-misiones-papel-tit">MISIONES</span>
            <div class="obj-misiones-strip" data-misiones-strip></div>
          </button>
        </section>
        <section class="shell-grupo shell-grupo-cotilleo-par">
          <button type="button" class="obj-cotilleo obj-cotilleo-par" data-open="diario" aria-label="Abrir diario">
            <span class="obj-cotilleo-tit">Cotilleo</span>
            <p class="obj-cotilleo-txt" data-cotilleo-teaser>—</p>
          </button>
        </section>
        <section class="shell-grupo shell-grupo-parejas">
          <span class="zona-tit zona-tit-parejas">PAREJAS</span>
          <div class="obj-parejas-list" data-parejas-strip></div>
        </section>
      </aside>
    </div>
  </div>
  <script src="assets/js/lab-audit.js?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="assets/js/play-v3.js?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>