const fs = require('fs');
const j = fs.readFileSync('assets/js/play-v3.js', 'utf8');
if (!j.includes('function ahtLabAuditLog')) throw new Error('missing ahtLabAuditLog');
if (!j.includes("config_id: 'juego_v1', seed: 'lab-")) throw new Error('lab should use juego_v1');
if (!j.includes('data-buzon-badge')) throw new Error('missing buzon badge patch');
console.log('lab_static_test OK');
