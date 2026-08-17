# Propuesta: Celestine, buzón, tiempo real y partidas

**Diseño. No es código.** Ronda 2026-08-17 (noche, segunda tanda). Complementa `PROPUESTA_VIDA_POOL_Y_MARCHAS.md`, `PROPUESTA_BLOQUE_3_A_24.md`, `DIARIO_DEL_PUEBLO.md`.

---

## Cerrado en esta ronda

| Tema | Decisión |
| --- | --- |
| Primera prueba jugable | **Solo Bloque A.** B queda como expansión posterior. No dibujar ni construir UI de B ahora; la arquitectura **no** debe impedir añadirlo. |
| Rol del jugador | **Celestine** — gestiona vínculos y convivencia de la comunidad (amor, amistad, conflictos, soledad, necesidades sociales). No es alcalde ni casamentero exclusivo. |
| Sin pareja ≠ problema | Un habitante puede **no querer** relación romántica. Eso **no** es un fallo que Celestine deba «arreglar». |
| Pool a largo plazo | Catálogo **80–100+** fichas en tandas. **32** = posible capacidad simultánea futura (A+B), **no** el total de personajes existentes. |
| Antiguos en una partida | Quien se marcha definitivamente queda **archivado / ya presentado**. **No** reaparece como llegada nueva aleatoria en **esa** partida. |
| Nueva partida | Puede reutilizar **todo** el catálogo. Inicio y llegadas **rejugables** (distintos tríos, distinto orden). |
| Partidas (concepto) | Debe existir: partida persistente · nueva partida · reiniciar · estado del pool **independiente por partida**. |
| Marcha offline | **Nunca.** Requiere decisión de Celestine. (Ya cerrado.) |
| Bloque A — inicio | ~**3** habitantes · **cafetería** como primer lugar social jugable · onboarding progresivo · llegadas con ritmo variable. |

## Candidato / estudiar (no canon de cifras)

| Tema | Hipótesis |
| --- | --- |
| Buzón de Celestine | Sustituir o **complementar** parte del diario con mensajes directos de habitantes. |
| Diario + buzón | Mantener **ambos**: diario objetivo/resumido + buzón personal/subjetivo. |
| Tiempo | **1 día real = 1 día de juego** (serio). Catch-up al volver, sin simular minuto a minuto. |
| Protección de residencia | No plantear marcha «normal» hasta llevar **~30 días** en la comunidad (número **no** canonizado). |

---

## 1. Celestine

### Qué es

La figura que la comunidad reconoce como quien **ordena el caos social**: propone encuentros, atiende conflictos, lee el pulso del bloque, desbloquea sitios con recursos, y **no** controla cuerpos ni voluntades.

No es:

- alcalde (no hay elecciones ni urbanismo)
- casamentero exclusivo (el romance es **una** vía, no la métrica de éxito)
- dueño del edificio (aunque gestione llegadas y convivencia)

Sí es:

- cotilla profesional con responsabilidad
- mediadora de vínculos
- quien recibe peticiones (→ buzón)
- quien tiene la **última palabra** en marchas definitivas

### Cambio de fantasía

Antes: «Yo manejo el desastre romántico del pueblo.»

Ahora: «Yo soy Celestine: el pueblo me escribe, yo propongo, ellos viven. A veces se enamoran; a veces solo necesitan un café con alguien.»

El sandbox **no** castiga solteros voluntarios ni amistades sin romance.

### Contradicciones resueltas

| Antes | Ahora |
| --- | --- |
| «Casamentero/cotilla» en `DECISIONES_CERRADAS` | **Celestine** manda; casamentero queda como tono coloquial, no rol exclusivo |
| Grafo = todo el mundo debe poder emparejarse | Recíproco potencial solo donde el diseño **permite** romance; **no buscar pareja ≠ bug** |
| Fama = gran casamentero | Fama = reconocimiento de **Celestine** en la comunidad (vínculos en general) |

---

