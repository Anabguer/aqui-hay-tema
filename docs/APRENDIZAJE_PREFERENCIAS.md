# Aprendizaje / cambio de preferencias

**Candidato. No es implementación. No es canon de cifras. No entra en la V0 programable todavía.**

Los gustos de pareja **no son siempre estáticos**. Una experiencia relevante puede dejar huella. Solo si hay **causalidad**: el rasgo tiene que haber sido *causa* del bien o del mal, no decorado de la escena.

No queremos:

```text
cita mala → se reescribe un gusto al azar
```

Queremos:

```text
evento relevante
→ impacto
→ causa ligada a un eje
→ probabilidad de aprendizaje
→ cambia o no cambia
```

Una cita desastrosa porque la otra persona prioriza los videojuegos y la ignora **sí** puede enfriar «me gustan las personas muy gamers».

Una cita mala porque el restaurante estaba cerrado **no** toca los videojuegos.

Orientación / `atraido_por` / `apertura_descubrimiento`: **otro mecanismo** (`PROPUESTA_JUGABLE_V0_CURSOR.md` §13). Este sistema **no** los usa como premio ni como castigo.

---

## Qué mejoramos respecto a la idea inicial

1. **La causalidad es un dato, no un comentario.** El evento declara `causas[]`. Si no hay causa que apunte a un eje, no hay tirada.
2. **Quién soy ≠ qué busco.** Este sistema mueve *preferencias sobre la otra persona*. No reescribe hobby, rasgos públicos, voz ni estilo social del personaje. Rocío no deja de ser observadora porque una cita salga mal.
3. **El texto de ficha no lleva números.** Seguimos sin peso 0–100 en `preferencias_romanticas` (`CONTRATO_PERSONAJE.md`). La intensidad vive en **ejes de motor**, no en la prosa que lee Neni.
4. **Una tirada, un eje.** Un divorcio no dispara un escopetazo a diez gustos. Se elige el eje con más evidencia causal.
5. **Primero moretón, luego cicatriz.** Casi todo empieza como huella **temporal**. Lo **consolidado** pide repetición o un golpe muy grande + tirada extra.
6. **Las cifras de abajo son hipótesis de simulador**, no ley del pueblo. Hay que verlas en headless antes de clavarlas.
7. **No modelamos (aún) el error de atribución** («culpar a los gamers porque el restaurante cerró»). Sería gracioso y caótico. Fuera de esta propuesta.

---

## Modelo

### Dos capas

| Capa | Dónde | Qué es |
| --- | --- | --- |
| Prosa de ficha | `romance.preferencias_romanticas` / estéticas / dealbreakers | Identidad escrita. Texto. Sin peso. |
| Eje de motor | catálogo `ejes_preferencia` + runtime | Lo que el aprendizaje puede mover |

Un texto de ficha **puede** apuntar a un eje (`eje: "eje_videojuegos"`). Si no apunta, el motor no lo usa para aprender; sigue valiendo como voz.

### Estado de un eje (por personaje)

```text
tipo                categoría del catálogo
valor               id del rasgo/eje (p. ej. eje_videojuegos)
intensidad_base     semilla de diseño, no se pisa
plasticidad         la del personaje (global V0)
aprendizaje         delta consolidado (partida)
temporal            delta que caduca
experiencias[]      ids de recuerdos/eventos que lo tocaron
estado_actual       clamp(base + aprendizaje + temporal)
```

Ejemplo conceptual (no escala canónica):

```text
eje_videojuegos
intensidad_base:  +2
plasticidad:      media
aprendizaje:      -1
temporal:         -2
estado_actual:    -1     # recelo, no fobia identitaria
```

### Escala recomendada para simular (no canon)

Enteros **-2 … +2**. Cinco ticks, legible, encaja con «no hay %»:

| Valor | Lectura interna |
| --- | --- |
| +2 | le tira mucho ese perfil |
| +1 | le tira |
|  0 | neutro / no es tema |
| -1 | recelo / precaución |
| -2 | rechazo claro (aún no es dealbreaker absoluto) |

Por qué no +3/−3 de entrada: el +3 invita a sudoku y a destrozar una predilección de un golpe. Si el simulador pide más grano, se abre a −3…+3 **después**.

`estado_actual` se **recorta** a −2…+2. Una sola cita no puede saltar de +2 a −2.

---

## Qué se puede modificar (y qué no)

### Sí (preferencias sobre *la otra persona*)

