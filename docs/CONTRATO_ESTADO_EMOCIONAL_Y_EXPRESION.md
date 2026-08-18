# Contrato: estado emocional ≠ expresión visual

**CANON 2026-08-18.** Arquitectura aprobada. Extensible. **No** es un sistema emocional de producción. **No** hay fórmulas ni mappings de pueblo.

Canon cerrado:

- estado interno independiente del asset
- `ExpressionResolver`
- fallback a `neutral`
- identidad visual versionada
- hooks emocionales **apagados** hasta definir reglas
- packs de expresiones **progresivos** (no hace falta el set completo)

Identidades visuales y packs por personaje: trabajo de diseño en curso. El motor no los genera.

```text
ESTADO INTERNO     = qué está sintiendo el residente (runtime)
EXPRESIÓN VISUAL   = qué asset de cara se pinta ahora (paquete gráfico)
```

Prohibido guardar `estado = triste.png`.
El motor **consume** assets aprobados. **No** los genera.

---

## 1. Dos capas

| Capa | Vive en | Quién la cambia | Ejemplo |
| --- | --- | --- | --- |
| Estado emocional | `residente.runtime.estado_emocional` | Motor / DEV (placeholder) | `frustrado`, `celoso`, `ilusionado` |
| Expresión visual | paquete gráfico + `ExpressionResolver` | Resolver (o override DEV) | `enfadado`, `esceptico`, `alegre` |

Dos residentes pueden compartir estado `frustrado` y mostrar caras distintas **cuando exista mapeo de producción** (aún no). El mapeo de LAB_01_Teo es solo prueba de tubo.

---

## 2. Runtime mínimo

```text
runtime.estado_emocional
  id          string     (catálogo estados_emocionales; default neutro)
  intensidad  null|int   (sin escala canónica todavía)
  origen      string     (catálogo origenes; default inicial)
  desde       {dia, hora}
  hasta       {dia, hora}|null

runtime.expresion_visual
  id            expression_id resuelto
  override_dev  expression_id|null   ← QA; no es un evento de juego
  fallback      bool
  motivo        string
```

### `animo` (legacy)

`runtime.animo` y `estado_inicial.animo` son **alias técnico/legacy** de `estado_emocional.id`.

El concepto canónico es **`estado_emocional`**.

No es:

- una barra tipo Sims (72/100, cansancio, higiene…)
- el PNG que se muestra (`expresion_visual` / `expression_id`)
- un sinónimo de «cara triste»

Semilla de ficha: `estado_inicial.animo: neutro` sigue válido al escribir A+B. En partida, leer y escribir `estado_emocional`.

`hasta: null` = sigue hasta que algo lo cambie. Si hay `hasta` y el reloj lo pasa, vuelve a `neutro` + expresión `neutral`. Eso es el campo funcionando, no una duración de diseño.

---

## 3. Paquete gráfico (identidad versionada)

```text
visual_identity_version: N
```

La cara aprobada (cabeza maestra) tiene versión estable. Cada expresión derivada declara la misma versión. Si se regenera una expresión con otra identidad, el motor **ignora** el PNG viejo y cae a `neutral` de la versión vigente.

```text
FICHA + cabeza maestra aprobada (versión N)
  → expresiones derivadas de ESA cabeza, versionadas N
  → el motor solo sirve archivos con identidad_version == N
```

`neutral` **siempre** debe existir en un pack usable.
El resto se genera **progresivamente**. No hace falta 9 PNG el día 1, ni 100 personajes × 9.

Lista actual de `expression_id` (ampliable):

`neutral` · `alegre` · `entusiasmado` · `pensativo` · `enfadado` · `triste` · `sorprendido` · `esceptico` · `complice`

Registro de packs: `data/personajes/_visual_packs/registro.json`  
Assets: carpetas bajo `assets/personajes/` (laboratorio / revisión / aprobados).

---

## 4. ExpressionResolver

```text
estado emocional
+ personalidad   (aceptada; NO aplicada aún)
+ contexto       (aceptado; NO aplicado aún)
+ pack del personaje
→ expression_id
  + fallback a neutral si el asset no existe o la versión no coincide
```

Orden:

1. `override_dev` o `expresion_solicitada` (laboratorio QA, sin eventos). Si el PNG no existe → `neutral`.
2. Mapeo **del pack** (`mapeo_estado_a_expresion`) si hay claves. El global está **vacío**. LAB_01_Teo trae un ejemplo `_placeholder` solo para probar el resolver (no es diseño del pueblo).
3. Mapeo **global** (`data/catalogos/mapeo_estado_expresion_placeholder.json`). **Vacío a propósito.**
4. `neutral`.

El motor no inventa un mapeo si el pack no lo declara. Un habitante sin pack pinta `neutral` aunque esté `frustrado`.

Si el `expression_id` candidato no está en el pack o su `identidad_version` no coincide: `neutral`.

Personalidad y contexto están en la firma para no tener que romper el contrato cuando existan reglas de verdad.

---

## 5. Orígenes futuros (hooks, sin reglas)

El puente `EmotionalEventBridge` se registra a eventos de dominio. Con el flag `emotional_state_from_events_enabled: false` **no cambia estados**. Solo deja el enchufe.

| origen (catálogo) | Pensado para |
| --- | --- |
| `encuentro` | encuentro |
| `rechazo` | rechazo |
| `discusion` | discusión |
| `descubrimiento` | descubrimiento |
| `romance` | romance |
| `problema_personal` | problema personal |
| `evento_edificio` | evento de edificio |
| `mensaje` | mensaje / buzón |
| `npc_autonomo` | acontecimiento autónomo NPC |
| `inicial` | al incorporar |
| `expiracion` | `hasta` cumplido |
| `dev_manual` | laboratorio DEV |

Eventos de dominio nuevos (emitidos al aplicar estado a mano / tests):

- `estado_emocional_cambiado`
- `expresion_visual_resuelta`

---

## 6. DEV

Laboratorio: selector de **paquete visual** (y/o residente) → tira `[neutral] [alegre] …`.

Sirve para ver assets **sin** disparar encuentros, rechazos ni romance.

Override DEV de expresión ≠ cambio de estado emocional.

---

## 7. Qué no es esto (sigue abierto a diseño, no a código)

- No hay valores de intensidad canónicos.
- No hay tabla definitiva estado→cara por rasgo / personalidad.
- El mapeo placeholder de LAB_01_Teo **no** es mapeo de producción.
- El mapeo global sigue **vacío**.
- No se generan PNG desde PHP.
- No se implementan reglas de eventos emocionales (`emotional_state_from_events_enabled: false`).
