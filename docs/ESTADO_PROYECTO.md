# Estado del proyecto — Aquí Hay Tema

**Fotografía de control:** 2026-08-18 (actualizada tras respuestas Fase 1)  
**Fase:** diseño y documentación. **No hay bucle jugable.** **No programar.**

Este documento manda como **mapa de situación**. Las decisiones cerradas siguen en `DECISIONES_CERRADAS.md`. Si algo aquí contradice a `DECISIONES_CERRADAS.md`, gana `DECISIONES_CERRADAS.md` salvo que este archivo marque explícitamente una contradicción sin resolver.

---

## Leyenda de clasificación

| Etiqueta | Significado |
| --- | --- |
| **CERRADO** | Decisión tomada; no rediseñar sin motivo fuerte |
| **CANDIDATO** | Propuesta seria; falta confirmación de Neni + ChatGPT o prueba |
| **PENDIENTE** | Hueco conocido; aún no hay propuesta suficiente |
| **OBSOLETO** | Documento o regla sustituida; no usar para diseñar |
| **CONTRADICTORIO** | Dos fuentes dicen cosas distintas; ver § Contradicciones |

---

## 1. Resumen por áreas

### CONCEPTO — CERRADO (tono) / CANDIDATO (detalle económico)

**Aquí Hay Tema** es un juego de gestión social en una villa pequeña, cutre y simpática. El jugador es **Celestine**: gestiona vínculos y convivencia (amor, amistad, conflictos, soledad), no un sudoku de parejas ni Los Sims. Pueblo **persistente + temporadas**; sandbox sin game over por rupturas. Mobile-first, caricatura 2D.

### ROL DEL JUGADOR — CERRADO

- **Celestine** = jugador. Propone; los personajes viven.
- Visible **narrativamente** (NPC se dirigen a Celestine, escriben, piden ayuda). **Sin avatar/retrato permanente.**
- **Tutorial:** NPC distinto (mayor, bajita/regordeta, llaves) cede responsabilidad y se va de vacaciones. **No diseñar aún.**

### BUCLE PRINCIPAL — CERRADO (dirección) / PENDIENTE (wireframes)

Entrada típica (cuando exista prototipo):

1. Ver **El pueblo** (tarjetas de habitantes).
2. Leer **Diario del pueblo** (+ posible **Buzón**).
3. Observar vida autónoma (coincidencias, diario).
4. Proponer cita o plan (romance **o** amistad) dentro de presupuesto diario.
5. Recibir informe narrativo; relaciones evolucionan.
6. Gestionar llegadas, marchas consentidas, desbloqueos progresivos.

Detalle de pantallas y flujos: **PENDIENTE** (sin wireframes).

### POBLACIÓN — CERRADO

| Pieza | Estado |
| --- | --- |
| Día 1 | **Rocío** + B + C (por decidir) |
| Capacidad Bloque A | ≈ **16** (A01…A16) |
| Bloque B | ≈16 futuro; **no** en 1.ª prueba |
| Pool catálogo | Tanda **16** ahora; objetivo **80–100+** |
| 32 | Capacidad simultánea A+B, **no** total de fichas |
| Llegadas | Irregulares, como acontecimiento |
| Marchas | Intención sí; salida **solo con OK de Celestine**; «quédate» siempre; nunca offline; archivado no reaparece en esa partida |
| Vivienda | Automática, primer hueco libre |
| Partidas | Persistente / nueva / reiniciar; pool **por partida** |

### PERSONAJES — CERRADO (contrato) / PENDIENTE (roster)

- Contrato A+B: `CONTRATO_PERSONAJE.md` — **CERRADO**
- Auditoría: `CHECKLIST_AUDITORIA_PERSONAJES.md` — **CERRADO**
- Esqueleto I01–L06: `FASE_DISENO_POOL_16.md` — **NO APROBADO** (revisión en curso)
- Fichas JSON: **1** (`per_i03` Rocío), estado `revision`, **confirmada día 1**
- Pilotos objetivo Fase C: **3–4** (Rocío + otros por definir)
- Retratos: estilo **06b canónico**; **0** aprobados; Rocío regenerar después