| Tipo de eje | Ejemplos |
| --- | --- |
| hobby / planes | videojuegos, copas, cine, deporte, bingo… |
| estilo social | fiestero, casero, intenso, planificador… |
| personalidad | cabezota, vanidoso, reservado, bromista… |
| estética / look | elegante, atrevido, cómodo… (`ROPA_Y_LOOKS.md`, cuando exista) |
| ritmo | mensajes constantes, prisa por convivir, calma |
| estilo de cita | planes grandes vs sencillos |
| estilo de vida | noctámbulo, muy casero |
| edad *como sesgo suave* | «bastante mayor / menor que yo» — lento, nunca de un plantón |

### No (identidad y orientación)

| Campo | Por qué |
| --- | --- |
| `genero`, `pronombres` | identidad |
| `atraido_por` | mecanismo §13, no este |
| `apertura_descubrimiento` | no se gasta aquí |
| `hobby_principal`, rasgos públicos/ocultos, `voz`, `estilo_social` *propio* | quién es, no a quién busca |
| `rasgos_fisicos`, cabeza maestra | arte |
| parentesco / vetos | capa 1 legal |
| dealbreaker `absoluto` de ficha | no se crea ni se borra por una cita |

Nacer un **rechazo absoluto** por aprendizaje en V0: **prohibido**. Lo máximo es −2 + una `precaucion` (ver más abajo).

---

## Causalidad (el filtro que manda)

Todo evento que pueda enseñar lleva:

```text
causas: [
  { eje, polaridad: +1|-1, evidencia: 1|2|3, sujeto: "la_otra_persona" }
]
```

Reglas:

1. La causa nombra un **eje** del catálogo, no «la cita en general».
2. El rasgo tiene que haber sido **saliente** en la otra persona (lo tiene de verdad, o el microevento iba de eso).
3. `evidencia` 1 = de fondo; 2 = claro; 3 = el conflicto *era* eso.
4. Si el resultado malo/bueno se explica por **contexto** (sitio cerrado, lluvia, horario, un tercero, un micro de lugar), las causas son de contexto. **No se copian** a hobbies de la pareja.
5. Sin causas con `evidencia ≥ 2`, **no hay tirada** de aprendizaje. El evento puede igual mover las 4 variables de relación.

Ejemplo:

| Qué pasó | Causas legales | Causas ilegales |
| --- | --- | --- |
| Le ignora toda la noche por el móvil / partida | `eje_videojuegos` −, evidencia 3 | — |
| El restaurante cerrado | `contexto_lugar` (no es eje de pareja) | `eje_videojuegos` |
| Discusión porque es fiestero y ella quería casa | `eje_fiestero` −, evidencia 3 | `eje_edad` |
| Cita genial *porque* se rieron igual | `eje_humor` +, evidencia 2–3 | reescribir orientación |

`contexto_lugar`, tráfico, servicio, clima: pueden joder la cita; **no** entran en este sistema.

---

## Eventos disparadores

No todos pesan igual. «Cita mediocre» **no** dispara.

| Evento | ¿Puede disparar? | Impacto base (hipótesis) | Notas |
| --- | --- | --- | --- |
| Cita tibia / mediocre | no | — | Solo las 4 variables |
| Cita muy mala | sí, si causa clara | 2 | Hace falta evidencia ≥ 2 |
| Cita excepcionalmente buena | sí, si causa clara | 2 | Misma barra, signo + |
| Plantón | sí, si la causa es desprecio/prioridad, no un autobús | 2 | Si fue un imprevisto de contexto: no |
| Discusión grave *sobre ese rasgo* | sí | 3 | |
| Traición | sí, ejes de confianza/lealtad/vanidad según el hecho | 4 | No «ahora odio el cine» |
| Ruptura | sí, si el motivo de ruptura apunta a un eje | 3 | |
| Divorcio / fin de convivencia grave | sí | 4 | Sobre todo si hay historial de la misma causa |
| Reconciliación importante | sí | 2 | Puede **suavizar** un recelo |
| Relación larga muy positiva | sí, poco a poco | 2 | Mejor por repetición que por un día |
| Experiencia repetida +/− | sí | +1 por repetición (tope) | La 3.ª vez de la *misma causa* |
| Evento traumático/memorable (tono del juego) | sí | 3–4 | Ridículo público, abandono en crisis… no gore |

El **divorcio** no es un botón mágico: si se van por un parentesco imposible o porque uno se muda, no hay eje de hobby que aprender.

---

## Cadena (cálculo conceptual, no fórmula de código)