## 2. No todo el mundo busca pareja

### Regla de diseño

Puede haber personajes con **cero interés activo** en romance. Celestine puede ayudarles con amistad, compañía, conflictos o soledad **sin** empujarles a una cita romántica.

### Campo candidato (no implementar)

`interes_romantico`: `activo` · `pasivo` · `ninguno`

| Valor | Significado |
| --- | --- |
| `activo` | Puede buscar pareja; citas románticas encajan en su arco |
| `pasivo` | No busca, pero **podría** acabar en romance (amistad→atracción, sorpresa) |
| `ninguno` | No quiere relación romántica; no es objetivo del jugador |

### Grafo romántico (ajuste)

`VALIDACION_GRAFO_ROMANTICO.md` sigue exigiendo recíproco potencial para quien **puede** tener romance legal (`activo` / `pasivo`). Quien tiene `ninguno` **no** entra en esa auditoría como «condenado».

El trío del día 1 puede incluir a alguien con `ninguno` para enseñar que **no todo es ligar**.

---

## 3. Buzón de Celestine

### Idea

Bandeja de **mensajes directos** de habitantes → Celestine. Sustituye o complementa parte de lo que hoy haría el diario como aviso personal.

### Ejemplos conceptuales

- «¿Me organizas una cita con…?»
- «Quiero conocer gente nueva.»
- «Estoy harta de Marta.»
- «Me siento muy sola últimamente.»
- «Ayúdame a arreglar las cosas con Joel.»
- «Estoy pensando en irme.» (→ flujo de marcha; **no** se resuelve solo)
- «Anoche pasó una cosa rara en el parque…»

### Regla de verdad

Los mensajes expresan lo que el personaje **piensa, cree o dice**. **No** revelan automáticamente variables internas reales.

- Puede **equivocarse** («creo que le gusto» cuando no).
- Puede **ocultar** («estoy bien» cuando no).
- Puede **interpretar mal** un roce como romance.

Celestine actúa con información **sesgada**, como en la vida real.

### ¿Buzón solo, o diario también?

**Recomendación: ambos.**

| | Diario del pueblo | Buzón de Celestine |
| --- | --- | --- |
| Voz | Narrador / cotilleo del barrio | 1.ª persona del habitante |
| Verdad | Más **objetivo** (hechos observables) | **Subjetivo** (creencias, peticiones) |
| Función | Contexto, humor, rumores, 👀/⚠️ de acontecimiento | **Llamadas a la acción** personales |
| Cupo | 5–10/día (cerrado) | 0–5/día candidato; priorizar lo jugable |
| Duplicados | «👀 Eva y Lucía coincidieron otra vez» | «Lucía: Celestine, creo que me cae bien Eva» |

El diario **no desaparece**. Responde: «¿Qué ha pasado en el pueblo?» El buzón: «¿Quién me necesita a mí?»

### Solapamientos

- **Marcha:** buzón = mensaje personal («pienso irme»); diario = puede haber rumor previo o silencio.
- **Pareja NPC:** diario puede anunciarlo; buzón puede traer reacciones («no me lo esperaba»).
- **Conflicto:** diario = «⚠️ Nora evita a Aina»; buzón = uno de los dos escribe a Celestine.

Evitar que el mismo hecho ocupe diario + buzón + ⚠️ en ficha sin filtro.

### Riesgos del buzón

| Riesgo | Mitigación |
| --- | --- |
| 16 buzones/día = call center | Cupo; agrupar «sin urgencia»; no todo pide respuesta inmediata |
| Mensajes mintiendo = frustración | Es feature; la ficha y el diario dan contraste |
| Sustituye explorar fichas | El buzón **abre** pistas; no sustituye El pueblo |
| Spam «organízame cita» | Cooldown; algunos mensajes son solo ventilar |

---

## 4. Tiempo real (1 día real = 1 día juego)

### Propuesta

Si abres el lunes y vuelves el viernes, han pasado **cuatro días de pueblo** aunque no hayas abierto el juego.

