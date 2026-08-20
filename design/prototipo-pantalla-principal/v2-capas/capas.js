(function () {
  const tpl = document.getElementById("tpl-play");
  function mount(id, frame) {
    const node = tpl.content.firstElementChild.cloneNode(true);
    node.classList.add(frame);
    document.getElementById(id).appendChild(node);
    return node;
  }
  const pc = mount("stage-pc", "pc");
  const phone = mount("stage-phone", "phone");
  const roots = [pc, phone];
  const sc = phone.querySelector(".board-scroll");
  if (sc) sc.scrollLeft = 40;

  function setView(mode) {
    document.body.classList.remove("ver-pc", "ver-movil", "ver-ambas");
    document.body.classList.add("ver-" + mode);
    document.querySelectorAll("[data-view]").forEach((b) => b.classList.toggle("is-on", b.getAttribute("data-view") === mode));
  }
  function setCapa(root, name) {
    if (!name) root.removeAttribute("data-capa");
    else root.setAttribute("data-capa", name);
    if (name === "organizar") {
      root.setAttribute("data-paso", root.getAttribute("data-paso") || "tipo");
      root.setAttribute("data-cupo", root.getAttribute("data-cupo") || "2");
    }
    if (name === "diario") root.setAttribute("data-diario", root.getAttribute("data-diario") || "hoy");
    root.querySelectorAll(".dock button").forEach((b) => {
      b.classList.toggle("is-on", !!name && b.getAttribute("data-open") === name);
    });
    syncDiarioTabs(root);
  }
  function setCapaAll(name) {
    roots.forEach((r) => setCapa(r, name));
    document.querySelectorAll("[data-capa-btn]").forEach((b) => {
      b.classList.toggle("is-on", b.getAttribute("data-capa-btn") === (name || "ninguna"));
    });
  }
  function setPasoAll(paso) {
    roots.forEach((r) => r.setAttribute("data-paso", paso));
  }
  function setCupoAll(n) {
    roots.forEach((r) => {
      r.setAttribute("data-cupo", String(n));
      enforceCupo(r);
    });
  }
  function setDiarioAll(dia) {
    roots.forEach((r) => {
      r.setAttribute("data-diario", dia);
      syncDiarioTabs(r);
    });
  }
  function syncDiarioTabs(root) {
    const dia = root.getAttribute("data-diario") || "hoy";
    root.querySelectorAll("[data-diario-tab]").forEach((b) => {
      b.classList.toggle("is-on", b.getAttribute("data-diario-tab") === dia);
    });
  }
  function enforceCupo(root) {
    const cupo = parseInt(root.getAttribute("data-cupo") || "2", 10);
    const on = Array.prototype.slice.call(root.querySelectorAll("[data-chip].is-on"));
    on.slice(cupo).forEach((c) => c.classList.remove("is-on"));
  }
  function marcarQuien(root, quien) {
    if (!quien) return;
    root.querySelectorAll("[data-chip]").forEach((c) => {
      c.classList.toggle("is-on", c.getAttribute("data-chip") === quien);
    });
  }
  function aplicarTipo(root, tipoBtn) {
    const cupo = tipoBtn.getAttribute("data-cupo") || "2";
    root.setAttribute("data-cupo", cupo);
    root.querySelectorAll("[data-tipo]").forEach((c) => c.classList.toggle("is-on", c === tipoBtn));
    enforceCupo(root);
  }

  document.body.addEventListener("click", (ev) => {
    const viewBtn = ev.target.closest("[data-view]");
    if (viewBtn) { setView(viewBtn.getAttribute("data-view")); return; }
    const capaBtn = ev.target.closest("[data-capa-btn]");
    if (capaBtn) {
      const v = capaBtn.getAttribute("data-capa-btn");
      setCapaAll(v === "ninguna" ? "" : v);
      if (v === "organizar") { setPasoAll("tipo"); setCupoAll(2); }
      if (v === "diario") setDiarioAll("hoy");
      return;
    }
    const diarioBtn = ev.target.closest("[data-diario-btn]");
    if (diarioBtn) {
      setCapaAll("diario");
      setDiarioAll(diarioBtn.getAttribute("data-diario-btn"));
      return;
    }
    const chip = ev.target.closest(".chip");
    if (chip && !chip.disabled && chip.closest(".play-root")) {
      const root = chip.closest(".play-root");
      if (chip.hasAttribute("data-tipo")) {
        aplicarTipo(root, chip);
        const other = roots.filter((r) => r !== root)[0];
        if (other) {
          const twin = other.querySelector('[data-tipo="' + chip.getAttribute("data-tipo") + '"]');
          if (twin) aplicarTipo(other, twin);
        }
        return;
      }
      if (chip.hasAttribute("data-chip")) {
        const cupo = parseInt(root.getAttribute("data-cupo") || "2", 10);
        if (cupo === 1) {
          root.querySelectorAll("[data-chip]").forEach((c) => c.classList.toggle("is-on", c === chip));
        } else if (chip.classList.contains("is-on")) {
          chip.classList.remove("is-on");
        } else if (root.querySelectorAll("[data-chip].is-on").length < cupo) {
          chip.classList.add("is-on");
        }
        return;
      }
      const group = chip.parentElement;
      if (group && group.classList.contains("chips")) {
        group.querySelectorAll(".chip").forEach((c) => c.classList.remove("is-on"));
        chip.classList.add("is-on");
      }
      return;
    }
    const tab = ev.target.closest("[data-diario-tab]");
    if (tab && tab.closest(".play-root")) {
      setDiarioAll(tab.getAttribute("data-diario-tab"));
      return;
    }
    const pasoBtn = ev.target.closest("[data-paso]");
    if (pasoBtn && pasoBtn.closest(".play-root")) {
      setPasoAll(pasoBtn.getAttribute("data-paso"));
      return;
    }
    const root = ev.target.closest(".play-root");
    if (!root) return;
    if (ev.target.closest("[data-close], .velo")) { setCapa(root, ""); return; }
    const open = ev.target.closest("[data-open]");
    if (open) {
      const name = open.getAttribute("data-open");
      setCapa(root, name);
      if (name === "organizar") {
        const quien = open.getAttribute("data-quien");
        const tipo = open.getAttribute("data-tipo");
        const cupo = open.getAttribute("data-cupo");
        if (tipo) {
          const tb = root.querySelector('[data-tipo="' + tipo + '"]');
          if (tb) aplicarTipo(root, tb);
          root.setAttribute("data-paso", "quien");
        } else {
          root.setAttribute("data-paso", "tipo");
          if (cupo) root.setAttribute("data-cupo", cupo);
        }
        if (quien) marcarQuien(root, quien);
        if (open.getAttribute("data-desde") === "marcha") {
          root.setAttribute("data-desde", "marcha");
        } else {
          root.removeAttribute("data-desde");
        }
      }
    }
  });

  setView("pc");
  setCapaAll("ficha-nueva");
  const hash = (location.hash || "").replace("#", "");
  if (hash.indexOf("movil") === 0) setView("movil");
  else if (hash === "pc") setView("pc");

  if (hash.indexOf("diario-ayer") >= 0) { setCapaAll("diario"); setDiarioAll("ayer"); }
  else if (hash.indexOf("diario-viejos") >= 0) { setCapaAll("diario"); setDiarioAll("viejos"); }
  else if (hash.indexOf("organizar-") >= 0) {
    setCapaAll("organizar");
    const rest = hash.split("organizar-")[1] || "tipo";
    const paso = rest.split("-")[0] || "tipo";
    setPasoAll(paso);
    if (paso === "quien" || hash.indexOf("conocerse") >= 0) {
      /* default quedar = 2; hash organizar-quien-1 for cupo 1 */
    }
    if (hash.indexOf("cupo1") >= 0 || hash.indexOf("conocerse") >= 0) {
      setCupoAll(1);
      roots.forEach((r) => {
        const tb = r.querySelector('[data-tipo="conocerse"]');
        if (tb) aplicarTipo(r, tb);
        marcarQuien(r, "luis");
      });
    }
  } else {
    const capa = hash.replace("movil-", "");
    if (["ficha-nueva","ficha-conocida","buzon","diario","organizar"].indexOf(capa) >= 0) setCapaAll(capa);
    if (capa === "organizar") setPasoAll("tipo");
    if (capa === "diario") setDiarioAll("hoy");
  }
})();
