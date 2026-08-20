<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
$ahtUi = 'v3-20260820g';
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
  <link rel="stylesheet" href="assets/css/play-v3.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-capas.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-app.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <style>
    .tutorial-pista { margin: 0; padding: .4rem .8rem; font-size: .9rem; }
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
  <p class="tutorial-pista" data-tutorial-pista hidden></p>
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
  <div class="playtest-cheats" data-playtest-cheats <?= $ahtLab ? '' : 'hidden' ?>>
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
  <div class="play-stage">
    <div class="play-root pc" data-pueblo="temprano" data-diario="hoy" data-aforo="1">
      <div class="board-scroll">
        <div class="board-fit">
          <div class="mapa-complejos">
            <button type="button" class="complejo cx-cafe" data-complejo="cafe_libros" aria-label="Cafetería">
              <img class="fachada fachada-temp" src="assets/play-v3/complejos/cafe_temprano.png" alt=""/>
              <img class="fachada fachada-pleno" src="assets/play-v3/complejos/cafe_evolucionado.png" alt=""/>
              <span class="eti-mapa">Cafetería</span>
              <span class="habs"></span>
            </button>
            <button type="button" class="complejo cx-lola" data-complejo="rincon_lola" aria-label="El Rincón de Lola">
              <img class="fachada fachada-temp" src="assets/play-v3/complejos/lola_temprano.png" alt=""/>
              <span class="eti-mapa">El Rincón de Lola</span>
              <span class="habs"></span>
            </button>
            <button type="button" class="complejo cx-cine" data-complejo="cine_game" aria-label="Cine">
              <img class="fachada fachada-temp" src="assets/play-v3/complejos/cine_temprano.png" alt=""/>
              <img class="fachada fachada-pleno" src="assets/play-v3/complejos/cine_evolucionado.png" alt=""/>
              <span class="eti-mapa">Cine</span>
              <span class="habs"></span>
            </button>
            <button type="button" class="complejo cx-mala" data-complejo="mala_idea" aria-label="La Mala Idea">
              <img class="fachada fachada-temp" src="assets/play-v3/complejos/mala_temprano.png" alt=""/>
              <span class="eti-mapa">La Mala Idea</span>
              <span class="habs"></span>
            </button>
            <button type="button" class="complejo cx-parque" data-complejo="parque" aria-label="Parque">
              <img class="fachada fachada-temp" src="assets/play-v3/complejos/parque_temprano.png" alt=""/>
              <span class="eti-mapa">Parque</span>
              <span class="habs"></span>
            </button>
            <button type="button" class="complejo cx-gym" data-complejo="gimnasio_spa" aria-label="Gimnasio">
              <img class="fachada fachada-temp" src="assets/play-v3/complejos/gym_temprano.png" alt=""/>
              <span class="eti-mapa">Gimnasio</span>
              <span class="habs"></span>
            </button>
          </div>
          <div class="bloque-a"><img src="assets/play-v3/complejos/bloque_a.png" alt="Bloque A"/></div>
          <div class="solar solar-b"><img src="assets/play-v3/complejos/solar_b.png" alt="Solar B"/></div>
          <div class="solar solar-c"><img src="assets/play-v3/complejos/solar_c.png" alt="Solar C"/></div>
          <aside class="selector">
            <button type="button" class="cerrar" data-close>cerrar</button>
            <h3 data-s-tit></h3>
            <p class="cotilleo" data-s-coti></p>
            <div class="destinos" data-s-btns></div>
          </aside>
          <aside class="quien">
            <button type="button" class="cerrar" data-close>cerrar</button>
            <p class="kicker">Quién está</p>
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
          <div class="hud-right">
            <div class="dinero" data-dinero>—</div>
            <button type="button" class="buzon" data-open="buzon" aria-label="Buzón">
              <img src="assets/play-v3/hud/sobre.png" alt=""/>
              <span class="badge">0</span>
              <img class="lacre-hud" src="assets/play-v3/hud/lacre.png" alt="OJO"/>
            </button>
            <button type="button" class="btn-organizar-pc" data-open="organizar">Organizar</button>
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

      <aside class="capa capa-vecinos">
        <button type="button" class="cerrar" data-close>cerrar</button>
        <p class="libreta-kicker">Libreta de Celestine</p>
        <h2>Vecinos</h2>
        <p class="mini">Lo que sé, escrito a mano. El resto llegará.</p>
        <div data-vecinos-list></div>
      </aside>
      <aside class="capa capa-ficha">
        <button type="button" class="cerrar" data-close>cerrar</button>
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
        <button type="button" class="cerrar" data-close>cerrar</button>
        <h2>Buzón</h2>
        <p class="mini">Cartas que te dejan. Las urgentes llevan lacre.</p>
        <div data-buzon-list></div>
      </aside>
      <aside class="capa capa-diario">
        <button type="button" class="cerrar" data-close>cerrar</button>
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
        <button type="button" class="cerrar" data-close>cerrar</button>
        <p class="libreta-kicker">Un papelito, no un parte</p>
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
    </div>
  </div>
  <script src="assets/js/play-v3.js?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
