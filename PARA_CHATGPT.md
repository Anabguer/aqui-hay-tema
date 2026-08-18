# Aquí Hay Tema — pack para ChatGPT

## Leer primero (orden obligatorio)

1. **`docs/ESTADO_PROYECTO.md`** — fotografía actual del proyecto.
2. **`docs/DECISIONES_CERRADAS.md`** — lo cerrado manda.
3. **`docs/PLAN_MAESTRO_IMPLEMENTACION.md`** — implementación y arquitectura.
4. **`docs/PENDIENTES_DISENO.md`** — huecos de diseño (existe en este repo).

### Clasificación (usar siempre; no mezclar)

| Etiqueta | Significado |
| --- | --- |
| **CERRADO** | Decisión tomada; no rediseñar sin motivo fuerte |
| **CANDIDATO** | Propuesta seria; falta confirmación o prueba |
| **BLOQUEADO_DECISION** | No seguir por ahí hasta que Neni/dirección cierre el punto |
| **PLACEHOLDER** | Hueco técnico o cifra provisional; no canonizar |

`ESTADO_PROYECTO.md` usa también **PENDIENTE**, **OBSOLETO** y **CONTRADICTORIO**. Respetar esas etiquetas cuando aparezcan.

### Precedencia

**No asumir** que documentación antigua prevalece sobre los documentos canónicos recientes.

- Este archivo **no** manda sobre los cuatro de arriba.
- El Documento Maestro / `docs/FUENTE_DOCUMENTO_MAESTRO.md` es semilla conceptual. Las decisiones posteriores **amplían o sustituyen** partes de esa semilla: gana `docs/DECISIONES_CERRADAS.md` en los puntos listados.
- Propuestas, packs de fase, cifras históricas (p. ej. 10/16/14) y el resto de este pack **no** pisan a los canónicos si hay conflicto.
- Si `ESTADO_PROYECTO.md` contradice a `DECISIONES_CERRADAS.md`, gana `DECISIONES_CERRADAS.md` salvo contradicción marcada explícitamente como sin resolver.

**No inventar decisiones** para “dejarlo limpio”. Si no está CERRADO, decirlo.

---

Carpeta de referencia en Drive: `G:\Mi unidad\Aqui Hay Tema`.  
Proyecto local: `W:\juegos\Aqui hay tema`.  
**No borres ni edites** el Google Doc `DOCUMENTO MAESTRO — AQUÍ HAY TEMA` salvo petición explícita.

Hay tag Git **`PLAYTEST_01`** (y **`PLAYTEST_01_PHP74`**). Qué cubre esa prueba y qué sigue abierto está en los canónicos (`docs/PLAYTEST_01.md`, `docs/ESTADO_PROYECTO.md`, `docs/PLAN_MAESTRO_IMPLEMENTACION.md`), no en este pack.

## Lectura siguiente (después de los canónicos)

Solo cuando los cuatro del principio estén leídos. Si chocan con un canónico, el canónico gana.

1. `docs/REGLAS_NO_NEGOCIABLES.md` + `docs/CHECKLIST_PRE_IMPLEMENTACION.md`
2. `docs/FASE_DISENO_POOL_16.md` — plantilla, grafo, esqueleto I01–L06
3. `docs/CONTRATO_PERSONAJE.md` — formato A+B
4. `docs/CONTRATO_VISUAL_PERSONAJES.md` — retratos
5. `docs/CHECKLIST_AUDITORIA_PERSONAJES.md` + `docs/REGISTRO_AUDITORIA_PERSONAJES.md`
6. `docs/VALIDACION_GRAFO_ROMANTICO.md` cuando haya destinos que comprobar
7. `docs/PROPUESTA_JUGABLE_V0_CURSOR.md` — bucle de un día (§2–3 con cifras 10/16/14 son **históricas**; no usar)
8. `docs/PROPUESTA_BLOQUE_3_A_24.md`
9. `docs/PROPUESTA_VIDA_POOL_Y_MARCHAS.md`
10. `docs/PROPUESTA_CELESTINE_BUZON_TIEMPO_PARTIDAS.md`
11. `docs/PROPUESTA_AGENDA_DISPONIBILIDAD_RUTINAS.md` — agenda **CANDIDATO**
12. Semilla: Google Doc o `docs/FUENTE_DOCUMENTO_MAESTRO.md` (muchas cifras **OBSOLETAS**)

## Notas de contexto (no canonizan nada nuevo)

- Nombre cerrado: **Aquí Hay Tema**.
- Rol jugador: **Celestine** (vínculos + convivencia; no casamentero exclusivo).
- Fichas: contrato A+B; Neni aprueba tono de pilotos viendo la ficha B. Auditoría obligatoria. Retratos: Cursor; estilo 06/06b **no** es canon vigente — ver canónicos de arte.
- Primera entrega ChatGPT (histórica de diseño): `docs/FASE_C_PACK_CHATGPT_I02.md`.

Detalle de escala, mapa, variables, grafo, marchas, bucle y qué hay o no hay implementado: **solo** los canónicos del principio.
