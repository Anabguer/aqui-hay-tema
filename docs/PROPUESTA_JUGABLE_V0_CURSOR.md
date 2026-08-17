# Propuesta jugable V0 — Cursor (tercer diseñador)

Estado: **aprobada** (2026-08-17) en bucle y tono. **Cifras de población (10/16/14) desplazadas** por `PROPUESTA_BLOQUE_3_A_24.md`. **Marchas y escala posterior:** `PROPUESTA_VIDA_POOL_Y_MARCHAS.md` (consentidas; Bloque A luego B, no un 24 único).

Matices respecto a la propuesta original: grafo **recíproco potencial** (no solo ida); ropa inerte en V0 pero campos de look en ficha. Escala de pantalla: ver la ronda del bloque (~3 al inicio), no la §2 de abajo.

Siguiente fase de fichas (tanda 1): `FASE_DISENO_POOL_16.md`.

> §2–3 de este archivo (10 al día 1, techo 14, no bajar de 8) son **históricas**. No usarlas para diseñar el trío tutorial.

---

## 1. Bucle jugable completo (V0)

Un día, visto por el jugador:

1. **Mañana breve.** Coletilla del día (una). Diario con 5–10 líneas. Cabezas en el mapa según rutina (trabajo / hobby / banco / casa-offmap).
2. **Leer el pueblo.** Quién está mal dormido, quién es nuevo, quién lleva sin verse, quién ha coincidido en la cafetería. Sin porcentajes.
3. **Decidir poco.** Como mucho **2 citas** ese día (huecos, no Excel). Lugar solo entre los 3 abiertos, o los que ya hayas desbloqueado. Opcional: atender un ⚠️ (llamar / citar / dejarlo estar).
4. **El pueblo hace lo suyo.** NPCs trabajan, pisan sitios, se cruzan. La mayoría de cruces no son drama.
5. **Informes cortos.** Cita = 2 microfrases + 1 coletilla + cambio de color de pareja. No una novela.
6. **Dinero y fama se mueven un poco.** Cita decente / pareja nueva / crisis salvada. Gastos solo si desbloqueas o tiras de plan especial.
7. **A veces llega alguien.** Maleta en el diario, halo «nuevo», el grafo se mueve. Casi nunca una crisis automática.

La fantasía que hay que sentir en 20 minutos: *yo no ligue; yo cotilleo y apago fuegos, y el pueblo no se queda quieto si yo no pincho*.

Dificultad = **elegir a quién ignorar**, no clicar a todos. Por eso el tope de citas/día es el motor, no las matemáticas de capas.

---

## 2. Población inicial recomendada

La cifra cerrada anterior (~16 para probar) era un techo de *contenido diseñado*, no de *gente en pantalla el día 1*. Con 3 sitios, 16 residentes simultáneos es un hormiguero ilegible y un infierno de agenda.

| Capa | Recomendación V0 | Por qué |
| --- | --- | --- |
| Residentes el día 1 | **10** | Par, se lee de un vistazo, hay solteros de verdad, 3 sitios se llenan (encuentros) sin ser caos |
| Pool diseñado | **16** | Los 6 de más entran como llegadas. El grafo se valida sobre el pool **y** sobre los 10 solos |
| Simultáneos máximo | **14** | Margen para 4 llegadas netas. Más de 14 + 2 citas/día = trabajo de secretaría |
| En pantalla (mapa) | los 10–14 | Cabezas; el padrón es otra vista |

**12 de salida** también vale si el grafo de 10 queda cojo. No bajaría de 8 (el pueblo se siente de juguete vacío, no «medio vacío»).

No fijar esto como canon hasta que ChatGPT dibuje el grafo; si el grafo de 10 aísla a alguien, se sube a 12 **antes** de inventar marchas.

---

## 3. Entradas y salidas

### Estados — crítica

La lista larga (residente, nuevo, visitante, ausente, se muda, antiguo residente) **da juego en un pueblo persistente** y **complica de más la V0**.