### RELACIONES — CERRADO (marco) / CANDIDATO (amistad jugable)

- 4 variables románticas: atracción, vínculo, conflicto, necesidad de contacto — **CERRADO**
- Resto derivado; jugador **no** ve desglose interno — **CERRADO**
- Grafo: recíproco **potencial** obligatorio quien pueda romance — **CERRADO**
- `interes_romantico: ninguno` permitido — **CERRADO**
- Relaciones no lineales; enemigo→romance raro y causal — **CERRADO** marco
- Plan de amistad jugable — **CANDIDATO**
- Ex, desconocidos, estados sociales detallados — **PENDIENTE** fórmulas

### COMPATIBILIDAD — CERRADO (principio) / PENDIENTE (capas)

- Por capas; **nunca** un único % visible — **CERRADO**
- Atracción asimétrica y dinámica — **CERRADO**
- Test cutre sin % — **CANDIDATO** (no día 1)
- Pesos, umbrales, capas concretas — **PENDIENTE**
- `SISTEMA_COMPATIBILIDAD.md`, `APRENDIZAJE_PREFERENCIAS.md` — **CANDIDATO**

### AUTONOMÍA NPC — CERRADO (mínimo) / CANDIDATO (agenda completa)

- Vida mínima: ocupación, franja, hobby, ánimo, lugares — **CERRADO**
- Pueden formar pareja **sin** Celestine — **CERRADO**
- Coincidir ≠ interactuar — **CERRADO**
- Aforo en lugares — **CERRADO** principio; cifras **PENDIENTE**
- Agenda slots 1h (trabajo, sueño, rutinas, citas) — **CANDIDATO** (propuesta en `PROPUESTA_AGENDA_DISPONIBILIDAD_RUTINAS.md`; alcance 1.ª prueba sin decidir)

### AGENDA Y TIEMPO — CANDIDATO / PENDIENTE

- **Tiempo 1:1** (1 día real = 1 día juego, catch-up) — **CANDIDATO serio** (`DECISIONES_CERRADAS.md`)
- Catch-up offline, cupos AFK — **PENDIENTE**
- Duración temporada — **PENDIENTE**
- Trabajo dinámico, rutinas descubribles — **CANDIDATO** (propuesta agenda)

### ENCUENTROS / CITAS — CERRADO (marco) / PENDIENTE (detalle)

- Presupuesto Celestine/día (romance o amistad); citas NPC no gastan — **CERRADO** marco
- Informe narrativo, no «+8 amor» — **CERRADO**
- Microeventos 25–40 en banco — **CANDIDATO**
- Aceptación/rechazo de citas — **PENDIENTE**
- `PREPARACION_DE_CITAS.md` — **CANDIDATO**

### ECONOMÍA Y FAMA — CERRADO (existen) / PENDIENTE (cifras)

- Dinero y fama existen; visibles en prototipo — **CERRADO**
- Fama = reconocimiento de Celestine en la comunidad — **CERRADO** (post-Celestine)
- Precios, umbrales, orígenes de ingresos — **PENDIENTE**
- Goteo offline — **PENDIENTE**

### MAPA Y LUGARES — CERRADO (catálogo) / PENDIENTE (mapa)

- Catálogo 14 tipos — **CERRADO**
- 1.ª prueba: **solo Bloque A**; **cafetería** primero — **CERRADO**
- Parque/biblioteca progresivos — **CERRADO**
- Candados visibles: cine, bar, arcade — **CERRADO**
- Casa: intimidad/convivencia, no parcela comercial — **CERRADO**
- Mapa celda a celda, aforos numéricos — **PENDIENTE**
- Asset mapa jugable — **no existe**

### INTERFAZ — CANDIDATO / PENDIENTE

