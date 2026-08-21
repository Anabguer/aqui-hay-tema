const fs = require('fs');
const jsPath = 'W:/juegos/aqui-hay-tema/assets/js/play-v3.js';
let js = fs.readFileSync(jsPath, 'utf8');

if (!js.includes('function metricasSociales')) {
  const HELPERS = `
  const ORGANIZAR_EXCLUIDOS = ['lug_tienda_ropa'];
  const DESTINO_NOMBRE = {
    lug_cafeteria: 'Cafetería', lug_biblioteca: 'Biblioteca', lug_tienda_ropa: 'Tienda',
    lug_restaurante: 'Restaurante', lug_bingo: 'Bingo', lug_cine: 'Cine', lug_arcade: 'Recreativo',
    lug_bar: 'Bar', lug_discoteca: 'Discoteca', lug_karaoke: 'Karaoke', lug_picnic: 'Picnic',
    lug_mirador: 'Mirador', lug_gimnasio: 'Gimnasio', lug_spa: 'Spa', lug_parque: 'Parque'
  };
  /** DEMO UI lab — no persistir, no mezclar con partida real. */
  const LAB_DEMO_PAREJAS = [
    { demo: true, ids: ['per_p002', 'per_p003'] },
    { demo: true, ids: ['per_p004', 'per_p001'] }
  ];
  const LAB_DEMO_PROXIMO = {
    demo: true,
    participantes: ['per_p002', 'per_p003'],
    lugar_nombre: 'Cafetería',
    hora_inicio: 17
  };

  function idsPareja(rel) {
    if (!rel) return [];
    if (Array.isArray(rel.pareja) && rel.pareja.length >= 2) return rel.pareja;
    if (Array.isArray(rel.participantes) && rel.participantes.length >= 2) return rel.participantes;
    if (rel.residente_a && rel.residente_b) return [rel.residente_a, rel.residente_b];
    return [];
  }

  function metricasSociales(partida) {
    const res = partida.residentes || {};
    const ids = Object.keys(res).filter(function (k) { return (res[k].presencia || '') === 'residente'; });
    const emo = { alegre: 0, triste: 0, enfadado: 0 };
    ids.forEach(function (id) {
      const rt = res[id].runtime && res[id].runtime.estado_emocional;
      const eid = rt && rt.id ? String(rt.id) : 'neutro';
      if (eid === 'alegre') emo.alegre++;
      else if (eid === 'triste') emo.triste++;
      else if (eid === 'enfadado') emo.enfadado++;
    });
    let parejas = 0;
    let crisis = 0;
    (partida.relaciones_romanticas || []).forEach(function (r) {
      const est = String(r.estado_pareja || r.estado || '');
      if (est === 'pareja') parejas++;
      if (est === 'crisis') crisis++;
    });
    return { vecinos: ids.length, parejas: parejas, crisis: crisis, emo: emo };
  }

  function posicionarPopover(anchor, panel) {
    const board = $('.board-fit');
    if (!anchor || !panel || !board) return;
    panel.style.right = 'auto';
    const br = board.getBoundingClientRect();
    const ar = anchor.getBoundingClientRect();
    const pw = panel.offsetWidth || 220;
    const ph = panel.offsetHeight || 160;
    let left = ar.left - br.left + (ar.width / 2) - (pw / 2);
    let top = ar.top - br.top - ph - 10;
    if (top < 6) top = ar.bottom - br.top + 10;
    left = Math.max(6, Math.min(left, br.width - pw - 6));
    top = Math.max(6, Math.min(top, br.height - ph - 6));
    panel.style.left = left + 'px';
    panel.style.top = top + 'px';
  }

  function findDestinoMeta(destId) {
    let found = null;
    (cachePueblo && cachePueblo.complejos || []).forEach(function (cx) {
      (cx.destinos || []).forEach(function (d) {
        if (d.id === destId) found = { cx: cx, dest: d };
      });
      if (!found) {
        (cx.destinos_operativos || []).forEach(function (d) {
          if (d.id === destId) found = { cx: cx, dest: d };
        });
      }
    });
    return found;
  }
`;
  js = js.replace(
    "    picnic: 'lug_picnic', mirador: 'lug_mirador',\r\n  };",
    "    picnic: 'lug_picnic', mirador: 'lug_mirador',\r\n  };" + HELPERS
  );
  if (!js.includes('function metricasSociales')) {
    js = js.replace(
      "    picnic: 'lug_picnic', mirador: 'lug_mirador',\n  };",
      "    picnic: 'lug_picnic', mirador: 'lug_mirador',\n  };" + HELPERS
    );
  }
  fs.writeFileSync(jsPath, js);
  console.log('helpers inserted', js.includes('function metricasSociales'));
} else {
  console.log('helpers already present');
}