Recomiendo **4 estados** + 1 flag:

| Estado | Qué es |
| --- | --- |
| `residente` | Vive aquí. Citas, rutina, diario. |
| `ausente` | Se fue. Sigue en datos. Puede volver. No ocupa máximo. |
| `visitante` | Pasa unos días. No cuenta igual para «máximo vital». V0: **fuera**. |
| `fuera_del_pool` | Ni siquiera está escrito aún. |

Flag, no estado: **`nuevo`** (se apaga a los 3–4 días). «Se muda» es una **transición** (residente → ausente), no un sitio en el que vivir. «Antiguo residente» = `ausente` con historial.

Visitantes en V0 son un segundo juego (¿citas con quien se va el domingo?). Fuera.

### Llegadas (sí en V0)

- **No** un chorro diario.
- Ritmo propuesto (orden de magnitud, no cifra sagrada): **primera llegada tras un hito** (primera pareja que aguanta unos días, o primer desbloqueo, o fama mínima). Luego **como mucho una llegada cada varios días**, y nunca dos el mismo día.
- Condición: hay hueco bajo el máximo; el candidato no deja a nadie del pueblo **sin un destino legal de `atraido_por`** (se simula el grafo *después* de entrar); no es pariente vetado de media aldea si eso vacía opciones.
- Aviso: **sí**, el diario lo dice el día anterior o esa mañana (`👀 El lunes se instala alguien en la calle de la cafetería`). Sin spoilear ficha completa.

### Marchas (V0 histórica: no; dirección actual: consentidas)

La V0 **solo sumaba gente**. Eso queda **obsoleto** como regla de producto.

Dirección actual (`PROPUESTA_VIDA_POOL_Y_MARCHAS.md`): un residente puede **querer** irse; **no se va** sin el jugador. Botones: dejar ir / pedir que se quede. «Quédate» = se queda siempre (sin tirada). La causa vira a arco. Primera prueba puede retrasar *usarlas*; el modelo ya está.

`nuevo` no se puede marchar. Antiguo se archiva. Offline no resuelve una marcha.

### Que no sea una puerta giratoria

- Cooldown global: no entrada y salida en la misma ventana corta.
- `nuevo` no se puede marchar.
- Quien se va deja recuerdos y puede volver **una vez** por temporada como mucho.
- El máximo simultáneo es un **techo duro**: si está lleno, la siguiente llegada espera (diario: «Alguien quería venir. No hay piso. Tú sabrás.») — eso ya es diseño, no hace falta simular inmobiliaria.

### Grafo cuando cambia la población

Validar **después** de cada alta (en V0 no hay bajas):

1. Nadie residente con 0 recíprocos potenciales legales (`genero` ⊆ `atraido_por` del otro, en los dos sentidos, + no parentesco + no dealbreaker absoluto).
2. Los 10 iniciales ya cumplen (1) entre ellos **antes** de la primera llegada.
3. `apertura_descubrimiento` no cuenta para este veto.

El pool de 16 existe para elegir quién entra sin romper el pueblo **y** para que las llegadas muevan el grafo, no para rescatar aislados.

---

## 4. Máximo simultáneo y por qué

**14** en V0. Con 2 citas/día, 14 cuerpos y 3–6 sitios, el jugador ya elige. 18–20 es el pueblo «de verdad» de más adelante, cuando haya más lugares y quizá 3 huecos de agenda.

El pool 16 ≠ 16 en pantalla. Mezclar esas dos cifras fue el error de la fase anterior; no lo repetiría.

---

## 5. Pantalla de habitantes

### Crítica de «un edificio en el mapa»

**A — Ayuntamiento / padrón / centro vecinal**  
Todo en el tablero. Coherente con «el pueblo es el UI». Pero: (1) ya hay 3 sitios de cita; un 4.º abierto el día 1 diluye «pueblo medio vacío»; (2) el padrón no es un lugar de cita ni de hobby; (3) en móvil, ir al ayuntamiento para ver fichas es un toque extra cada vez.