```text
1. ¿El evento está en la tabla de disparadores? si no → stop
2. Recoger causas con evidencia ≥ 2
3. Quedarse con 1 eje: mayor (evidencia × |polaridad|), desempate al azar
4. ¿Cooldown de ese eje? si sí → stop (memoria «lo sentí, no cambió»)
5. Calcular p_cambio
6. Tirada
7. Si falla → memoria interna, sin diario, sin delta
8. Si acierta → aplicar TEMPORAL (±1)
9. ¿Puede consolidar? (repetición o impacto ≥ 4 o temporal ya activo en el mismo signo)
   → segunda tirada más baja → APRENDIZAJE ±1
10. Escribir memoria. Diario solo si el cambio se nota (paso 8 fuerte o paso 9)
```

Dirección:

| Experiencia | Efecto posible (si acierta) |
| --- | --- |
| Negativa sobre un eje en + | baja temporal; a veces aprendizaje −1 |
| Negativa sobre un eje en 0 | temporal −1 (nace recelo) |
| Negativa sobre un eje ya en − | puede reforzar hacia −2; puede nacer `precaucion` |
| Negativa + dealbreaker `leve`/`fuerte` del mismo tema | puede **subir severidad un grado**, nunca a `absoluto` por este sistema |
| Positiva sobre un eje en − | temporal +1 (el prejuicio se agrieta) |
| Positiva sobre 0 | nace gusto temporal +1 |
| Positiva sobre + | refuerzo temporal; consolidar a +2 es lento |

Una cita mediocre **no** destruye un +2. Un +2 contrario solo se mueve −1 temporal, y consolidar el enfriamiento pide repetición o ruptura/divorcio con esa causa.

---

## Probabilidades iniciales (hipótesis de simulador)

Plasticidad **media**. Un solo eje. Evidencia ya filtrada (≥ 2).

| Situación | p propuesta | Por qué |
| --- | --- | --- |
| Incidente pequeño pero relacionado | 5 % | Casi nunca; si no, todo el pueblo gira cada martes |
| Cita muy mala / muy buena, causa clara | 15 % | Se nota a veces; no es automático |
| Plantón atribuible a esa persona/rasgo | 20 % | Duele más que una cena floja |
| Discusión grave de ese rasgo | 25 % | Ya es conflicto nombrado |
| Ruptura cuyo motivo es ese rasgo | 30 % | Cierra un capítulo |
| Traición ligada al eje | 35 % | Raro y marcado |
| Divorcio / fin grave tras **conflictos repetidos de la misma causa** | 45–50 % | Lo más alto; aún falla a menudo |
| Relación larga muy positiva, misma causa (hito, no cada día) | 20 % | El cariño enseña despacio |
| 3.ª experiencia igual (misma causa, mismo signo) | +10 puntos sobre la fila que toque, techo 50 % | La racha importa más que el golpe suelto |

Ajustes (se multiplican; luego se recorta a **50 %** máximo en V0 de prueba):

| Factor | Efecto |
| --- | --- |
| Plasticidad baja | × 0,4 |
| Plasticidad media | × 1 |
| Plasticidad alta | × 1,6 |
| `\|intensidad_base\| = 2` y el cambio va **en contra** | × 0,5 |
| Ya hay temporal activo en el mismo eje y signo | consolidar: p aparte = la mitad de la p que acertó |
| Cooldown | p = 0 |

Ejemplo: ruptura 30 % × baja 0,4 × predilección en contra 0,5 ≈ **6 %**. Casi nunca. Eso es lo que queremos para gente cabezota.

Divorcio 50 % × alta 1,6 → se recorta a **50 %**. Nadie «aprende siempre».

**No canonizar** estos % hasta ver el simulador. Si en 60 días todo el pueblo ha girado, bajar. Si nadie cambia nunca, subir un peldaño o la repetición, no el incidente pequeño.

---

## Temporal vs consolidada

| Huella | Qué es | Cómo nace | Cómo se va |
| --- | --- | --- | --- |
| **Temporal** | Moretón. «Ahora mismo no quiero fiesteros.» | Casi todo acierto de tirada | Baja 1 tick cada **7 días** de pueblo sin reforzar. A 0, desaparece |
| **Consolidada** | Cicatriz / aprendizaje. Cambia el sesgo de verdad | 2.ª tirada, o 2.ª experiencia igual mientras el temporal sigue vivo, o impacto 4 con acierto | No caduca sola. Solo otra experiencia **contraria** (misma cadena) puede mover `aprendizaje` 1 tick hacia 0 o el otro signo |

Duración típica del moretón: **7–21 días** según impacto (2 → ~7; 4 → ~21). Hipótesis.

No hay «permanente absoluto» salvo la prosa de ficha y los dealbreakers `absoluto` de diseño. El pueblo no se fosiliza, pero tampoco amnesia semanal.

