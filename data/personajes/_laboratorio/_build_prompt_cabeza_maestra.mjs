#!/usr/bin/env node
/**
 * Prompt de cabeza maestra / expresión v1.
 * Solo laboratorio o fichas con visual.rasgos_fisicos.
 *
 * Uso:
 *   node data/personajes/_laboratorio/_build_prompt_cabeza_maestra.mjs LAB_01_Teo
 *   node data/personajes/_laboratorio/_build_prompt_cabeza_maestra.mjs LAB_01_Teo alegre
 *
 * NO incluye slots militares, secretos, ni nombres de la lámina maestra.
 */
import { readFileSync, existsSync } from "fs";
import { join, dirname } from "path";
import { fileURLToPath } from "url";

const root = join(dirname(fileURLToPath(import.meta.url)), "../../..");
const labId = process.argv[2];
const expresion = (process.argv[3] || "neutral").toLowerCase();

if (!labId) {
  console.error("Uso: node _build_prompt_cabeza_maestra.mjs LAB_01_Teo [neutral|alegre|enfadado|triste|...]");
  process.exit(1);
}

const fichaPath = join(root, "assets/personajes/_laboratorio", labId, "ficha_lab.json");
if (!existsSync(fichaPath)) {
  console.error("No existe", fichaPath);
  process.exit(1);
}

const ficha = JSON.parse(readFileSync(fichaPath, "utf8"));
const edad = ficha.identidad.edad;
const genero = ficha.identidad.genero;
const forma = ficha.visual.forma_cara;
const estilo = ficha.visual.estilo_visual;
const rasgos = ficha.visual.rasgos_fisicos.join("; ");
const look = ficha.visual.look_base_laboratorio || "simple everyday top, shoulders only";

const STYLE_REF = join(
  root,
  "assets/referencias_visuales/personajes/REFERENCIA_MAESTRA_PERSONAJES_v1.png"
);
const EXPR_REF = join(
  root,
  "assets/referencias_visuales/personajes/REFERENCIA_MAESTRA_EXPRESIONES_v1.png"
);
const HEAD_REF = join(
  root,
  "assets/personajes/_laboratorio",
  labId,
  `${labId}_cabeza_maestra_neutral.png`
);

const EXPR = {
  neutral:
    "NEUTRAL rest face. Mouth closed/relaxed, not smiling, not frowning. Eyes open, looking at camera. This is the master head.",
  alegre:
    "HAPPY expression on the SAME face: warm smile, maybe teeth, eyes slightly squinted. Do NOT change hair, nose, ears, face shape, age, or clothes.",
  entusiasmado:
    "EXCITED expression on the SAME face: wider eyes, bigger open grin. No hands. Do NOT change identity.",
  pensativo:
    "THOUGHTFUL expression on the SAME face: eyes glance up-side, small closed mouth. Head still front-facing. No hand on chin. Do NOT change identity.",
  enfadado:
    "ANGRY expression on the SAME face: V-shaped brows, tense mouth. Do NOT change hair, nose, ears, face shape, age, or clothes.",
  triste:
    "SAD expression on the SAME face: inner brows up, drooping lids, small downturned mouth. Do NOT change hair, nose, ears, face shape, age, or clothes.",
  sorprendido:
    "SURPRISED expression on the SAME face: very wide eyes, small O mouth, raised brows. Do NOT change identity.",
  esceptico:
    "SKEPTICAL expression on the SAME face: one brow up, mouth pulled to one side. Do NOT change identity.",
  complice:
    "KNOWING/COMPLICIT expression on the SAME face: wink or sly half-smile. Same glasses/hair. Do NOT change identity.",
};

if (!EXPR[expresion]) {
  console.error("Expresión desconocida:", expresion);
  process.exit(1);
}

const negativos = [
  "photorealistic",
  "semirealistic",
  "3D render",
  "anime",
  "manga",
  "Pixar",
  "Disney",
  "chibi toddler",
  "same-face",
  "beauty filter",
  "text",
  "watermark",
  "UI",
  "ID card",
  "full body",
  "hands in frame",
  "copying Rocío",
  "copying Marta",
  "copying Lucas",
  "copying Daniela",
  "copying Álvaro",
  "copying Inés",
  "copying Dani",
  "military",
  "weapons",
].join(", ");

const bloqueEstilo = `Match GRAPHIC STYLE ONLY from the attached master style sheet: friendly social-game caricature, slightly oversized head, large expressive eyes with small catchlights, simplified but DISTINCT features, warm palette, dark clean outlines, soft shading. NOT cute generic chibi. NOT photoreal. NOT anime. NOT board-game naive 06b.

DO NOT copy any face identity from the style sheet. Those people are mannequins, not this character. Create a NEW person from the written traits.`;

const bloqueEncaje = `FRAMING: fully front-facing bust. Head centered. Entire head visible (hair crown + chin). Neck and tiny shoulder tops. Simple clothes: ${look}. White/light flat background. Square. One character. NO hands. NO scenery. NO text.`;

let prompt;
let identityRef = null;

if (expresion === "neutral") {
  prompt = `${bloqueEstilo}

${bloqueEncaje}

NEW person, ${edad} year old ${genero}. Face shape: ${forma}.
Physical traits that MUST show: ${rasgos}.
${estilo}

${EXPR.neutral}

Identity from bone structure (skull, jaw, nose, ears), not only hair. Do not beautify or rejuvenate.`;
} else {
  identityRef = HEAD_REF;
  prompt = `This is the SAME person as the attached MASTER HEAD. Keep identical: face shape, nose, ears, eye shape, hair silhouette and color, skin, freckles, stubble, age, clothes.

Only change facial expression muscles (brows, eyelids, mouth).
${EXPR[expresion]}

${bloqueEncaje}

Still ${edad}, ${genero}, ${forma} face. Traits: ${rasgos}.

Match the graphic finish of the master head (line, color, shading). Do NOT redesign the character. Do NOT copy anyone from a style sheet.`;
}

const out = {
  personaje_id: ficha.id,
  lab_folder: labId,
  expresion,
  prompt,
  negativos,
  reference_style: expresion === "neutral" ? STYLE_REF : null,
  reference_identity: identityRef,
  reference_expresiones_lamina: expresion === "neutral" ? null : EXPR_REF,
  meta: {
    edad,
    genero,
    forma_cara: forma,
    nombre: ficha.identidad.nombre,
    canon: false,
  },
};

console.log(JSON.stringify(out, null, 2));