**B — Cómplice de UI (recomendado)**  
La referencia ya tiene cinta de parejas abajo y laterales. El padrón es el hermano de esa cinta: botón **«El pueblo»** (o «Gente») siempre visible. Mapa = sitios + cabezas. Padrón = tarjetas. En PC, panel o overlay a la derecha; en móvil, pestaña inferior junto a Mapa / Diario / Parejas.

Un ayuntamiento **decorativo bloqueado** o desbloqueable más tarde («archivo del padrón») puede existir sin ser la única puerta.

No recomiendo inventar un 4.º lugar de cita llamado Ayuntamiento.

### Qué se ve en cada tarjeta (rápido, no tabla)

- Retrato caricatura
- Nombre, edad
- Estado sentimental (soltero / con X + color)
- Disponibilidad de la franja actual (punto: libre / currando / en cita)
- Halo **Nuevo**
- Punto de atención si hay ⚠️ que le toca
- Icono de cita programada hoy
- Nada de barras numéricas

Filtros (pueden llegar en la misma V0, son baratos): todos, solteros, parejas, nuevos, en crisis, disponibles. Uno activo a la vez.

PC: grid de tarjetas, hover = coletilla o ánimo en una línea. Móvil: lista de tarjetas grandes, 2 por fila como mucho, filtros en chips arriba.

---

## 6. Plantilla definitiva de personaje

No son los 16. Es lo que el sistema **necesita**. Lo que no está aquí no se simula en V0.

### Fijos (catálogo)

| Campo | Para qué |
| --- | --- |
| `id` | Nunca el nombre como clave |
| `nombre` | Ficha |
| `edad` | Ficha; adultos |
| `genero` | Grafo. Propuesta V0: `mujer` / `hombre` / `no_binarie` |
| `pronombres` | Texto |
| `atraido_por[]` | Capa 1. Lista de géneros (puede ser varios) |
| `etiqueta_visible` | Opcional, corta; no manda |
| `apertura_descubrimiento` | `cerrada` / `permeable` / `abierta` — ver §12 |
| `retrato` / `silueta` / `etiquetas_look_base` | Arte |
| `ocupacion` | Franja de trabajo |
| `franja_laboral` | mañana / tarde / noche / flexible |
| `hobby_principal` | Rutina + encuentros |
| `lugares_preferentes[]` | 1–2 ids de sitios **existentes o futuros** |
| `estilo_social` | Microfrases, sitios |
| `rasgos_publicos[]` | 3, del set corto |
| `rasgos_ocultos[]` | 1–2, se descubren |
| `gustos_declarados[]` | Algunos visibles |
| `preferencias_romanticas[]` | Parte visible, parte oculta (estilo, ritmo…) |
| `dealbreakers[]` | Ocultos; absolutos = capa 1 |
| `familia[]` | ids + tipo (vetos) |
| `amigos_iniciales[]` | ids; el resto nace en partida |
| `necesidad_contacto_base` | baja / media / alta — semilla de la variable de pareja |
| `voz` / `registro` | `seca` / `dramatica` / `tranquila` / `borde` / `cándida` — para el motor de frases |
| `tono_coletilla` | si este personaje «arranca» frases propias o solo el pueblo |

### Que evolucionan (runtime)

ánimo (enum corto), cansancio como **flag** no barra, dónde está ahora, `nuevo` hasta fecha, estado de residencia, relaciones sociales que nazcan, relaciones románticas (las 4), atracciones dirigidas hacia gente concreta (incluso sin pareja), recuerdos (ids), descubrimientos del jugador sobre esta ficha, ropa extra (fuera de V0 si se recorta), citas programadas, «me quiero ir» (cooldown de marcha, V0.5).

Fama/notoriedad **del NPC**: no en V0. La fama es del jugador-casamentero. Un NPC «conocido en el pueblo» es texto, no recurso.