| Pantalla | Estado |
| --- | --- |
| Landing biblioteca (`index.php`) | Existe (no es partida) |
| El pueblo (tarjetas) | **CANDIDATO** — sin wireframe |
| Diario del pueblo | **CERRADO** concepto; sin UI |
| Buzón de Celestine | **CANDIDATO fuerte** |
| Mapa con lugares | **PENDIENTE** |
| Economía (dinero/fama) | **CERRADO** mostrar; sin UI |
| Ficha habitante | **CANDIDATO** (conocimiento progresivo) |

### BUZÓN / DIARIO — CANDIDATO fuerte / CERRADO diario

- **Diario:** canal objetivo del pueblo, 5–10 entradas/día, tipos pista/aviso/ruido — **CERRADO**
- **Buzón:** mensajes directos a Celestine, subjetivos — **CANDIDATO fuerte** (`BUZON_DE_CELESTINE.md`)
- Evitar triplicar el mismo hecho — **PENDIENTE** reglas de deduplicación

### PROGRESIÓN — CERRADO (dirección) / PENDIENTE (hitos)

- Onboarding progresivo (café → más lugares) — **CERRADO**
- Desbloqueos visibles (candados) — **CERRADO**
- Bloque B, temporadas, rótulo comunidad — **PENDIENTE** hitos concretos
- Test compatibilidad desbloqueable — **CANDIDATO**

### VISUAL — CANDIDATO estilo / REFERENCIA ancla

- Ancla tono: `REFERENCIA_VISUAL_01_AQUI_HAY_TEMA.png` — **REFERENCIA** (layout partida **no** canon)
- Estilos 01–05: comparativa — **REFERENCIA** histórica
- Estilo **06 / 06b**: — **CANDIDATO** (no APROBADO)
- Diferenciación estructural (06): — **CANDIDATO** en contrato visual
- Cabeza maestra + look futuro — **CERRADO** proceso; look **fuera** de V0

### PARTIDAS — CERRADO

- Persistente, nueva, reiniciar — **CERRADO**
- Pool y archivados por partida — **CERRADO**
- Mecanismo concreto semilla vs pools trío — **PENDIENTE**

### MVP / PRIMERA PRUEBA — CERRADO (alcance) / sin código

Ver `MVP.md`. Resumen: trío ~3, solo Bloque A, cafetería primero, placeholders visuales, diario, posible buzón, una cita/plan, una llegada, NPC que se mueve, economía dummy, **sin** tienda ropa, **sin** test día 1.

### SIMULADOR — PENDIENTE

`SIMULADOR_DISENO.md` describe herramienta de mesa; **no implementada**.

---

## 2. Clasificación por sistema

| Sistema | Clasificación | Nota |
| --- | --- | --- |
| Nombre Aquí Hay Tema | CERRADO | |
| Rol Celestine | CERRADO | |
| Escala ~3 / Bloque A ~16 / pool tandas | CERRADO | |
| Marchas consentidas | CERRADO | Sustituye retención con dado |
| Vivienda automática | CERRADO | |
| Coincidir ≠ interactuar | CERRADO | |
| 4 variables relación | CERRADO | |
| Grafo recíproco potencial | CERRADO | |
| Diario del pueblo | CERRADO | |
| Buzón de Celestine | CERRADO | V0 pequeño |
| Tiempo 1:1 | CERRADO | |
| Agenda slots 1h + plantillas | CERRADO | 24 h |
| Amistad jugable | CERRADO | |
| Seed + configs prevalidadas | CERRADO | |
| Estilo visual 06b | CERRADO | Canónico |
| Esqueleto pool §7 | PENDIENTE | No aprobado |
| 2× interes_romantico ninguno | CERRADO | En tanda 16 |
| Aforo numérico por lugar | PENDIENTE | Principio cerrado |
| Simulador de mesa | PENDIENTE | |
| 16 fichas pool tanda 1 | PENDIENTE | 1 borrador |
| Wireframes UI | PENDIENTE | |
| Fórmulas compatibilidad | PENDIENTE | |
| Economía (cifras) | PENDIENTE | |
| Mapa funcional | PENDIENTE | |
| Motor de juego | PENDIENTE | No existe |
| «Casamentero exclusivo» | OBSOLETO | → Celestine |
| 10 al día 1 / techo 14 | OBSOLETO | → ~3 + Bloque A |
| Marchas con retención/dado | OBSOLETO | → consentidas |
| Pool = 32 fichas | OBSOLETO | → 80–100+ catálogo |
| Estilo «retrato PNG 01» en PARA_CHATGPT | OBSOLETO | → cabeza maestra + pruebas 06 |
| Encuadre jersey 3/4 Rocío | OBSOLETO | → cabeza maestra |

