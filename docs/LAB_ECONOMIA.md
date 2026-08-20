# Lab de economía — Aquí Hay Tema

**Estado:** ANÁLISIS + PROPUESTA + LAB. **No canon.** No implementado en PLAY. `economy_enabled` sigue OFF. EconomyLedger sigue siendo placeholder.

**Motor:** `src/Engine/SimuladorEconomia.php`
**CLI:** `php dev/simulador_economia.php 8 lab-economia`
**Semillas de este informe:** 8 × seed `lab-economia` × horizontes 30 / 100 / 365.

Copia canónica de lectura: `docs/LAB_ECONOMIA.md` (este archivo se copia allí).

La unidad **U** es abstracta. No es un euro. En el modelo media, **1 misión diaria cumplida = 8 U**. Si más adelante se decide que una misión “se siente” como 10 €, se multiplica toda la escala; las *proporciones* son lo que este lab defiende.

**Cierre de entrega (20/08):** la referencia adelantada (jugadora normal → Bloque B ~día 50–52; farmer no salta el gate temporal) **cuadra con el lab**. Sigue **sin canonizar**. Este archivo es la lectura completa pedida. Mapa:

| Qué pedía Producto | Dónde está |
|---|---|
| Fuentes de ingreso | §2 |
| Precios | §3 |
| Edificios / ampliaciones | §3 y §5 (fase1 / amp) |
| Bloque B | §5 día 30/100 + §6 + §7 |
| Bloque C | §5 día 365 + §6 + §7 |
| Encargos extra | §2.2 + farmer en §5 |
| Acumulación de dinero | §5 día 365 + §7 “infinito” |
| Grind | §7 |
| Farmer | perfiles F en §4–§6 |
| Jugador normal/activo | perfiles B y A |
| Horizontes 30 / 100 / 365 | §5 |

`PARA` economía: sí, con este informe. Cifras **no** a `data/` ni a EconomyLedger. `economy_enabled` OFF.

---

## 1. Qué está cerrado y qué es hipótesis

### Cerrado (Documento Maestro 19–20/08/2026 + este encargo)

- **DINERO = POSIBILIDADES.** No supervivencia. No tycoon.
- La jugadora **no paga** facturas, luz, agua, nóminas, alquiler, mantenimiento ni gastos periódicos obligatorios.
- Las **citas/encuentros normales no dan dinero** por celebrarse. Recompensa = consecuencias sociales/narrativas.
- **Cita premium romántica: descartada.** El dinero compra oportunidad, no resultado.
- **Fuentes previstas (tipo cerrado, cifras no):**
  1. renta/ingreso periódico ligado al **estado general del pueblo** (bandas de Vida);
  2. recompensa económica de las **3 misiones diarias**;
  3. **encargos extra** opcionales, que **no** alimentan Vida ni Latidos.
- **Bingo:** el premio pertenece al residente/evento. No es impresora de Celestine.
- **B/C** se compran enteros (+16 plazas). Exigen **tiempo + ocupación + dinero**. El dinero solo no basta.
- Capacidad: A 16 + B 16 + C 16 = **48**. Tutorial → 8/16 en A.
- Llegadas por **huecos**. Comprar B/C reabre presión de candidatos. Vacío **no** daña Vida.
- **6 complejos** (20/08), no 14 edificios sueltos. Ampliaciones = contenido real (planes, eventos, contextos), no “nivel 2 = +X%”.
- Día 1 de primera prueba: **solo cafetería** operativa. Economía jugable **apagada**.

### Hipótesis de este lab (`HIPOTESIS_DE_LAB`)

| Tema | Hipótesis usada | No es |
|---|---|---|
| Gates B | día ≥ **50** AND residentes ≥ **12/16** AND dinero ≥ precio_B | cifra canónica |
| Gates C | día ≥ **160** AND residentes ≥ **26** (≈10 plazas de B) AND dinero ≥ precio_C | cifra canónica |
| Renta | diaria, también si Celestine es pasiva (el reloj del pueblo sigue) | cerrado si es offline |
| Encargos | cola máx. 2/día; **1** si hay <3 complejos fase 1, **2** si ≥3. Tope **independiente** de cuántos edificios hay después | catálogo de encargos |
| Latido | **no paga dinero** en la simulación principal | el Maestro no lo lista como fuente |
| Parque fase 1 | **se compra**, como el resto de complejos | decisión de mapa |
| Plaza / Casa | siempre disponibles, sin coste | no están en los 6 complejos |
| Mirador | dinero + hito “primera cita positiva” (día ~10–40 según perfil; no se simula el relacional V1) | |
| Experiencias | oportunidad ~cada 12 d (escapada), ~70 d (viaje), ~8 d (actividad); regalos ≈ n/365 | catálogo cerrado |
| Comportamiento A/B/D/F | reservan el precio de B/C al acercarse al gate (la UI lo anunciaría) | C y E no planifican |
| Bandas de Vida | sintéticas por perfil, **no** el ledger B1 real | no se retoca B1 |