### Ocultos al jugador (y cómo salen)

| Dato | Cómo se ve |
| --- | --- |
| `atraido_por` fino / excepciones | Pistas 👀, citas, nunca un menú «es bi» el día 1 si no es visible |
| Dealbreakers | Tras chocar o tras confianza |
| Preferencias de estilo/ritmo | Citas, diario |
| `apertura_descubrimiento` | No se nombra; se infiere |
| Números de atracción/vínculo/conflicto | Nunca. Color + frases |
| Crush hacia alguien | Diario 👀 o informe de cita ajena, no un radar 87% |

**Campo que nos faltaba y sí metería:** `voz` (para el catálogo de frases) y `apertura_descubrimiento`. Sin voz, ChatGPT no puede etiquetar variantes. Sin apertura, el «descubrimiento» es una ruleta disfrazada.

**Campo que recortaría en V0:** música, rarezas largas, looks múltiples, independencia/celos como barras. Celos = evento + conflicto, no un hidrómetro.

---

## 7. Actividades mínimas del NPC

Tres bloques al día: **mañana / tarde / noche**. Ni horas ni Sims.

Por bloque, el NPC elige **una** cosa, en este orden:

1. Si el jugador le ha puesto **cita** en ese bloque → va (o rechaza: ánimo fatal / trabajo inamovible / dealbreaker de lugar).
2. Si el bloque coincide con `franja_laboral` → **trabaja** (off-mapa o en su sitio si más adelante hay local; en V0, off-mapa basta).
3. Si no: dados cortos entre **hobby** (si el lugar está abierto), **sitio preferente abierto**, **ver a un amigo** (mismo sitio), **en casa** (invisible).

Azar: poco. Pesos fijos por ficha + 1 tirada. Nada de pathfinding.

El jugador **no** edita la rutina. Solo agenda citas (y en V0.5, quizá «no les juntes hoy» vía no citar). Eso es agencia de casamentero, no de dios.

Si el hobby es cine y el cine está 🔒: va a cafetería/parque/biblioteca o se queda en casa. Diario ocasional, no cada día (`Joel extraña las máquinas.` como mucho una vez por semana).

---

## 8. Economía (circuito, sin cifras sagradas)

### Separación (esto sí es diseño, no números)

| | Dinero | Fama |
| --- | --- | --- |
| Fantasía | Te pagan por el cotilleo / el pueblo te suelta efectivo | Eres *el* casamentero |
| Entra | Paga diaria pequeña + extra por cita que no es un desastre + extra por crisis resuelta | Pareja que nace, pareja que aguanta, ⚠️ atendido a tiempo, historia memorable (boda más adelante) |
| Sale | Desbloquear lugar, cita especial (cena en restaurante cuando exista) | Casi nada. La fama no se «gasta» |
| Desbloquea | Sitios, planes de pago | Llegadas, algún evento, más tarde ropa/gente |

**No** ganar dinero por desbloquear. **No** comprar fama. **No** empresas.

### Que no sobre / que no falte

- Siempre hay un siguiente edificio que cuesta **un poco más** de lo que sueles ahorrar en unos días. El jugador elige: sitio nuevo vs guardar.
- La paga diaria evita sequía si fallas dos citas.
- Las citas especiales son un sumidero opcional, no un peaje para jugar.

### ¿La fama baja?

Recomendación: **sí, poco, y solo si ignoras ⚠️ de verdad** (cadena de rupturas por abandono). Una ruptura *natural* o una pareja que se deja en paz **no** te castiga. Castigar el arco bueno sería moralina, y el Maestro lo prohibió.

---

## 9. Lugares (V0, con tijera)

Cada edificio tiene que **hacer una pregunta de diseño distinta**. Si dos hacen la misma, sobra uno al principio.

