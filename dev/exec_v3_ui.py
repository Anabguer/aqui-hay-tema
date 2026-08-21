#!/usr/bin/env python3
"""Parches UI V3: play.php + play-v3.js + juego_v1.json"""
from pathlib import Path
import re
import json

ROOT = Path(__file__).resolve().parent.parent

# --- play.php ---
php = (ROOT / "play.php").read_text(encoding="utf-8")

php = php.replace(
    "$ahtTaller = isset($_GET['taller']) && (string) $_GET['taller'] !== '0';\n$ahtLab = isset($_GET['lab']) && (string) $_GET['lab'] !== '0';\nif ($ahtLab) {\n    $ahtTaller = true; // playtest siempre muestra cheats de tiempo\n}",
    "$ahtLab = isset($_GET['lab']) && (string) $_GET['lab'] !== '0';\n$ahtTaller = $ahtLab; // dev solo con ?lab=1",
)

php = re.sub(
    r'  <link rel="stylesheet" href="assets/css/play-v3-bloques-residencias\.css[^"]*"/>\n',
    "",
    php,
)

php = php.replace(
    "body.play-v3:not([data-taller=\"1\"]) .taller-cheat { display: none; }",
    "body.play-v3:not([data-lab=\"1\"]) .taller,\n    body.play-v3:not([data-lab=\"1\"]) .taller-cheat { display: none !important; }",
)

php = php.replace(
    '<span class="obj-dia-num" data-dia-num>Dï¿½A ï¿½</span>',
    '<span class="obj-dia-num" data-dia-num>DÍA ·</span>',
)
php = php.replace(
    '<span class="obj-dia-meta" data-dia-meta>ï¿½</span>',
    '<span class="obj-dia-meta" data-dia-meta>·</span>',
)

php = re.sub(
    r'\s*<div class="obj-dinero" style="--rot:0\.6deg">[\s\S]*?</div>\n',
    "\n",
    php,
    count=1,
)

php = php.replace(
    """        <svg class="corazon-svg corazon-org" viewBox="0 0 58 52" width="68" height="62" aria-hidden="true">
          <defs><filter id="corazon-hand" x="-5%" y="-5%" width="110%" height="110%"><feTurbulence type="fractalNoise" baseFrequency="0.04" numOctaves="2" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="0.8"/></filter></defs><filter id="corazon-hand" x="-5%" y="-5%" width="110%" height="110%"><feTurbulence type="fractalNoise" baseFrequency="0.04" numOctaves="2" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="0.8"/></filter></defs> width="68" height="62" aria-hidden="true">
          <path class="corazon-bg" d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/>
          <clipPath id="corazon-clip"><path d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/></clipPath>
          <rect class="corazon-fill-rect" clip-path="url(#corazon-clip)" x="0" y="52" width="58" height="0"/>
          <path class="corazon-stroke" fill="none" d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/>
        </svg>""",
    """        <svg class="corazon-svg corazon-org" viewBox="0 0 58 52" width="68" height="62" aria-hidden="true">
          <defs><filter id="corazon-hand" x="-5%" y="-5%" width="110%" height="110%"><feTurbulence type="fractalNoise" baseFrequency="0.04" numOctaves="2" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="0.8"/></filter></defs>
          <path class="corazon-bg" d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/>
          <clipPath id="corazon-clip"><path d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/></clipPath>
          <rect class="corazon-fill-rect" clip-path="url(#corazon-clip)" x="0" y="0" width="58" height="52" data-corazon-fill/>
          <path class="corazon-stroke" fill="none" d="M29 48.5 C29 48.5 5.5 31 4.5 17.5 C3.5 8.5 11.5 2.5 19.5 3.5 C24.5 4 28 8.5 29 9.5 C30 8 33.5 3.5 38.5 3 C46.5 2 53.5 9 52.5 18.5 C51 32 29 48.5 29 48.5 Z"/>
        </svg>""",
)

php = re.sub(
    r"  \?\> endif; \?\>\n  <div class=\"taller\">[\s\S]*?  \?\> endif; \?\>\n  <div class=\"playtest-cheats\"",
    "  <?php endif; ?>\n  <div class=\"playtest-cheats\"",
    php,
    count=1,
)
if "  <?php if (!$ahtLab): ?>" in php and '<div class="taller">' in php:
    php = re.sub(
        r"  <\?php if \(\!\$ahtLab\): \?\>\n  <div class=\"taller\">[\s\S]*?  <\?php endif; \?\>\n",
        "",
        php,
        count=1,
    )

php = re.sub(
    r"      <aside class=\"capa capa-residencias\">[\s\S]*?      </aside>\n",
    "",
    php,
    count=1,
)

php = re.sub(
    r"        <section class=\"shell-grupo shell-grupo-residencias\">[\s\S]*?        </section>\n",
    "",
    php,
    count=1,
)

