# Propuesta: vida autónoma, pool y marchas

**Diseño. No es código.** Amplía `PROPUESTA_BLOQUE_3_A_24.md`. No toca Rocío ni el estilo 06.

Ronda (2026-08-17, noche-2). Separa **siempre**:

```text
capacidad simultánea  ≠  pool total diseñado
cuántos viven ahora      cuántas fichas existen en el juego
```

La primera tanda de fichas sigue siendo **16**. No se diseñan 80–100 ahora. Catálogo a largo plazo: **80–100+** en tandas.

---

## Qué compro tal cual

| Idea | Por qué |
| --- | --- |
| Marchas con aviso; **la última palabra es del jugador** | Encariñarse semanas sin miedo a un dado |
| «Quédate» **siempre** conserva; la causa se vuelve arco/problema | No es magia vacía: genera juego |
| Antiguo residente **no se borra** ni se recicla | «¡Ha vuelto X!» es acontecimiento |
| Pool grande futuro; tanda 16 ahora | Auditoría y arte no explotan |
| Capacidad del edificio **aparte** del catálogo | 16 fichas no obligan a 16 en casa el día 1, ni a 16 para siempre |
| Los NPC viven solos; **puede nacer una pareja sin el jugador** | Si no, 16 personas son un call center |
| Simulación al volver, **con freno** en lo irreversible | El mundo no se congela ni se incendia en una semana AFK |
| El trabajo da un goteo al bloque | 3 vecinos = poco dinero; más gente = más margen. Sin cifras |
| Franjas reales, no agenda Sims | «Uno trabaja» es jugable y legible |
| Test **comprable**, no día 1 | Cierra la duda de la ronda anterior |
| Planes especiales caros = modificador, no compra de amor | Igual que preparación de citas |
| Grafo social **solo de lo descubierto** | Escala cuando hay muchos |
| Convivencia / aptos, sin interiores | Identidad + capacidad + eventos |
| Acciones en **escalas** distintas | Pueblo vivo ≠ diario de cafés |
| Equilibrio: ellos viven, yo meto mano | Principio central. Ya estaba en la visión; ahora es regla |

---

## Qué modificaría

1. **Primera capacidad ≈ un bloque de ~16, no un edificio de 24.** Ampliar = **abrir Bloque B** (~16 más), no hinchar el mismo mapa. Ver § Varios bloques. 24-en-uno queda peor para móvil.
2. **Marchas: el jugador autoriza la marcha definitiva.** «Quédate» no tira un dado. La causa sigue (necesidad / misión / crisis). Arquitectura ya; en la primera prueba, después de las llegadas.
3. **Tras «quédate», un arco reutiliza sistemas que ya existen** (encuentro, amistad, ánimo, ⚠️). Tope de arcos abiertos o se ignoran.
4. **Grafo recíproco por tanda y por coexistencia**, no sobre 100 fichas a la vez.
5. **Ingresos por trabajo con rendimientos decrecientes**, no `€ × n_residentes`.
6. **Aptos = unidades del bloque**, distintos de `lug_casa`. **Asignación automática**; Celestine no elige.
7. **Parejas NPC offline: sí, con cupo.** 0–1 por ausencia larga, no 8.
8. **Tienda de herramientas ≠ tienda de ropa.** El test puede ser la primera herramienta cara.

---

## Riesgos

| Riesgo | Si no se corta |
| --- | --- |
| Todos «piensan irse» a la vez | El jugador es conserje de permisos |
| El jugador **siempre** pulsa quédate | A se llena; sin B no hay hueco ni drama de adiós |
| Arcos de «me quedé pero sigo mal» se acumulan | ⚠️ eternos; se ignoran |
| Offline resuelve una marcha | Rompe la regla: **nunca** se va sin el jugador |
| Offline sin tope | Semana AFK = otro pueblo |
| Offline demasiado tímido | El mundo está congelado |
| € lineal con población | El dinero sobra; los desbloqueos sobran |
| Grafo de 16 en móvil | Pelo de relaciones, ilegible |
| 100 fichas × recíproco global | Auditoría imposible y grafo mentiroso (no coexisten) |
| Aptos con inventario | Los Sims por la puerta de atrás |
| NPC ligan tanto que el jugador sobra | Fantasía rota |
| El jugador tiene que ligar a todos | Call center; ya lo temíamos |

