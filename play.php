<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
$ahtUi = 'v3-20260820a';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <meta name="aht-ui" content="v3"/>
  <title>Aquí Hay Tema</title>
  <link rel="stylesheet" href="assets/css/play-v3.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-capas.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
  <link rel="stylesheet" href="assets/css/play-v3-app.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>
</head>
<body class="play-v3" data-ui="v3">
  <div class="taller">
    <strong>Aquí Hay Tema</strong>
    <button type="button" id="btn-guardar">Guardar</button>
    <button type="button" id="btn-nueva">Nueva partida</button>
    <button type="button" data-horas="1">+1h</button>
    <button type="button" data-horas="8">+8h</button>
    <button type="button" data-horas="24">+1 día</button>
    <button type="button" id="btn-proximo">Ir al próximo</button>
    <a href="play-provisional.php">UI anterior</a>
    <span class="msg" data-taller-msg></span>
  </div>
  <div class="play-stage">
    <div class="play-root pc" data-pueblo="temprano" data-diario="hoy" data-aforo="1">
      <div class="board-scroll">
        <div class="board-fit">
          <div class="mapa-complejos">
            <button type="button" class="complejo cx-cafe" data-complejo="cafe_libros" aria-label="Café y libros">
              <img class="fachada fachada-temp" src="assets/play-v3/complejos/cafe_temprano.png" alt=""/>
              <img class="fachada fachada-pleno" src="assets/play-v3/complejos/cafe_evolucionado.png" alt=""/>
              <span class="habs"></span>
            </button>
            <button type="button" class="complejo cx-lola" data-complejo="rincon_lola" aria-label="El Rincón de Lola">
              <img class="fachada fachada-temp" src="assets/play-v3/complejos/lola_temprano.png" alt=""/>
              <span class="habs"></span>
            </button>
            <button type="button" class="complejo cx-cine" data-complejo="cine_game" aria-label="Cine Game">
              <img class="fachada fachada-temp" src="assets/play-v3/complejos/cine_temprano.png" alt=""/>
              <img class="fachada fachada-pleno" src="assets/play-v3/complejos/cine_evolucionado.png" alt=""/>
              <span class="habs"></span>
            </button>
            <button type="button" class="complejo cx-mala" data-complejo="mala_idea" aria-label="La Mala Idea">
              <img class="fachada fachada-temp" src="assets/play-v3/complejos/mala_temprano.png" alt=""/>
              <span class="habs"></span>
            </button>
            <button type="button" class="complejo cx-parque" data-complejo="parque" aria-label="Parque">
              <img class="fachada fachada-temp" src="assets/play-v3/complejos/parque_temprano.png" alt=""/>
              <span class="habs"></span>
            </button>
            <button type="button" class="complejo cx-gym" data-complejo="gimnasio_spa" aria-label="Gimnasio">
              <img class="fachada fachada-temp" src="assets/play-v3/complejos/gym_temprano.png" alt=""/>
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
          <span class="corazon" aria-label="Vida del pueblo"><span class="corazon-dibujo" style="--fill:0%"></span></span>
          <div class="dia"><div class="dow" data-dow>—</div><div class="hora" data-hora>—</div></div>
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
        <p class="mini">Te escriben a ti. Lo importante lleva lacre.</p>
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
        <p class="mini">Tú propones. El motor decide si el plan existe entre esas dos personas.</p>
        <div class="org-row"><label>Quién</label><select data-org-a></select></div>
        <div class="org-row"><label>Con quién</label><select data-org-b></select></div>
        <p><strong>Qué tipo de plan</strong></p>
        <div class="chips" data-org-tipos></div>
        <div class="org-row"><label>Dónde</label><select data-org-lugar></select></div>
        <div class="org-row"><label>Día</label><select data-org-dia></select></div>
        <div class="org-row"><label>Hora</label><select data-org-hora></select></div>
        <button type="button" class="cta" data-org-go>Proponer</button>
      </aside>
    </div>
  </div>
  <script src="assets/js/play-v3.js?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