---

## 3. Contradicciones activas

| # | Documento A | Documento B | Parece más reciente | Resolución |
| --- | --- | --- | --- | --- |
| 1 | `PARA_CHATGPT.md` — «Marchas con retención» | `DECISIONES_CERRADAS.md` — marchas consentidas | DECISIONES | **Resolver:** actualizar PARA_CHATGPT (hecho en esta parada) |
| 2 | `README.md` raíz — «casamentero/cotilla profesional» | Celestine en DECISIONES | Celestine | **Pendiente Neni:** alinear README raíz |
| 3 | `index.php` meta — «cotilleo romántico» | Celestine vínculos amplios | Celestine | **Pendiente:** texto landing |
| 4 | `PROPUESTA_JUGABLE_V0_CURSOR.md` §2 — 10 iniciales, techo 14 | ~3 al inicio | ~3 | **Resolver:** aviso histórico ya en §1 del doc; no usar §2 |
| 5 | `data/personajes/personajes.json` meta — «10 iniciales + 6 llegadas» | ~3 + llegadas irregulares | Propuestas recientes | **Pendiente:** actualizar meta JSON |
| 6 | `FASE_DISENO_POOL_16.md` — I01–I10 «iniciales» del pool 16 | Día 1 solo ~3 | Ambos válidos en capas distintas | **No es contradicción real** si se entiende: 16 = catálogo tanda; 3 = activos día 1. **Riesgo:** confusión de nomenclatura |
| 7 | `DECISIONES_CERRADAS.md` — fila «Agenda» en tabla Cerrado | `PENDIENTES_DISENO.md` — «Agenda mínima vs completa en 1.ª prueba» | Ambos | **CONTRADICTORIO leve:** principio de agenda cerrado; **alcance V0** no cerrado. Tratar agenda como **CANDIDATO** hasta confirmar mínimo viable |
| 8 | `DECIONES_CERRADAS.md` — «Tiempo 1:1 candidato serio» en tabla Cerrado | No reflejado en simulador ni MVP detallado | Candidato | **CONTRADICTORIO leve:** listado como cerrado pero etiqueta «candidato». Clasificar **CANDIDATO** hasta simulador |
| 9 | `FUENTE_DOCUMENTO_MAESTRO.md` — casamentero, 8 lugares, 30 días | DECISIONES actuales | DECISIONES | **OBSOLETO** parcial; Maestro = semilla tono |
| 10 | `PROPUESTA_JUGABLE_V0_CURSOR.md` — «fama del casamentero» | Fama de Celestine | Celestine | **Pendiente:** pasada de limpieza terminológica en propuesta V0 |
| 11 | Referencia PNG — 12 sitios abiertos, barra 72/100 | 1.ª prueba cafetería primero; 4 variables | DECISIONES | **No contradicción** si PNG = tono/UI aspiracional, no layout V0 |

---

## 4. Preguntas para Neni + ChatGPT

### NECESITO RESPUESTA AHORA (bloquean siguiente paso)

1. ¿**Celestine** aparece con nombre/avatar en UI o es rol interno del jugador?
2. ¿**Buzón** entra en la 1.ª prueba jugable o solo **Diario** + ficha?
3. ¿Confirmáis **cafetería sola** al arrancar (parque/biblioteca después)? — cerrado en DECISIONES; validación final.
4. ¿Cuántos con **`interes_romantico: ninguno`** en la tanda de 16?
5. **Nueva partida rejugable:** ¿semilla fija, pools de trío/llegadas, u otro mecanismo?
6. **Agenda en 1.ª prueba:** ¿mínima (franja + ocupación) o slots 1h completos?
7. ¿Aprobáis o retocáis el **esqueleto I01–L06** (`FASE_DISENO_POOL_16.md` §7)?
8. ¿**Estilo 06 o 06b** (o seguir probando)? Sin esto no hay retrato definitivo.
9. ¿Los **3 del día 1** son 3 del esqueleto concreto o genéricos hasta cerrar pool?

