#!/usr/bin/env node
'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');

const artPath = path.join(root, 'assets/css/play-v3-shell-art.css');
const basePath = path.join(root, 'assets/css/inicio/inicio-base.css');
let art = fs.readFileSync(artPath, 'utf8');
let base = fs.readFileSync(basePath, 'utf8');

// Revert wrong scope if present
art = art.replace(/\.inicio-stage :is\(\.shell-grupo-planes, \.encursos-movil\)/g, ':is(.shell-grupo-planes, .encursos-movil)');

const start = art.indexOf(':is(.shell-grupo-planes, .encursos-movil) .enc-int {');
const endMarker = ':is(.shell-grupo-planes, .encursos-movil) .enc-int-volver:hover';
if (start < 0) {
  console.log('enc-int block not found, skip');
  process.exit(0);
}
const end = art.indexOf('\n\n', art.indexOf(endMarker, start));
const block = art.slice(start, end > start ? end : art.length);
const migrated = block
  .split('\n')
  .map((line) => {
    if (!line.trim()) return line;
    return line.replace(
      /:is\(\.shell-grupo-planes, \.encursos-movil\)/g,
      '.play-v3 .inicio-stage .encursos-movil'
    ).replace(
      /^\.encursos-movil /,
      '.play-v3 .inicio-stage .encursos-movil '
    );
  })
  .join('\n');

if (!base.includes('INICIO-ENC-INT-AUTHORITY')) {
  base = base.trimEnd() + '\n\n/* INICIO-ENC-INT-AUTHORITY — UI encuentro en laterales Inicio */\n' + migrated + '\n';
  fs.writeFileSync(basePath, base);
}

// Remove encursos-movil from shell-art (solo shell-grupo-planes legacy fuera inicio)
art = art.replace(/:is\(\.shell-grupo-planes, \.encursos-movil\)/g, '.shell-grupo-planes');
art = art.replace(/\n\.encursos-movil \.enc-int-temas-panel \{[\s\S]*?\}\s*/g, '\n');
fs.writeFileSync(artPath, art);

const respPath = path.join(root, 'assets/css/play-v3-responsive.css');
let resp = fs.readFileSync(respPath, 'utf8');
resp = resp.replace(/\.play-v3 \.inicio-stage :is\(\.shell-grupo-planes, \.encursos-movil\)/g, '.play-v3 .inicio-stage .encursos-movil');
resp = resp.replace(/\.play-v3 :is\(\.shell-grupo-planes, \.encursos-movil\)/g, '.play-v3 .inicio-stage .encursos-movil');
if (!base.includes('INICIO-ENC-INT-RESULT')) {
  const rStart = resp.indexOf('.play-v3 .inicio-stage .encursos-movil .enc-int-result');
  if (rStart >= 0) {
    const rEnd = resp.indexOf('.play-v3 .inicio-stage .encursos-movil:not(.is-on)');
    const rBlock = resp.slice(rStart, rEnd > rStart ? rEnd : resp.length);
    base = fs.readFileSync(basePath, 'utf8');
    fs.writeFileSync(basePath, base.trimEnd() + '\n\n/* INICIO-ENC-INT-RESULT */\n' + rBlock + '\n');
    resp = resp.replace(rBlock, '');
  }
}
resp = resp.replace(
  /\.play-v3 \.inicio-stage \.encursos-movil:not\(\.is-on\) \{ display: none; \}/,
  '/* inicio-base authority */\n'
);
fs.writeFileSync(respPath, resp);

// Remove orphan map block from mobile
const mobPath = path.join(root, 'assets/css/inicio/inicio-mobile.css');
let mob = fs.readFileSync(mobPath, 'utf8');
mob = mob.replace(/\.inicio-stage > \.inicio-map-host\s*\{[^}]*\}\s*/g, '');
mob = mob.replace(/\.inicio-stage > \.inicio-map-host \.play-stage\s*\{[^}]*\}\s*/g, '');
mob = mob.replace(/\.inicio-stage > \.inicio-map-host \.mapa-canonico,[\s\S]*?\}\s*/g, '');
mob = mob.replace(/\.play-v3 \.inicio-stage > \.inicio-map-host \.board-fit\s*\{[\s\S]*?\}\s*/g, '');
fs.writeFileSync(mobPath, mob);

console.log('migrate_encint_to_inicio_base OK');
