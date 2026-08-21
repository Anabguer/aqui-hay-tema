<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
$ahtUi = 'v3-20260821aa';
$ahtTaller = isset($_GET['taller']) && (string) $_GET['taller'] !== '0';
$ahtLab = isset($_GET['lab']) && (string) $_GET['lab'] !== '0';
if ($ahtLab) {
    $ahtTaller = true; // playtest siempre muestra cheats de tiempo
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <meta name="aht-ui" content="v3"/>
  <title><?= $ahtLab ? 'Playtest · Aquí Hay Tema' : 'Aquí Hay Tema' ?></title>
  <link rel="icon" href="cover.svg" type="image/svg+xml"/>
  <link rel="stylesheet" href="assets/css/play-v3.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-capas.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-app.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-shell-ui.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-shell-art.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-capas-shell.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-mensajitos.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-bloques-residencias.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <style>
    .tutorial-pista {
      margin: 0; padding: .45rem .85rem; font-size: .88rem;
      font-family: Fraunces, Georgia, serif; font-style: italic;
      background: #f3e6cc; border-bottom: 2px solid #c9b8a0; color: #2a2218;
    }
    body.play-v3:not([data-taller="1"]) .taller-cheat { display: none; }
    body.play-v3:not([data-lab="1"]) .playtest-guia,
    body.play-v3:not([data-lab="1"]) .playtest-cheats { display: none !important; }
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
  </style>
</head>
<body class="play-v3" data-ui="v3" data-taller="<?= $ahtTaller ? '1' : '0' ?>" data-lab="<?= $ahtLab ? '1' : '0' ?>">
    <?php if ($ahtLab): ?>
  <div class="playtest-float" data-playtest-float>
    <button type="button" class="playtest-float-toggle" data-playtest-toggle aria-expanded="false" title="Controles de playtest">🧪 Playtest</button>
    <div class="playtest-float-panel" hidden>
      <p class="playtest-float-title">Laboratorio</p>
      <button type="button" id="btn-nueva">Nueva partida</button>
      <button type="button" class="taller-cheat" id="btn-guardar">Guardar</button>
      <button type="button" class="taller-cheat" data-horas="1">+1h</button>
      <button type="button" class="taller-cheat" data-horas="8">+8h</button>
      <button type="button" class="taller-cheat" data-horas="24">+1 día</button>
      <button type="button" class="taller-cheat" id="btn-proximo">Ir al próximo</button>
      <a class="taller-cheat" href="play-provisional.php">UI anterior</a>
      <span class="msg taller-cheat" data-taller-msg></span>
    </div>
  </div>
  <?php endif; ?>
  <p class="tutorial-pista" data-tutorial-pista hidden></p>
  <?php if (!$ahtLab): ?>
  <div class="taller">
    <strong class="<?= $ahtLab ? 'lab' : '' ?>"><?= $ahtLab ? 'Playtest Neni' : 'Aquí Hay Tema' ?></strong>
    <button type="button" id="btn-nueva">Nueva partida</button>
    <button type="button" class="taller-cheat" id="btn-guardar">Guardar</button>
    <button type="button" class="taller-cheat" data-horas="1">+1h</button>
    <button type="button" class="taller-cheat" data-horas="8">+8h</button>
    <button type="button" class="taller-cheat" data-horas="24">+1 día</button>
    <button type="button" class="taller-cheat" id="btn-proximo">Ir al próximo</button>
    <a class="taller-cheat" href="play-provisional.php">UI anterior</a>
    <span class="msg taller-cheat" data-taller-msg></span>
  </div>
  <?php endif; ?>
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
      <h1 class="brand" aria-label="Aquí Hay Tema">
        <span class="brand-text">AQUÍ HAY TEMA</span>
        <span class="brand-heart" aria-hidden="true"></span>
      </h1>
      <div class="top-center">
        <div class="obj-dia" style="--rot:-2deg">
          <div class="obj-dia-placa">
            <span class="obj-dia-num" data-dia-num>Dï¿½A ï¿½</span>
          </div>
          <div class="obj-dia-cuerpo">
            <span class="obj-dia-estacion" data-dia-estacion>Primavera</span>
            <span class="obj-dia-meta" data-dia-meta>ï¿½</span>
          </div>
          <span class="sr-only" data-fecha></span>
        </div>
        <div class="obj-dinero" style="--rot:0.6deg">
          <span class="obj-dinero-txt" data-dinero>Dinero: …</span>
        </div>
      </div>
      <div class="top-vida" aria-label="Vida del pueblo">
        <span class="obj-vida-kicker">Vida del pueblo</span>
        <svg class="corazon-svg corazon-org" viewBox="0 0 58 52" width="68" height="62" aria-hidden="true">
          <defs><filter id="corazon-hand" x="-5%" y="-5%" width="110%" height="110%"><feTurbulence type="fractalNoise" baseFrequency="0.04" numOctaves="2" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="0.8"/></filter></defs><filter id="corazon-hand" x="-5%" y="-5%" width="110%" height="110%"><feTurbulence type="fractalNoise" baseFrequency="0.04" numOctaves="2" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="0.8"/></filter></defs> width="68" height="62" aria-hidden="true">
          <path class="corazon-bg" d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/>
          <clipPath id="corazon-clip"><path d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/></clipPath>
          <rect class="corazon-fill-rect" clip-path="url(#corazon-clip)" x="0" y="52" width="58" height="0"/>
          <path class="corazon-stroke" fill="none" d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/>
        </svg>
        <span class="sr-only" data-vida-pct>0%</span>
      </div>
    </header>
    <div class="game-main">
      <aside class="game-left zona-actividad">
        <section class="shell-grupo shell-grupo-resumen">
          <button type="button" class="obj-vecinos-resumen celestine-nota" data-open="vecinos" aria-label="Ver vecinos">
            <span class="libreta-kicker">Celestine apunta</span>
            <span class="obj-vecinos-tit">Vecinos</span>
            <div class="obj-vecinos-stats" data-resumen-stats></div>
          </button>
        </section>
        <section class="shell-grupo shell-grupo-buzon">
          <button type="button" class="obj-buzon" data-open="buzon" aria-label="Abrir buzón">
            <span class="obj-buzon-badge" data-buzon-badge hidden>0</span>
            <img class="obj-buzon-img" src="assets/play-v3/hud/sobre.png" alt="" width="52" height="42"/>
            <span class="obj-buzon-txt">BUZÃ“N</span>
          </button>
        </section>
        <section class="shell-grupo shell-grupo-planes">
          <div class="obj-proximo">
            <span class="obj-proximo-tit">Próximo plan</span>
            <div class="obj-proximo-body" data-proximo-plan><p class="obj-proximo-vacio">Nada en agenda. Sospechoso.</p></div>
            <button type="button" class="obj-planes-pend" data-planes-pend hidden>
              <span data-planes-pend-txt></span>
            </button>
          </div>
          <button type="button" class="obj-nuevo-plan obj-nota-rosa" data-open="organizar" aria-label="Nuevo plan">
            <span class="obj-nuevo-plan-ico" aria-hidden="true">+</span>
            <span class="obj-nuevo-plan-txt">NUEVO PLAN</span>
          </button>
        </section>
        <section class="shell-grupo shell-grupo-cotilleo">
          <button type="button" class="obj-cotilleo" data-open="diario" aria-label="Abrir diario">
            <span class="obj-cotilleo-tit">Cotilleo</span>
            <p class="obj-cotilleo-txt" data-cotilleo-teaser>—</p>
          </button>
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
          <div class="mapa-complejos">
            <button type="button" class="complejo cx-cafe" data-complejo="cafe_libros" aria-label="Cafetería">
              <span class="edificios">
                <img class="edif b-cafeteria" data-fase="cafeteria" data-destino="lug_cafeteria" src="assets/play-v3/edificios/cafeteria.png" alt=""/>
                <img class="edif b-biblioteca" data-fase="biblioteca" data-destino="lug_biblioteca" src="assets/play-v3/edificios/biblioteca.png" alt=""/>
                <img class="edif b-tienda" data-fase="tienda" data-destino="lug_tienda_ropa" src="assets/play-v3/edificios/tienda.png" alt=""/>
              </span>
              <span class="habs"></span>
            </button>
            <button type="button" class="complejo cx-lola" data-complejo="rincon_lola" aria-label="El Rincón de Lola">
              <span class="edificios">
                <img class="edif b-restaurante" data-fase="restaurante" data-destino="lug_restaurante" src="assets/play-v3/edificios/restaurante.png" alt=""/>
                <img class="edif b-bingo" data-fase="bingo" data-destino="lug_bingo" src="assets/play-v3/edificios/bingo.png" alt=""/>
              </span>
              <span class="habs"></span>
            </button>
            <button type="button" class="complejo cx-cine" data-complejo="cine_game" aria-label="Cine">
              <span class="edificios">
                <img class="edif b-cine" data-fase="cine" data-destino="lug_cine" src="assets/play-v3/edificios/cine.png" alt=""/>
                <img class="edif b-recreativo" data-fase="recreativo" data-destino="lug_arcade" src="assets/play-v3/edificios/recreativo.png" alt=""/>
              </span>
              <span class="habs"></span>
            </button>
            <button type="button" class="complejo cx-mala" data-complejo="mala_idea" aria-label="La Mala Idea">
              <span class="edificios">
                <img class="edif b-bar" data-fase="bar" data-destino="lug_bar" src="assets/play-v3/edificios/bar.png" alt=""/>
                <img class="edif b-discoteca" data-fase="discoteca" data-destino="lug_discoteca" src="assets/play-v3/edificios/discoteca.png" alt=""/>
                <img class="edif b-karaoke" data-fase="karaoke" data-destino="lug_karaoke" src="assets/play-v3/edificios/karaoke.png" alt=""/>
              </span>
              <span class="habs"></span>
            </button>
            <button type="button" class="complejo cx-parque" data-complejo="parque" aria-label="Parque">
              <span class="edificios">
                <img class="edif b-picnic" data-fase="picnic" data-destino="lug_picnic" src="assets/play-v3/edificios/picnic.png" alt=""/>
                <img class="edif b-mirador" data-fase="mirador" data-destino="lug_mirador" src="assets/play-v3/edificios/mirador.png" alt=""/>
              </span>
              <span class="habs"></span>
            </button>
            <button type="button" class="complejo cx-gym" data-complejo="gimnasio_spa" aria-label="Gimnasio">
              <span class="edificios">
                <img class="edif b-gimnasio" data-fase="gimnasio" data-destino="lug_gimnasio" src="assets/play-v3/edificios/gimnasio.png" alt=""/>
                <img class="edif b-spa" data-fase="spa" data-destino="lug_spa" src="assets/play-v3/edificios/spa.png" alt=""/>
              </span>
              <span class="habs"></span>
            </button>
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
            <button type="button" class="buzon" data-open="buzon" aria-label="Buzón">
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
      <button type="button" class="btn-guia" data-tut-reopen hidden>¿Cómo va esto?</button>
      <aside class="tut-intro" data-tut-intro hidden aria-live="polite">
        <div class="tut-papel">
          <button type="button" class="cerrar tut-skip" data-tut-skip aria-label="Saltar tutorial">Saltar</button>
          <p class="libreta-kicker">Primeros pasos</p>
          <h2 data-tut-tit></h2>
          <p class="tut-texto" data-tut-texto></p>
          <div class="tut-pasos" data-tut-pasos></div>
          <div class="tut-acciones">
            <button type="button" class="cta ghost" data-tut-atras hidden>Atrás</button>
            <button type="button" class="cta" data-tut-siguiente>Siguiente</button>
          </div>
        </div>
      </aside>

      <aside class="capa capa-vecinos">
        <button type="button" class="cerrar capa-cerrar-pestaña" data-close>cerrar</button>
        <p class="libreta-kicker">Libreta de Celestine</p>
        <h2>Vecinos</h2>
        <p class="mini">Lo que sé, escrito a mano. El resto llegará.</p>
        <div data-vecinos-list></div>
      </aside>
      <aside class="capa capa-residencias">
        <button type="button" class="cerrar capa-cerrar-pestaña" data-close>cerrar</button>
        <p class="libreta-kicker">Libreta de Celestine</p>
        <h2>Quién vive aquí</h2>
        <p class="mini">Por bloque, como apunto en la libreta.</p>
        <div class="res-placas-row" data-res-bloque-tabs></div>
        <div class="res-busca-tira">
          <label class="res-busca-etiq" for="res-busca-input">¿Buscas a alguien?</label>
          <input type="text" id="res-busca-input" class="res-busca-campo" data-res-busca autocomplete="off" spellcheck="false" placeholder="nombreâ€¦"/>
        </div>
        <div class="res-grid" data-res-grid></div>
      </aside>
      <aside class="capa capa-ficha">
        <button type="button" class="cerrar capa-cerrar-pestaña" data-close>cerrar</button>
        <p class="libreta-kicker">Libreta de Celestine</p>
        <div class="ficha-hero">
          <div class="cara" data-ficha-img></div>
          <div>
            <h2 data-ficha-nombre></h2>
            <p class="animo" data-ficha-animo></p>
          </div>
        </div>
        <div class="seccion-lib">
          <h3>Lo que ya sé</h3>
          <ul data-ficha-pistas></ul>
        </div>
        <button type="button" class="cta" data-ficha-org>Organizar un plan</button>
      </aside>
      <aside class="capa capa-buzon">
        <button type="button" class="cerrar capa-cerrar-pestaña" data-close>cerrar</button>
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
      <aside class="capa capa-organizar">
        <button type="button" class="cerrar capa-cerrar-pestaña" data-close>cerrar</button>
        <p class="libreta-kicker">Libreta de Celestine</p>
        <h2>Organizar un plan</h2>
        <p class="mini">Tú propones. Ellas viven.</p>
        <div class="org-row"><label>¿Con quién?</label><select data-org-a></select></div>
        <div class="org-row"><label>¿Y con quién más?</label><select data-org-b></select></div>
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
        <section class="shell-grupo shell-grupo-residencias">
          <span class="zona-tit">Bloques</span>
          <div class="obj-residencias-row obj-bloques-res" data-bloques-row></div>
        </section>
        <section class="shell-grupo shell-grupo-parejas">
          <span class="zona-tit zona-tit-parejas">Parejas</span>
          <div class="obj-parejas-list" data-parejas-strip></div>
        </section>
      </aside>
    </div>
  </div>
  <script src="assets/js/play-v3.js?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>