### PUEDE ESPERAR (importante, no bloquea documentación inmediata)

- Cifras de **aforo** por lugar (tras prueba mapa PC/móvil)
- **Tiempo 1:1** — confirmar tras simulador de mesa
- Duración y objetivos de **temporada**
- Precios, umbrales **fama/dinero**
- **Marchas** en 1.ª prueba o solo modeladas en texto
- Nombre visible diario / buzón / villa / pantalla El pueblo
- Set definitivo **10 rasgos + 12 hobbies** candidatos
- `compromisos_recurrentes` y `susceptibilidad_enemigo_romance` en tanda 1
- Wireframes El pueblo, diario, buzón
- Protección residencia: ¿~30 días u otra cifra?

### NO HACE FALTA DECIDIR TODAVÍA

- Contenido masivo de eventos (25–40 microeventos pueden ser placeholder)
- Bloque B: precio, hito, UI
- Tienda herramientas sociales, tienda ropa
- Fórmulas finas de capas compatibilidad
- Catch-up offline detallado, goteo económico AFK
- Cabeza + cuerpo / looks
- Grafo social visible completo

---

## 5. Plan de trabajo (fases — acordado Fase 1)

| Fase | Qué | Estado |
| --- | --- | --- |
| **A** | Incorporar decisiones + limpiar obsoletos | **En curso / casi hecho** |
| **B** | Revisar esqueleto I01–L06 → resumen → aprobación Neni | **Siguiente** |
| **C** | 3–4 pilotos completos (Rocío incluida) | Tras B |
| **D** | **Micro-simulación de mesa** con pilotos | Tras C |
| **E** | Tanda 16 solo si el molde sobrevive D | Tras D |
| **F** | Simulación amplia | Tras E |
| **G** | Wireframes / interfaz | Tras F |
| **H** | Implementación | Cuando Neni diga |

**Dependencias:** no fichas 16 ni código hasta D OK · no retratos definitivos 06b hasta pilotos auditados · no wireframes finales hasta micro-sim · B bloquea C.

---

## 5b. Esqueleto I01–L06 — resumen compacto (Fase B)

**Pool 16:** I01–I10 (plantilla catálogo) + L01–L06 (llegadas). **Día 1 real:** solo ~3 (Rocío + B + C).

**Demografía objetivo:** 7M / 7H / 2 NB · edades 22–72 (4+6+4+2 por bandas).

**Patrones orientación (cupos):** ~5 solo mujeres · ~5 solo hombres · ~3 bi · ~3 incluyen NB.

**Iniciales I01–I10 (extracto):**

| Slot | Perfil breve |
| --- | --- |
| I01 | Mujer 22–29, hetero, flexible, estudiante, espontánea |
| I02 | Hombre 22–29, hetero, noche, camarero, fiester |
| I03 | ⚠️ Esqueleto: lésbica docente — **Rocío real difiere** |
| I04 | Mujer puente (M+H+NB), autónoma |
| I05 | Hombre gay, noche, sanitario |
| I06 | Hombre puente gay/hetero, oficina |
| I07 | Mujer 45–59 hetero; posible progenitora I01 |
| I08 | Hombre 45–59 hetero, sanitario |
| I09 | NB flexible, autónomo |
| I10 | Mujer 60–72 jubilada hetero |

**Llegadas L01–L06:** camarero noche (amistad/triángulo gay) · estudiante lésbica · oficinista química emparejado · docente vida pueblo · NB soltero · jubilado vida pueblo.

**Familia sugerida:** I07↔I01 (veto romance). **Ex:** 0–1 en pool. **Amistades semilla:** 6–10 lazos.

