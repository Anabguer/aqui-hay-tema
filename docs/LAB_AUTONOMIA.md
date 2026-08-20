# Lab de vida autónoma — Aquí Hay Tema

**Estado:** ARQUITECTURA APROBADA + CALIBRACIÓN REHECHA (lab 20/08). Cifras **no canon**. Movimiento del pueblo ≠ Cotilleo. No toca Relacional V1, B4/E3, B5 ni economía.

**Motor:** `src/Engine/AutonomiaSalidas.php`, `ComplejoCatalog.php`, `AforoEngine.php`, `LugarAutonomo.php`, `CotilleoNarrativo.php`  
**CLI:** `php dev/simulador_autonomia.php 7 3`  
**Semillas de este informe:** 7 días × 3 seeds × n = 8 / 16 / 32 / 48 · seed base `lab-autonomia`.

Copia de lectura: `docs/LAB_AUTONOMIA.md`.

---

## 1. Qué está cerrado (no reabrir)

- Casa = default.
- Máximo **1 salida autónoma por residente/día**.
- Plan de Celestine tiene prioridad (no pisa agenda, no duplica el slot).
- Ponderación por gustos / rechazos / rasgos / emoción.
- Horarios + aforo destino + aforo de complejo.
- Anti-repetición (no veto duro).
- Coincidencia ≠ cita.
- Interacción casual solo cuando toca (`InteraccionCasual`).
- No publicar cada salida. El Cotilleo es filtro narrativo.
- Coincidencia solo si hay residentes reales en el mismo sitio/hora.
- **V1 no organiza quedadas de dos.** Pueden salir, coincidir, hablar, casual, mover relación si corresponde. No existe “Carmen ha quedado con José a las 18:00” como plan autónomo. Eso es papel de Celestine.
- Relacional V1 no se toca.

### Cine / Arcade / Cine Game (Producto 20/08)

| Destino | Horario | Aforo |
|---|---|---:|
| Cine | 16:00–00:00 | 8 |
| Arcade | 12:00–00:00 | 8 |
| Complejo Cine Game | — | 12 (techo) |

Fin de franja **exclusive**.

---

## 2. Recalibración (el √n dejaba el pueblo vacío)

La calibración anterior `round(0.8√n + 0.6)` daba ~2.3 / 2.7 / 2.8 / 3.0 salidas/día. A n=48 casi nadie salía.

Dirección de estudio (no canon numérico): n=8 → 3–5; n=16 → 5–8; n=32 → 10–18; n=48 → 18–30.

**Cupo teórico:** `round(0.48 n + 0.5)` → 4 / 8 / 16 / 24.

Las `p_franjas` ya **no** son un dado que puede dejar el cupo a medias. Ponderan **cuándo** se reparte el cupo (más tarde que mañana). El motor persigue el objetivo acumulado de esa hora, con tope de arranques simultáneos.

| Pieza | Valor |
|---|---|
| Cupo pueblo | `round(0.48 n + 0.5)` |
| Máx. / persona / día | 1 |
| Arranques misma hora | 2 (n=8/16) · 3 (n=32) · 4 (n=48) |
| Pesos de franja | 07–12: 0.16 · 12–17: 0.28 · 17–22: 0.42 · 22–03: 0.26 |
| Anti-aislamiento | umbral 2 días, bonus 2.2 (sale quien lleva días en casa) |
| Cotilleo | mismo par + mismo lugar ≥ 3 días en 7 |

El lab desbloquea más destinos al crecer n (n=8: 6 sitios; n=48: mapa completo) para no saturar 6 locales. PLAY día 1 sigue con los operativos de la partida; el aforo recorta solo.

---

## 3. Resultados (7 d × 3 seeds)

Casa 24 h incluye sueño. La columna útil de “pueblo vacío” es **casa 10–22** y, sobre todo, **salidas/día** + **% que sale en 7 días**.

| n | cupo | salidas/día | % salen/día | % ≥1 vez / 7d | % nunca / 7d | coincid./día | casuales/día | Cotilleo/día |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| 8 | 4 | **3.00** | 37.5 % | 100 % | 0 % | 0.33 | 0.14 | 0 |
| 16 | 8 | **6.00** | 37.5 % | 97.9 % | 2.1 % | 1.10 | 0.38 | 0 |
| 32 | 16 | **11.90** | 37.2 % | 100 % | 0 % | 3.14 | 1.29 | 0 |
| 48 | 24 | **18.19** | 37.9 % | 100 % | 0 % | 5.24 | 2.57 | 0 |

Escala 8→48: **×6.06**. Antes era ×1.3. El crecimiento de población produce crecimiento real de vida autónoma.

| n | media fuera (24 h) | media fuera 10–22 | máx. simultáneos | máx. destino | máx. complejo | horas en aforo | rechazos aforo |
|---|---:|---:|---:|---:|---:|---:|---:|
| 8 | 0.22 | 0.43 | 2 | 2 | 2 | 0 | 0 |
| 16 | 0.45 | 0.87 | 4 | 4 | 4 | 0 | 0 |
| 32 | 0.91 | 1.68 | 6 | 4 | 6 | 0 | 0 |
| 48 | 1.33 | 2.40 | 9 | 4 | 4 | 2.7 | 0.3 |

