# ESTADO VISUAL ACTUAL

Contrato de integración entre el hilo de imágenes y el motor.
El hilo visual puede actualizar este documento. El motor **consume** lo que aquí esté marcado; **no genera** PNG ni decide identidades.

**Fecha:** 2026-08-18

---

## Dirección visual vigente

Estado: **PROVISIONAL** (sustituye la dirección antigua 06b / A-B-C).

- caricatura amable
- cabezas algo grandes
- ojos muy expresivos
- rasgos faciales variados
- línea marcada
- sombreado suave
- lectura clara a escala pequeña
- edades, cuerpos, caras y estilos muy diferentes
- identidad visual estable por personaje

Cada personaje: **cabeza maestra / identidad aprobada** → expresiones derivadas del mismo rostro.

Expresiones que el hilo visual está **probando** (no obliga a las nueve en cada pack):

`neutral` · `alegre` · `entusiasmado` · `pensativo` · `enfadado` · `triste` · `sorprendido` · `esceptico` · `complice`

---

## Estado PROVISIONAL / APROBADO

| Pieza | Estado |
| --- | --- |
| Dirección de estilo (caricatura amable) | PROVISIONAL |
| Lista de expression_id | PROVISIONAL (ampliable; N variable por pack) |
| Cabeza maestra por personaje | APROBADO solo cuando el hilo visual lo marque y se registre el pack |
| Mapeo estado emocional → expresión | NO APROBADO (catálogo global vacío; LAB_01_Teo es placeholder de tubo) |
| Rocío visual / I10 / I06 / B / C | FUERA DE ESTE HILO — no tocar |

---

## Estructura de assets esperada

Registro técnico (fuente que el motor lee):

- `data/personajes/_visual_packs/registro.json`

Cada pack declara:

- `pack_id`
- `visual_identity_version`
- `carpeta` relativa a la raíz del proyecto
- `expresiones`: mapa `expression_id` → `{ archivo, identidad_version }`
- opcional: `mapeo_estado_a_expresion` (solo laboratorio; no es diseño de pueblo)

Carpetas:

- Laboratorio (no canon): `assets/personajes/_laboratorio/LAB_##_Nombre/`
- En revisión: `assets/personajes/revision/`
- Aprobados: `assets/personajes/aprobados/<SLOT>_<Nombre>/`

El motor **no** recicla caras de láminas maestras. Solo sirve archivos registrados con `identidad_version` igual a la del pack.

---

## Personajes visualmente aprobados

Ninguno registrado en `assets/personajes/aprobados/` a 2026-08-18.

---

## Personajes en revisión

Hay material histórico en `assets/personajes/revision/` (p. ej. `I02_Dani/`).
El motor **no** los trata como canon ni como pack jugable hasta que el hilo visual los apruebe y se registren.

---

## Expresiones disponibles por personaje

### LAB_01_Teo — laboratorio, NO canon

- Pack: `lab_01_teo`
- Identidad: `visual_identity_version: 1`
- Disponibles en disco: `neutral`, `alegre`, `enfadado`, `triste`
- No disponibles aún: `entusiasmado`, `pensativo`, `sorprendido`, `esceptico`, `complice`

Ningún personaje de catálogo (incluida Rocío) tiene pack visual vinculado.

---

## Versión de identidad visual

Regla técnica:

- `visual_identity_version` del pack debe coincidir con `identidad_version` de cada PNG de expresión.
- Si no coincide, el motor ignora ese archivo.

Vigente:

- `lab_01_teo` → versión **1**

---

## Fallback

- Expresión inexistente, pack incompleto o versión de identidad distinta → **`neutral`**.
- Packs parciales son válidos (N = 1, 4, 9 o más).
- Sin pack → no hay cara; el motor no inventa un PNG.
- El estado emocional interno **no cambia** por un fallback de expresión.

---

## Decisiones que el motor NO debe asumir

- Que exista una fórmula psicológica estado → cara.
- Que un estado (`celoso`, `frustrado`, etc.) tenga un `celoso.png` o un 1:1 con una expresión.
- Que todos los personajes necesiten las nueve expresiones de prueba.
- Que la dirección 06b / A-B-C siga vigente.
- Que Rocío, B, C, I10 o I06 tengan identidad o pack visual asignados por este hilo.
- Que LAB_01_Teo sea un habitante del pueblo.
- Que las láminas de referencia sean identidades reciclables.
- Que un encuentro, un descubrimiento o un NPC autónomo cambien la cara (hooks apagados).
- Que coincidir en un lugar implique interactuar o mostrar una expresión concreta.
- Duraciones, intensidades o reglas de personalidad aplicadas a la cara.