---

## Propuesta concreta (aún no canon de cifras)

### Capacidad vs pool

| Capa | Hipótesis | Qué no es |
| --- | --- | --- |
| Día 1 | **3** residentes | El catálogo |
| Capacidad inicial | **Bloque A ≈ 16** aptos/residentes | El pool de fichas |
| Ampliar | **Bloque B ≈ 16** (comprar/rehabilitar) | Hinchar el mismo edificio a 24 |
| Tanda 1 de diseño | **16** fichas | Gente en pantalla el día 1 |
| Pool a años vista | **80–100+** en tandas | Hacerlas esta semana |
| Capacidad simultánea futura | ~32 (A+B) | Confundir con total de fichas |

Un solo edificio de 24 **no** es la ampliación recomendada. Ver § Varios bloques.

Llegadas mientras haya **hueco en algún bloque abierto**. Si A está lleno y B no existe: cola, convivir, o una marcha **consentida**. Como las marchas ya no liberan hueco solas, **B es la válvula de crecimiento**.

### Estados de presencia (modelo)

```text
nunca_ha_vivido
residente
nuevo                  (halo; caduca)
pensando_en_irse       (el jugador puede intervenir)
ausente_temporal
antiguo_residente      (archivo; no entra en el sorteo normal de llegadas)
puede_regresar         (flag sobre antiguo)
```

Un regreso es evento con nombre, no un slot vacío que se rellena al azar.

**En esta partida:** un `antiguo_residente` **no** vuelve como llegada aleatoria. Nueva partida = catálogo disponible otra vez. `PROPUESTA_CELESTINE_BUZON_TIEMPO_PARTIDAS.md`.

### Marchas — última palabra del jugador

Un residente puede **querer** irse. **No se va** de la partida sin el jugador.

1. Causa visible (soledad, ruptura, aburrimiento, trabajo, conflicto, ganas de cambio…).
2. Fase: `pensando_en_irse` (días, no minutos). Espera al jugador. **Offline no lo resuelve.**
3. Dos botones, no una tirada:
   - **Dejar que se vaya** → marcha definitiva, archivo de antiguo residente.
   - **Pedirle que se quede** → **se queda**. Siempre.
4. Si se queda, la causa **no se borra**: pasa a necesidad / problema / arco / misión / crisis / objetivo opcional. Genera juego. No es un «ok» vacío.
5. Cooldown para no spamear «me quiero ir» cada dos días.

Encariñarse semanas o meses **sin miedo a un dado**. Una marcha definitiva es **siempre consentida**.

«Quédate» no arregla la vida. Arregla la ficha de presencia.

### Vida autónoma — cuatro relojes

| Escala | Ejemplos | Diario |
| --- | --- | --- |
| Rutinaria | Trabajo, sueño, café solo | Casi nunca |
| Encuentro | Coincidir, hablar, roce | A veces ruido; 👀 si racha |
| Social | Amistad nueva, cita espontánea, discusión | Sí, recortado |
| Importante | Pareja nueva, ruptura, pensando en irse, vuelta | ⚠️ o acontecimiento; a menudo **espera** al jugador |

Frecuencia: el pueblo se mueve cada franja; lo *importante* es raro. A más residentes, más rutina de fondo, **mismo** cupo de diario.

**Una pareja puede formarse sin el jugador.** Celestine se entera por diario / buzón (candidato) / rótulo / ficha. Puede intervenir *después* (celebrar, meter cizaña, ignorar).

### Tiempo y offline / catch-up