Otras:

- Máx. salidas misma persona/día = **1** en todos los tamaños.
- Cafetería fuera de horario = **0**.
- Casa 10–22 ≈ 95 %: la mayoría sigue en casa (default). Lo que cambia es que **cada día sale ~38 %** y **en 7 días sale casi todo el mundo**.
- n=16 deja un 2.1 % sin salir en la semana (ruido de 1 persona × alguna seed). No hay residentes que pasen la semana enteros en casa a n=8/32/48.
- Cotilleo = 0 en 7 días: correcto. El umbral pide patrón de 3 días. Hay movimiento; no hay serial.
- El realizado (~75 % del cupo teórico a n grande) recorta por noche (menos locales abiertos) y por huecos de agenda. No se fuerza el techo.

Repetición persona/lugar (misma persona vuelve al mismo sitio otro día): 3.3 / 6.7 / 9.7 / 12.3 pares repetidos por run de 7 días. Anti-repetición suaviza; no veta. No hay un solo destino monopolizado (máx. ocupación destino 4, techos 8–12).

---

## 4. Lectura

El pueblo **ya no escala plano**. A n=48 salen ~18 personas/día, coinciden ~5 veces/día, hay ~2.6 interacciones casuales/día y El Cotilleo sigue en silencio. Eso es exactamente separar movimiento de acontecimiento.

No se ha convertido cada salida en evento. No se han creado quedadas autónomas de dos.

Banda del lab vs dirección de estudio: los cuatro puntos caen **dentro**. n=48 pisa el suelo (18). Subir más el cupo llenaría locales de noche; el lab no lo recomienda hasta ver PLAY con mapa real día 1 (menos destinos → más aforo).

---

## 5. Horarios y aforos

Fin de franja **exclusive**. Cafetería 08:00–20:00 ⇒ última hora posible = 19.

| Complejo (máx. simultáneo) | Destino | Horario | Aforo |
|---|---|---|---:|
| Café & Libros (10) | Cafetería | 08–20 | 8 |
| | Biblioteca | 10–20 | 6 |
| | Tienda (`lug_tienda_ropa`) | 10–20 | 4 |
| El Rincón de Lola (10) | Restaurante | 13–16 y 20–00 | 8 |
| | Bingo | 17–23 | 8 |
| Cine Game (12) | Cine | 16–00 | 8 |
| | Arcade | 12–00 | 8 |
| La Mala Idea (12) | Bar | 17–00 | 8 |
| | Discoteca | 22–04 | 8 |
| | Karaoke | 20–03 | 4 |
| Parque (16) | Parque | 07–22 | 12 |
| | Picnic | 10–20 | 8 |
| | Mirador | 08–00 | 4 |
| Gimnasio & Spa (10) | Gimnasio | 07–22 | 8 |
| | Spa | 10–22 | 4 |

---

## 6. Coincidencias, relación y canales

1. **Técnica:** dos o más en el mismo destino y hora. No teletransporta. No crea conocidos por sí sola.
2. **Interacción pequeña:** `InteraccionCasual`. Contacto leve. Relacional V1 no se toca.
3. **El Cotilleo:** mismo par, mismo lugar, ≥ 3 días en ventana 7.
4. **Buzón:** no recibe un correo por cada salida.
5. **Resumen de avance:** omite salidas `intencion=autonomo` y coincidencias no dignas.

---

## 7. Protecciones

| Riesgo | Protección |
|---|---|
| Misma persona varias veces al día | `max_salidas_por_residente_dia = 1` |
| Dos sitios a la vez | agenda |
| Pueblo plano al crecer n | cupo lineal |
| Todos salen a las 08:00 | pesos de franja (tarde > mañana) |
| Mismo sitio constantemente | peso ×0.08 hoy, ×0.28 ayer |
| Semanas en casa sin motivo | anti-aislamiento umbral 2 d |
| Cada salida = acontecimiento | autonomía ≠ hueco de Vida; Cotilleo selectivo |
| Cafetería a las 21 | `LUGAR_CERRADO` |
| Destino lleno | aforo destino + complejo; se cuenta el rechazo y se prueba otro sitio |
| Quedada NPC de dos | no existe en V1 |

---

## 8. Qué hay implementado

Flag ya existente `npc_autonomy_enabled` (ON en playtest_01). Sin flag extra.

`AutonomousPlanner` sigue placeholder. `acontecimientos_dia.activo_en_play` sigue false.

No UI. No assets. No Relacional V1.

---

## BLOQUEADO_DECISION

Ninguno de autonomía. Cine/Arcade cerrados. Quedadas NPC de dos: **no en V1**.

Economía sigue con sus 5 preguntas (ver `LAB_ECONOMIA.md`); no bloquean este motor.
