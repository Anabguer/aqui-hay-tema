# Decisiones cerradas (2026-08-18)

Este archivo manda sobre el Documento Maestro **solo** en los puntos listados. El resto del Maestro sigue vigente.

El Maestro nació como *Proyecto Cupido Cutre*. El Doc y la carpeta de Drive se llamaron después *Aquí Hay Tema*.

La **escala** la mandan `PROPUESTA_BLOQUE_3_A_24.md` + `PROPUESTA_VIDA_POOL_Y_MARCHAS.md`: ~3 al inicio, **capacidad ≠ pool**, tanda 16 de fichas, **Bloque A ≈ 16** (ampliar = Bloque B, no un 24 único). Marchas: **última palabra del jugador**. **Primera prueba jugable: solo Bloque A.** Rol: **Celestine** (`PROPUESTA_CELESTINE_BUZON_TIEMPO_PARTIDAS.md`).

**Respuestas Fase 1 (parada de control):** 2026-08-18. Detalle operativo en `ESTADO_PROYECTO.md`.

## Cerrado

| Tema | Decisión |
| --- | --- |
| Nombre del juego | **Aquí Hay Tema** |
| Carpetas | Local `W:\juegos\Aqui hay tema` · Drive `G:\Mi unidad\Aqui Hay Tema` · URL `/juegos/aqui-hay-tema/` |
| Fantasía / agencia | **Celestine** = **jugador**. Gestiona vínculos y convivencia (amor, amistad, conflictos, soledad…). No es alcalde ni casamentero exclusivo. **El jugador propone. Los personajes viven.** Visible **narrativamente** (NPC se dirigen a Celestine, escriben, piden ayuda). **Sin avatar/retrato permanente** de Celestine. |
| Tutorial | Un **NPC distinto** (persona mayor, bajita/regordeta, llaves) presenta el tutorial y cede temporalmente la responsabilidad del bloque antes de marcharse de vacaciones. **No diseñar aún** ese personaje definitivo. |
| Sin pareja ≠ fallo | Un habitante puede **no querer** romance. Eso **no** es un problema que Celestine deba solucionar. |
| Primera prueba jugable | **Solo Bloque A.** ~3 al inicio (**Rocío** + B + C por decidir). **Solo la cafetería** operativa el día 1. Parque/biblioteca y resto: en mapa base **cerrados** hasta desbloqueo. Bloque B: arquitectura sí, UI/mapa **no** ahora. |
| Compatibilidad | Por capas; nunca un único % visible |
| Atracción | Asimétrica y dinámica |
| Lugares iniciales (catálogo) | **Cafetería, parque, biblioteca.** Día 1: **solo cafetería operativa.** |
| Mapa V0 | Mapa **base** con edificios/lugares **visibles pero cerrados** (persiana, obras, abandonado, sin actividad…). **No** aparecen mágicamente al desbloquear. Al abrir: cambio visual + lugar operativo. Notificación contextual a Celestine (ej. «Hay movimiento en el antiguo bingo…»). Precio, día exacto y catálogo visible completo: **no fijados**. Candados visibles **cine, bar, arcade**. |
| Catálogo de lugares | Ver lista abajo. **Casa no es parcela comercial.** |
| Parentesco que veta romance | Padre/madre↔hijo/a, hermanos, medio hermanos, abuelo/a↔nieto/a, tío/a↔sobrino/a, primos hermanos |
| 4 variables románticas | **atracción, vínculo, conflicto, necesidad de contacto.** El resto es derivado. |
| Vida NPC mínima | ocupación, franja de disponibilidad, hobby principal, **estado emocional** (no barra Sims; no es el retrato), lugar(es) preferente(s). |
| Diario | Nombre técnico **Diario del pueblo**. 5–10 entradas/día. Tipos: ruido/humor, info útil, pista, problema. **Complemento:** **Buzón de Celestine** (mensajes directos, subjetivos). Resumen objetivo mínimo opcional si la simulación lo pide. |
| Indicadores diario | Pista `👀` · aviso importante `⚠️` · el resto, sin alarma |
| Estructura de partida | **Pueblo persistente + temporadas.** No termina el día 30. Duración de temporada sin cifrar. |
| Orientación | `atraido_por` (lista de `mujer` / `hombre` / `no_binarie`). Género de ficha: los mismos tres valores. |
| Grafo romántico | Cada ficha con `interes_romantico` ≠ `ninguno` tiene ≥1 recíproco **potencial** entre gente que **puede coexistir**. **2** personajes por tanda de 16 con `interes_romantico: ninguno`. No clones; amistad/vínculos con valor propio; no puzzles a arreglar. El trío del día 1 puede no tener vía romántica. |
| Trío día 1 | **Rocío** (`per_i03`, slot **I08**) confirmada. B y C **por decidir** tras pilotos. Micro-sim α: I02+I10; I10 **no cerrado** en día 1 real. |
| Escala | Día 1 ~**3** · **Bloque A ≈ 16** · ampliar = **Bloque B ≈ 16** (futuro; no en 1.ª prueba) · **pool catálogo** 80–100+ en tandas (ahora tanda 16) · **32** = capacidad simultánea futura A+B, no total de fichas. |
| Marchas | Intención de irse **sí**. Marcha definitiva **solo si Celestine deja ir**. «Quédate» = se queda siempre; la causa vira a arco. Sin tirada. **Nunca offline.** Antiguo **archivado**; no reaparece en **esta** partida. |
| Partidas | Partida persistente · nueva partida · reiniciar · estado del pool **por partida**. **Seed por partida** (reproducible debug). Inicio entre **configuraciones prevalidadas** (no random puro). Llegadas: azar controlado entre candidatos válidos. |
| NPC autónomos | Los residentes viven sin el jugador. **Puede nacer una pareja sin él.** El jugador interviene; no es el pulmón del bloque. |
| Objetivo / derrota | Sandbox. **Sin** «X parejas = ganas». **Sin** game over por rupturas. Lectura: **rótulo por bloque** + posible **estado de la comunidad**. Textos no canónicos. |
| Ocupaciones de referencia | sanitario/a, oficina, camarero/a, comercio, docente, estudiante adulto, autónomo/freelance, jubilado/a |
| Recursos | Dinero y fama (valores no fijados) |
| Coletillas / encuentros | Sistemas a preparar |
| Ropa | **Fuera de la V0 jugable** (ni tienda de ropa ni look). La ficha **sí** guarda estilo visual. Tienda de **herramientas sociales** (test, etc.): candidato aparte. |
| Consulta de habitantes | Pantalla de UI **El pueblo** (nombre provisional). Tarjetas, no tabla. A ~16 (y al abrir B) harán falta filtros, incluido por bloque. Grafo social **egocéntrico** y solo de lo descubierto. |
| Vivienda | **Asignación automática.** Celestine **no** elige piso ni bloque. Primer hueco libre (A01…A16, luego B01…B16 si B está abierto). Hueco que se libera (marcha o convivencia) vuelve al pool. **No** es gestión inmobiliaria. No modifica personalidad, compatibilidad, atracción, relaciones ni romance. |
| Aforo de lugares | Cada lugar social tiene **aforo** (jugable + visual). Cifras **no** fijadas: se deciden con prueba real de mapa (PC y móvil). Legibilidad de cabezas manda. |
| Coincidencia ≠ interacción | Estar en el mismo sitio genera **oportunidad**, no un encuentro automático. El motor decide si hablan, se ignoran, etc. |
| Test de compatibilidad | Juguete cutre, **sin %**, info incompleta. **No día 1:** desbloqueo/compra. Candidato. |
| Encuentros / día | Un presupuesto (romántico o amistoso). Las citas **NPC** no lo gastan. Cifra fina abierta. |
| Buzón V0 | **Sí** en 1.ª prueba (pequeño). Tipos mínimos: bienvenida, petición, problema, respuesta/reacción, información/descubrimiento, avisos/desbloqueos. Voz subjetiva del personaje; **no** acceso al estado interno. Ver `BUZON_DE_CELESTINE.md`. |
| Amistad | **Jugable**, valor propio. |
| Tiempo | **1 día real = 1 día de juego.** Catch-up al volver; no simular minuto a minuto. Detalle: `PROPUESTA_CELESTINE_BUZON_TIEMPO_PARTIDAS.md`. |
| Agenda V0 | Slots internos de **1 hora**, **24 h** (sin límite artificial diurno). Trabajo, sueño, rutinas, compromisos y citas bloquean horas. **No** diseñar 24×16 manualmente: **plantillas**, horarios estructurales, excepciones, `compromisos_recurrentes`, eventos dinámicos. UI: horas incompatibles, anti-doble-reserva, sugerencia de horas compatibles. Ver `PROPUESTA_AGENDA_DISPONIBILIDAD_RUTINAS.md`. |
| Relaciones sociales | **No lineales.** Ejes por par (afecto, confianza, tensión) + rutas múltiples. Enemigo→romance **raro y causal**. |
| Protección residencia (hipótesis) | Marcha «normal» no antes de **~30 días** en la comunidad. Número **no** canonizado. |
| Arte / cuadrícula | Caricatura 2D de la PNG; mapas importantes con limpia + grid |
| Retratos | Ancla **provisional v1** `REFERENCIA_MAESTRA_PERSONAJES_v1.png`. 06b **en pausa**. Diferenciación estructural, variedad de atractivo, Cursor genera, Neni APROBADO. Packs de expresiones **progresivos**. Identidad visual **versionada**. No reciclar caras de la lámina. |
| Estado emocional ≠ expresión | **Cerrado 2026-08-18.** Interno = `estado_emocional`. Asset = `expresion_visual` vía `ExpressionResolver`, fallback `neutral`. `animo` = alias legacy (no barra, no PNG). Hooks emocionales **apagados**. Sin fórmulas ni mappings de producción. `CONTRATO_ESTADO_EMOCIONAL_Y_EXPRESION.md`. |
| Esqueleto pool §7 | **Aprobado** — `FASE_B_ESQUELETO_REVISADO.md` · Rocío **I08** |
| Micro-sim cast | Config **α:** Rocío + I02 + I10 (+ I06 piloto 4) |
| Día 1 real | Rocío sí · B/C pendientes · I10 no cerrado en tutorial |
| Agenda base | 24×1h · plantillas · 0–3 compromisos con variedad real · `PLANTILLAS_AGENDA_BORRADOR.md` |
| Fase actual | **C** — pilotos · `FASE_C_ENTREGA_CHATGPT.md` |