**Candidato serio:** **1 día real = 1 día de juego.** Análisis completo: `PROPUESTA_CELESTINE_BUZON_TIEMPO_PARTIDAS.md`.

Al volver tras X días sin abrir:

1. Resolver lo **ya citado por Celestine** (informe listo).
2. **Comprimir** rutina y encuentros menores por **días calendario** (no simular minuto a minuto).
3. Cola de **importantes**: máximo 0–2 por sesión de vuelta. El resto espera o queda en resumen.
4. **Marchas, «quédate», compras caras, respuestas a buzón urgente:** **pendientes** — nunca resueltas offline.
5. Nunca: 8 divorcios + 5 mudanzas en una semana AFK.

El modelo anterior (techo 12–24 h de simulación) queda **obsoleto** si se confirma tiempo 1:1.

### Protección de residencia (hipótesis)

Marcha «normal» (`pensando_en_irse`) no antes de **~30 días** en la comunidad. Cifra **no** canonizada. Con tiempo 1:1 = días de pueblo transcurridos. Excepciones por crisis: estudiar aparte.

### Economía

Origen principal propuesto: **goteo por actividad laboral de residentes presentes** (no por fichas en el disco). 3 personas = poco. Más gente = más, con **techo y curva cóncava**.

El oficio manda **cuándo** está ocupado más que cuánto paga. Jubilado ≠ autónomo en franja, no en una tabla Excel de sueldos.

Sumideros: lugares, **herramientas**, planes especiales. Si solo hay desbloqueo de cine, el dinero se estanca o sobra. Fama sigue aparte (reconocimiento de cotilla, no nómina).

### Horarios

**Obsoleto como diseño final:** «Una franja de trabajo + una de libre + sueño».

**Sustituido por:** agenda por persona (`PROPUESTA_AGENDA_DISPONIBILIDAD_RUTINAS.md`): bloques estructurales, citas que ocupan tiempo, UI que sugiere horas compatibles. Celestine no edita rutinas a mano; propone planes.

### Herramientas (tienda social)

Candidatos: test de compatibilidad, mejoras del test, mensajería, un tipo de plan, un informe extra. **El test no está el día 1.** Usos limitados, coste, info imperfecta. Monetización exacta: no.

### Planes especiales

Viaje / escapada = contexto caro. Empuja probabilidades. Puede salir fatal. Cuanto mayor el empujón potencial, mayor coste, requisito (vínculo mínimo, sitio, fama) y riesgo visible. `PREPARACION_DE_CITAS.md`.

### Grafo social visible

Por habitante, **1 salto** de lazos **conocidos**: pareja, crush descubierto, amigo, amigo fuerte, familia pública, roce visto, conflicto visto, ex conocido, convive.

Móvil: lista de cabezas + etiqueta, no un ovillo. PC: opcionalmente el mismo vecindario dibujado. Nunca el grafo completo del bloque como pantalla principal.

### Aptos y convivencia

**Asignación automática. Celestine no elige piso ni bloque.**

```text
A01 → A02 → … → A16
si A está lleno y B está abierto:
B01 → B02 → … → B16
```

Cada llegada ocupa el **primer hueco libre**. Si alguien se marcha o dos personas pasan a convivir, ese hueco **vuelve** a estar disponible (la siguiente llegada lo llena; no hay cola de «me mudo al A03 porque mola»).

Sirve para: dónde vive, localizarlo, pintar el bloque, capacidad, convivencia futura, liberar plazas.

**No** modifica: personalidad, compatibilidad, atracción, relaciones, estatus, probabilidad romántica.

No hay preferencia de portal, ni optimización de apartamentos, ni mecánica inmobiliaria.

Convivir: dos residentes, un apto, **siguen contando como dos** en el padrón. Liberan el otro apto. Qué apto conservan: **automático** (hipótesis: el de id más bajo, A antes que B). No es una elección de Celestine. Pueden volver a apartamentos distintos (otra vez: primer hueco libre × 2).

