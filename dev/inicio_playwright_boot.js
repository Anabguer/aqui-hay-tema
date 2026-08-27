'use strict';
const { execSync } = require('child_process');
const path = require('path');

const root = path.join(__dirname, '..');
const PARTIDA_FIXTURE = 'e2erit-part_5af4821';

let cachedRefreshPayload = null;

function loadFixtureRefresh(partidaId) {
  if (cachedRefreshPayload && cachedRefreshPayload._partidaId === partidaId) {
    return cachedRefreshPayload.payload;
  }
  const raw = execSync('php dev/inicio_fixture_refresh.php ' + partidaId, {
    cwd: root,
    encoding: 'utf8',
    maxBuffer: 8e6,
  });
  const payload = JSON.parse(raw);
  cachedRefreshPayload = { _partidaId: partidaId, payload: payload };
  return payload;
}

async function routeFixturePartida(page, partidaId) {
  const refreshPayload = loadFixtureRefresh(partidaId);

  await page.route('**/api/index.php?action=partida.listar**', function (route) {
    route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        ok: true,
        partidas: [{ partida_id: partidaId, nombre: 'Fixture E2E' }],
      }),
    });
  });

  await page.route('**/api/index.php?action=partida.refresh**', function (route) {
    route.fulfill({
      contentType: 'application/json; charset=utf-8',
      body: JSON.stringify(refreshPayload),
    });
  });

  await page.route('**/api/index.php?action=partida.nueva**', function (route) {
    route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({ ok: true, partida_id: partidaId }),
    });
  });
}

async function prepInicioPage(page, partidaId) {
  await routeFixturePartida(page, partidaId || PARTIDA_FIXTURE);
  await page.addInitScript(function (pid) {
    try {
      localStorage.setItem('aht_partida_id_juego', pid);
      localStorage.setItem('aht_partida_id', pid);
    } catch (_) {}
  }, partidaId || PARTIDA_FIXTURE);
  await page.goto('http://127.0.0.1:8765/play.php', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.reload({ waitUntil: 'networkidle', timeout: 120000 }).catch(function () {
    return page.reload({ waitUntil: 'domcontentloaded', timeout: 120000 });
  });
  await page.waitForSelector('.inicio-stage', { timeout: 90000 });
  try {
    await page.waitForSelector('[data-tut-intro]:not([hidden])', { timeout: 4000 });
    await page.click('[data-tut-skip]', { timeout: 4000 });
    await page.waitForTimeout(500);
  } catch (_) {}
  try {
    if (await page.locator('[data-vida-derrota]:not([hidden])').count()) {
      await page.click('[data-vida-derrota-ok]', { timeout: 3000 });
      await page.waitForTimeout(400);
    }
  } catch (_) {}
  await page.waitForTimeout(4000);
}

module.exports = { prepInicioPage, routeFixturePartida, loadFixtureRefresh, PARTIDA_FIXTURE };