No se simula cada minuto offline. Al volver: **resumen + cola de pendientes**.

### Qué SÍ puede resolverse offline

| Tipo | Ejemplos | Cómo se presenta |
| --- | --- | --- |
| Rutina | Trabajo, sueño, ir al sitio preferente | Comprimido en resumen |
| Encuentros menores | Coincidir, charla, roce | Diario; a veces una línea |
| Citas **ya programadas por Celestine** | La cita del miércoles ocurrió | Informe listo al volver |
| Evolución lenta | Calor de amistad, ánimo | Sin fanfarria |
| Una pareja NPC (cupo) | 0–1 por tramo offline largo | Diario + posible buzón de reacción |
| Goteo económico | Dinero por días transcurridos | Cifra actualizada |
| Llegada programada | Si el sistema ya la tenía en cola | Evento al volver o en resumen |

### Qué NO debe resolverse offline (queda pendiente)

| Tipo | Por qué |
| --- | --- |
| **Marcha definitiva** | Requiere Celestine. Regla cerrada. |
| «Quédate» / «vete» | Decisión del jugador |
| Elección entre dos crisis | No elegir0 por el jugador |
| Desbloqueo caro (cine, B…) | Compra consciente |
| Respuesta a buzón urgente | El mensaje **espera** en bandeja |
| Ruptura / convivencia / mudanza **importante** | Irreversible o muy visible |
| Más de N eventos importantes | Cola; el resto «sigue en el aire» |

### Catch-up al volver (borrador)

1. Pantalla **«Han pasado X días»** con 3–8 bullets (no 40).
2. Diario: entradas destacadas del tramo (recortadas).
3. Buzón: mensajes acumulados **sin resolver** (los urgentes arriba).
4. Cola de **0–2 importantes** que necesitan pulsar algo (marcha, crisis).
5. El resto: «días tranquilos» o rutina comprimida.

### Tensión con catch-up anterior

`PROPUESTA_VIDA_POOL_Y_MARCHAS.md` hablaba de techo **12–24 h** de simulación. Con **1:1**, cuatro días offline son **96 h** de pueblo: hay que **comprimir por días**, no simular franja a franja. El techo pasa a ser **días calendario** + cupo de eventos, no horas de CPU ficticias.

### Riesgos tiempo real

| Riesgo | Nota |
| --- | --- |
| Vuelves tras 3 semanas | ¿30 eventos pendientes? → tope duro + resumen |
| Jugador diario vs jugador mensual | Misma regla 1:1; el mensual recibe digest grande |
| Protección ~30 días marcha | Con 1:1, **30 días = 30 días reales** de convivencia antes de plantear irse — encaja con encariñarse |
| Temporadas | ¿Temporada = mes real? Pendiente |
| Citas programadas mientras AFK | Deben resolverse; si no, frustración |

---

## 5. Protección de residencia (~30 días)

### Hipótesis

Un personaje **no** entra en `pensando_en_irse` «normal» hasta llevar **aproximadamente 30 días** en la comunidad.

- El número **30 no está canonizado.**
- Objetivo: que nadie pida marcharse el día 4 del tutorial; que el jugador tenga tiempo de conocerles.
- **Excepciones** posibles (crisis grave, evento script): estudiar aparte; no invalidar la regla base.

### Con tiempo 1:1

30 días de juego ≈ 30 días reales si juegas a menudo — o menos calendario si entras poco pero el reloj sigue. Hay que decidir si la protección mira **días de pueblo transcurridos** (independiente de sesiones) — recomendado — no «días en los que abriste la app».

---

## 6. Pool y partidas

### Capas

```text
Catálogo global (diseño)     80–100+ fichas en tandas
        ↓
Estado por partida           quién ha vivido, quién archivado, quién disponible
        ↓
Presentes simultáneos        Bloque A ≈ 16 (B ≈ 16 futuro → ~32 techo capacidad)
```

### Archivados