`lug_casa` del catálogo = sitio de encuentro íntimo / «quedar en casa», no el plano del bloque. «Quedarse en casa» cuando un lugar está lleno = su **apartamento**, no `lug_casa`.

Detalle: `MAPA_Y_LUGARES.md`.

---

## Varios bloques (candidato: mejor que un 24 único)

**No implementar.** Hipótesis frente a «un edificio de 24».

```text
Bloque A  ≈ 16 plazas     (la primera prueba)
Bloque B  ≈ 16 plazas     (comprar / rehabilitar / abrir)
Bloque C…                 (no cifrar cuántos ni el precio)
```

Lugares sociales (cafetería, biblioteca, cine, bingo…) **compartidos**. El vecindario es uno; los edificios, varios.

### Por qué encaja ahora

Con marchas **consentidas**, A no se vacía solo. Si el jugador se encariña, **16 se llenan y se quedan**. Sin una segunda casa, se acaba el acontecimiento de llegar. B es la recompensa de progresión y la válvula, sin pintar 24 cabezas en el mismo tablero.

### 16+16 vs un 24

| | Un bloque de 24 | A ≈16, luego B ≈16 |
| --- | --- | --- |
| Mapa / móvil | 24 chinchetas en un edificio | Cada vista ~16; pestaña o icono de bloque |
| Progresión | Reforma invisible («cabe más») | **Acontecimiento:** se abre B |
| Encariñarse | Igual de seguro con marchas consentidas | Igual + sitio nuevo para gente nueva |
| Sitios sociales | 24 en la cafetería | **También 24–32 en la cafetería** (el cuello no es el edificio, es el café) |
| Primera prueba | O 16 o 24 de golpe | Solo A. B ni se dibuja |

**Recomendación:** varios bloques de ~16 **gana** a un único 24. La primera prueba es **solo A**. No hace falta 24-en-uno.

### Consecuencias (las que pedíais)

- Residentes A y B se relacionan en sitios **compartidos**.
- Parejas y amistades **entre bloques**: sí. Viven donde el **hueco automático** les tocó (`bloque_id` + `apartamento_id`). El portal **no** sesga el romance.
- Mudanza A↔B **no** es una acción de Celestine. Solo: llegada al primer hueco, marcha (libera), convivencia (conservan un apto, liberan el otro).
- Capacidad **independiente** por bloque (llena A, B vacío hasta que A no tenga huecos).
- UI: **ver** un bloque (icono de edificio), no asignar vivienda.
- Rótulo: uno **por bloque** + uno **de comunidad**. Prioridad al de comunidad y al del bloque **que estás viendo**.
- Eventos entre bloques: fiesta de inauguración de B, roce «los del otro portal» (en **sitios sociales**, no porque compartan rellano).

### Problemas **nuevos** que introduce