| Programar | Todavía no (ni el bucle, ni los 16 en código) |
| Creación de fichas | Neni ve **2–3 pilotos** (tono). ChatGPT diseña el resto. Nadie es **final** sin `CHECKLIST_AUDITORIA_PERSONAJES.md`. Estados ficha `borrador` / `revision` / `aprobado`. Pool `borrador` / `auditoria_global` / `aprobado`. |

## Catálogo base de lugares (cerrado)

Parcelas / sitios de diseño:

1. biblioteca *(inicial, mapa V0 abierto)*
2. cafetería *(inicial, mapa V0 abierto)*
3. parque *(inicial, mapa V0 abierto)*
4. cine *(candado visible V0)*
5. plaza *(catálogo; fuera del mapa V0)*
6. restaurante *(uno, no dos; fuera del mapa V0)*
7. bar *(candado visible V0)*
8. discoteca *(fuera del mapa V0)*
9. bingo *(fuera del mapa V0)*
10. arcade *(candado visible V0)*
11. tienda de ropa *(fuera del mapa V0; ropa no jugable en V0)*
12. gimnasio *(fuera del mapa V0)*
13. mirador *(fuera del mapa V0)*
14. casa *(no se compra como el cine; intimidad / convivencia / fases de relación)*

## Obsoleto