---

## Plasticidad

Campo de ficha (diseño, no runtime):

```text
romance.plasticidad_preferencias: baja | media | alta
```

| Valor | Lectura |
| --- | --- |
| `baja` | Cambia poco. Cabezota afectiva. Default bueno para gente mayor de carácter cerrado, no por edad automática |
| `media` | La mayoría |
| `alta` | Reinterpreta más; no es veleta: el techo de p sigue en 50 % |

V0: **una** plasticidad global. Plasticidad por categoría (hobbies vs estética) se deja para si el simulador demuestra que hace falta. Si falta el campo en una ficha ya escrita: tratar como `media` en simulación; ChatGPT lo rellena en la siguiente pasada.

No atar plasticidad a `apertura_descubrimiento`. Una persona `cerrada` en orientación puede ser `alta` en hobbies, y al revés.

---

## Precaución (objeto nuevo, blando)

Cuando un eje llega a −2 consolidado, puede nacer:

```text
precaucion: { eje, texto_interno, origen_evento, visible_jugador: false }
```

No es capa 1. Sesga citas y preparación (`PREPARACION_DE_CITAS.md`). El jugador puede enterarse por diario 👀, no por un icono de veto.

Subir un dealbreaker `leve` → `fuerte` del **mismo tema**: solo con consolidación + impacto ≥ 3. `fuerte` → `absoluto`: **no** por este sistema.

---

## Memoria

Cada intento (acierte o no) deja un recuerdo interno:

```text
{
  personaje,
  eje,
  evento_id,
  dia,
  polaridad,
  evidencia,
  resultado: cambio_temporal | cambio_consolidado | sin_cambio,
  causa_en_claro: "priorizaba el juego y la ignoró"
}
```

Sirve para auditoría y para prosa posterior: *«¿por qué ahora le molesta ese perfil?»*

El jugador **no** ve la ficha ni los %. Si acierta un cambio notable, el diario puede citar la **experiencia**, no el eje técnico.

Cooldown por eje: **14 días** tras un *cambio* (no tras un fallo). Hipótesis.

Tope por personaje y temporada (hipótesis, medir):

- máximo **1** consolidación cada **21 días**
- máximo **3** consolidaciones por temporada
- máximo **1** eje nuevo (de 0 a ≠0 consolidado) cada **14 días**

---

## Diario / narrativa

Solo si el cambio es temporal con impacto ≥ 3 **o** consolidado.

Tipo `pista`, indicador 👀. Una línea. Sin números. Cupo 5–10/día sigue mandando: si el día está lleno, se guarda para mañana o se descarta el más flojo.

Tono (no roster):

- 👀 Después de aquello, parece que mira a los fiesteros de otra manera.
- «Intensos ya he tenido suficientes, gracias.»
- 👀 Lleva unos días más abierta a planes caseros. Nadie sabe si dura.

Coletilla: no enganchar una coletilla a cada aprendizaje (ruido). El informe de cita sigue siendo el sitio del palo o del piropo.

---

## Impacto sobre compatibilidad

Sigue **sin** existir `compatibilidad = X%`.

| Capa | Efecto del aprendizaje |
| --- | --- |
| 1 dura | **Ninguno** sobre orientación, parentesco, absolutos de diseño |
| Atracción (variable) | Sesgo al *iniciar o empujar* atracción hacia alguien que porta ese rasgo |
| 3–5 (afinidad, complementariedad, personalidad) | El `estado_actual` del eje empuja, igual que una preferencia estática |
| 7 historial | Las memorias de aprendizaje *son* historial |
| Preparación de citas | Un eje descubierto por el jugador es información útil; uno no descubierto se elige a ciegas |

El pueblo no converge a «nadie datea gamers»: cada cual aprende **su** historia. Dos vecinos pueden salir de la misma discordia con huellas distintas (o una sí y otra no).

---

## Límites anti-caos (auditoría)

FAIL de diseño / de simulación si:

1. Un evento sin `causas` legales mueve un eje.
2. Un evento de contexto (sitio cerrado, clima) mueve un eje de pareja.
3. Más de un eje consolidado en el mismo evento.
4. Una cita mediocre mueve preferencias.
5. Un absoluto de ficha nace o muere por aprendizaje.
6. `atraido_por` o `apertura` cambian por este pipeline.
7. Hobby / rasgos / voz / cara del personaje cambian por este pipeline.
8. Más de 3 consolidaciones/temporada/personaje (alarma; no necesariamente bug si Neni lo quiere ver).
9. Tras muchas partidas, la **media del pueblo** de un eje se desplaza igual en todas las semillas → hay un imán global; hay que romperlo.
10. Un personaje con `\|aprendizaje\|` ≠ 0 en **más de la mitad** de sus ejes → ha perdido identidad; bajar p o topes.