| Lugar | ¿V0? | Función jugable |
| --- | --- | --- |
| Cafetería | **Inicio** | Cita default, encuentros, «el salón del pueblo» |
| Parque | **Inicio** | Exterior, ánimo, coincidencias de paseo |
| Biblioteca | **Inicio** | Silencio vs cuchicheo, hobby leer, contraste fiestero |
| Cine | 1.er desbloqueo (dinero y/o fama) | Plan «de verdad», cita de tarde/noche, cierra si hay apagón más adelante |
| Bar | 2.º | Noche, alcohol/humor, peor idea si ánimo fatal |
| Arcade | 3.º | Hobby videojuegos, tono cutre bueno |
| Restaurante | Después | Cita especial (cuesta dinero). Distinto de la cafetería |
| Discoteca | Después | Bailar, noche dura, no es el bar |
| Plaza | **Fuera del mapa V0** o parcela muda | Redundante con parque al inicio. Útil en fiesta mayor *más tarde* |
| Bingo | Después | Humor, recuerdos («la noche del bingo»). No abre la V0 |
| Gimnasio | Después | Cuerpos, encuentros; no aporta cita distinta a parque/cafetería aún |
| Tienda de ropa | Con ropa | Si la ropa sale de la V0, la tienda también |
| Mirador | Después | Cita romántica; el parque cubre «afuera» al inicio |
| Casa | No parcela | Intimidad cuando el vínculo lo pida; **fuera de la prueba** o al final |

**Crítica:** el catálogo de 14 es bueno como *pueblo a largo plazo*. En la V0, **enseñar 11 candados** es ruido y contradice la maqueta *y* el pueblo vacío. Recomiendo mapa V0 = 3 abiertos + **3 candados visibles** (cine, bar, arcade). El resto ni se dibuja todavía (la arquitectura de datos sí los tiene).

---

## 10. Relaciones y mantenimiento

Las 4 variables se quedan. El jugador ve **color** (Bien / Regular / Crisis / Mal), no el desglose.

### Frecuencia (orden de magnitud)

| `necesidad_contacto` | Quiere verse | Amarillo si pasas | Rojo / ⚠️ |
| --- | --- | --- | --- |
| Baja | cada 4–5 días | +1 día | +2 |
| Media | cada 3 días | +1 | +2 |
| Alta | cada 2 días | el siguiente | +1 |

No es un daily de 10 parejas. Con 2 citas/día y 4 parejas, *ya* eliges. Eso es el juego.

Personalidad: `alta` en gente intensa/ansiosa; `baja` en caseros independientes. Sale de la ficha, no de un slider oculto extra.

### Demasiadas citas

Repetir el mismo plan o citar cada día a la misma pareja: la atracción no sube (o baja un poco: agobio). El diario se ríe. No hay que inventar un sistema de «stamina de cita» más allá de: **rendimientos decrecientes** + ánimo.

### Que no sea administración

- Tope **2 citas/día** (3 cuando el pueblo sea grande, no en V0).
- Las parejas verdes **no piden** clic. Solo las amarillas/naranjas y los ⚠️.
- Cinta de parejas de la maqueta = el gestor. No un segundo Excel.
- Encuentros espontáneos **no** cuentan como cita completa; a veces dan un pelín de vínculo o de tentación.

Nacimiento de pareja: tras una cita que no sea desastre **y** atracción mínima en *algún* sentido. Si B está en «bueno, sin más», puede nacer igual como «están quedando» (estado blando) sin boda. Umbral fino = prueba, no fórmula ahora.

Casa: fuera de V0.

---

## 11. Diario y narrativa breve

Motor **local**, catálogos. Cero IA en partida.

Una línea de diario o de cita es:

```text
evento_id + voz + (ánimo) + (lugar) + (resultado) → variante
```

Ejemplo pedido:

- `evento_id`: `llega_tarde_cita`
- `voz`: `seca` | `dramatica` | `tranquila`
- fallback: si no hay variante, frase neutra del evento

Campos mínimos del catálogo de frases:

