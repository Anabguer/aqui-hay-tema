# Composición completa del pueblo — medidas y zonas

Carlos II. Puzzle entero ANTES de fabricar PNG definitivos.
Escala ancla: cafetería C3 `cafe_temprano.png` = **1301×814** (aspecto ≈ 1,60).

## Unidad de escala

| Símbolo | Valor | Notas |
|---|---|---|
| **MC** | ancho de la cafetería C3 en mapa | Módulo Café = referencia de presencia |
| **MC_h** | alto de la cafetería C3 en mapa | ≈ 0,625 × MC |
| Tablero propuesto | **2880 × 1620 px** (16:9) | PC primero |
| MC en tablero | **600 px** | ≈ 46 % del master C3; presencia legible; el mapa se adapta a ella |
| MC_h en tablero | **375 px** | |

Tokens (referencia, no se redibujan aquí): 52 px móvil / 62 px PC. Delante de cada fachada se reserva un **apron** ≥ 70 px (≈ 0,12 MC) para cabezas.

Regla: **COMPLEJO → evolución máxima → parcela → calles → mapa**.  
No hay seis parcelas idénticas. Cada una tiene tamaño distinto.

---

## 1. Café & Libros (`cafe_libros`)

| Fase | Contenido | Ancho relativo | Alto relativo |
|---|---|---|---|
| F1 | Cafetería | **1,00 MC** | **1,00 MC_h** |
| F2 | + Biblioteca (ala derecha) | **1,55 MC** | 1,00 MC_h |
| F3 | + Tienda (ala derecha de biblio) | **2,10 MC** | 1,00 MC_h |

- **Parcela reservada (siempre F3):** 2,10 MC × 1,25 MC_h ≈ **1260 × 470 px** (incluye apron frontal).
- **Dirección de crecimiento:** hacia la **derecha** (este). Núcleo café anclado a la izquierda de la parcela; no se desplaza.
- **Espacio F1 libre:** ~1,10 MC a la derecha = suelo continuo / jardín / farola (no caja vacía etiquetada).
- **Apron tokens:** delante de la fachada, zona inferior de la parcela.
- **Calle:** carretera ilustrada al este y al sur de la parcela.
- **Posición tablero (bbox %):** left **3%**, top **4%**, width **44%**, height **29%**.

---

## 2. El Rincón de Lola (`rincon_lola`)

| Fase | Contenido | Ancho | Alto |
|---|---|---|---|
| F1 | Restaurante | **0,85 MC** | **0,95 MC_h** |
| F2 | + Bingo (ala / cuerpo trasero-derecho) | **1,15 MC** | **1,35 MC_h** |

- **Parcela F2:** ≈ **690 × 510 px**.
- **Crecimiento:** hacia **abajo-derecha** (bingo como volumen secundario, no encima del toldo).
- **Apron:** frontal sur.
- **Calle:** oeste (separa de parque) y sur.
- **Bbox %:** left **68%**, top **5%**, width **28%**, height **32%**.

---

## 3. Cine Game (`cine_game`)

| Fase | Contenido | Ancho | Alto |
|---|---|---|---|
| F1 | Cine (marquesina) | **0,95 MC** | **1,05 MC_h** |
| F2 | + Arcade (ala derecha) | **1,60 MC** | 1,05 MC_h |

- **Parcela F2:** ≈ **960 × 430 px**.
- **Crecimiento:** hacia la **derecha**.
- **Apron:** frontal.
- **Calle:** norte (horizontal) y este.
- **Bbox %:** left **3%**, top **48%**, width **36%**, height **28%**.
- **Nota anti-sorpresa:** el arcade **cabe** porque la parcela F2 ya está reservada; no se inventa detrás del cine en un hueco menor.

---

## 4. La Mala Idea (`mala_idea`)

| Fase | Contenido | Ancho | Alto |
|---|---|---|---|
| F1 | Bar | **0,80 MC** | **1,00 MC_h** |
| F2 | + Discoteca (ala / cuerpo lateral) | **1,40 MC** | **1,20 MC_h** |
| F3 | + Karaoke (tercer volumen) | **1,85 MC** | **1,35 MC_h** |

- **Parcela F3:** ≈ **1110 × 540 px** (la más ancha tras el café).
- **Crecimiento:** **derecha** (disco) y **abajo-derecha** (karaoke, volumen más bajo).
- **Apron:** frontal; noche = menos verde, más farola/neón dibujado.
- **Calle:** oeste y norte.
- **Bbox %:** left **58%**, top **42%**, width **39%**, height **34%**.

---

## 5. Parque (`parque`)

| Fase | Contenido | Forma |
|---|---|---|
| F1 | Parque + fuente | irregular, rompe cuadrícula |
| F2 | + Picnic (claro SO) | + zona césped/mesas |
| F3 | + Mirador (loma NE) | + pequeño altozano |

- **Parcela F3:** ≈ **900 × 720 px** (no rectangular perfecto).
- **Crecimiento:** **hacia fuera** desde el núcleo fuente: picnic SO, mirador NE.
- **Tokens:** caminos internos, no solo apron de fachada.
- **Calles:** rodean; el parque **rompe** la cuadrícula.
- **Bbox %:** left **36%**, top **18%**, width **32%**, height **42%**.

---

## 6. Gimnasio & Spa (`gimnasio_spa`)

| Fase | Contenido | Ancho | Alto |
|---|---|---|---|
| F1 | Gimnasio | **0,90 MC** | **0,90 MC_h** |
| F2 | + Spa (ala derecha, más baja/clara) | **1,45 MC** | 0,90 MC_h |

- **Parcela F2:** ≈ **870 × 400 px**.
- **Crecimiento:** hacia la **derecha**.
- **Apron:** frontal.
- **Calle:** norte.
- **Bbox %:** left **28%**, top **72%**, width **32%**, height **24%**.

---

## Piezas de apoyo (no complejos de destino)

| Pieza | Rol | Bbox aprox. |
|---|---|---|
| Bloque A | viviendas | left 2%, top 78%, 14% × 16% |
| Solar B | futuro bloque | left 88%, top 8%, 10% × 18% |
| Solar C | futuro bloque | left 88%, top 75%, 10% × 16% |

---

## Red viaria (lenguaje ilustrado)

- Calles **finas dibujadas** (gris medio, borde tinta orgánico, cebra puntual).
- Separadores, no protagonistas.
- Misma gramática que `prueba-suelo-ilustrado` (aprobada de lenguaje).

---

## Checklist anti-sorpresa

- [x] Café F3 cabe sin mover el núcleo.
- [x] Arcade cabe a la derecha del cine (parcela 1,60 MC).
- [x] Karaoke + disco caben en Mala Idea F3.
- [x] Bingo cabe sin tapar la fachada de Lola.
- [x] Picnic + mirador no expulsan la fuente.
- [x] Spa cabe a la derecha del gym.
- [x] Aprons ≥ 70 px delante de fachadas.

## Qué NO es esto

No es PLAY. No sustituye `suelo_calles.png`. No pide PNG definitivos a C3 todavía.