# Propuesta: bloque de 3 a ~24

El nombre del archivo es histórico. **24-en-uno ya no es la ampliación recomendada** (ver §7 y `PROPUESTA_VIDA_POOL_Y_MARCHAS.md`).

**Diseño. No es código. No interrumpe el trabajo visual ni a Rocío.**

Ronda de jugabilidad (2026-08-17, noche). **Prioridad sobre las cifras de escala de la V0** (10 al inicio, pool 16, techo ~14). Ampliada por `PROPUESTA_VIDA_POOL_Y_MARCHAS.md` (capacidad ≠ pool, marchas **consentidas**, NPC autónomos, test comprable, **varios bloques**).

```text
capacidad simultánea  ≠  pool total diseñado
```

**Bloque A ≈ 16** (primera prueba **solo A**). Ampliar = **Bloque B ≈ 16**. 80–100+ es catálogo futuro en tandas. **32** = capacidad A+B, no total de fichas.

El resto de la V0 (4 variables, sin %, Celestine propone, sin game over duro, cabeza maestra) **sigue**. **Primera prueba:** cafetería como primer sitio social; parque/biblioteca en onboarding progresivo (`DECISIONES_CERRADAS.md`).

---

## Qué compro tal cual

| Idea | Por qué |
| --- | --- |
| Empezar con **~3** | Onboarding real. 10 personas × 8 sistemas el día 1 es un manual disfrazado de pueblo. |
| Que los 3 **no** formen dos parejas (incluso 0 química romántica) | Enseña amistad, roce e incompatibilidad. El juego no es «empareja a los que hay». |
| Llegadas con **ritmo y sorpresa**, no 1/día | Si cada mañana llega alguien, deja de ser acontecimiento. |
| Llegada = pequeño evento (aviso, vehículo, tarjeta B, padrón) | Encaja con cutrez y con `funcion_llegada`. |
| Amistad **jugable**, con valor propio | Si solo sirve para ligar, es un tutorial de romance. |
| Resultados **no binarios** (cita ≠ pareja/fracaso) | Ya iba por aquí el informe narrativo. Hay que nombrarlo como regla. |
| Test de compatibilidad **sin %** y con **info incompleta** | Evita el sudoku. Encaja con preparación de citas y con ficha A/B. |
| Ficha que **crece** (público / descubierto / desconocido) | El contrato ya separa B y A; faltaba el estado de partida del *jugador*. |
| Sandbox, **sin victoria** «X parejas» | Ya habíamos tirado el game over. Esto tira también el «ganar» disfrazado. |
| **Estado / rótulo** en cabecera, no derrota | Lectura divertida. Por bloque + posible estado de comunidad. |
| Onboarding **orgánico** por crecimiento | El tutorial es el pueblo pequeño. |
| Arquitectura **ampliable** | Los ids `per_` y el padrón no pueden asumir 16 para siempre. |

---

## Qué cambiaría (no rechazo: ajuste)

1. **Un solo presupuesto de intervención al día**, no «2 citas románticas» *más* planes de amistad sueltos. Si no, con 3 habitantes el jugador los casará o amistará a todos el día 1. Propuesta: el jugador propone **encuentros** (intención romántica *o* amistosa). Tope temprano **1/día**; luego 2; más adelante se estudia. Los NPC siguen cruzándose solos.
2. **El diario escala con la población.** 5–10 líneas con 3 vecinos es relleno; con A lleno (o A+B) es un cuello de botella bueno *si* el filtro es agresivo. Cupo relativo: p. ej. `3 + suelo(n/4)` recortado a 5–10, o 4–6 al inicio y 5–10 desde ~10 residentes. Varios bloques **no** bajan el ruido del café.
3. **Llenar A no es cifra de día 30.** Si A se llena en dos semanas, el onboarding muere. El techo de *un* bloque (~16) no es el techo de la partida: después puede abrirse B.
4. **El grafo recíproco deja de exigir que el grupo inicial se baste.** El recíproco potencial se valida sobre gente que **puede coexistir** (tanda / bloques abiertos), no sobre los 3 del día 1 ni sobre un grafo 100×100.
5. **El test no se desbloquea como oráculo el minuto 0** — o sí, pero entonces su primer resultado tiene que ser a menudo «aún no te conozco» / «mejor de cañas» por falta de datos, no un corazón preciso. El juguete puede estar; la precisión no.
6. **Temporadas** no mueren, pero **no llevan objetivos de «haz 5 parejas»**. Pueden ser clima, eventos de villa, llegadas agrupadas, un resumen. Si una temporada exige cupo de parejas, volvemos a la victoria tradicional.
7. **Los 2–3 pilotos de Neni ≠ los 3 del día 1.** Pilotos = tono de ficha y cara. El trío inicial se **diseña** para enseñar el juego (edades/voces distintas, poca o nula vía romántica, al menos un lazo social posible). Rocío puede estar o no; no se fuerza.
8. **El esqueleto de 16 no se tira.** Es la primera tanda de contenido. Encaja con **Bloque A ≈ 16**. Ampliar = Bloque B, no hinchar A a 24.
9. **El test no es del día 1:** desbloqueo/compra (`PROPUESTA_VIDA_POOL_Y_MARCHAS.md`). La fila «Test día 1 o 2» de §2 queda **obsoleta**.