`id`, `evento_id`, `voz[]`, `animos[]`, `lugares[]`, `resultado[]`, `texto`, `tipo_diario` (si va al diario), `indicador`.

El motor elige: filtro exacto → si vacío, ignora ánimo → ignora lugar → neutra. Nunca un párrafo. 1–2 líneas.

Cupo 5–10/día: **prioridad** problema > pista > info > ruido. Se rellenan con ruido hasta un mínimo de 5 para que el pueblo respire, sin pasar de 10. Los ⚠️ no se tiran por el cupo: si hay 3 crisis, hay 3 ⚠️ y menos chistes ese día. Eso es correcto.

ChatGPT fabricará el banco etiquetado. Cursor/juego solo selecciona.

---

## 12. Coletillas (sistema, no el cancionero)

Una coletilla por **golpe** de UI, no por cada NPC.

Categorías:

| id | Cuándo |
| --- | --- |
| `aqui_hay_tema` | Crush, triángulo, llegada jugosa, cita que chispea |
| `aqui_no_hay_tema` | Cita tibia, rechazo amable, cero química |
| `aqui_habia_tema` | Ruptura, marcha, «se enfrió» |
| `aguante_tu_madre` | Desastre cómico, plan mal elegido |
| `fenomenal` | ⚠️, incendio, coincidencia mala |
| `tu_sabras` | El jugador ignora un aviso / elige raro |
| `si_pasa` | Ruido, resignación, humor seco |

Sitios: coletilla del día (una, al despertar); cierre de cita; desbloqueo; llegada. El diario **no** lleva coletilla en cada línea (sería ruido). Máximo una coletilla a la vez en pantalla.

Banco: mismas reglas que las frases de evento (`id`, `categoria`, `texto`). Neni/ChatGPT escriben; el juego elige por categoría + un poco de azar entre 2–3 del mismo cajón.

---

## 13. Descubrimiento de atracción (con respeto)

**Prohibido:** dado → cambia orientación.

**Modelo:**

- `atraido_por` es el mapa **estable** de la persona. La mayoría no se reescribe.
- Puede existir un **interés concreto** `hacia_persona_id` que *no* estaba en el mapa, si `apertura_descubrimiento` no es `cerrada`, hay vínculo o historia (varias coincidencias, una cita impuesta por el jugador que sale bien, cuidado en crisis), y la química va hacia **esa persona**, no hacia «los hombres» de golpe.
- El jugador lo ve como pista (`👀`), nunca como «ahora es bi +20».
- Solo tras **varios** hitos con sentido se puede **ampliar** `atraido_por` (reconocimiento). Es raro, reversible en UI solo como texto de ficha descubierto, y no se usa para gag.

Personajes `cerrada`: no hay excepción. `permeable`: un caso en toda la partida como mucho. `abierta`: puede pasar antes, igual con causa.

Si esto se siente a tropo, no se pone en la V0: se deja el campo en ficha y se activa en V0.5. Recomiendo **campo sí, evento no** en la primera prueba (así el roster ya nace bien) y **un** personaje permeable como test cuando Neni quiera.

---

## 14. Qué simplificaría de lo que ya tenemos

- **No 16 en el mapa el día 1.** Pool 16, pueblo 10.
- **No 14 edificios visibles.** 3 + 3 candados.
- **No ropa ni tienda en V0.**
- **No casa, no bingo, no gym, no mirador, no plaza** en la prueba.
- **No visitantes** hasta que las llegadas sepan bien. Marchas: modelo consentido en `PROPUESTA_VIDA_POOL_Y_MARCHAS.md` (puede retrasarse *usarlas* en la primera prueba).
- **No temporada con días cifrados** en V0: jugar un pueblo que no se acaba; el primer «cierre de temporada» puede ser un resumen cuando pidas, no un timer.
- **No 9 capas visibles ni 4 barras.** 4 números internos, 1 color.
- **No «6 variables de personaje».** Ficha + ánimo + flag.
- **No ayuntamiento como 4.º café.**
- **No IA en runtime.**

