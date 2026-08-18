# Pipeline: cabeza maestra → expresiones (v1 provisional)

Solo Cursor genera. ChatGPT no entrega PNGs sueltos al pool. Neni no da por bueno nada hasta **APROBADO**.

```text
FICHA PERSONAJE
        +
REFERENCIA_MAESTRA_PERSONAJES_v1   ← ESTILO
        ↓
CABEZA MAESTRA NEUTRAL
        ↓
VALIDACIÓN (identidad + estilo + encuadre)
        ↓
EXPRESIONES DERIVADAS
  referencia de IDENTIDAD = la cabeza maestra de ESE personaje
  referencia de ESTILO    = la lámina maestra v1
        ↓
VALIDACIÓN DE CONSISTENCIA
        ↓
APROBADO / REGENERAR
```

Regla anti-deriva (la que falló con Dani):

```text
REFERENCIA MAESTRA = ESTILO
CABEZA MAESTRA     = IDENTIDAD
```

Prohibido usar la lámina como «elige una cara y ponle otro nombre».  
Prohibido generar cada emoción desde cero sin la cabeza maestra.  
Prohibido usar el personaje N−1 como única referencia de estilo.

---

## 0. Alcance ahora

Esto es **preparación del sistema**. No se genera:

- Rocío, Dani, I10, I06
- pool de 16
- ningún personaje canon

Única generación permitida hasta nuevo aviso: personajes `_laboratorio/LAB_*`.

---

## 1. Estructura de carpetas

Propuesta de producción (cuando haya habitantes de verdad):

```text
assets/personajes/
  aprobados/
    <SLOT>_<Nombre>/
      cabeza_maestra_neutral.png
      alegre.png
      enfadado.png
      triste.png
      ...
      meta.json
  revision/
    <SLOT>_<Nombre>/
      <SLOT>_<Nombre>_cabeza_maestra_neutral.png
      <SLOT>_<Nombre>_alegre.png
      ...
      comparativa/
      evidencia/
      meta.json
```

Laboratorio (no canon):

```text
assets/personajes/_laboratorio/
  LAB_01_Teo/
    ficha_lab.json
    LAB_01_Teo_cabeza_maestra_neutral.png
    LAB_01_Teo_alegre.png
    LAB_01_Teo_enfadado.png
    LAB_01_Teo_triste.png
    LAB_01_Teo_comparativa_4_expresiones.png
    meta.json
```

`assets/personajes/_revision/` y `revision/I02_Dani/` se dejan **intocados**. Son el archivo 06b.

No usar `borrador.png` ni `test.png` sin nombre.

Convención de archivo:

```text
<ID>_<Nombre>_<estado>.png
```

Estados de expresión: `cabeza_maestra_neutral` | `alegre` | `entusiasmado` | `pensativo` | `enfadado` | `triste` | `sorprendido` | `esceptico` | `complice`

---

## 2. Sistema de expresiones

```text
PERSONAJE
  → identidad visual base  (cabeza maestra neutral)
  → expresiones derivadas  (mismo cráneo, otra musculatura)
```

Catálogo objetivo (no todas obligatorias en V0):

| id | Qué cambia |
| --- | --- |
| `neutral` | Reposo. Boca relajada. Mirada al frente. **Esta es la cabeza maestra.** |
| `alegre` | Sonrisa (dientes si encaja), ojos más estrechos, cejas suaves. |
| `entusiasmado` | Ojos más abiertos, sonrisa grande. Sin manos en V0. |
| `pensativo` | Mirada arriba/lado, boca pequeña. Sin dedo en la barbilla en V0. |
| `enfadado` | Cejas en V, boca tensa o entreabierta. |
| `triste` | Cejas internas arriba, párpados caídos, boca hacia abajo. |
| `sorprendido` | Ojos muy abiertos, boca en O pequeña. |
| `esceptico` | Una ceja arriba, boca ladeada. |
| `complice` | Guiño o media sonrisa cómplice. Mismo pelo, misma gafa. |

**V0 laboratorio:** solo `neutral`, `alegre`, `enfadado`, `triste`.

La identidad facial permanece: misma forma de cara, misma nariz, mismos ojos (forma base), mismas orejas, mismo pelo, misma edad aparente. Solo cambia expresión.

---

## 3. Entradas de generación

### Cabeza maestra

| Entrada | Uso |
| --- | --- |
| Ficha (`identidad` + `visual.rasgos_fisicos` + `forma_cara`) | IDENTIDAD |
| `REFERENCIA_MAESTRA_PERSONAJES_v1.png` (lámina **completa**) | ESTILO |
| Prompt construido por script | Encaje + negativos |

Prohibido en prompt: slot tipo `I02`, ocupación, romance, secretos, nombres de la lámina como si fueran este personaje.

### Expresión derivada

| Entrada | Uso |
| --- | --- |
| `cabeza_maestra_neutral.png` **de ese personaje** | IDENTIDAD |
| `REFERENCIA_MAESTRA_PERSONAJES_v1.png` | ESTILO (opcional si el modelo se confunde; la identidad manda) |
| `REFERENCIA_MAESTRA_EXPRESIONES_v1.png` | Cómo se deforma ceja/ojo/boca, **no** copiar esa fila de persona |
| Instrucción de emoción | Solo músculo facial |