if "shell-grupo-misiones" not in php:
    php = php.replace(
        """        <section class="shell-grupo shell-grupo-cotilleo">
          <button type="button" class="obj-cotilleo" data-open="diario" aria-label="Abrir diario">""",
        """        <section class="shell-grupo shell-grupo-misiones">
          <button type="button" class="obj-misiones obj-nota-rosa" data-open="misiones" aria-label="Misiones de hoy">
            <span class="obj-misiones-tit">Hoy en el pueblo</span>
            <p class="obj-misiones-teaser" data-misiones-teaser>—</p>
          </button>
        </section>
        <section class="shell-grupo shell-grupo-cotilleo">
          <button type="button" class="obj-cotilleo" data-open="diario" aria-label="Abrir diario">""",
    )

if "capa-misiones" not in php:
    php = php.replace(
        """      <aside class="capa capa-organizar">""",
        """      <aside class="capa capa-misiones">
        <button type="button" class="cerrar capa-cerrar-pestaña" data-close>cerrar</button>
        <p class="libreta-kicker">Libreta de Celestine</p>
        <h2>Hoy en el pueblo</h2>
        <p class="mini">Pequeños objetivos sociales. Opcionales.</p>
        <div data-misiones-list></div>
      </aside>
      <aside class="capa capa-organizar">""",
    )

(ROOT / "play.php").write_text(php, encoding="utf-8")
print("play.php patched")

# --- play-v3.js ---
js = (ROOT / "assets/js/play-v3.js").read_text(encoding="utf-8")

# ZONA_TO_LUGS V3 - only 9 canonical
js = re.sub(
    r"  var LUG_TO_ZONA = \{[\s\S]*?\};\n  var ZONA_TO_LUGS = \{[\s\S]*?\};",
    """  var LUG_TO_ZONA = {
    lug_cafeteria: 'cafeteria', lug_biblioteca: 'biblioteca', lug_gimnasio: 'gimnasio',
    lug_restaurante: 'restaurante', lug_parque: 'parque', lug_bar: 'bar',
    lug_cine: 'cine', lug_discoteca: 'discoteca', lug_bingo: 'bingo'
  };
  var ZONA_TO_LUGS = {
    cafeteria: ['lug_cafeteria'], biblioteca: ['lug_biblioteca'], gimnasio: ['lug_gimnasio'],
    restaurante: ['lug_restaurante'], parque: ['lug_parque'], bar: ['lug_bar'],
    cine: ['lug_cine'], discoteca: ['lug_discoteca'], bingo: ['lug_bingo']
  };""",
    js,
    count=1,
)

# Remove SLOTS block
js = re.sub(
    r"  const SLOTS = \{[\s\S]*?\};\n\n",
    "",
    js,
    count=1,
)

NEW_RENDER = """  function renderPueblo(pueblo) {
    cachePueblo = pueblo;
    var layer = $('[data-mapa-zonas]');
    if (!layer) return;
    $$('.mapa-zona-hit .habs').forEach(function (b) { b.innerHTML = ''; });
    var porZona = {};
    (pueblo.complejos || []).forEach(function (cx) {
      (cx.visibles || cx.personas || []).forEach(function (p) {
        var zid = LUG_TO_ZONA[p.destino_id];
        if (!zid) return;
        if (!porZona[zid]) porZona[zid] = [];
        if (porZona[zid].length >= 5) return;
        porZona[zid].push(p);
      });
    });
    Object.keys(porZona).forEach(function (zid) {
      var btn = layer.querySelector('[data-zona="' + zid + '"]');
      if (!btn) return;
      var box = btn.querySelector('.habs');
      if (!box) return;
      porZona[zid].forEach(function (p, i) { placeHabEnZona(box, p, i); });
    });
  }"""

js = re.sub(
    r"  function renderPueblo\(pueblo\) \{[\s\S]*?\n  \}\n\n  function applyFases",
    NEW_RENDER + "\n\n  function applyFases",
    js,
    count=1,
)

# Remove dinero from renderHud
js = re.sub(
    r"\n    \$\\('\\[data-dinero\\]'\\)\\.textContent = dineroTxt\\(cacheInsp, estado\\);\n",
    "\n",
    js,
)
js = js.replace(
    "    $('[data-dinero]').textContent = dineroTxt(cacheInsp, estado);\n",
    "",
)

# Fix corazon fill in renderHud
js = js.replace(
    """    const fill = $('.corazon-fill') || $('.corazon-dibujo');
    if (fill) fill.style.setProperty('--fill', pct + '%');""",
    """    const fillRect = $('[data-corazon-fill]');
    if (fillRect) {
      var h = 52 * (pct / 100);
      fillRect.setAttribute('y', String(52 - h));
      fillRect.setAttribute('height', String(h));
    }
    const fill = $('.corazon-fill') || $('.corazon-dibujo');
    if (fill) fill.style.setProperty('--fill', pct + '%');""",
)