---

## 15. Qué añadiría yo (y no estaba)

1. **Tope de citas/día** como regla de oro de dificultad.
2. **Flag `nuevo`** con caducidad, no un estado más.
3. **`voz` en la ficha** para que el banco de frases sea industrializable.
4. **Candados visibles limitados** (3) para que el vacío se lea y el deseo de desbloquear también.
5. **Rechazo simple de cita** (trabajo / ánimo / sitio 🔒). Si siempre aceptan, el jugador es un dios y se acaba el juego.
6. **Llegada como hito**, no como goteo de calendario.
7. **Las parejas verdes no llaman.** El juego tiene que enseñar a ignorar lo sano.
8. **Validar el grafo en cada alta**, no solo al diseñar los 16.

---

## 16. Riesgos

| Riesgo | Mitigación V0 |
| --- | --- |
| 3 sitios × 10 personas = siempre los mismos cruces | Bienvenido (encuentros). Variar franjas y «en casa» |
| 10 personas × diario 5–10 = o silencio o spam | Prioridad ⚠️/👀; ruido con cupo |
| Llegadas rompen parejas a lo loco | La mayoría de llegadas son neutras; ver § llegada |
| Orientación-ruleta | `apertura` + persona concreta + fuera de V0 el evento |
| Micromanagement | 2 citas/día, verdes mudas |
| Pueblo persistente sin objetivo = se apaga | Un objetivo blando de prueba (abajo), no una derrota |
| Maqueta vs 3 sitios | Aceptar que la PNG es tono |
| Grafo cojo al marcharse | No marchar en V0 |

---

## 17. Qué entra exactamente en la primera prueba jugable

Cuando se programe (aún no):

- Mapa: 3 abiertos + 3 🔒 (cine, bar, arcade)
- 10 residentes (del pool de 16 fichas)
- Padrón en UI (tarjetas + filtros)
- Diario 5–10 + 1 coletilla/día
- 2 citas/día, 4 variables internas, colores de pareja
- Rutina 3 bloques, encuentros que a veces son 👀
- Dinero/fama visibles, 1–2 desbloqueos posibles
- 1–2 llegadas por hito, máximo 14
- Placeholders de caricatura
- Rechazo tosco de citas
- Cero ropa, cero casa, cero temporadas-timer, cero visitantes, cero marchas, cero IA

## 18. Qué se queda fuera de esa prueba

Ropa y tienda; casa; plaza/bingo/gym/mirador/discoteca/restaurante (datos sí, mapa no); visitantes; mudanzas; bodas/divorcios como sistema (una ruptura simple sí); reputación punitiva; 9 capas con pesos finales; prompt de arte masivo; banco de 400 frases (con 30–40 de prueba basta); objetivos de temporada duros; el habitante 15–16 en pantalla.

---

## Llegadas que no son crisis (reglas)

Al entrar alguien, se tira **una** categoría, sesgada por química real (atracción previa calculada en silencio) y por si hay parejas frágiles (conflicto alto o contacto desatendido):

| Dado sesgado | Efecto |
| --- | --- |
| Frecuente | Nada. Diario info. Halo nuevo. |
| A menudo | Amistad / conocido con 1–2 residentes |
| A veces | Interés romántico **hacia un soltero** |
| Raro | Química con alguien **emparejado** → 👀, no ruptura |
| Muy raro | Triángulo de verdad (solo si la pareja ya estaba amarilla/naranja) |

Nunca: llegada + divorcio el mismo día. El jugador tiene margen (los mismos 1–2 días del ⚠️).

---

## Cómo crece después sin rehacer

Todo es id + catálogo: personajes, sitios, frases, coletillas, ocupaciones, vetos. El máximo simultáneo y los huecos/día son **números de partida**, no código de ficha. Las temporadas envuelven el mismo pueblo. Los 6 del pool que no salieron el día 1 son el tutorial del sistema de llegadas.
