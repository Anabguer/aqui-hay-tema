'use strict';
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const OUT = process.argv[2] || path.join(__dirname, 'visual-replica-evidence');
const ITER = process.argv[3] || '1';
const BASE = 'http://127.0.0.1:8765/dev/visual_validate_content.php?partida_id=e2erit-part_5af4821&capa=inicio&view=mobile';
const OUTFILE = path.join(OUT, `inicio-mobile-piloto-iter${ITER}.png`);

const MOCKS = () => {
  const guia = document.querySelector('.btn-guia');
  if (guia) guia.removeAttribute('hidden');
  document.querySelectorAll('.dev-bar,.aht-debug-float,.taller-debug,.capa-derrota,.velo-derrota,[data-derrota],[data-vida-derrota]').forEach(el => {
    if (el.classList && (el.classList.contains('dev-bar') || el.matches('.capa-derrota,.velo-derrota,[data-derrota],[data-vida-derrota]'))) el.style.display = 'none';
  });
  const gr = document.querySelector('.game-right');
  if (!gr) return;
  let ev = gr.querySelector('.ds-event');
  if (!ev) {
    ev = document.createElement('div');
    ev.className = 'ds-event ds-event--proximo';
    ev.innerHTML = '<span class="ds-event-ico" aria-hidden="true">\u{1FAA9}</span><span class="ds-event-body"><span class="ds-event-title">Proximo evento</span><span class="ds-event-meta">Se aproxima fiesta en la disco</span></span>';
    gr.insertBefore(ev, gr.firstChild);
  }
  const cara = document.querySelector('.obj-vecinos-preview-cara');
  const url = cara && cara.tagName === 'IMG' ? cara.getAttribute('src') : null;
  const img = url ? '<img class="pp-face-img" src="' + url + '" alt=""/>' : '<span class="cara-ini">·</span>';
  const img2 = url ? '<img src="' + url + '" alt=""/>' : '';
  const enc = document.querySelector('[data-encursos-block]');
  if (enc) {
    enc.classList.add('is-on'); enc.hidden = false; enc.style.display = '';
    const track = enc.querySelector('[data-encursos-track]');
    if (track) track.innerHTML = '<article class="enc-mov-card"><div class="prox-faces">' + img + img2 + '</div><p class="enc-mov-nombres">Paula · Sergio</p><p class="enc-mov-lugar">Bingo</p><p class="enc-mov-hora"><span class="enc-mov-punto" aria-hidden="true"></span>AHORA · 21:00</p><button type="button" class="enc-mov-cta"><span class="enc-mov-cta-txt">Ver encuentro</span><span class="enc-mov-cta-flecha" aria-hidden="true">›</span></button></article><article class="enc-mov-card"><div class="prox-faces">' + img + '</div><p class="enc-mov-nombres">David</p><p class="enc-mov-lugar">Cine</p><p class="enc-mov-hora"><span class="enc-mov-punto" aria-hidden="true"></span>AHORA · 21:00</p><button type="button" class="enc-mov-cta"><span class="enc-mov-cta-txt">Ver encuentro</span><span class="enc-mov-cta-flecha" aria-hidden="true">›</span></button></article>';
  }
  const pp = document.querySelector('[data-proxplanes-block]');
  if (pp) {
    pp.classList.add('is-on'); pp.hidden = false; pp.style.display = '';
    const track = pp.querySelector('[data-proxplanes-track]');
    if (track) track.innerHTML = '<article class="pp-mov-card"><p class="pp-mov-hora">HOY · 20:00</p><div class="prox-faces">' + img + img2 + '</div><p class="pp-mov-nombres">Paula · Sergio</p><p class="pp-mov-lugar">Bingo</p></article><article class="pp-mov-card"><p class="pp-mov-hora">HOY · 20:00</p><div class="prox-faces">' + img + '</div><p class="pp-mov-nombres">David</p><p class="pp-mov-lugar">Cine</p></article>';
  }
  const strip = document.querySelector('[data-parejas-strip]');
  if (strip) {
    const caraImg = url ? '<img class="obj-pareja-cara" src="' + url + '" alt=""/>' : '<span class="obj-pareja-cara"></span>';
    const piece = document.createElement('div');
    piece.className = 'obj-pareja-piece';
    piece.innerHTML = '<span class="obj-pareja-fotos">' + caraImg + '<span class="obj-pareja-enlace" aria-hidden="true"></span>' + caraImg + '</span><span class="obj-pareja-nombres">Paula · Sergio</span>';
    strip.innerHTML = ''; strip.appendChild(piece);
  }
};

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 393, height: 852 }, deviceScaleFactor: 2 });
  await page.goto(BASE, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForSelector('.game-shell', { timeout: 60000 });
  await page.waitForTimeout(4500);
  await page.addStyleTag({ content: '.dev-bar,.aht-debug-float,.taller-debug{display:none!important}' });
  await page.evaluate(MOCKS);
  await page.waitForTimeout(800);
  const alto = await page.evaluate(() => Math.min(8000, Math.max(852, document.documentElement.scrollHeight)));
  await page.setViewportSize({ width: 393, height: alto });
  await page.waitForTimeout(400);
  await page.evaluate(() => window.scrollTo(0, 0));
  const shell = page.locator('.game-shell').first();
  await shell.screenshot({ path: OUTFILE });
  console.log('OK', OUTFILE);
  await browser.close();
})();
