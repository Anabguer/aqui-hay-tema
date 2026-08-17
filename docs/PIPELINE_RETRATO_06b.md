# Pipeline retrato 06b (v2 — referencia maestra ABC)

Generación de cabezas maestras. **Solo Cursor.**

```text
FICHA DEL PERSONAJE (per_xxx.json)
        +
REFERENCIA MAESTRA 06b A+B+C (lámina conjunta)
        ↓
GENERACIÓN
        ↓
GATE VISUAL REAL (PNG)
        ↓
COMPARATIVA CON UNIVERSO VISUAL (ABC + personaje)
        ↓
REVISIÓN NENI
```

---

## Regla central

```text
REFERENCIA DE ESTILO: SÍ — COMPARATIVA_MAESTRA_06b_ABC.png
REFERENCIA DE IDENTIDAD: NO — identidad solo desde ficha
```

**Prohibido:** usar A, B o C **individualmente** como `reference_image`.  
**Prohibido:** usar el retrato del personaje N−1 como única referencia (deriva de estilo).

Todos los personajes beben de la **misma** lámina maestra ABC.

Ruta canónica: `assets/referencias_visuales/estilo_06b/COMPARATIVA_MAESTRA_06b_ABC.png`

---

## Entrada identidad

Solo `data/personajes/<id>.json`: edad, género, `visual.rasgos_fisicos`, `visual.estilo_visual`, expresión desde `rasgos_publicos`.

**Prohibido en prompt:** slot `I02`, códigos militares, ocupación, romance, texto del juego.

Constructor: `data/personajes/_build_retrato_prompt.mjs`

---

## Generación

| Parámetro | Regla |
| --- | --- |
| `reference_image_paths` | **Solo** `COMPARATIVA_MAESTRA_06b_ABC.png` |
| Prompt | «Match graphic style ONLY from reference sheet. Create NEW person, do NOT copy faces A/B/C.» |
| `description` (tool) | «Cartoon head game character style 06b» — sin slot |
| Salida temporal | `revision/<Slot>_<Nombre>_revision_NN.png` |

---

## Nombres humanos

Ver `assets/personajes/revision/README.md`.

Ejemplo Dani: `I02_Dani_revision_01.png` + `.meta.json`

---

## Gate posterior (PNG real)

Checklist de 9 puntos (identidad + estilo + encuadre + sin contenido ajeno) + **comparativa visual** con lámina ABC.

| Extra estilo | Comprueba |
| --- | --- |
| 10 | Misma familia gráfica que ABC (línea plana, ojos simplificados) |
| 11 | No es clon de cara A, B ni C |
| 12 | Convive en comparativa ABC+D |
| 13 | **Gate cabezas rapadas:** sin pelo/barba/gafas/accesorios, ¿distinto de A? Si confundible con A → **FAIL** aunque cambie el pelo |

FAIL → `evidencia/` con nombre humano descriptivo. No canonizar.

---

## Validación del sistema

1. Dani convive con A+B+C (**esta fase**).
2. Después: prueba con varios personajes muy distintos.
3. Sistema estable cuando: **mismo ilustrador + personas claramente diferentes**.

---

## Referencias

- `docs/CONTRATO_VISUAL_PERSONAJES.md`
- `assets/referencias_visuales/estilo_06b/README.md`