ChatGPT **no** escribe deltas. Escribe plasticidad + textos + (opcional) enlace a eje. El motor aprende en partida.

---

## Cómo validarlo en el simulador

Añadir política `aprendizaje_on` / `aprendizaje_off` (control).

Horizonte: una temporada (cuando exista cifra) o **60 días** de pueblo de prueba.

Medir por personaje y por pueblo:

| Métrica | Alarma tentativa |
| --- | --- |
| nº ejes que se mueven (temporal) / personaje / 60 d | si media > 6: demasiado nervioso |
| nº consolidaciones / personaje / 60 d | si media > 3: demasiado plástico |
| % tiradas que aciertan | si ≈ 100 % o ≈ 0 %: la p está mal |
| % cambios **sin** causa legal | debe ser **0** |
| personajes que pierden su eje más fuerte (`base` ±2 invertido consolidado) | debe ser raro (p. ej. < 10 % de los que tienen base ±2) |
| distancia de vectores de ejes entre semillas distintas | si todas las partidas acaban en el mismo perfil medio: convergencia |
| correlación «evento restaurante cerrado» vs `eje_videojuegos` | debe ser ~0 |
| entradas de diario por aprendizaje / día | no comerse el cupo 5–10 |

Políticas útiles: `max_atraccion`, `monocultivo` (forzar siempre la misma causa) vs `aleatorio_legal`. El monocultivo debe producir **más** aprendizaje en un eje y **cero** en los no causados.

Dependencia: no simular esto antes de tener 16 fichas, 4 variables y un banco mínimo de micros **con causas**.

Detalle de preguntas: `SIMULADOR_DISENO.md`.

---

## Campos nuevos en el contrato

Detalle de entrega: `CONTRATO_PERSONAJE.md`. Resumen:

### Diseño (A, ChatGPT). No van en B

| Campo | Obligatorio | Valores |
| --- | --- | --- |
| `romance.plasticidad_preferencias` | sí, en fichas nuevas | `baja` / `media` / `alta` |
| `preferencias_romanticas[].eje` | no | id de catálogo o `null` |
| `dealbreakers[].eje` | no | para saber si un aprendizaje refuerza *ese* tema |

Los textos y `visible` / `tipo` / `severidad` **no cambian de formato**. Sigue **prohibido** poner peso numérico en A/B.

Fichas ya escritas (Rocío): **no se rellenan ahora**. En simulación, `media` si falta. ChatGPT lo añade en la siguiente pasada de esa ficha, sin tocar identidad.

### Runtime (el juego / simulador). Vacío en diseño

```text
runtime.aprendizaje_preferencias: {
  ejes: [ { id, aprendizaje, temporal, temporal_hasta_dia, experiencias[] } ],
  precauciones: [],
  huellas: [],
  cooldown_eje: { eje: dia_hasta }
}
```

`intensidad_base` se deriva al empezar partida: del enlace `eje` si existe, si no **0** (neutro) salvo que más adelante definamos un mapa texto→eje. No inventar bases en silencio para que «quede interesante».

### Catálogo

`data/catalogos/ejes_preferencia.json` — candidatos, no cerrados. Cada ítem: `id`, `tipo`, `nombre`, `notas`. Tipos: `hobby` `estilo_social` `personalidad` `estetica` `ritmo` `estilo_cita` `estilo_vida` `edad_sesgo`.

Citas / micros futuros: campo `causas[]` en el banco. Sin eso el sistema no puede ser honesto.

### Qué no se añade

- Plasticidad por categoría
- Error de atribución
- Cambio de orientación
- Números en el diario
- Fórmulas de código

---

## Relación con otros docs

- Citas y 4 variables: `RELACIONES_Y_CITAS.md`
- Preparar citas (info incompleta): `PREPARACION_DE_CITAS.md`
- Ropa como modificador, no como «mejor look»: `ROPA_Y_LOOKS.md`
- Compatibilidad: `SISTEMA_COMPATIBILIDAD.md`
- Diario: `DIARIO_DEL_PUEBLO.md`
- Descubrimiento identitario: `PROPUESTA_JUGABLE_V0_CURSOR.md` §13
- Plantilla: `data/personajes/_plantilla.personaje.json`

---

## Estado

Propuesta de diseño. **Neni / ChatGPT pueden recortar ejes o cifras.** Hasta que el simulador hable, no se programa y no se pisa el roster.