Si hay conflicto entre lámina y cabeza maestra (pelo, nariz, edad): **gana la cabeza maestra**.

---

## 4. Encuadre v1

Retrato / cabeza maestra:

- Frente absoluta. Mirada a cámara (salvo pensativo/cómplice, que pueden desviar un poco los ojos **sin girar el cráneo**).
- Cabeza completa (coronilla y mentón).
- Cuello + nacimiento mínimo de hombros.
- Ropa simple del look base (la lámina v1 **sí** lleva ropa; no volvemos al cuello desnudo 06b).
- Fondo plano claro.
- Un personaje. Sin escenario, sin texto, sin UI.
- V0: **sin manos**.

---

## 5. Gates

### Gate A — cabeza maestra (antes de derivar emociones)

Abrir el PNG y comprobar:

1. Género / presentación = ficha.
2. Edad aparente coherente.
3. Familia gráfica v1 (caricatura amable, línea marcada, ojos expresivos, sombra suave). No foto. No 06b naïf. No anime.
4. Encuadre frente, cabeza completa, busto corto.
5. Sin texto, armas, ficha ID, nombres de la lámina.
6. ≥3 rasgos de `rasgos_fisicos` reconocibles **y** la `forma_cara` declarada.
7. No es un recorte de la lámina maestra (Rocío magenta, Marta bun, Lucas rizo, etc.).
8. No es A/B/C 06b ni Dani ni ningún `per_*`.
9. Neutral de verdad (no sonrisa de stock, no enfado).

FAIL → `evidencia/` con nombre descriptivo. No generar expresiones encima de un FAIL.

### Gate B — consistencia de expresiones

Sobre la comparativa de 4 (o las que existan), tapar bocas y cejas con la mano:

1. ¿Sigue siendo la misma silueta de pelo?
2. ¿Misma nariz?
3. ¿Misma forma de cara y orejas?
4. ¿Mismo tono de piel / pecas / lunar / barba?
5. ¿Misma edad?
6. ¿La emoción se lee a tamaño chincheta?
7. ¿Ninguna cara se ha «embellecido» o rejuvenecido respecto al neutral?

Un solo FAIL de identidad → regenerar **esa** expresión desde la cabeza maestra, no el set entero, salvo que el neutral también esté mal.

---

## 6. Automatizable vs manual

| Automatizable | Revisión humana (Neni / Cursor con el PNG abierto) |
| --- | --- |
| Crear carpeta y nombres de archivo | ¿Es la misma persona? |
| Construir prompt desde JSON | ¿Es la familia gráfica v1? |
| Encadenar: primero neutral, luego derivadas | ¿Se lee la emoción a 32 px? |
| Pasar cabeza maestra como `reference_image` de identidad | ¿Se copió un maniquí de la lámina? |
| Montar comparativa recortando los 4 PNG reales | ¿Atractivo / edad / rareza coinciden con la ficha? |
| Rellenar `meta.json` (rutas, fecha, prompts) | APROBADO / REGENERAR |
| Checklist vacía en meta | No marcar `gate: pass` sin mirar |

La comparativa **nunca** se regenera como ilustración nueva (el modelo redibujaría 4 caras distintas). Se **compone** con los PNG ya generados.

---

## 7. Limitaciones técnicas (conocidas)

1. El generador no bloquea geometría facial. Puede cambiar nariz, pelo o edad entre emociones. Por eso el pipeline es secuencial y hay gate humano.
2. La propia lámina de expresiones ya deriva en al menos un fotograma (pelo distinto). No es prueba de que el modelo «entienda» identidad.
3. Dos `reference_image` a la vez (estilo + identidad) pueden mezclarse: el modelo copia una cara de la lámina. Si pasa: **solo** cabeza maestra como referencia en las derivadas.
4. Nombres escritos en la lámina (Rocío, Marta…) pueden colarse. Negativos + nunca pedir «like Rocío».
5. Ojos grandes + brillo pueden resbalar hacia anime. Hay que vigilarlo en el gate de estilo.
6. No está resuelto todavía: cuerpo entero de producción, looks de cita, y cómo pegar cabeza maestra a un cuerpo sin perder identidad.
7. Legibilidad a 32 px hay que **medirla** recortando, no asumirla.
8. 06b y v1 no deben mezclarse en el mismo personaje.

---

## 8. Motor (consumo, no generación)

El motor **no** genera PNG. Lee `data/personajes/_visual_packs/registro.json`.

- `visual_identity_version` estable en el pack.
- Cada expresión declara `identidad_version`; si no coincide, se ignora y se usa `neutral`.
- `neutral` obligatorio; el resto progresivo.
- Estado emocional ≠ `expression_id`. Ver `docs/CONTRATO_ESTADO_EMOCIONAL_Y_EXPRESION.md`.

Constructor de prompt de laboratorio: `data/personajes/_laboratorio/_build_prompt_cabeza_maestra.mjs`
