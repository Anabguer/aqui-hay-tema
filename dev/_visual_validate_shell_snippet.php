<?php
// AUTO-GENERADO — no editar a mano. Regenerar: php dev/_gen_visual_shell_snippet.php
declare(strict_types=1);
?>

    <header class="game-top">
      <div class="brand-col">
        <h1 class="brand" aria-label="Aqu� Hay Tema">
          <span class="brand-text">AQU� HAY TEMA</span>
          <span class="brand-heart" aria-hidden="true"></span>
        </h1>
        <p class="top-meta-line" data-top-meta-mobile></p>
        <button type="button" class="btn-guia" data-tut-reopen hidden>�C�mo va esto?</button>
      </div>
      <div class="top-center">
        <div class="top-reloj">
          <div class="obj-dia" style="--rot:-2deg">
            <div class="obj-dia-placa">
              <span class="obj-dia-num" data-dia-num>�</span>
            </div>
            <div class="obj-dia-cuerpo">
              <span class="obj-dia-estacion" data-dia-estacion>Primavera</span>
              <span class="obj-dia-meta" data-dia-meta>�</span>
            </div>
            <span class="sr-only" data-fecha></span>
          </div>
          <div class="obj-hora" style="--rot:3deg" aria-label="Hora del pueblo">
            <span class="obj-hora-ico" aria-hidden="true"></span>
            <span class="obj-hora-val" data-hora>�</span>
          </div>
          <span class="es-noche" data-es-noche hidden>
            <svg class="es-noche-luna" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            <span class="es-noche-txt">Es de noche</span>
          </span>
          <button type="button" class="pasar-rato" data-pasar-rato title="Avanza el tiempo exactamente 1 hora">
            <span class="pasar-rato-ico" aria-hidden="true">?</span>
            <span class="pasar-rato-txt">Pasar el rato</span>
          </button>
        </div>
      </div>
      <button type="button" class="top-vida top-vida-btn" data-open="vida_pueblo" aria-label="Vida del pueblo, pulsa para m�s informaci�n">
        <span class="obj-vida-kicker">Vida del pueblo</span>
        <svg class="corazon-svg corazon-org" viewBox="0 0 58 52" aria-hidden="true">
          <defs>
            <clipPath id="corazon-clip"><path d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/></clipPath>
            <linearGradient id="corazon-agua-grad" x1="29" y1="52" x2="29" y2="0" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#d46278"/>
              <stop offset="55%" stop-color="#e57d90"/>
              <stop offset="100%" stop-color="#f0a8b6"/>
            </linearGradient>
          </defs>
          <path class="corazon-bg" d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/>
          <path class="corazon-fill-path" clip-path="url(#corazon-clip)" fill="url(#corazon-agua-grad)" d="" data-corazon-fill/>
          <path class="corazon-fill-surface" clip-path="url(#corazon-clip)" fill="none" stroke="rgba(255,255,255,.62)" stroke-width="1.15" stroke-linecap="round" d="" data-corazon-surface/>
          <path class="corazon-stroke" fill="none" d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/>
        </svg>
        <span class="sr-only" data-vida-pct>0%</span>
      </button>
      <div class="control-audio" aria-label="Controles de audio">
        <button type="button" class="control-musica" data-musica-toggle aria-pressed="true" aria-label="Desactivar m�sica" title="Desactivar m�sica">
          <span class="control-musica-ico" aria-hidden="true">?</span>
        </button>
        <button type="button" class="control-efectos" data-efectos-toggle aria-pressed="true" aria-label="Desactivar efectos de sonido" title="Desactivar efectos de sonido">
          <span class="control-efectos-ico" aria-hidden="true">?</span>
        </button>
        <button type="button" class="control-inventario" data-open="inventario" aria-label="Abrir inventario" title="Inventario">
          <span class="control-inventario-ico" aria-hidden="true">&#127873;</span>
        </button>
      </div>
    </header>
    <div class="game-main">
      <aside class="game-left zona-actividad">
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
        <section class="shell-grupo shell-grupo-planes">
          <div class="obj-planes-lateral">
            <div class="obj-proximo obj-proximo-polaroid">
              <span class="obj-proximo-tit">Plan en curso</span>
              <div class="obj-curso-nav" data-curso-nav hidden>
                <button type="button" class="obj-curso-btn" data-curso-prev aria-label="Encuentro en curso anterior">&#8249;</button>
                <span class="obj-curso-cont" data-curso-cont>1 / 2</span>
                <button type="button" class="obj-curso-btn" data-curso-next aria-label="Siguiente encuentro en curso">&#8250;</button>
              </div>
              <div class="obj-proximo-body" data-proximo-plan><p class="obj-proximo-vacio">Nada en curso ahora.</p></div>
            </div>
            <div class="obj-planes-prox-block" data-planes-prox-block hidden>
              <header class="obj-planes-prox-cab">
                <span class="obj-planes-prox-tit">Pr&oacute;ximos planes</span>
                <button type="button" class="obj-planes-prox-ver" data-open="agenda">ver todos &#8250;</button>
              </header>
              <div class="obj-planes-prox-grid" data-planes-prox-list></div>
            </div>
            <button type="button" class="obj-nuevo-plan obj-proximo-cta obj-nuevo-plan-horiz" data-open="organizar" aria-label="Nuevo plan">
              <span class="obj-nuevo-plan-ico" aria-hidden="true">+</span>
              <span class="obj-nuevo-plan-txt">Nuevo plan</span>
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
            <button type="button" class="nota-atras" data-consulta-atras hidden aria-label="Atr�s">? Atr�s</button>
            <p class="libreta-kicker">Un vistazo al lugar</p>
            <h3 data-s-tit></h3>
            <p class="cotilleo" data-s-coti></p>
            <div class="destinos" data-s-btns></div>
          </aside>
          <aside class="quien nota-mapa ds-modal-sheet">
            <button type="button" class="cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
            <button type="button" class="nota-atras" data-consulta-atras hidden aria-label="Atr�s">? Atr�s</button>
            <header class="quien-bloque quien-bloque--lugar">
              <h3 class="quien-lugar-tit" data-q-tit></h3>
              <p class="quien-horario" data-q-horario hidden></p>
            </header>
            <section class="quien-bloque quien-bloque--presencia">
              <p class="libreta-kicker quien-kicker">�Qui�n est�?</p>
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
            <div class="dow" data-dow>�</div>
            <div class="fecha" data-fecha></div>
            <div class="hora" data-hora>�</div>
          </div>
          <div class="tiempo-juego" aria-label="Avanzar el tiempo">
            <button type="button" class="tique-hora" data-horas="1" title="Avanzar una hora">+1 h</button>
            <button type="button" class="tique-dia" data-horas="24" title="Avanzar un d�a">+1 d�a</button>
          </div>
          <div class="hud-right">
            <div class="dinero" data-dinero>�</div>
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
          <div class="tut-hero" data-tut-hero hidden></div>
          <h2 class="tut-titulo" data-tut-tit></h2>
          <p class="tut-intro-line" data-tut-intro-line hidden></p>
          <p class="tut-intro-extra" data-tut-intro-extra hidden></p>
          <div class="tut-caras" data-tut-caras hidden></div>
          <p class="tut-bloques-pref" data-tut-bloques-pref hidden></p>
          <div class="tut-bloques" data-tut-bloques hidden></div>
          <div class="tut-tareas" data-tut-tareas hidden></div>
          <p class="tut-cierre" data-tut-cierre hidden></p>
          <div class="tut-pasos" data-tut-pasos></div>
          <div class="tut-acciones">
            <button type="button" class="cta ghost" data-tut-atras hidden>Atr�s</button>
            <button type="button" class="cta tut-cta-final" data-tut-siguiente>Siguiente</button>
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
          <p class="tut-texto">La vida del pueblo ha llegado a un punto cr�tico. Celestine no ha podido mantener el equilibrio.</p>
          <button type="button" class="cta" data-vida-derrota-ok>Entendido</button>
        </div>
      </aside>

      <aside class="capa capa-vecinos" aria-label="Vecinos del pueblo">
        <button type="button" class="cerrar vecinos-cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
        <header class="vecinos-cab">
          <div class="ds-modal-head">
            <div class="ds-modal-head-row vecinos-head-row">
              <h2 class="ds-modal-tit ds-modal-tit--ink">Vecinos del pueblo</h2>
              <span class="vecinos-cuenta-wrap" data-vec-cuenta-wrap><span class="vecinos-cuenta ds-pill ds-pill--pink" data-vecinos-count></span></span>
            </div>
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
            <p class="ds-modal-sub agenda-sub">Lo que est� por venir.</p>
          </div>
        </header>
        <div class="agenda-list capa-scroll" data-agenda-list></div>
      </aside>
      <aside class="capa capa-ficha ds-modal-sheet" aria-label="Ficha de vecino">
        <span class="ficha-tape ficha-tape-l" aria-hidden="true"></span>
        <span class="ficha-tape ficha-tape-r" aria-hidden="true"></span>
        <button type="button" class="cerrar ficha-cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
        <button type="button" class="ficha-btn-diario" data-ficha-diario-btn><span class="ficha-btn-diario-ico" aria-hidden="true">&#128211;</span> Diario</button>
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
                <button type="button" class="ficha-animo-q" data-ficha-animo-q hidden aria-label="�Por qu� est� as�?">?</button>
              </div>
            </div>
          </div>
        </section>
        <div class="ficha-body">
          <div class="ficha-rasgos" data-ficha-rasgos></div>
          <div class="ficha-col ficha-col-detalles capa-scroll">
            <section class="ficha-seccion">
              <h4 class="ficha-seccion-tit">Hobbies</h4>
              <div class="ficha-seccion-body">
                <div class="ficha-hobbies" data-ficha-hobbies></div>
              </div>
            </section>
            <section class="ficha-seccion ficha-seccion-prefs" data-ficha-sabes hidden>
              <h4 class="ficha-seccion-tit ficha-seccion-tit-sm">Lo que sabes</h4>
              <div class="ficha-seccion-body ficha-seccion-body-prefs" data-ficha-sabes-body></div>
            </section>
            <section class="ficha-seccion">
              <h4 class="ficha-seccion-tit">Relaciones</h4>
              <div class="ficha-seccion-body">
                <div class="ficha-relaciones" data-ficha-relaciones></div>
              <button type="button" class="ficha-ver-mas" data-ficha-rel-mas hidden>Ver m�s relaciones</button>
              </div>
            </section>
            <section class="ficha-seccion">
              <h4 class="ficha-seccion-tit">Pr�ximos planes</h4>
              <div class="ficha-seccion-body">
                <div class="ficha-planes" data-ficha-planes></div>
              </div>
            </section>
            <button type="button" class="ficha-btn-org" data-ficha-org>+ Organizar plan</button>
            <button type="button" class="ficha-btn-regalo" data-ficha-regalar>REGALAR</button>
            <p class="ficha-aprecio" data-ficha-aprecio hidden></p>
          </div>
        </div>
        <div class="ficha-rel-overlay" data-ficha-rel-overlay hidden>
          <div class="ficha-rel-modal" role="dialog" aria-label="Relaciones del vecino">
            <span class="ficha-tape ficha-tape-l" aria-hidden="true"></span>
            <span class="ficha-tape ficha-tape-r" aria-hidden="true"></span>
            <button type="button" class="cerrar ficha-cerrar ds-modal-close" data-ficha-rel-close aria-label="Cerrar">X</button>
            <h3 class="ficha-rel-modal-tit ds-modal-tit ds-modal-tit--ink" data-ficha-rel-modal-tit>Relaciones</h3>
            <div class="ficha-rel-scroll capa-scroll" data-ficha-rel-list></div>
          </div>
        </div>
        <div class="ficha-rel-overlay" data-animo-overlay hidden>
          <div class="ficha-rel-modal ficha-modal-animo" role="dialog" aria-label="�Por qu� est� as�?">
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
          <p class="mis-sub mini" data-misiones-teaser>�</p>
        </header>
        <div class="mis-body capa-scroll misiones-body" data-misiones-list></div>
      </aside>
      <aside class="capa capa-vida-pueblo vida-modal-papel ds-modal-sheet" aria-label="Vida del pueblo" role="dialog" aria-modal="true">
        <button type="button" class="cerrar vida-cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
        <header class="vida-top">
          <p class="vida-modal-ico" aria-hidden="true">??</p>
          <h2 class="vida-tit ds-modal-tit ds-modal-tit--pink">Vida del pueblo</h2>
          <p class="vida-valor" data-vida-modal-valor>� / 100</p>
          <div class="vida-valor-bar" data-vida-modal-bar hidden><span style="width:0%"></span></div>
          <p class="vida-estado-pista mini" data-vida-modal-estado hidden></p>
        </header>
        <div class="vida-body capa-scroll">
          <div class="vida-copy">
            <p>Esto no es decoraci�n, aunque lo parezca.</p>
            <p>Tus vecinos tienen una peligrosa tendencia a complicarse la vida y, por alg�n motivo, ahora son responsabilidad tuya.</p>
            <p>Haz que las cosas salgan bien y el coraz�n subir�. D�jalos a su suerte demasiado tiempo y� bueno, procura que esto no llegue a 0.</p>
          </div>
          <p class="vida-latido mini">�Llegas a 100? ?? Hay latido.<br>S�, conseguir que este pueblo funcione tiene premio. Incre�ble, pero cierto.</p>
        </div>
      </aside>
      <aside class="capa capa-buzon" aria-label="Mensajitos">
        <button type="button" class="cerrar mensajitos-cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
        <header class="mensajitos-cab">
          <div class="mensajitos-cab-row">
            <span class="mensajitos-icono" aria-hidden="true">&#9993;</span>
            <h2 class="mensajitos-tit ds-modal-tit ds-modal-tit--pink">Mensajitos</h2>
            <span class="mensajitos-contador" data-buzon-nuevos-count hidden></span>
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
      <aside class="capa capa-inventario" aria-label="Inventario de Celestine">
        <button type="button" class="cerrar inv-cerrar" data-close aria-label="Cerrar">&times;</button>
        <header class="inv-cab">
          <h2 class="inv-tit">Inventario</h2>
          <p class="inv-sub" data-inv-sub>Detalles guardados para regalar a los vecinos.</p>
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

      <aside class="capa capa-diario coti-modal-papel" aria-label="Cotilleos">
        <button type="button" class="cerrar coti-cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
        <header class="coti-top">
          <div class="ds-modal-head">
            <div class="ds-modal-head-row">
              <span class="ds-modal-icon ds-modal-icon--lavender" aria-hidden="true">&#128226;</span>
              <h2 class="coti-tit ds-modal-tit ds-modal-tit--lavender">Cotilleos</h2>
              <span class="coti-badge" data-coti-count hidden></span>
            </div>
            <p class="ds-modal-sub">Lo �ltimo que corre por el pueblo</p>
          </div>
          <div class="coti-filtros" data-coti-filtros role="group" aria-label="Filtrar por tipo" hidden></div>
        </header>
        <div class="coti-body capa-scroll">
          <div class="coti-list" data-coti-list></div>
        </div>
      </aside>
      <aside class="capa capa-organizar org-plan-papel ds-modal-sheet" aria-label="Nuevo plan">
        <span class="ficha-tape ficha-tape-l org-tape-tl" aria-hidden="true"></span>
        <span class="ficha-tape ficha-tape-r org-tape-tr" aria-hidden="true"></span>
        <span class="org-pin org-pin-l" aria-hidden="true"></span>
        <button type="button" class="cerrar org-cerrar ds-modal-close" data-close aria-label="Cerrar">X</button>
        <header class="org-top">
          <div class="ds-modal-head">
            <div class="ds-modal-head-row">
              <span class="ds-modal-icon ds-modal-icon--pink" aria-hidden="true">&#10024;</span>
              <h2 class="org-tit ds-modal-tit ds-modal-tit--ink">Nuevo plan</h2>
            </div>
          </div>
          <p class="org-aviso" data-org-aviso hidden></p>
        </header>
        <div class="org-body capa-scroll">
          <section class="ficha-seccion org-seccion org-seccion--modo">
            <div class="org-seccion-head org-seccion-head--center">
              <div class="org-modo-chips" data-org-modo-row>
                <button type="button" class="org-modo-chip" data-org-modo="solo">Solo</button>
                <button type="button" class="org-modo-chip is-on" data-org-modo="pareja">Acompa�ado</button>
              </div>
            </div>
          </section>
          <section class="ficha-seccion org-seccion org-seccion--quienes">
            <div class="org-seccion-head">
              <h4 class="ficha-seccion-tit">�Qui�nes van?</h4>
              <p class="org-seccion-meta org-picker-hint" data-org-picker-hint>Elige hasta 2 vecinos.</p>
            </div>
            <div class="ficha-seccion-body">
              <div class="org-busca-wrap">
                <input type="search" class="org-busca" data-org-busca placeholder="Buscar vecino�" autocomplete="off" aria-label="Buscar vecino"/>
                <span class="org-busca-todos" data-org-mostrar-todos role="button" tabindex="0" hidden>mostrar todos</span>
              </div>
              <div class="org-picker-strip capa-scroll" data-org-picker></div>
            </div>
          </section>
          <section class="ficha-seccion org-seccion org-seccion--que">
            <h4 class="ficha-seccion-tit">�Qu� buscamos?</h4>
            <div class="ficha-seccion-body">
              <div class="org-tipos" data-org-tipos></div>
            </div>
          </section>
          <section class="ficha-seccion org-seccion org-seccion--donde">
            <h4 class="ficha-seccion-tit">�D�nde?</h4>
            <div class="ficha-seccion-body org-donde-fila">
              <div class="org-dd" data-org-dd-lugar></div>
              <select class="org-select org-select-native" data-org-lugar hidden tabindex="-1" aria-hidden="true"></select>
              <p class="org-lugar-horario mini" data-org-lugar-horario hidden></p>
            </div>
          </section>
          <section class="ficha-seccion org-seccion org-seccion--cuando">
            <h4 class="ficha-seccion-tit">�Cu�ndo?</h4>
            <div class="ficha-seccion-body">
              <div class="org-cuando">
                <div class="org-dd org-dd--dia" data-org-dd-dia></div>
                <div class="org-dd org-dd--hora" data-org-dd-hora></div>
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
      </div>
      <aside class="game-right zona-personas">
        <section class="shell-grupo shell-grupo-misiones-par" id="mob-misiones">
          <div class="obj-misiones-papel" aria-label="Misiones de hoy">
            <span class="mision-tape mision-tape-tl" aria-hidden="true"></span>
            <span class="mision-tape mision-tape-tr" aria-hidden="true"></span>
            <span class="mision-tape mision-tape-bl" aria-hidden="true"></span>
            <span class="mision-tape mision-tape-br" aria-hidden="true"></span>
            <span class="obj-misiones-papel-tit">MISIONES</span>
            <div class="obj-misiones-strip" data-misiones-strip></div>
          </div>
        </section>
        <section class="shell-grupo proxplanes-movil" data-proxplanes-block aria-label="Pr�ximos planes programados">
                    <header class="pp-mov-cab plan-seccion-cab">
            <svg class="plan-seccion-ico pp-mov-ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="3.2" y="5" width="17.6" height="15.4" rx="2.4" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M3.4 9.6h17.2" stroke="currentColor" stroke-width="1.7"/><path d="M8.2 3.2v3.4M15.8 3.2v3.4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
            <h3 class="pp-mov-tit">PR�XIMOS PLANES</h3>
            <span class="plan-seccion-rule" aria-hidden="true"></span>
            <button type="button" class="plan-seccion-ver" data-open="agenda">VER TODOS �</button>
          </header>
          <div class="pp-mov-track" data-proxplanes-track></div>
        </section>
        <section class="shell-grupo encursos-movil" data-encursos-block aria-label="Planes en curso ahora">
                    <header class="enc-mov-cab plan-seccion-cab">
            <svg class="plan-seccion-ico enc-mov-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 0 1 13.3-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M20 12a8 8 0 0 1-13.3 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M16.5 3.5V8h-4.5M7.5 20.5V16H12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <h3 class="enc-mov-tit">PLANES EN CURSO</h3>
            <span class="plan-seccion-rule" aria-hidden="true"></span>
          </header>
          <div class="enc-mov-track" data-encursos-track></div>
          <p class="enc-mov-indice" data-encursos-indice hidden aria-hidden="true"></p>
        </section>
        <section class="shell-grupo shell-grupo-cotilleo-par">
          <button type="button" class="obj-cotilleo obj-cotilleo-par" data-open="diario" aria-label="Abrir cotilleo del pueblo">
            <span class="obj-cotilleo-tit">Cotilleo</span>
            <span class="obj-cotilleo-cuerpo">
              <span class="obj-cotilleo-txt" data-cotilleo-teaser>Hoy est�n sospechosamente tranquilos�</span>
              <span class="obj-cotilleo-badge" data-cotilleo-badge hidden></span>
            </span>
            <span class="obj-cotilleo-flecha" aria-hidden="true">�</span>
          </button>
        </section>
        <section class="shell-grupo shell-grupo-parejas" id="mob-parejas">
          <span class="zona-tit zona-tit-parejas">PAREJAS</span>
          <div class="obj-parejas-list" data-parejas-strip></div>
        </section>
      </aside>