---

## Problemas que detecto

### Graves (hay que decidirlos antes de tratar 24-en-uno o Bloque B)

**Marchas consentidas + un solo bloque = tapa igual.** El jugador puede encariñarse y no dejar ir a nadie. A se llena y se queda. La válvula de crecimiento es **abrir Bloque B**, no un dado que expulse, ni hinchar el mismo mapa a 24.

**Con 15–32, 2 intervenciones/día no cuidan a nadie.** Eso está bien para el jugador (elegir a quién ignorar). Está mal si `necesidad_contacto` castiga al *jugador* porque Marta lleva 20 días sola. Los NPC tienen que **quedar entre ellos** (amistad y, a veces, romance; también **entre bloques** en sitios compartidos) o el sandbox se siente como un call center.

**El test puede soplar orientación.** Si A no está en `atraido_por` de B y el test dice «ni con un palo», el jugador acaba de leer la ficha A. Con info incompleta, un veto de capa 1 **no descubierto** debe parecer tibieza, no un no legal. Si el jugador *ya sabe* que son hermanos, ahí sí puede ser tajante.

**Tres lugares abiertos + tres personas** el día 1 sigue siendo mucho mapa. El onboarding pide **un sitio estrella** (cafetería) y los otros dos visibles pero casi vacíos, o candados extra al inicio. Si no, el jugador recorre un tablero muerto.

### Medios

- Dinero/fama con 3 vecinos: o no se enseña hasta el primer desbloqueo, o la cifra no se mueve y parece rota.
- Dos amigos que llegan juntos: hay que ver el lazo en B si es público; si no, el jugador no entiende el «paquete».
- Amistad → atracción inesperada: si pasa demasiado, la amistad deja de tener valor propio. Probabilidad baja, causa, no cada cañas.
- Estado del bloque en el título: si persigue «AQUÍ SE RESPIRA AMOR», es un high score. Hace falta **inercia** (no cambia cada día) y que «MENUDO CUADRO» también sea una partida buena.
- Móvil + 16 tarjetas (y 32 al abrir B): sin filtros, El pueblo muere. PC aguanta mejor el mapa; el cuello es la lista y el diario, no el GPU.
- El trío del día 1 mal elegido (dos que se comen y un tercero) enseña el anti-tutorial: el jugador ignora al tercero para siempre.

### El trabajo actual

No rehacer I01–L06 ahora. No parar estilo 06 / Rocío. Esta ronda cambia **cuántos hay en pantalla y cuándo se enseña cada sistema**, no la identidad de las fichas ya escritas.

---

## Qué propongo añadir (sistemas que aún no estaban)

1. **Encuentro con intención** (`romantico` | `amistoso`) en el mismo flujo de cita. Mismos sitios, otro contrato de resultado.
2. **Calor de amistad** mínimo: no 4 barras nuevas. Bastan tipo de lazo (`conocido` → `amigo` → `mejor_amigo`) + un entero chico de calor o recuerdos compartidos. El romance sigue con las 4 variables. Un par puede tener **los dos** registros (amigos que se lían; expareja que se cae bien).
3. **Conocimiento del jugador** por persona: cada campo sensible tiene estado `publico` | `descubierto` | `desconocido`. La UI de ficha muestra huecos (`…`) sin decir *cuántos* secretos hay. `runtime.descubrimientos_jugador` ya estaba previsto; hay que usarlo de verdad.
4. **Test = lectura sesgada**, no evaluador interno. Ingredientes: solo hechos descubiertos + ruido + «confianza del test» (baja al inicio). Nunca imprime las 4 variables.
5. **Plantilla de llegada** (evento, no código): aviso → vehículo/cutrez → cabeza en el bloque → 2 líneas de B → halo `nuevo`. A veces 2 personas a la vez.
6. **Vida NPC autosuficiente** que escala: a más población, más encuentros espontáneos *entre ellos*, no más trabajo para el jugador.
7. **Filtros de El pueblo:** todos / nuevos / en lío / solteros / amigos / no los veo. Obligatorio antes de 15.
8. **Inercia del rótulo** del bloque (cambios cada varios días, no cada informe).
9. **Marchas consentidas** (última palabra del jugador; la causa vira a arco) y **varios bloques** (A luego B): `PROPUESTA_VIDA_POOL_Y_MARCHAS.md`.
10. **Trío tutorial diseñado** (brief aparte, cuando toque), distinto del esqueleto I01–I03 si hace falta.