Al marcharse definitivamente:

- `antiguo_residente` / archivado en **esta partida**
- Sigue en el registro (historia, diario pasado, buzón antiguo)
- **Excluido** del sorteo de llegadas nuevas en **esta** partida
- Flag opcional `puede_regresar` para evento con nombre (no slot aleatorio)

### Nueva partida / reiniciar

| Acción | Pool catálogo | Estado partida |
| --- | --- | --- |
| Partida persistente | Igual | Sigue |
| Nueva partida | Todo el catálogo otra vez | Reset: nadie archivado, trío/llegadas pueden variar |
| Reiniciar (misma ranura) | Igual | Como nueva partida en esa ranura |

**Rejugabilidad (Neni):** semilla o reglas de selección del trío inicial y orden de llegadas deben permitir **distintas** primeras semanas.

### Arquitectura (no implementar)

- `partida_id`
- `catalogo_version`
- por ficha: `estado_en_partida` = `no_presentado` · `residente` · `archivado` · …
- Bloque B: flag `bloque_b_abierto` por partida (false en primera prueba)

---

## 7. Bloque A — primera prueba (detalle)

| Pieza | Alcance |
| --- | --- |
| Edificio | Solo **A** en mapa y UI |
| Población | ~3 → … → ~16 |
| Lugar social | **Cafetería** primero (sitio estrella). Parque/biblioteca: catálogo cerrado, **desbloqueo progresivo** en onboarding (no día 1). |
| Bloque B | No dibujar. Datos/modelo preparados para `bloque_id` futuro. |
| Buzón | Candidato; puede ser papel-only al principio |
| Tiempo 1:1 | Candidato serio; simulador debe probarlo |

---

## 8. Atractivo visual (catálogo futuro)

«Personas normales» **no** significa hacer a todos poco atractivos a propósito.

El catálogo 80–100 debe incluir:

- personas **muy atractivas**, normales, peculiares
- mayores, jóvenes adultas, distintos cuerpos y estructuras faciales
- siempre dentro del contrato visual (caricatura editorial, no realismo)
- **sin** la misma cara base (regla candidata de diferenciación estructural)

**Prohibido:** personas reales como personajes.

Ver `CONTRATO_VISUAL_PERSONAJES.md` § Variedad de atractivo.

---

## 9. Contradicciones detectadas (y cómo quedan)

| Doc / idea | Conflicto | Resolución |
| --- | --- | --- |
| `DECISIONES`: 3 lugares abiertos día 1 | Primera prueba = solo café jugable | Catálogo sigue con 3 iniciales; **primera prueba** enseña café y abre el resto con progresión |
| `PROPUESTA_BLOQUE_3_A_24`: sitio estrella vs 3 abiertos | Misma tensión | **Cerrado** para primera prueba: café primero |
| Catch-up 12–24 h | Tiempo 1:1 = días calendario | Sustituido por compresión por **días** + cola |
| Grafo: todos con recíproco | Alguien no quiere romance | Recíproco solo si `interes_romantico` ≠ `ninguno` (candidato) |
| Diario como única voz | Buzón | Diario se mantiene; buzón complementa |
| «Casamentero» en todos los docs | Celestine | Actualizar; casamentero = tono, no rol |
| `FASE_DISENO_POOL_16`: 10 iniciales | Día 1 ~3 | Esqueleto 16 = tanda; partida empieza con 3 |
| `VALIDACION_GRAFO`: 10 iniciales se bastan | Trío 3 sin recíproco | Regla de tanda/coexistencia, no del trío |
| Pool 60–100 | Usuario dice 80–100+ | Actualizar cifra orientativa |
| 32 = total personajes | Solo capacidad A+B | Aclarado en pool |

---

## 10. Problemas de diseño (nuevos)