# renderShellPanels - N de 24, remove renderResidencias
js = js.replace(
    """      stats.innerHTML =
        '<div class="stat-row"><span>Parejas</span><strong>' + parejas.length + '</strong></div>' +
        '<div class="stat-row"><span>Residentes</span><strong>' + nRes + '</strong></div>' +
        '<div class="stat-row"><span>Buzón pendiente</span><strong>' +""",
    """      stats.innerHTML =
        '<div class="stat-row"><span>Vecinos</span><strong>' + nRes + ' de 24</strong></div>' +
        '<div class="stat-row"><span>Parejas</span><strong>' + parejas.length + '</strong></div>' +
        '<div class="stat-row"><span>Buzón pendiente</span><strong>' +""",
)
js = js.replace("    renderResidencias();\n\n", "")

# Remove renderResidencias function and residencias handlers
js = re.sub(r"  function renderResidencias\(\) \{[\s\S]*?\n  \}\n\n", "", js, count=1)
js = js.replace("      renderResidencias();\n", "")

# Misiones UI
if "function renderMisiones" not in js:
    js = js.replace(
        "  function renderShellPanels(estado, buzon, diario) {",
        """  function renderMisiones(misiones) {
    var teaser = $('[data-misiones-teaser]');
    var list = $('[data-misiones-list]');
    var items = (misiones && misiones.items) ? misiones.items : (misiones || []);
    var dia = cacheEstado && cacheEstado.reloj ? cacheEstado.reloj.dia_pueblo : 0;
    var hoy = items.filter(function (m) { return (m.dia || 0) === dia; });
    if (teaser) {
      var pend = hoy.filter(function (m) { return (m.estado || '') === 'pendiente'; });
      teaser.textContent = pend.length
        ? (pend.length + ' objetivo' + (pend.length === 1 ? '' : 's') + ' pendiente' + (pend.length === 1 ? '' : 's'))
        : (hoy.length ? 'Nada pendiente hoy.' : 'Sin misiones hoy.');
    }
    if (!list) return;
    list.innerHTML = '';
    if (!hoy.length) {
      list.innerHTML = '<p class="muted">No hay misiones para hoy.</p>';
      return;
    }
    hoy.forEach(function (m) {
      var row = document.createElement('div');
      row.className = 'mision-row mision-' + (m.estado || 'pendiente');
      row.innerHTML = '<p>' + esc(m.texto || m.hecho || 'Objetivo') + '</p>' +
        '<span class="mision-estado">' + esc(m.estado || '') + '</span>';
      list.appendChild(row);
    });
  }

  function renderShellPanels(estado, buzon, diario) {""",
    )

# Hook misiones in refresh - find renderShellPanels call
js = js.replace(
    "renderShellPanels(cacheEstado, buzon.mensajes || [], diario);",
    "renderShellPanels(cacheEstado, buzon.mensajes || [], diario);\n      renderMisiones((insp && insp.misiones_diarias) || cacheEstado.misiones_diarias);",
)

# Map click - prefer zona
if "abrirConsultaZona(zona.getAttribute" not in js:
    js = js.replace(
        """    const cx = ev.target.closest('.complejo[data-complejo]');
    if (cx) {
      abrirConsulta(cx.getAttribute('data-complejo'));
    }""",
        """    const zona = ev.target.closest('.mapa-zona-hit[data-zona]');
    if (zona) {
      abrirConsultaZona(zona.getAttribute('data-zona'), zona);
      return;
    }""",
    )

(ROOT / "assets/js/play-v3.js").write_text(js, encoding="utf-8")
print("play-v3.js patched")

# --- juego_v1.json ---
cfg_path = ROOT / "data/configs/prevalidadas/juego_v1.json"
cfg = json.loads(cfg_path.read_text(encoding="utf-8"))
cfg["_comentario"] = "Arranque V3: 3 aleatorios + 5 incorporaciones tutorial aleatorias. 9 lugares desde inicio."
cfg["descripcion"] = "Nueva partida V3: trio aleatorio, tutorial, 8 vecinos dia 1."
cfg["poblacion_v3"] = {"iniciales_aleatorios": 3, "incorporaciones_aleatorias": 5}
cfg.pop("residentes_iniciales", None)
cfg.pop("tutorial_incorporaciones_dia_1", None)
cfg["lugares_operativos_dia_1"] = [
    "lug_cafeteria", "lug_biblioteca", "lug_gimnasio", "lug_restaurante",
    "lug_parque", "lug_bar", "lug_cine", "lug_discoteca", "lug_bingo",
]
cfg["bloques_abiertos"] = ["a", "b"]
cfg["features"]["misiones_diarias_enabled"] = True
cfg_path.write_text(json.dumps(cfg, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
print("juego_v1.json patched")
