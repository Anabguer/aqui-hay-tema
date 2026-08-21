const fs = require('fs');
const path = 'assets/js/play-v3.js';
let s = fs.readFileSync(path, 'utf8');

if (s.includes('AhtLabAudit')) {
  console.log('already patched');
  process.exit(0);
}

const nl = s.includes('\r\n') ? '\r\n' : '\n';

s = s.replace(
  `    if (method === 'GET') {${nl}      const q = new URLSearchParams();${nl}      q.set('action', action);`,
  `    if (IS_LAB) body.lab = 1;${nl}    if (method === 'GET') {${nl}      const q = new URLSearchParams();${nl}      q.set('action', action);${nl}      if (IS_LAB) q.set('lab', '1');`
);

s = s.replace(
  `    if (!resp.ok || data.ok === false) {${nl}      logApiError(action, method, body, resp.status, data, data.error || ('http_' + resp.status));${nl}    }${nl}    return data;`,
  `    if (!resp.ok || data.ok === false) {${nl}      logApiError(action, method, body, resp.status, data, data.error || ('http_' + resp.status));${nl}    }${nl}    if (IS_LAB && typeof AhtLabAudit !== 'undefined' && AhtLabAudit.log) {${nl}      try { AhtLabAudit.log(data); } catch (e) {}${nl}    }${nl}    return data;`
);

if (!s.includes('AhtLabAudit')) {
  console.error('PATCH FAILED');
  process.exit(1);
}

fs.writeFileSync(path, s);
console.log('patched play-v3.js');
