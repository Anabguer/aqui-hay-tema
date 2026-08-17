# Sistema de compatibilidad

**No hay un único `compatibilidad = X%`.** El jugador no lo ve.

## Capa 1 (dura)

No hay cita ni relación romántica legal si:

- las reglas `atraido_por` de **ambos** no cubren al otro *para formar pareja*. Un crush **unilateral** sí puede existir si solo un lado encaja. El roster, eso sí, exige que cada ficha tenga ≥1 persona con la que *ambos* lados encajan (recíproco potencial).
- hay parentesco vetado
- hay dealbreaker absoluto
- (cuando existan) límites de edad de diseño

### Orientación: reglas, no etiquetas barrocas

La etiqueta visible puede ser corta. Por dentro, cada personaje declara por qué tipos de personas puede sentir atracción romántica:

- `mujer` / `hombre` / `no_binarie` (los mismos ids que `genero`)
- varios = lista de dos o tres
- `apertura_descubrimiento` no sustituye esta lista al validar el grafo

Eso alimenta el grafo. Ver `VALIDACION_GRAFO_ROMANTICO.md`.

## Atracción (variable principal + capa 2)

Asimétrica y dinámica: `atraccion_a_hacia_b` ≠ `atraccion_b_hacia_a`. Puede nacer baja y subir, o al revés. No se clava al generar la partida.

Es **una de las 4 variables de relación**, guardada en dos direcciones. No es un % de pareja.

## Otras capas (sesgos, no barras extra)

| # | Capa | Qué hace |
| --- | --- | --- |
| 3 | Afinidad | Hobbies/intereses compartidos |
| 4 | Complementariedad | Atrae aunque no se comparta |
| 5 | Personalidad | Tabla pendiente |
| 6 | Momento vital | Ánimo/franja del día |
| 7 | Historial | Citas, encuentros, recuerdos |
| 8 | Contexto | Lugar abierto, hora, ropa, clima |
| 9 | Azar controlado | Nunca matemáticamente segura |

Pesos: **no fijados**.

Las capas 3–9 **empujan** las 4 variables y el resultado de cita; no se muestran como barras.

Aprendizaje de gustos (candidato): un eje puede sesgar 3–5 y la atracción hacia quien porta ese rasgo. **No** toca capa 1 (orientación, parentesco, absolutos de diseño). `APRENDIZAJE_PREFERENCIAS.md`.

## Test de compatibilidad (candidato)

Juguete de UI: dos cabezas, escáner cutre, cubo de tono (**sin %**). Solo usa lo que el **jugador** ha descubierto. No es el evaluador interno. Un veto de capa 1 oculto no se delata.

**No está el día 1.** Se desbloquea / compra (tienda de herramientas). Usos limitados, info imperfecta. `PROPUESTA_VIDA_POOL_Y_MARCHAS.md`.

## Derivados (no se guardan como núcleo)

Ver mapping en `RELACIONES_Y_CITAS.md`: chispa, confianza, comodidad, deseo, riesgo de ruptura.
