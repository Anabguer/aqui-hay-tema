#!/usr/bin/env node
'use strict';
const fs = require('fs');
const path = require('path');
const STRUCT = /^\s*(display|grid-template-columns|grid-template-rows|grid-template-areas|grid-area|grid-column|grid-row|flex|flex-direction|flex-wrap|flex-grow|flex-shrink|flex-basis|align-items|align-self|align-content|justify-content|justify-self|justify-items|gap|row-gap|column-gap|place-items|place-content|place-self|position|inset|top|right|bottom|left|width|min-width|max-width|height|min-height|max-height|margin|margin-top|margin-right|margin-bottom|margin-left|padding|padding-top|padding-right|padding-bottom|padding-left|overflow|overflow-x|overflow-y|box-sizing|order|contain|aspect-ratio|object-fit|object-position)\s*:/i;
const p = path.join(__dirname, '..', 'assets/css/inicio/inicio-cromatica-desktop.css');
const lines = fs.readFileSync(p, 'utf8').split(/\r?\n/);
const out = lines.filter((l) => !STRUCT.test(l));
fs.writeFileSync(p, out.join('\n'));
const left = out.join('\n').match(/\bdisplay\s*:/g);
console.log('strip_cromatica_struct lines removed', lines.length - out.length, 'display left', left ? left.length : 0);