**No se ha tocado:** B1 Vida, B3 (recompensa de Vida), E3 peticiones, relacional V1, B5, UI de Carlos II, assets de Carlos III.

---

## 2. Fuentes analizadas

### 2.1 Misiones diarias (B3 existente + dinero hipotético)

B3 sigue igual: máx. 3/día, Vida ±, 1 de 3 cuenta para Latido. Este lab **añade dinero encima** sin recalibrar Vida.

`p_cumplir` reutiliza el lab B3 (A 1.00 / B 0.55 / C 0.22) y se adapta a D/E/F.

En el modelo **media** (M = 8 U):

| Perfil | Misiones esperadas/día | U/día por misiones |
|---|---:|---:|
| A excelente | 3.00 | 24 |
| B normal | 1.65 | 13 |
| C casual | 0.66 | 5 |
| D ahorradora | 1.65 | 13 |
| E gastadora | 2.10 | 17 |
| F farmer | 3.00 | 24 |

Caducada = 0 U. No hay incentivo a dejar misiones pudriéndose.

### 2.2 Encargos extra (tipo cerrado, números de lab)

Función: jugar más rato a cambio de dinero **sin** imprimir Latidos.

Hipótesis anti-exploit:

- Tope diario 1→2 al tener 3 complejos. **No** sigue subiendo. Comprar el 4.º local no imprime más U/día.
- Variedad sí (más sitios = más tipos de encargo), volumen no.
- Condiciones reales del pueblo, no “repite la misma cita 17 veces”.

Eso permite que F acelere **lugares y ampliaciones**, no A→B→C.

### 2.3 Vida del Pueblo / Latidos

- **Renta por banda de Vida:** fuente **cerrada como tipo**. En lab: alta/media/baja según perfil sintético. Es el suelo: una racha mala **ralentiza** posibilidades, no apaga el pueblo.
- **Latido:** ~cada 22–28 días en perfiles que cumplen 1 positivo válido/día (B1 no se toca). **No entra como ingreso** en este lab. Propuesta aparte, más abajo.

### 2.4 Otras fuentes realmente cerradas

Ninguna más en el Maestro 19/08. El **goteo laboral por residente** de `docs/DINERO_Y_FAMA.md` es **histórico**. Choca con “no salarios / no tycoon” y **no** está en el bloque económico del Maestro. Este lab **no lo usa**.

### 2.5 Propuestas (NO aprobadas)

1. **Bono de Latido** (hito raro, ~2–3 días de ingreso normal). Narrativo, no granja: B1 ya impide farmear Latidos.
2. **Ropa / looks** como sumidero opcional (aparecía en docs viejos; el bloque 19/08 no lo lista).
3. **Deseos que escalan con n** (más residentes → más peticiones de experiencias). Sumidero de final de partida.
4. **Fiesta / temporada** que Celestine puede financiar (oportunidad, no factura).
5. Goteo laboral proporcional a n — **descartado como candidato de trabajo.**
6. Premio de bingo a Celestine — **cerrado que no.**
7. Pagar la cita para mejor romance — **cerrado que no.**

---

## 3. Gastos (rangos, no precios por edificio)

Mismos precios en los **tres** modelos. Lo que cambia es el ingreso. Así se compara “cuántos días de juego cuesta esto”.

| Categoría | Precio (U) | En días de jugadora **normal** (media, ~24 U/día) | Qué compra |
|---|---:|---:|---|
| Lugar fase 1 (complejo nuevo) | 160 | ~7 d | contenido: planes, eventos, contextos |
| Ampliación estándar | 200 | ~8 d | igual, sobre un edificio ya abierto |
| Ampliación premium (Karaoke / Spa / Mirador) | 280 | ~12 d | contenido + a veces hito extra |
| Bloque B | 480 | ~20 d de ahorro **o** el gate de día 50 | +16 huecos → reabre llegadas |
| Bloque C | 880 | ~37 d de ahorro **o** el gate de día 160 | +16 huecos, partida avanzada |
| Escapada | 70 | ~3 d | duele una semana, no rompe el mes |
| Viaje grande | 350 | ~15 d | rivaliza con un complejo; raro |
| Actividad especial / grupal | 20 | ~1 d | condimento |
| Regalo de cumpleaños | 12 | ~medio día | opcional; escala con n |

