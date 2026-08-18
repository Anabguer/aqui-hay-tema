#!/usr/bin/env node
/**
 * Monta una comparativa horizontal con PNG reales.
 * No redibuja caras.
 *
 * Uso: node compose_comparativa.mjs LAB_01_Teo
 */
import { spawnSync } from "child_process";
import { join, dirname } from "path";
import { fileURLToPath } from "url";
import { existsSync } from "fs";

const root = join(dirname(fileURLToPath(import.meta.url)), "../../..");
const labId = process.argv[2];
if (!labId) {
  console.error("Uso: node compose_comparativa.mjs LAB_01_Teo");
  process.exit(1);
}

const dir = join(root, "assets/personajes/_laboratorio", labId);
const py = `
from PIL import Image, ImageDraw, ImageFont
from pathlib import Path
lab = Path(r'${dir.replace(/\\/g, "/")}')
files = [
    ('neutral', lab / '${labId}_cabeza_maestra_neutral.png'),
    ('alegre', lab / '${labId}_alegre.png'),
    ('enfadado', lab / '${labId}_enfadado.png'),
    ('triste', lab / '${labId}_triste.png'),
]
imgs = [(label, Image.open(p).convert('RGBA')) for label, p in files]
size, pad, label_h, title_h = 512, 24, 56, 72
n = len(imgs)
W = pad + n * (size + pad)
H = title_h + size + label_h + pad
out = Image.new('RGB', (W, H), '#FAF6F1')
draw = ImageDraw.Draw(out)
try:
    font_title = ImageFont.truetype('C:/Windows/Fonts/segoeui.ttf', 28)
    font_label = ImageFont.truetype('C:/Windows/Fonts/segoeui.ttf', 22)
    font_small = ImageFont.truetype('C:/Windows/Fonts/segoeui.ttf', 14)
except Exception:
    font_title = font_label = font_small = ImageFont.load_default()
draw.text((pad, 18), '${labId}  ·  prueba de consistencia  ·  NO CANON', fill='#2B2118', font=font_title)
draw.text((pad, 48), 'Misma cabeza maestra. Solo cambia ceja / ojo / boca. No es habitante del pool.', fill='#6B5A4A', font=font_small)
for i, (label, im) in enumerate(imgs):
    w, h = im.size
    s = min(w, h)
    left, top = (w - s) // 2, (h - s) // 2
    im = im.crop((left, top, left + s, top + s)).resize((size, size), Image.Resampling.LANCZOS)
    x = pad + i * (size + pad)
    y = title_h
    out.paste(im.convert('RGB'), (x, y))
    bbox = draw.textbbox((0, 0), label, font=font_label)
    tw = bbox[2] - bbox[0]
    draw.text((x + (size - tw) / 2, y + size + 12), label, fill='#2B2118', font=font_label)
dest = lab / '${labId}_comparativa_4_expresiones.png'
out.save(dest, 'PNG', optimize=True)
print(dest)
`;

if (!existsSync(dir)) {
  console.error("No existe", dir);
  process.exit(1);
}

const r = spawnSync("python", ["-c", py], { encoding: "utf8" });
if (r.stdout) process.stdout.write(r.stdout);
if (r.stderr) process.stderr.write(r.stderr);
process.exit(r.status ?? 1);