1. **Dos mapas o uno.** Si B es un segundo tablero completo, móvil duele. Mejor: el mapa de la villa tiene **dos edificios-icono**; al pulsar, ves ese bloque (aptos + quién está en casa). Los sitios sociales siguen en el mapa único de siempre.
2. **El pueblo con 32 tarjetas.** Al abrir B, la lista global explota. Filtro obligatorio: este bloque / todos / en lío. Si no, B mata la UI que A había salvado.
3. **La cafetería no sabe de portales.** 32 personas pueden *querer* el mismo sitio. El **aforo** (visual + jugable) corta el hormiguero: no caben 32 cabezas. Quien no entra elige otro sitio, otra actividad, casa o posponer. Varios bloques **no** resuelven solos el café; el aforo sí. Coincidir ≠ interactuar (`MAPA_Y_LUGARES.md`).
4. **«Quédate» + A lleno + B cerrado** = no caben llegadas. Hay que enseñar «no hay piso» / incentivar abrir B, no presionar para que echen a alguien.
5. **«Bloque basurero» (obsoleto como riesgo de Celestine).** Antes: el jugador aparcaba favoritos en A. **Ya no puede:** la vivienda es automática. B no es un almacén que Celestine rellene. Sigue siendo posible que B «se sienta» más nuevo (las llegadas van allí cuando A está lleno); eso es ritmo, no gestión.
6. **Rótulos triples.** Comunidad + A + B. Si los tres cambian a diario, ruido. Inercia y mostrar uno.
7. **Tope de encuentros del jugador** con 32 almas: sigue 1–2/día. Aún más «yo ignoro». Correcto si los NPC se bastan **entre bloques** en sitios compartidos.
8. **Goteo × 32.** B debe costar lo bastante (dinero/fama) para ser sumidero. El goteo cóncavo se calcula sobre **residentes totales presentes**, no por bloque por separado (o A y B se desequilibran).
9. **Catch-up × 2 edificios.** Misma cola de importantes para toda la comunidad, no una por bloque (si no, al volver hay 4 crisis).
10. **Inaugurar B demasiado pronto** (aún no sabes A) o demasiado tarde (A lleno y aburrido). Hito: A razonablemente vivo + recursos; no el día 5.
11. **Convivencia cruzada.** Dos de bloques distintos que se juntan: **automático** (hipótesis: conservan el apto de id más bajo). El otro bloque gana un hueco. Celestine no elige.
12. **Identidad de «pueblo».** Ya vacilábamos villa/bloque. Con dos bloques, el nombre paraguas (Aquí Hay Tema / el barrio) tiene que ser claro o parece un tycoon inmobiliario. La vivienda invisible ayuda: no es un juego de portales.
13. **Mapa de referencia PNG.** Sigue siendo un pueblo, no un rascacielos. B es otro portal en la misma calle, no un DLC de ciudad.

### Qué no es

No es un segundo juego. No es simular dos interiores. No hace falta decidir Bloque C ahora.

---

## Los 12 puntos

### 1. ¿16 o 24 simultáneos?

**Un bloque de ~16** para la primera prueba. Ampliar **no** es pintarlo a 24: es **abrir Bloque B** (~16). Un único edificio de 24 pierde en móvil y no da el beat de «rehabilitamos el portal de al lado».

### 2. Escalar a 80–100+ fichas

Tandas de ~12–16 con el **mismo contrato y checklist**. Cada tanda:

- se audita **sola** (diversidad, voces, no clones)
- declara lazos a fichas ya existentes (ids, no nombres inventados)
- cumple recíproco potencial **entre gente que puede coincidir en el bloque** (capacidad + reglas de llegada), no un grafo completo 100×100
- no se genera arte masivo hasta que la tanda anterior se ha jugado / simulado

`per_` ya admite más de 16. `empieza_en_pueblo` / `orden_llegada` se vuelven «oleada / tanda», no un 1–6 eterno.

### 3. Frecuencia autónoma

Rutina cada franja. Encuentros cuando coinciden agendas. Social: pocos por día en todo el bloque (escalan poco con n). Importante: excepcional. El diario recorta.

### 4. Offline

Catch-up con techo temporal + cola de importantes. Citas del jugador se resuelven. Lo cotidiano se resume. Lo irreversible no se aplasta.

### 5. Qué espera al jugador

- Cita que **él** programó: resuelta, pendiente de **leer**
- Pensando en irse: **solo el jugador** cierra (irse o quedarse). Offline **nunca** lo resuelve.
- Vuelta de un antiguo
- Crisis ⚠️ de una relación que el jugador ha tocado
- Desbloqueos / compras
- Primer «somos pareja» NPC: puede resolverse, pero se **presenta** al volver (no 8 a la vez)

No espera: cada café, cada turno de trabajo.

### 6. Marchas

Última palabra del jugador. «Quédate» = se queda + arco. «Deja que se vaya» = archivo. Sin tirada de expulsión.

### 7. Economía