**Catálogo estructural simulado** (cafetería ya abierta): 5 fase 1 + 9 ampliaciones + B + C = **4.200 U** de techo estructural.

Plaza y Casa no se compran (hipótesis).

---

## 4. Tres modelos

Misma tabla de precios y mismos gates. Cambia solo el grifo.

| | Generosa | **Media (recomendada)** | Lenta |
|---|---:|---:|---:|
| Misión cumplida | 12 | **8** | 5 |
| Renta alta / media / baja | 14 / 9 / 4 | **10 / 6 / 2** | 5 / 3 / 1 |
| Encargo extra | 16 | **10** | 6 |
| Ingreso teórico A (U/día) | 70 | **47** | 28 |
| Ingreso teórico B normal | 37 | **24** | 14 |
| Ingreso teórico C casual | 13 | **8** | 5 |
| Ingreso teórico F farmer | 74 | **49** | 29 |
| Días de B-normal para 1 lugar | 4.3 | **6.7** | 11.4 |
| Días para 1 ampliación | 5.4 | **8.3** | 14.3 |
| Días para 1 escapada | 1.9 | **2.9** | 5.0 |
| Días para 1 viaje | 9.5 | **14.6** | 25 |

### Lectura humana, modelo media

- **Día / semana / año de una normal:** ~24 U/día, ~170 U/semana, ~8.800–9.400 U/año (lab 365 d: 9.440 U ganados).
- **Excelente:** ~50 U/día (el doble). Acelera catálogo y experiencias; **no** salta B/C.
- **Casual:** ~8 U/día. En un mes abre ~1 lugar. En un año llena casi el mapa (12.6 / 14 destinos) y **no** llega a B si no ahorra.
- **Farmer:** ~54 U/día. Día 30: 5 complejos + 3 ampliaciones y **espera al día 50**. Día 160: C en el frame del gate. A→B→C de golpe = imposible.

---

## 5. Escenarios 30 / 100 / 365 (modelo media)

Medias de 8 semillas. `fase1` = complejos comprados además de la cafetería (máx. 5). `amp` = ampliaciones (máx. 9). Catálogo total comprable = 14.

### Día 30

| Perfil | U/día real | fase1 | amp | B | C | Residentes | Lectura |
|---|---:|---:|---:|---|---|---:|---|
| A excelente | 47 | 5.0 | 2.0 | no | no | 14/16 | Pueblo rico en sitios; espera el calendario de B |
| B normal | 24 | 3.6 | 0 | no | no | 14/16 | 3–4 complejos nuevos en el primer mes |
| C casual | 8 | 0.9 | 0 | no | no | 11/16 | Primer lugar al cabo de ~un mes. No está bloqueada |
| D ahorradora | 21 | 0 | 0 | no | no | 14/16 | Montaña para B. El pueblo no crece en mapa |
| E gastadora | 28 | 4.0 | 0 | no | no | 14/16 | Mapa sí, ahorro no |
| F farmer | 50 | 5.0 | 3.0 | no | no | 15/16 | Dinero sobra; el gate temporal manda |

**Nadie compra B en 30 días.** Incluido F. El farmeo no rompe la progresión residencial.

### Día 100

| Perfil | fase1 | amp | B (día medio) | C | Gold | Lectura |
|---|---:|---:|---|---|---:|---|
| A | 5 | 9 | **50** | no | 1.149 | Catálogo estructural hecho; empieza a sobrar U |
| B | 5 | 4.9 | **52** | no | 84 | B acaba de caer; mitad de ampliaciones pendientes |
| C | 3.9 | 0 | no (dinero) | no | 144 | Sitios sí, bloque no. 15/16 en A |
| D | 0 | 0 | **50** | no | 1.620 | B por ahorro puro. Mapa vacío |
| E | 5 | 7.1 | no (dinero) | no | 130 | Lo gastó en mapa + experiencias |
| F | 5 | 9 | **50** | no | 1.970 | Catálogo + B; espera C |

Valor económico de B: A/F pasan de 16 a ~30 residentes a día 100. C se queda en 16. Las llegadas **sí** recompensan haber pagado el bloque.

### Día 365