**Revisión necesaria por decisiones posteriores:** ver respuesta aparte en este turno.

---

## 6. Estado del código

| Componente | ¿Existe? | Detalle |
| --- | --- | --- |
| Landing biblioteca | **Sí** | `index.php`, `css/home.css`, `cover.svg`, `meta.json` |
| Motor de juego | **No** | |
| Simulación tiempo/NPC | **No** | |
| Mapa funcional | **No** | |
| Pantalla partida | **No** | |
| UI El pueblo / diario / buzón | **No** | |
| Base de datos runtime | **No** | |
| Modelo datos | **Parcial** | Plantillas JSON en `data/`; `per_i03.json`; `lugares.json`; eventos placeholder |
| Motor personajes | **No** | |
| Prototipo ejecutable jugable | **No** | Solo landing informativa |

**Conclusión:** el proyecto es **essencialmente diseño/documentación**. El código existente es puerta de biblioteca, no juego.

---

## 7. Estado de assets

| Asset | Clasificación | Ubicación / nota |
| --- | --- | --- |
| Referencia visual mapa+UI | REFERENCIA | `docs/referencias_visuales/REFERENCIA_VISUAL_01_AQUI_HAY_TEMA.png` |
| Comparativa estilos 01–05 (A/B/C) | REFERENCIA | `assets/personajes/_revision/_comparativa_estilos_abc/` |
| Estilo 06 / 06b | **APROBADO** (06b canónico) | `assets/personajes/_revision/_estilo_06/` |
| Rocío borrador principal | BORRADOR | `assets/personajes/_revision/per_i03/borrador.png` |
| Rocío pruebas estilo 01–05 | BORRADOR / REFERENCIA | `per_i03/pruebas_estilo/` |
| Retrato aprobado Rocío | — | **No existe** |
| Ficha JSON Rocío | BORRADOR (revision) | `data/personajes/per_i03.json` |
| Mapa jugable | — | **No existe** |
| UI partida | — | **No existe** |
| Edificios / sprites lugar | — | **No existe** |
| cover.svg | APROBADO | Icono biblioteca |

---

## 8. Porcentajes de avance

### A) Diseño del juego: **~52 %**

**Sube** tras Fase 1: Celestine, buzón, mapa cerrado, agenda, seed, 06b, trío Rocío, 2 sin romance.

**Falta:** esqueleto aprobado, plantillas agenda, 3–4 pilotos, micro-sim, wireframes, cifras economía/aforo.

### B) Implementación: **~28 %**

**Incluido:** Turno 1 + schema v2, RNG, logging, EncuentroEngine/lifecycle/resolver, mapa técnico presencia, buzón/diario estructura, CompatibilityEvaluator contrato, NPC planner dev, EconomyLedger, bucle play.php, tests modulares ALL PASS.

**Falta:** catch-up eventos, B/C día 1, contenido narrativo, fórmulas compatibilidad, economía balanceada, retratos.

---

## 9. Qué está terminado / a medias

| Completamente terminado | A medias | No empezado (implementación) |
| --- | --- | --- |
| Identidad nombre y carpetas | Pool 16 (1/16 ficha) | Motor juego |
| Contratos personaje y auditoría | Visual (candidatos 06, 0 aprobados) | Simulación |
| DECISIONES núcleo escala/marchas/vivienda | Propuestas agenda/buzón/tiempo | Mapa/UI partida |
| Landing biblioteca | Diario/buzón (docs, sin UI) | 15 fichas restantes |
| Catálogo lugares (diseño) | Compatibilidad (marco sin fórmulas) | Simulador mesa |
| Modelo datos plantilla | README/PARA_CHATGPT (limpieza pendiente) | |

---

## 10. Siguiente fase inmediata

**Fase B:** devolver esqueleto revisado para aprobación Neni + ChatGPT.  
**Paralelo Fase C prep:** no regenerar Rocío; completar 2–3 pilotos adicionales tras B.

*Última actualización: respuestas Fase 1 — 2026-08-18.*
