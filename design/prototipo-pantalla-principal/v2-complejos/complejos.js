(function () {
  const FICHAS = {
    cafe: {
      titT: "Cafetería", titP: "Café & Libros",
      cotiT: "Huele a espresso. De momento, solo el café.",
      cotiP: "Rocío en barra. Alguien lee en el anexo. La tienda oye más de lo que vende.",
      destT: ["Cafetería"], destP: ["Cafetería", "Biblioteca", "Tienda"]
    },
    lola: {
      titT: "El Rincón de Lola", titP: "El Rincón de Lola",
      cotiT: "Manteles, rumor y un segundo plato que siempre se pide.",
      cotiP: "Se cena. Al fondo, el bingo. Lola no cambió de nombre.",
      destT: ["Restaurante"], destP: ["Restaurante", "Bingo"]
    },
    cine: {
      titT: "Cine", titP: "Cine Game",
      cotiT: "Marquesina, palomitas, oscuridad pactada.",
      cotiP: "A un lado se proyecta. Al otro pitan los recreativos.",
      destT: ["Cine"], destP: ["Cine", "Arcade"]
    },
    mala: {
      titT: "La Mala Idea", titP: "La Mala Idea",
      cotiT: "Un bar. El nombre ya era una advertencia.",
      cotiP: "Bar, disco y karaoke. El nombre no cambió. El volumen, sí.",
      destT: ["Bar"], destP: ["Bar", "Discoteca", "Karaoke"]
    },
    parque: {
      titT: "Parque", titP: "Parque",
      cotiT: "Bancos, fuente, y Luis esperando un milagro municipal.",
      cotiP: "Picnic a la sombra. Mirador para verse de lejos.",
      destT: ["Parque"], destP: ["Parque", "Picnic", "Mirador"]
    },
    gym: {
      titT: "Gimnasio", titP: "Gimnasio & Spa",
      cotiT: "Hierro, toalla, propósitos de enero en agosto.",
      cotiP: "Sudor a un lado, albornoz al otro.",
      destT: ["Gimnasio"], destP: ["Gimnasio", "Spa"]
    }
  };

  const MAX_VIS = 5;
  const IMG = {
    p138: "img/p138-neutral.png",
    p001: "img/p001-neutral.png",
    nora: "img/cabeza-nora.png",
    luis: "img/cabeza-luis.png",
    rocio: "img/cabeza-rocio.png"
  };

  function p(id, nom, dest, img, extra) {
    return Object.assign({ id: id, nom: nom, dest: dest, img: img || "", ini: nom.slice(0, 2).toUpperCase(), relevante: false }, extra || {});
  }

  const SCENES = {
    "3": {
      focus: "cafe",
      people: {
        cafe: [
          p("r1", "Rocío", "Cafetería", IMG.rocio, { relevante: true }),
          p("p138", "P138", "Cafetería", IMG.p138),
          p("lu", "Luis", "Cafetería", IMG.luis)
        ]
      }
    },
    "8": {
      focus: "cine",
      people: {
        cafe: [
          p("r1", "Rocío", "Cafetería", IMG.rocio, { relevante: true }),
          p("p138", "P138", "Cafetería", IMG.p138)
        ],
        cine: [
          p("no", "Nora", "Arcade", IMG.nora, { relevante: true }),
          p("c1", "Hugo", "Cine", IMG.p001),
          p("c2", "Aina", "Cine", ""),
          p("c3", "Eva", "Cine", ""),
          p("a1", "Dani", "Arcade", ""),
          p("a2", "Marta", "Arcade", ""),
          p("a3", "Paco", "Arcade", ""),
          p("a4", "Luz", "Arcade", "")
        ]
      }
    },
    "12": {
      focus: "mala",
      people: {
        cafe: [
          p("r1", "Rocío", "Cafetería", IMG.rocio),
          p("p138", "P138", "Cafetería", IMG.p138),
          p("b1", "Inés", "Biblioteca", "")
        ],
        cine: [
          p("no", "Nora", "Arcade", IMG.nora),
          p("c1", "Hugo", "Cine", IMG.p001)
        ],
        mala: [
          p("ev", "Eva", "Karaoke", "", { relevante: true }),
          p("b1m", "Toni", "Bar", IMG.luis),
          p("b2", "Lola", "Bar", ""),
          p("b3", "Gabi", "Bar", ""),
          p("b4", "Nuria", "Bar", ""),
          p("b5", "Raúl", "Bar", ""),
          p("d1", "Sofi", "Discoteca", IMG.rocio),
          p("d2", "Kim", "Discoteca", ""),
          p("d3", "Alex", "Discoteca", ""),
          p("d4", "Vero", "Discoteca", ""),
          p("d5", "Jon", "Discoteca", ""),
          p("k2", "Mila", "Karaoke", "")
        ],
        parque: [
          p("lu", "Luis", "Parque", IMG.luis),
          p("q1", "Nerea", "Picnic", ""),
          p("q2", "Omar", "Mirador", "")
        ]
      }
    }
  };

  const SLOTS = {
    cafe: { "Cafetería": [[22, 52], [34, 66], [16, 70]], "Biblioteca": [[60, 38], [70, 52]], "Tienda": [[86, 46], [80, 62]] },
    lola: { "Restaurante": [[30, 40], [50, 48]], "Bingo": [[40, 78], [58, 82]] },
    cine: { "Cine": [[22, 58], [34, 72], [18, 78]], "Arcade": [[76, 42], [68, 58], [82, 68]] },
    mala: { "Bar": [[22, 40], [34, 54], [18, 62]], "Discoteca": [[76, 26], [68, 40], [82, 48]], "Karaoke": [[30, 80], [46, 84]] },
    parque: { "Parque": [[48, 48], [36, 58]], "Picnic": [[22, 78], [34, 84]], "Mirador": [[78, 22], [70, 32]] },
    gym: { "Gimnasio": [[28, 48], [40, 62]], "Spa": [[76, 42], [70, 58]] }
  };

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

  function setView(mode) {
    document.body.classList.remove("ver-pc", "ver-movil", "ver-ambas");
    document.body.classList.add("ver-" + mode);
    document.querySelectorAll("[data-view]").forEach((b) => b.classList.toggle("is-on", b.getAttribute("data-view") === mode));
  }
  function setPuebloAll(p) {
    roots.forEach((r) => {
      r.setAttribute("data-pueblo", p);
      r.removeAttribute("data-consulta");
      r.removeAttribute("data-destino");
      r.removeAttribute("data-capa");
      r.removeAttribute("data-escala");
      r.removeAttribute("data-token");
    });
    document.querySelectorAll("button[data-pueblo]").forEach((b) => {
      b.classList.toggle("is-on", b.getAttribute("data-pueblo") === p);
    });
  }
  function destsOf(root, id) {
    const f = FICHAS[id];
    if (!f) return [];
    return root.getAttribute("data-pueblo") === "pleno" ? f.destP : f.destT;
  }
  function setCapaAll(name) {
    roots.forEach((r) => {
      if (!name) r.removeAttribute("data-capa");
      else r.setAttribute("data-capa", name);
      r.removeAttribute("data-consulta");
      r.querySelectorAll(".dock button").forEach((b) => {
        const open = b.getAttribute("data-open");
        b.classList.toggle("is-on", name ? open === name : !open);
      });
    });
  }
  function entrar(txt) {
    roots.forEach((r) => {
      r.setAttribute("data-destino", txt);
      r.removeAttribute("data-consulta");
      const n = r.querySelector("[data-destino-txt]");
      if (n) n.textContent = txt;
    });
  }
  function abrirSelector(id) {
    roots.forEach((r) => {
      const f = FICHAS[id];
      const pleno = r.getAttribute("data-pueblo") === "pleno";
      r.setAttribute("data-consulta", "sel");
      r.querySelector("[data-s-tit]").textContent = pleno ? f.titP : f.titT;
      r.querySelector("[data-s-coti]").textContent = pleno ? f.cotiP : f.cotiT;
      const box = r.querySelector("[data-s-btns]");
      box.innerHTML = "";
      destsOf(r, id).forEach((d) => {
        const b = document.createElement("button");
        b.type = "button";
        b.setAttribute("data-ir", d);
        b.textContent = "Ir a " + d.toLowerCase();
        box.appendChild(b);
      });
    });
  }

  function pickVisible(people) {
    const vis = [];
    const used = {};
    people.forEach((p) => {
      if (p.relevante && vis.length < MAX_VIS) { vis.push(p); used[p.id] = 1; }
    });
    const dests = [];
    people.forEach((p) => { if (dests.indexOf(p.dest) < 0) dests.push(p.dest); });
    dests.forEach((d) => {
      if (vis.length >= MAX_VIS) return;
      const cand = people.filter((p) => p.dest === d && !used[p.id])[0];
      if (cand) { vis.push(cand); used[cand.id] = 1; }
    });
    people.forEach((p) => {
      if (vis.length >= MAX_VIS) return;
      if (!used[p.id]) { vis.push(p); used[p.id] = 1; }
    });
    return vis;
  }

  function renderHabs(root) {
    const sceneId = root.getAttribute("data-aforo");
    const scene = SCENES[sceneId];
    root.querySelectorAll(".habs").forEach((box) => {
      box.innerHTML = "";
      if (!scene) return;
      const cid = box.getAttribute("data-habs");
      const people = scene.people[cid] || [];
      if (!people.length) return;
      const vis = pickVisible(people);
      const extra = people.length - vis.length;
      const destOrder = [];
      vis.forEach((p) => { if (destOrder.indexOf(p.dest) < 0) destOrder.push(p.dest); });
      const usedSlot = {};
      vis.forEach((p) => {
        const el = document.createElement("span");
        el.className = "hab" + (p.relevante ? " is-rel" : "");
        el.setAttribute("aria-hidden", "true");
        const di = Math.max(0, destOrder.indexOf(p.dest));
        const i = usedSlot[p.dest] || 0;
        usedSlot[p.dest] = i + 1;
        const bases = (SLOTS[cid] && SLOTS[cid][p.dest]) || [[22 + di * 28, 48 + di * 8]];
        const xy = bases[Math.min(i, bases.length - 1)];
        const left = Math.min(82, xy[0] + (bases.length <= 1 ? i * 14 : 0));
        const top = Math.min(82, xy[1] + (bases.length <= 1 ? i * 6 : 0));
        el.style.left = left + "%";
        el.style.top = top + "%";
        if (p.img) {
          el.innerHTML = '<span class="cara"><img src="' + p.img + '" alt=""/></span>';
        } else {
          el.innerHTML = '<span class="cara cara-ini">' + p.ini + "</span>";
        }
        box.appendChild(el);
      });
      if (extra > 0) {
        const mas = document.createElement("span");
        mas.className = "aforo-mas";
        mas.textContent = "+" + extra;
        box.appendChild(mas);
      }
    });
  }

  function abrirQuien(id) {
    roots.forEach((r) => {
      const scene = SCENES[r.getAttribute("data-aforo")];
      const f = FICHAS[id];
      const pleno = r.getAttribute("data-pueblo") === "pleno";
      const people = (scene && scene.people[id]) || [];
      r.setAttribute("data-consulta", "quien");
      r.querySelector("[data-q-tit]").textContent = pleno ? f.titP : f.titT;
      const tot = people.length;
      r.querySelector("[data-q-sum]").textContent = tot
        ? tot + " dentro · en el mapa se ven " + Math.min(tot, MAX_VIS) + (tot > MAX_VIS ? " y un +" + (tot - MAX_VIS) : "")
        : "Nadie ahora mismo.";
      const list = r.querySelector("[data-q-list]");
      list.innerHTML = "";
      const groups = {};
      people.forEach((p) => {
        if (!groups[p.dest]) groups[p.dest] = [];
        groups[p.dest].push(p);
      });
      Object.keys(groups).forEach((dest) => {
        const h = document.createElement("p");
        h.className = "quien-dest";
        h.textContent = dest + " · " + groups[dest].length;
        list.appendChild(h);
        const ul = document.createElement("ul");
        groups[dest].forEach((p) => {
          const li = document.createElement("li");
          li.textContent = p.nom + (p.relevante ? " · cotilleo" : "");
          ul.appendChild(li);
        });
        list.appendChild(ul);
      });
      const box = r.querySelector("[data-q-btns]");
      box.innerHTML = "";
      destsOf(r, id).forEach((d) => {
        const b = document.createElement("button");
        b.type = "button";
        b.setAttribute("data-ir", d);
        b.textContent = "Ir a " + d.toLowerCase();
        box.appendChild(b);
      });
    });
  }

  function setAforo(n) {
    setPuebloAll("pleno");
    roots.forEach((r) => {
      r.setAttribute("data-aforo", n);
      r.removeAttribute("data-escala");
      r.removeAttribute("data-token");
      renderHabs(r);
    });
    document.querySelectorAll("[data-aforo]").forEach((b) => {
      b.classList.toggle("is-on", b.getAttribute("data-aforo") === n);
    });
  }

  document.body.addEventListener("click", (ev) => {
    const viewBtn = ev.target.closest("[data-view]");
    if (viewBtn) { setView(viewBtn.getAttribute("data-view")); return; }
    const puebloBtn = ev.target.closest("button[data-pueblo]");
    if (puebloBtn && !puebloBtn.closest(".play-root")) {
      setPuebloAll(puebloBtn.getAttribute("data-pueblo"));
      roots.forEach((r) => { r.removeAttribute("data-aforo"); renderHabs(r); });
      document.querySelectorAll("[data-aforo]").forEach((b) => b.classList.remove("is-on"));
      return;
    }
    const aforoBtn = ev.target.closest("button[data-aforo]");
    if (aforoBtn && !aforoBtn.closest(".play-root")) {
      document.querySelectorAll("[data-demo]").forEach((b) => b.classList.remove("is-on"));
      setAforo(aforoBtn.getAttribute("data-aforo"));
      return;
    }
    const demo = ev.target.closest("[data-demo]");
    if (demo) {
      const d = demo.getAttribute("data-demo");
      document.querySelectorAll("[data-demo]").forEach((b) => b.classList.toggle("is-on", b === demo));
      document.querySelectorAll("[data-aforo]").forEach((b) => b.classList.remove("is-on"));
      roots.forEach((r) => { r.removeAttribute("data-aforo"); renderHabs(r); });
      if (d === "sel") { setPuebloAll("pleno"); abrirSelector("cine"); }
      if (d === "entrar") { setPuebloAll("temprano"); entrar("Cafetería"); }
      return;
    }
    const root = ev.target.closest(".play-root");
    if (!root) return;
    if (ev.target.closest("[data-close], .velo")) {
      roots.forEach((r) => {
        r.removeAttribute("data-consulta");
        r.removeAttribute("data-destino");
        r.removeAttribute("data-capa");
      });
      return;
    }
    const open = ev.target.closest("[data-open]");
    if (open) { setCapaAll(open.getAttribute("data-open")); return; }
    const ir = ev.target.closest("[data-ir]");
    if (ir) { entrar(ir.getAttribute("data-ir")); return; }
    const cx = ev.target.closest(".complejo[data-complejo]");
    if (cx) {
      const id = cx.getAttribute("data-complejo");
      if (root.getAttribute("data-aforo")) {
        abrirQuien(id);
        return;
      }
      const ds = destsOf(root, id);
      if (ds.length <= 1) entrar(ds[0] || FICHAS[id].titT);
      else abrirSelector(id);
    }
  });

  setView("pc");
  setPuebloAll("temprano");
  const hash = (location.hash || "").replace("#", "");
  if (hash.indexOf("cap") >= 0) document.body.classList.add("captura");
  if (hash.indexOf("movil") >= 0) setView("movil");
  if (hash.indexOf("pleno") >= 0) setPuebloAll("pleno");
  if (hash.indexOf("sel") >= 0) { setPuebloAll("pleno"); abrirSelector("cine"); }
  if (hash.indexOf("entrar") >= 0) { setPuebloAll("temprano"); entrar("Cafetería"); }
  if (hash.indexOf("aforo3") >= 0) setAforo("3");
  if (hash.indexOf("aforo8") >= 0) setAforo("8");
  if (hash.indexOf("aforo12") >= 0) {
    setAforo("12");
    if (hash.indexOf("quien") >= 0) abrirQuien("mala");
  }
  if (hash.indexOf("cotilleo") >= 0 || hash.indexOf("diario") >= 0) setCapaAll("cotilleo");
})();