| Perfil | Catálogo 14 | B | C (día) | Gold | Escapadas | Viajes | Lectura |
|---|---:|---|---|---:|---:|---:|---|
| A | 14 | sí | **160** | 11.502 | 21 | 1.9 | El dinero **deja de importar** salvo viajes/deseos |
| B | 14 | sí | **168** | 3.856 | 12 | 0.8 | Año completo, pueblo lleno, colchón sano |
| C | 12.6 | **nunca** | no | 228 | 3 | 0 | Mapa casi lleno en A. Sin expansión residencial |
| D | 0 | sí | **160** | 6.376 | 0 | 0 | 48 residentes en un pueblo-solar. Caso patológico útil |
| E | 14 | sí (d139) | **185** | 3.385 | 25 | 2.0 | B/C más tarde porque no reserva |
| F | 14 | sí | **160** | 15.400 | 0 | 0 | Impresora topeada + nada que comprar |

---

## 6. Los tres modelos, cara a cara

### Bloque B (día medio si lo alcanzan)

| Perfil | Generosa | Media | Lenta |
|---|---:|---:|---:|
| A | 50 | 50 | 51 |
| B normal | 50 | 52 | 66 |
| C casual | 260 | *nunca en 365* | *nunca* |
| D | 50 | 50 | 50 |
| E | 87 | 139 | 289 |
| F | 50 | 50 | 50 |
| F a día 30 | 0 % | 0 % | 0 % |

### Bloque C (día medio)

| Perfil | Generosa | Media | Lenta |
|---|---:|---:|---:|
| A / D / F | 160 | 160 | 160–163 |
| B normal | 160 | 168 | 189 |
| E | 160 | 185 | *no (ocupación)* |
| C | 330 (88 %) | nunca | nunca |

### Generosa — ventajas y riesgos

- **Ventaja:** la casual llega a B dentro del año (día ~260). Nadie se queda sin mapa.
- **Riesgo:** a día 100 la normal ya tiene el catálogo entero. A arrastra 20.000 U a fin de año. El dinero deja de ser decisión muy pronto. Las escapadas (1.9 días de ahorro) no duelen.

### Media — ventajas y riesgos

- **Ventaja:** un lugar = una semana de juego normal. Una escapada duele ~3 días. Un viaje ~2 semanas. B cae en el gate temporal para quien juega de verdad. F no se salta el calendario. La casual **no** se queda sin contenido: abre sitios, aunque no expanda a 32.
- **Riesgo:** la casual nunca ve B si no ahorra. A/F acumulan montaña tras el catálogo (~día 100). Hace falta un sumidero de final de partida (propuesta: deseos que escalan con n, ropa). La ahorradora “gana” B/C con el pueblo vacío de lugares: la UI debería **mostrar** B como beat visible, no como ganga invisible.

### Lenta — ventajas y riesgos

- **Ventaja:** F sigue anclado a día 50 / 160. Las experiencias duelen (viaje = 25 días de una normal).
- **Riesgo:** la casual tarda 100 días en 1–2 lugares. La gastadora llega a B el día 289 y se queda corta de ocupación para C. Una mala racha de misiones **sí** puede bloquear la diversión. Demasiado cerca de economía de supervivencia, que está descartada.

---

## 7. Respuestas a las preguntas del encargo

*(Modelo media, salvo que se indique.)*

**¿Cuánto gana una normal / excelente / pasiva por día y semana?**
Normal ~24 U/día (~170/semana). Excelente ~50 (~350/semana). Casual ~8 (~55/semana). Farmer ~54.

**¿Cuánto tiempo para un lugar / ampliación / B / C?**
Lugar ~7 días de juego normal. Ampliación ~8. Premium ~12. B: el dinero se tiene en ~20 días, el **calendario** pide 50 y la ocupación ~12/16 (el lab llena A hacia el día 30 si se aceptan candidatos). C: el dinero se tiene en ~37 días de ahorro puro; el **calendario** pide 160 y ~26 residentes (hay que haber abierto B y dejado entrar gente).

**¿Cuánto debe doler una escapada / un viaje?**
Escapada ≈ 3 días de una normal: se nota, no cancela el mes. Viaje ≈ 15 días: rivaliza con abrir un complejo o con acercarse a B. Eso es el “duele”.

**¿Puede quien hace extras acelerar compras sin romper progresión?**
Sí: F tiene el mapa hecho el día 30–100 y B el día 50. No tiene C antes del 160. Los extras **no** saltan A→B→C.

**¿Puede acumularse dinero infinito?**
Sí, en cuanto el catálogo estructural está completo. A día 365, F tiene 15.400 U y A 11.500. No hay factura que lo drene. Es un hueco de diseño de **sumideros tardíos**, no de fuentes. Propuesta (no aprobada): deseos/ropa/temporada que crecen con n.

