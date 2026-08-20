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
    root.querySelectorAll(".dock button").forEach((b) => {
      b.classList.toggle("is-on", !!name && b.getAttribute("data-open") === name);
    });
  }
  function setCapaAll(name) {
    roots.forEach((r) => setCapa(r, name));
    document.querySelectorAll("[data-capa-btn]").forEach((b) => {
      b.classList.toggle("is-on", b.getAttribute("data-capa-btn") === (name || "ninguna"));
    });
  }

  document.body.addEventListener("click", (ev) => {
    const viewBtn = ev.target.closest("[data-view]");
    if (viewBtn) { setView(viewBtn.getAttribute("data-view")); return; }
    const capaBtn = ev.target.closest("[data-capa-btn]");
    if (capaBtn) {
      const v = capaBtn.getAttribute("data-capa-btn");
      setCapaAll(v === "ninguna" ? "" : v);
      return;
    }
    if (ev.target.closest("[data-grid]")) {
      const on = !roots[0].classList.contains("show-grid");
      roots.forEach((r) => r.classList.toggle("show-grid", on));
      ev.target.closest("[data-grid]").classList.toggle("is-on", on);
      return;
    }
    const root = ev.target.closest(".play-root");
    if (!root) return;
    const pin = ev.target.closest(".chincheta");
    if (pin && window.matchMedia("(hover: none)").matches) {
      if (!pin.classList.contains("is-nom")) {
        root.querySelectorAll(".chincheta.is-nom").forEach((c) => c.classList.remove("is-nom"));
        pin.classList.add("is-nom");
        return;
      }
    }
    if (ev.target.closest("[data-close], .velo")) { setCapa(root, ""); return; }
    const open = ev.target.closest("[data-open]");
    if (open) setCapa(root, open.getAttribute("data-open"));
  });

  setView("pc");
  setCapaAll("");
  const hash = (location.hash || "").replace("#", "");
  if (hash === "movil" || hash === "pc" || hash === "ambas") setView(hash);
  if (["ficha","buzon","hoy","organizar","evento","vecinos"].indexOf(hash) >= 0) setCapaAll(hash);
  if (hash === "movil-ficha") { setView("movil"); setCapaAll("ficha"); }
})();