| Antes | Ahora |
| --- | --- |
| Cupido Cutre / nombre pendiente | Aquí Hay Tema |
| 30 días = fin de partida | Pueblo persistente + temporadas |
| 8 lugares fijos / inicio sin recortar | 3 iniciales concretos |
| Dos restaurantes (barato / bonito) | Un restaurante |
| Plaza / tienda / gimnasio «candidatos» | En el catálogo |
| 4 barras = chispa, confianza, conflicto, contacto | 4 = **atracción, vínculo, conflicto, necesidad de contacto** |
| «6 variables internas de personaje» como sistema aparte | Vida NPC mínima; no seis barras de Sims |
| Calendario de partida pendiente | Estructura elegida; solo faltan cifras de temporada |
| Parentesco «¿primos?» | Primos hermanos **sí** vetan |
| Grafo = basta un crush de ida | Grafo = ≥1 recíproco **potencial** por personaje |
| Ropa mecánica en el primer prototipo | Ropa en el modelo; no en la V0 jugable |
| Ayuntamiento / padrón como 4.º sitio | UI **El pueblo** |
| Derrota dura / game over V0 | Sandbox + rótulo por bloque / comunidad; sin «X parejas = ganas» |
| 10 al día 1 · 16 = población · techo ~14 | Día 1 ~3 · Bloque A ~16 · pool = tandas |
| Solo llegadas / techo ~24 de golpe | Marchas **consentidas**; ampliar = Bloque B, no un 24 único |
| Test el día 1 | Test comprable, más tarde |
| Los 10 iniciales se bastan en el grafo | El trío tutorial puede no tener vía romántica |
| Amistad solo como lazo de ficha | Plan de amistad jugable (candidato) |
| Casamentero exclusivo | **Celestine** (vínculos + convivencia) |
| Pool = 32 personajes | 32 = capacidad A+B; catálogo **80–100+** |
| Antiguo reaparece al azar | Archivado = fuera de llegadas en **esa** partida |
| Diario único canal | Diario + **Buzón** |
| 3 sitios jugables día 1 | **Solo cafetería operativa**; resto cerrado en mapa hasta desbloqueo |
| Edificios aparecen al desbloquear | Mapa base con edificios **cerrados desde el inicio** |
| Estilo visual candidato | **v1 provisional**; 06b en pausa |
| Agenda «reducida» solo franja | Slots 1 h + plantillas motor |
| Buzón candidato | **Buzón en V0** |
| Amistad candidato | **Amistad jugable** cerrada |
| Tiempo candidato | **Tiempo 1:1** cerrado |
| Random puro al inicio | **Seed + configs prevalidadas** |
| Celestine sin presencia | Celestine **narrativo**, sin avatar |
| Celestine elige piso / portal | Vivienda **automática**; primer hueco libre |
| Coincidir = hablar | Coincidir = **oportunidad**; el motor decide |

## Lo que el Maestro sigue mandando

- No es un sudoku. Historias, no matching único.
- Azar + causas en consecuencias grandes.
- Informe de cita narrativo, no un «+8 amor».
- Estado de pareja legible por color; el jugador no ve el desglose interno.
- Cutrez intencionada. El jugador no obliga.
- Diversidad como pueblo. Mobile-first.

## La referencia visual

Sigue sin canonizar precios, 12 edificios abiertos, barra 72/100, nombres de maqueta ni el tamaño del mapa. Ahora **contradice** el inicio de 3 lugares: es tono, no layout de partida.
