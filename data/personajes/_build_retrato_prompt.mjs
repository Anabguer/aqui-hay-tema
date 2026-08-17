#!/usr/bin/env node
/**
 * Construye prompt de retrato 06b SOLO desde per_xxx.json.
 * Uso: node data/personajes/_build_retrato_prompt.mjs per_i02
 * NO incluye slot (I02) ni secretos. Salida: JSON { prompt, negativos, checks }.
 */
import { readFileSync } from "fs";
import { join, dirname } from "path";
import { fileURLToPath } from "url";

const root = join(dirname(fileURLToPath(import.meta.url)), "../..");
const id = process.argv[2];
if (!id || !id.startsWith("per_")) {
  console.error("Uso: node _build_retrato_prompt.mjs per_xxx");
  process.exit(1);
}

const ficha = JSON.parse(
  readFileSync(join(root, "data/personajes", `${id}.json`), "utf8")
);

const edad = ficha.identidad.edad;
const genero = ficha.identidad.genero;
const estilo = ficha.visual.estilo_visual;
const rasgos = ficha.visual.rasgos_fisicos.join(", ");
const rasgosPub = (ficha.vida.rasgos_publicos || []).join(", ");

const expresion =
  rasgosPub.includes("bromista")
    ? "relaxed confident half-smile, playful direct energy, not worried, not blank"
    : "neutral friendly village expression";

const STYLE_REF = join(root, "assets/referencias_visuales/estilo_06b/COMPARATIVA_MAESTRA_06b_ABC.png");

const bloqueFijo = `Match the GRAPHIC STYLE ONLY from the attached reference sheet (three cartoon heads A B C together): same line weight, flat colors, minimal shading, eye/nose/mouth treatment, simplification level, board-game sitcom cartoon finish.

DO NOT copy the face identity of person A, B, or C from the reference. Create a completely NEW fourth person.

Board-game 2D cartoon MASTER HEAD. Style 06b. NOT photoreal, NOT anime, NOT Pixar. NO text, NO ID card, NO military.

FRAMING: fully front-facing. Head centered. Full head and chin. Neck visible. NO clothing. White flat background. Square. One character only.`;

const negativos = [
  "photorealistic",
  "identification card",
  "dossier",
  "infiltration unit",
  "military",
  "spy",
  "tactical suit",
  "weapons",
  "barcode",
  "skills list",
  "text",
  "I02",
  "classified",
  "3D render",
  "anime",
  "clothing",
  "bust portrait",
].join(", ");

const prompt = `${bloqueFijo}

${edad} year old ${genero}. ${estilo}
Physical traits (must show): ${rasgos}.
Expression: ${expresion}.

Cartoon master head only. Identity from bone structure. Do NOT beautify.`;

const checks = {
  personaje_id: id,
  prohibido_en_prompt: ["slot", "I02", "ocupacion", "romance", "lazos"],
  verificar_despues: [
    "genero coincide",
    "edad aparente coherente",
    "caricatura 06b no foto",
    "sin texto ni ficha ID",
    "sin ropa protagonista",
    "rasgos fisicos reconocibles",
    "no maniqui A/B/C",
  ],
};

console.log(JSON.stringify({ prompt, negativos, checks, reference_style: STYLE_REF, meta: { edad, genero, slot: ficha.slot, nombre: ficha.identidad.nombre } }, null, 2));