**¿Existe un punto donde el dinero deja de importar?**
En media, para A/F, **hacia el día 100–160** (catálogo + B, y luego C al gate). Para B normal, más tarde (después de C). Para C casual, el dinero **sigue** importando todo el año (vive al límite de cada lugar).

**¿Hay riesgo de grind?**
De citas: **no**, está cerrado que no pagan. De encargos: tope 1–2/día, variedad sí, volumen no. El “grind” de F es hacer los 2 encargos todos los días; acelera el mapa, no el calendario de bloques.

**¿Una mala racha económica bloquea la diversión?**
En media, no: hay renta-suelo y las misiones no son la única vía. Una casual sigue abriendo lugares. En **lenta**, sí hay riesgo. Por eso se descarta lenta.

**¿Incentivos perversos?**
- Farmear citas: cerrado que no.
- Comprar edificios para imprimir más encargos: solo el salto 1→2 al 3.er complejo. Después, tope plano.
- Ahorrar y no abrir nada (perfil D): B/C salen a tiempo y el mapa queda muerto. Contramedida de producto, no de cifra: B debe sentirse como **crecimiento del pueblo visible**, no como upgrade opcional de Excel.
- Gastadora: retrasa B (día 139) porque no reserva. Eso es lectura de jugador, no exploit.

**Peso relativo a 365 d, jugadora normal (media):**
lugares 8 % · ampliaciones 22 % · bloques 14 % · experiencias 15 % · **sobrante 41 %**.
El mapa y las ampliaciones pesan más que B/C. Las experiencias no bastan como sumidero de año 1 cuando el catálogo se acaba. Los bloques pesan **llegadas**, no solo U.

---

## 8. Recomendación de Carlos I

**Trabajar a partir del modelo media.** No canonizar aún las cifras; sí la *forma*.

1. Anclar la escala a **misión diaria = unidad**. El resto en múltiplos de “días de una jugadora normal”.
2. Gates de B/C **temporales + ocupación + precio**, con el precio de B alcanzable por una normal *alrededor* del día 50 (no mucho antes para F: F llega al dinero en ~10 días y **espera**). C anclado a ~día 160 + haber llenado B lo bastante.
3. Encargos extra con **tope diario**, no con multiplicador por edificio.
4. Renta por Vida como **suelo**, no como sueldo de 48 habitantes.
5. No pagar citas. No pagar Latidos hasta que Neni lo pida. No goteo laboral.
6. Antes de implementar: decidir sumidero de **final de partida** (si no, A/F nadan en U desde el mes 4). Eso puede esperar a un segundo lab; no bloquea elegir el modelo.

**No recomiendo generosa** como base: el dinero deja de ser decisión demasiado pronto.
**No recomiendo lenta:** empuja a la casual hacia sequía de contenido.

Cuando Neni + ChatGPT elijan modelo y respondan los `BLOQUEADO_DECISION`, *entonces* se puede cablear el ledger. No antes. No B5.

---

## 9. BLOQUEADO_DECISION

Hace falta respuesta de **Neni + ChatGPT**. El lab no inventa la cifra canónica.

1. **gates_B_C** — ¿Confirmáis B: día ≥ 50 y ≥ 12/16 en A; C: día ≥ 160 y ≥ 26 residentes? El Maestro deja los exactos pendientes. Si queréis B más “a mitad de temporada” o C más tardío, se re-simula sin tocar B1/B3.
2. **renta_offline** — ¿La renta por banda de Vida llega cada día de calendario (buzón de ingresos) aunque Celestine no abra el juego, o solo en días con sesión?
3. **parque_inicial** — ¿El Parque fase 1 se compra, o es espacio público abierto el día 1 junto a la cafetería?
4. **fama_en_desbloqueos** — ¿La fama entra como AND junto al dinero, o los lugares van a dinero + hitos (Mirador) y la fama queda en otra capa?
5. **latido_paga** — ¿Un Latido da bono de dinero o solo Vida/narrativa/buzón? El Maestro **no** lo lista como fuente.

Ninguno de estos bloquea a Carlos II ni a Carlos III salvo (3) Parque, que afecta a la ficha/mapa.

---

## 10. Qué no se ha hecho (a propósito)

- No se han escrito precios en `data/` ni en EconomyLedger.
- No se ha encendido `economy_enabled`.
- No se ha modificado B1 / B3 / E3 / relacional V1 / B5.
- No se ha tocado diseño de Carlos II ni assets de Carlos III.
- No se ha canonizado U → €.

Reproducir: `php tests/economia_lab_test.php` y `php dev/simulador_economia.php 8 lab-economia`.