---

## 1. Ritmo 3 → ~24 (hipótesis de simulador, no calendario)

No «una persona por día». Oleadas + huecos.

| Tramo | Residentes (aprox.) | Días de pueblo (orden de magnitud) | Qué se siente |
| --- | --- | --- | --- |
| A. Patio | 3 | 1–3 | Aprender fichas, un sitio, un encuentro, el test mentiroso |
| B. Primeros vecinos | 4–6 | +3–6 | Primera llegada-acontecimiento; 0 o 1 día vacío; quizá un dúo |
| C. Bloque vivo | 7–10 | +5–8 | Segundo sitio se usa de verdad; primera crisis chica |
| D. Vecindario | 11–16 | +8–14 | Candado que se abre; hay que ignorar gente; NPC se bastan más |
| E. A lleno | Bloque A ≈ 16 | temporada(s) | Techo de A; llegadas más raras; **abrir B** es el beat (no un 24 único) |

Picos conceptuales (no fechas): día 1 = 3; luego nadie; 1; nadie; **2 juntos**; 1; pausa más larga; 1… Mezclar: individuo, dupla (amigos/hermanos), alguien sin lazo.

Si en 10 días ya hay 12, frenar. Si en 15 días sigue habiendo 4, el pueblo aburre. El simulador tendrá que mirar eso (`SIMULADOR_DISENO.md`).

---

## 2. Cuándo introducir cada sistema

| Sistema | Cuándo se *usa* | Cuándo se *explica* |
| --- | --- | --- |
| Mapa + 1 sitio estrella | Día 1 | Nada. Se pulsa. |
| Ficha B / pública | Día 1, al llegar y al abrir | Huecos `…` ya enseñan que hay más |
| Encuentro (1/día) | Día 1–2 | La intención romance/amistad en el mismo botón |
| Test | Tras desbloqueo/compra, no día 1 | Sale impreciso a propósito |
| Diario | Desde día 1, corto | Se entiende al leer 3 líneas, no 10 |
| Llegada | Primera oleada (no día 1) | El evento es el tutorial |
| 2.º y 3.er lugar | Cuando hay 5–8 y alguien *quiere* ir | Un 🔒 que se nota |
| Dinero/fama | Al primer desbloqueo o mejora | No el minuto 0 |
| Mantenimiento / crisis | Primera pareja o amistad que se tuerce | Un ⚠️, no un manual |
| Tentación / encuentros ajenos | ≥8–10 | El pueblo ya no cabe en la cabeza |
| Preparación fina de citas | Citas «importantes», más tarde | `PREPARACION_DE_CITAS.md` |
| Ropa / cuerpos | Fuera de esta ronda | Visual aparte |
| Aprendizaje de gustos | Aún más tarde | `APRENDIZAJE_PREFERENCIAS.md` |
| Marchas | Cuando las llegadas ya se entienden | Aviso «piensa irse»; no un dado a solas |

Prohibido el día 1: tienda, 10 fichas, economía visible como tutorial, triángulos, test de compatibilidad, tutorial de 12 ventanas.

---

## 3. Cómo hacer útil la amistad

El jugador elige **intención amistosa**. Eso no es un romance con el corazón apagado: el motor **no tira** de capa romántica igual (o tira poco y en silencio).

Una buena amistad puede, sin números en pantalla:

- bajar ánimo malo / soledad (necesidad de contacto *social*, no solo de pareja)
- dar pistas 👀 (gustos, dealbreakers leves, «no le va lo intenso»)
- suavizar una ruptura (no la borra)
- crear lazos que luego importan (el hermano que llega; el amigo que celos)
- eventos propios (cañas, favor, pelea de amigos)
- dejar a alguien *en mejores condiciones* para una cita futura (ánimo, no +20 atracción regalada)

**No** es requisito para ligar. Dos personas pueden ser `mejor_amigo` para siempre.

Si un plan de amistad genera atracción: rara, con causa (alcohol, roce, malentendido), y el informe lo cuenta como sorpresa, no como «misión cumplida».

