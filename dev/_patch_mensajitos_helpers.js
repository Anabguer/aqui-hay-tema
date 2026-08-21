  function esIdInterno(s) {
    if (typeof s !== 'string' || !s) return false;
    return /^per_[a-z0-9_]+$/i.test(s) || /^lug_[a-z0-9_]+$/i.test(s) || /^msg_/.test(s);
  }

  function nombrePublicoDe(m) {
    if (!m || typeof m !== 'object') return '';
    if (m.tipo === 'candidato_llegada' && m.texto) {
      const mt = String(m.texto).match(/^([^\n.:]{2,32}?)\s+quiere\s+/i);
      if (mt) return mt[1].trim();
    }
    const id = remitenteIdDe(m);
    if (id) {
      const n = nombreDe(id);
      if (n && !esIdInterno(n)) return n;
    }
    const t = String(m.texto || '');
    const ci = t.indexOf(':');
    if (ci > 0 && ci < 28) {
      const pref = t.slice(0, ci).trim();
      if (pref && !esIdInterno(pref)) return pref;
    }
    return 'Alguien';
  }

  function cuerpoMensajito(m, nombre) {
    let t = String(m.texto || '').trim();
    if (m.tipo === 'respuesta_plan') {
      return copyRechazoPlanCelestine(t);
    }
    if (nombre && t.indexOf(nombre + ':') === 0) {
      t = t.slice(nombre.length + 1).trim();
    } else if (nombre && t.indexOf(nombre + ' ') === 0) {
      const rest = t.slice(nombre.length).trim();
      if (/^quiere\s+/i.test(rest)) t = rest;
    }
    if (m.plazo_humano) {
      const ph = String(m.plazo_humano).trim();
      if (ph && t.endsWith(ph)) t = t.slice(0, t.length - ph.length).trim();
      t = t.replace(/\s*Te quedan\s+\d+\s*h\s*$/i, '').trim();
    }
    return t;
  }

  function copyRechazoPlanCelestine(texto) {
    const m = String(texto || '').match(/^(.+?)\s+no han quedado\.?$/i);
    if (!m) return texto;
    const nombres = m[1].trim();
    const frases = [
      nombres + ' ten\u00edan plan. Ten\u00edan.',
      'Pues al final ' + nombres + ' se han quedado con las ganas.',
      nombres + ': mucho plan y poca cita.',
      nombres + ' no han quedado. Aqu\u00ed ha pasado algo\u2026 o absolutamente nada.'
    ];
    let h = 0;
    for (let i = 0; i < nombres.length; i++) h = (h + nombres.charCodeAt(i)) | 0;
    return frases[Math.abs(h) % frases.length];
  }

  function mensajitoRequiereAccion(m) {
    if (!m || typeof m !== 'object') return false;
    if (m.tipo === 'candidato_llegada' && (m.estado || '') === 'pendiente') return true;
    if ((m.clasificacion || '') === 'peticion' && m.peticion_id && (m.estado || '') === 'pendiente') return true;
    return false;
  }

  async function marcarMensajitoLeido(m) {
    if (!m || !m.id || (m.estado || '') !== 'pendiente') return null;
    const lr = await api('buzon.leer', { mensaje_id: m.id });
    return lr.tutorial || null;
  }