Goteo cóncavo por residentes *presentes* y activos. Oficios = horario. Sumideros: herramientas, planes, **abrir Bloque B**. Sin cifras.

### 8. Aptos

Etiqueta + id. Convivencia libera un piso. Cero interiores Sims. Distinto de `lug_casa`.

### 9. Grafo en móvil/PC

Vista **egocéntrica** de lo descubierto. Lista en móvil. Grafo pequeño en PC. Filtros en El pueblo.

### 10. Herramientas

Tienda social. Test = desbloqueo. Usos, coste, mentira útil.

### 11. Planes especiales

Modificador caro. Puede fallar. Requisitos suben con el poder.

### 12. Contradicciones con docs existentes

| Antes | Esta ronda | Cómo se lee ahora |
| --- | --- | --- |
| Marchas V0: no / retención con dado | Marchas **consentidas**; «quédate» siempre funciona | La causa vira a arco |
| Techo ~24 en un edificio | Bloque A ~16; ampliar = Bloque B | 24-en-uno, no recomendado |
| Pool 16 = contenido del producto | 16 = **tanda 1**; pool futuro **80–100+** | Arquitectura amplia; no producir 100 |
| Recíproco en los 16 (y se temía en 24) | Recíproco por coexistencia / tanda | Obligatorio en tanda; no en 100 a la vez |
| Test día 1 o 2 como juguete | Test **comprable**, no día 1 | Gana esta ronda |
| «Prohibido simulación laboral compleja» | Trabajo + dinero + horarios | Sigue prohibido el Excel laboral; **sí** franja de trabajo y goteo tonto |
| `lug_casa` no se compra | Aptos en el bloque, **asignación automática** | Dos capas: vivienda vs sitio de quedar. Celestine no es inmobiliaria |
| Celestine elige A o B | Primer hueco libre | «Bloque basurero» ya no aplica |
| Fama suelta habitantes | Llegadas con ritmo | No usar fama como «comprar vecino» el día 1; puede empujar oleadas *después* |
| Solo llegadas → se sella el bloque | Consentir marchas **o** abrir B **o** convivir | B es la válvula si nadie se va |
| El jugador propone | NPC también emparejan | Compatible: ellos viven; tú no eres su pulmón |
| 2 citas/día del jugador | Citas NPC autónomas | Las autónomas **no** gastan el tope del jugador |
| Tienda de ropa fuera | Tienda de **herramientas** | No es la misma tienda |
| Diario 5–10 | Sigue, con filtro más duro si hay autonomía | El ruido rutinario no entra |

---

## Decisiones que hace falta cerrar

1. ¿Primera prueba = **solo Bloque A ~16**? (Cursor: sí.)
2. ¿Ampliar = **Bloque B ~16**, no un 24 único? (Cursor: sí, candidato.)
3. ¿Marchas en la primera prueba o solo el modelo? (La regla de consentimiento ya está.)
4. ¿El goteo económico es el origen **principal** del dinero?
5. ¿Convivir libera un apto? (**Sí.** Conservan uno en automático.)
6. ¿El test se compra con dinero, fama, o hito?
7. ¿Rótulo de comunidad + el del bloque que miras, o solo comunidad?
8. ¿Tope de catch-up con tiempo 1:1 (días, no 12–24 h)?
9. ¿Nombre: bloque, portal, edificio?

No hace falta cerrar: cifras de €, tamaño final del pool, textos del test, interiores.

---

## Relación con fichas y arte

No se reescribe el esqueleto I01–L06. No se piden 84 fichas. Estados de presencia = runtime / modelo, no prosa de ChatGPT en cada piloto. Visual: sin cambios en esta ronda.

Ver: `VIDA_DE_HABITANTES.md`, `DINERO_Y_FAMA.md`, `ENCUENTROS_ESPONTANEOS.md`, `RELACIONES_SOCIALES.md`, `PREPARACION_DE_CITAS.md`, `FASE_DISENO_POOL_16.md`.