Tope: los planes de amistad **consumen el mismo hueco** que una cita. Si no, el jugador maxea el pueblo a cañas.

---

## 4. Test de compatibilidad sin revelar el motor

```text
entrada: dos ids + lo que el JUGADOR sabe de cada uno
salida: cubo grosero + una línea de tono
nunca: %, las 4 variables, atraido_por crudo, crush, dealbreaker oculto
```

Cubos (textos **no canónicos**): tema / algo hay / mejor de cañas / uy / ni con un palo / *aún no te conozco*.

Precisión ≈ confianza:

| Qué sabe el jugador | El test puede |
| --- | --- |
| Solo B pública | Ambiguar. Empujar a amistad o «no sé» |
| Varios gustos descubiertos | Sesgar hacia afinidad social o «cañas» |
| Un rechazo descubierto que aplica | Permitir «uy» / «palo» |
| Capa 1 oculta (orientación, absoluto, hermanos no públicos) | **No** delatarla. Tibieza o ruido |
| Historial de encuentros ya vistos | Contar el *relato* («ya quedaron y salió regular»), no el Excel |

El resultado es **tendencia**, no destino. Una pareja «ni con un palo» puede funcionar; un corazón puede ser un desastre. Encaje con azar + personas.

Jugo: las dos cabezas entran, escáner cutre, sonido. Una vez por pareja cada X días, o un coste chico, para no spam.

No es la máquina de las 9 capas. Es un cotilleo industrializado.

---

## 5. Qué descubre el jugador y cómo

| Capa | Ejemplos | Cómo entra |
| --- | --- | --- |
| Pública (B, llegada) | Nombre, edad, oficio a groso modo, hobby declarado, look, frase | Tarjeta de llegada + ficha |
| Descubierta | Gusto oculto, ritmo, un dealbreaker que *saltó*, lazo no público que se vio, ánimo reiterado | Diario 👀, encuentro, amistad, crisis, rumor |
| Desconocida | Crush, `atraido_por` fino, absolutos no disparados, secretos | No se listan. La ficha no enseña un inventario de spoilers |

La ficha **crece** por campos que pasan de `…` a texto. No por desbloquear la hoja A.

Nunca automático: compatibilidad matemática, atracciones numéricas, crush secreto, preferencias no descubiertas, dealbreakers que no han tenido escena, función de llegada.

Aprendizaje de gustos (`APRENDIZAJE_PREFERENCIAS.md`): si un gusto *cambia*, lo descubierto puede quedar viejo — otra 👀, no un recálculo.

---

## 6. Estado social/sentimental del bloque

Un **rótulo**, no una barra 72/100.

Se deriva (concepto, sin fórmula):

- calidad de romances (vínculo alto vs conflicto)
- amistades vivas vs gente aislada
- soledad (necesidad de contacto alta y sin roce reciente — *incluyendo* roce NPC-NPC)
- rupturas recientes
- ambiente (ánimos, ⚠️ abiertos)

Inercia: el título vive **varios días**. Un mal sábado no pinta «MENUDO CUADRO».

Textos: abiertos. Los de la ronda valen como tono.

Esto **no** es victoria. «AQUÍ SE RESPIRA AMOR» es un clima. «MENUDO CUADRO» también se juega. Si más adelante hay temporadas, el resumen puede *citar* el rótulo, no puntuarlo.

---

## 7. ¿Un 24 único es razonable en PC y móvil?

**No como un solo edificio.** 24-en-uno satura el mapa. Mejor: **Bloque A ≈ 16** (primera prueba) y, más adelante, **Bloque B ≈ 16**. Cada vista ~16; sitios sociales compartidos.

| Superficie | Un 24 único | A luego B (~16+16) |
| --- | --- | --- |
| PC / móvil mapa | 24 chinchetas en un portal | Dos edificios-icono; al pulsar, ese bloque |
| El pueblo | Filtros o muere | Igual al abrir B (32 tarjetas): filtro este bloque / todos |
| Diario | Cupo duro | Cupo igual de duro: el café no sabe de portales |
| Test / encuentros | El tope salva el pulgar | Igual |

24 cabezas maestras en disco no es un problema técnico. 24 *sistemas* visibles en un tablero sí.

Primera prueba: **solo A**. No hace falta producir 24 fichas ni dibujar B. Detalle y problemas nuevos: `PROPUESTA_VIDA_POOL_Y_MARCHAS.md` § Varios bloques.

---

## 8. Pueblo jugable con A lleno (y luego B)

