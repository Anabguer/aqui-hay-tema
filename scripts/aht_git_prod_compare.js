#!/usr/bin/env node
'use strict';
/**
 * Compara assets Git (HEAD) vs produccion HTTP.
 * Texto: SHA256 normalizado LF. Binario: SHA256 raw.
 * Exit 0 = todo alineado; 1 = divergencias.
 */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const https = require('https');
const { execSync } = require('child_process');

const ROOT = process.cwd();
let repoRoot = ROOT;
const args = process.argv.slice(2);
for (let i = 0; i < args.length; i++) {
  if (args[i] === '--repo' && args[i + 1]) repoRoot = path.resolve(args[++i]);
}

const manifestPath = path.join(repoRoot, 'scripts/aht_visual_manifest.json');
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const PROD_BASE = manifest.prodBase;

const sha = (buf) => crypto.createHash('sha256').update(buf).digest('hex');
const normLf = (buf) => buf.toString('utf8').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
const isTextAsset = (rel) => /\.(css|js|php|txt|json|html|md)$/i.test(rel);

function fetchUrl(url) {
  return new Promise((resolve, reject) => {
    https.get(url, { headers: { 'Cache-Control': 'no-cache', Pragma: 'no-cache' } }, (res) => {
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => resolve({ status: res.statusCode, body: Buffer.concat(chunks) }));
    }).on('error', reject);
  });
}

function gitShow(rel) {
  try {
    return execSync(`git -C "${repoRoot}" show HEAD:${rel}`, { encoding: 'buffer', maxBuffer: 50 * 1024 * 1024 });
  } catch (e) {
    return null;
  }
}

function compareOne(rel, prodBody, gitBody) {
  const text = isTextAsset(rel);
  const result = {
    rel,
    gitBytes: gitBody.length,
    prodBytes: prodBody.length,
    text,
    match: false,
    matchMode: text ? 'lf-normalized' : 'raw',
  };

  if (text) {
    const gNorm = sha(Buffer.from(normLf(gitBody), 'utf8'));
    const pNorm = sha(Buffer.from(normLf(prodBody), 'utf8'));
    result.gitSha256Lf = gNorm;
    result.prodSha256Lf = pNorm;
    result.match = gNorm === pNorm;
    result.gitSha256Raw = sha(gitBody);
    result.prodSha256Raw = sha(prodBody);
    result.rawMatch = result.gitSha256Raw === result.prodSha256Raw;
    if (!result.match && result.rawMatch) {
      result.note = 'solo difiere por finales de linea (raw match)';
      result.match = true;
    }
  } else {
    result.gitSha256Raw = sha(gitBody);
    result.prodSha256Raw = sha(prodBody);
    result.match = result.gitSha256Raw === result.prodSha256Raw;
  }
  return result;
}

(async () => {
  const head = execSync(`git -C "${repoRoot}" rev-parse HEAD`, { encoding: 'utf8' }).trim();
  const remoteHead = execSync(`git -C "${repoRoot}" rev-parse origin/deploy/integrated`, { encoding: 'utf8' }).trim();

  const busterRes = await fetchUrl(`${PROD_BASE}/assets/aht-cache-buster.txt`);
  const buster = busterRes.body.toString('utf8').trim();

  const results = [];
  const mismatches = [];

  for (const rel of manifest.textAssets) {
    const gitBody = gitShow(rel);
    if (!gitBody) {
      mismatches.push({ rel, error: 'no existe en Git HEAD' });
      continue;
    }
    const url = `${PROD_BASE}/${rel}?v=${encodeURIComponent(buster)}`;
    const prodRes = await fetchUrl(url);
    if (prodRes.status !== 200) {
      mismatches.push({ rel, error: `HTTP ${prodRes.status}` });
      continue;
    }
    const cmp = compareOne(rel, prodRes.body, gitBody);
    results.push(cmp);
    if (!cmp.match) mismatches.push(cmp);
  }

  for (const rel of manifest.volatileAssets || []) {
    const gitBody = gitShow(rel);
    const prodRes = await fetchUrl(`${PROD_BASE}/${rel}`);
    if (gitBody && prodRes.status === 200) {
      const cmp = compareOne(rel, prodRes.body, gitBody);
      cmp.volatile = true;
      results.push(cmp);
    }
  }

  const report = {
    checkedAt: new Date().toISOString(),
    repoRoot,
    head,
    remoteHead,
    headAlignedWithOrigin: head === remoteHead,
    prodBase: PROD_BASE,
    cacheBuster: buster,
    totalChecked: results.length,
    mismatches: mismatches.length,
    results,
    ok: mismatches.length === 0,
  };

  const outPath = path.join(repoRoot, 'logs/aht-git-prod-compare-latest.json');
  fs.mkdirSync(path.dirname(outPath), { recursive: true });
  fs.writeFileSync(outPath, JSON.stringify(report, null, 2));

  console.log(JSON.stringify({
    ok: report.ok,
    head,
    remoteHead,
    mismatches: mismatches.length,
    mismatchFiles: mismatches.map((m) => m.rel || m.error),
    report: outPath,
  }, null, 2));

  process.exit(report.ok ? 0 : 1);
})().catch((e) => {
  console.error('FATAL', e);
  process.exit(2);
});