1. **Dos bandejas de entrada** (diario + buzón): riesgo de redundancia o ignorar una.
2. **Buzón mentiroso:** jugador puede sentirse engañado si no hay contraste en ficha/diario.
3. **Tiempo 1:1 + jugador ausente meses:** digest imposible sin tope brutal.
4. **Tiempo 1:1 + protección ~30 días:** quien juega 1 h/semana tarda meses reales en ver marchas — ¿ok?
5. **Rejugabilidad vs grafo fijo:** mismas 16 fichas, distinto trío — hace falta pool de «candidatos a inicial» > 3.
6. **Archivados 80–100:** partida larga puede agotar catálogo sin B — B es válvula de capacidad, no de catálogo nuevo.
7. **Celestine sin romance:** UI y tutoriales no pueden enseñar solo «empareja».
8. **Mensajes offline:** ¿se acumulan en buzón o se congelan? Recomendación: **se escriben** con fecha; no se resuelven solos.
9. **Cita programada + 5 días AFK:** ¿5 informes o uno resumen? Pendiente.
10. **Fama/dinero offline 1:1:** ¿goteo por días enteros? Probablemente sí, con techo.

---

## 11. Qué decidir AHORA vs qué puede esperar

### Ahora (bloquea diseño fino o la primera prueba en papel)

1. **Celestine** como nombre/rol del jugador en docs y UI — **cerrado**.
2. **Primera prueba = solo Bloque A**, B arquitectura — **cerrado**.
3. **Cafetería primero** en onboarding — **cerrado** (parque/biblioteca después).
4. **Sin pareja voluntaria ≠ fallo** — **cerrado**; falta campo `interes_romantico` en contrato cuando toque fichas.
5. **Diario + buzón** (ambos) — **candidato fuerte**; confirmar con Neni si el buzón entra en la **primera** prueba o en la segunda.
6. **Archivados no reaparecen** en la misma partida — **cerrado**.
7. **Nueva partida rejugable** — **cerrado** como requisito; falta definir *cómo* (semilla, pools de inicio).

### Puede esperar (simulador, wireframes, implementación)

1. Cifra exacta protección residencia (30 u otra).
2. Cupo exacto del buzón (0–5/día).
3. Tiempo 1:1 — confirmar en simulador antes de canonizar; afecta temporadas, economía, marchas.
4. Qué % de mensajes son «mentira» / sesgo.
5. Wireframe del buzón vs diario en móvil.
6. Bloque B: precio, hito, UI.
7. Catálogo 80–100: producción por tandas después de jugar tanda 1.
8. Excepciones a protección de residencia.
9. Citas múltiples acumuladas tras AFK largo.
10. Nombre visible: ¿«Celestine» en UI o solo rol interno?

---

## 12. Preguntas para Neni y ChatGPT

1. ¿**Celestine** es el nombre visible del jugador en UI, o solo concepto de diseño?
2. ¿El **buzón** entra en la primera prueba jugable o solo diario + ficha?
3. ¿Confirmáis **cafetería sola** al inicio (parque/biblioteca después)?
4. ¿**Tiempo 1:1** os encaja para el tono del juego, o preferís acelerar días inactive?
5. ¿Protección ~**30 días** antes de marcha os suena bien con 1:1?
6. ¿Cuántos personajes del catálogo pueden ser **`interes_romantico: ninguno`** en una tanda de 16?
7. ¿Nueva partida: mismas 16 fichas siempre, o subconjuntos distintos por semilla?

---

## Relación con otros docs

- Rol y bucle: `VISION_JUEGO.md`
- Diario: `DIARIO_DEL_PUEBLO.md`
- Buzón (detalle UI cuando toque): pendiente wireframe
- Pool, marchas, bloques: `PROPUESTA_VIDA_POOL_Y_MARCHAS.md`
- Onboarding: `PROPUESTA_BLOQUE_3_A_24.md`
- Grafo: `VALIDACION_GRAFO_ROMANTICO.md`
- Partidas (datos): `MODELO_DATOS_INICIAL.md`
- Visual: `CONTRATO_VISUAL_PERSONAJES.md`