- NPC quedan entre ellos (amistad y algo de romance; también entre bloques).
- El jugador ignora a la mayoría; el diario señala 👀/⚠️ de verdad.
- Filtros en El pueblo (obligatorios antes de ~15; al abrir B, filtro por bloque).
- Llegadas más raras cuando A está lleno; **abrir B** reabre el acontecimiento.
- Sitios con **aforo** (cifras después, con prueba de mapa). Quien no entra va a otro sitio / casa / pospone. Coincidir ≠ interactuar.
- Tope de encuentros del jugador estable (1–2); no subir a 6 «porque hay más gente».
- Rótulo del bloque que miras + posible estado de comunidad.

Si 20 personas esperan al jugador para tener vida, el sandbox está roto.

---

## 9. Qué queda obsoleto (escala V0)

| Antes (V0) | Ahora (esta ronda) |
| --- | --- |
| 10 residentes día 1 | **~3** |
| Los 10 iniciales deben tener recíproco *entre ellos* | El trío **puede** no tener vía romántica. Recíproco = pool grande |
| Pool 16 = población del producto | 16 = **primera tanda** + encaje con Bloque A. Ampliar = B, no un 24 único |
| 6 llegadas por hitos hasta ~14 | Llegadas con ritmo irregular hasta llenar (o casi) A; luego B |
| Máximo ~14 como alarma | Alarma de A ≈ 16; B es la válvula, no hinchar A |
| «12 de salida también vale; no bajar de 8» (`PROPUESTA_JUGABLE_V0`) | **Sí bajar** a 3 a propósito |
| Objetivos blandos tipo cupo de parejas de temporada | Sandbox + **rótulo del bloque**. Sin «X parejas = ganas» |
| «Si todas se separan, pierdes» (Maestro / duda vieja) | **No.** Un desastre también es partida |
| Amistad solo como lazo de ficha / ruido | **Plan de amistad** jugable |
| Cita → pareja o fracaso (lectura fácil) | Abanico de resultados, incluido amistad inesperada |
| Tutorial implícito «hay 10, apáñatelas» | Onboarding por pueblo vacío que se llena |

**No obsoleto:** 3 lugares de catálogo (sí se puede *enseñar* 1 primero); 4 variables románticas; sin %; sin game over duro; jugador propone; diario 👀/⚠️; cabeza maestra; no programar aún; 2–3 pilotos de tono para Neni.

`PROPUESTA_JUGABLE_V0_CURSOR.md` sigue siendo útil para el *bucle de un día*; sus cifras de población **ya no mandan**.

---

## 10. Preguntas para Neni y ChatGPT

Las que **bloquean** diseño fino. El resto puede esperar al simulador.

1. ¿**3 al inicio** queda cerrado? (Cursor lo trata ya como dirección.)
2. ¿El trío del día 1 se **diseña a propósito** (voces/edades distintas, 0–1 vía romántica, al menos un plan de amistad posible) o se sortea entre fichas ya escritas?
3. ¿Rocío **puede** ser una de las 3, o los pilotos de tono y el tutorial se separan?
4. ¿Confirmáis que los 3 **no** necesitan recíproco romántico entre ellos?
5. ¿Ampliar = **Bloque B ≈ 16** (candidato Cursor: sí) o un único edificio de 24? ¿Cuándo se enseña B?
6. ¿Un solo tope de **encuentros/día** (romance+amistad) o dos presupuestos?
7. Día 1: ¿cuántos sitios se *usan*? ¿Cafetería sola, o los 3 abiertos ya?
8. ¿El test existe desde el día 1 como juguete impreciso, o se desbloquea?
9. ¿El rótulo del bloque sustituye del todo los objetivos de temporada, o las temporadas siguen para clima/eventos sin cupo de parejas?
10. ¿Nombre: villa, bloque, pueblo? La ronda dice bloque; la docs dicen pueblo.
11. ¿Seguimos produciendo el esqueleto de **16** como tanda 1 (recomendado: sí) sin dibujar Bloque B ni 24 fichas?

---

## Relación con fichas y arte

- Contrato A/B: igual. B = pública de llegada. Descubierto = runtime.
- Grafo: recíproco potencial en el pool completo, no en el trío.
- `empieza_en_pueblo` / `orden_llegada`: habrá que rediseñar cuando exista el trío; **no ahora**.
- Visual: estilo 06 y comparativas siguen. Esta ronda no pide retratos nuevos.

Ver también: `RELACIONES_SOCIALES.md`, `SISTEMA_COMPATIBILIDAD.md`, `DIARIO_DEL_PUEBLO.md`, `PREPARACION_DE_CITAS.md`, `CONTRATO_PERSONAJE.md` §4.
