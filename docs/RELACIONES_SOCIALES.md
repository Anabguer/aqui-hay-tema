# Relaciones no románticas

Familia, amigos, compañeros, conocidos, ex. Modelo listo; no es un simulador social gigante.

## Parentescos que **vetan** romance (cerrado)

Capa 1. No hay compatibilidad romántica legal entre:

- padre/madre ↔ hijo/a
- hermanos
- medio hermanos
- abuelo/a ↔ nieto/a
- tío/a ↔ sobrino/a
- primos hermanos

El modelo **sí** guarda estas relaciones (eventos, diario, «Paqui ha discutido con su hermana»). Solo se prohíbe el romance.

Otros tipos (no vetan): amigo/amiga, mejor_amigo, compañero, conocido, ex, **roce**.

**Plan de amistad** (candidato): Celestine puede proponer un encuentro con intención amistosa. Valor propio, no preludio obligatorio de romance.

## Relaciones no lineales (propuesta)

**No** hay una sola escalera `desconocido → amigo → pareja`.

Rutas distintas: amistad→romance, atracción directa, roce→enemistad, y — **raro y causal** — enemistad→tensión→atracción→romance.

Vínculo **no binario** entre dos personas: afecto, confianza, tensión, atracción (asimétrica) pueden combinar estados como «me cae bien pero no confío», «me irrita pero me tira», «amigos con tensión».

Detalle: `PROPUESTA_AGENDA_DISPONIBILIDAD_RUTINAS.md` § Relaciones.

`enemistad` = **estado derivado** (tensión alta + afecto bajo), no parentesco. Catálogo `tipos_relacion_social.json` no necesita fila `enemigo` obligatoria.

Vista social por habitante: lazos **descubiertos** (pareja, amigo, familia pública, ex conocido, roce visto…). No secretos. En móvil, lista; no un ovillo de 16. `PROPUESTA_VIDA_POOL_Y_MARCHAS.md`.

Semilla de diseño: `personaje.lazos[]` = `{ persona, tipo, publico }`. Catálogo: `tipos_relacion_social.json`.

Siguen pudiendo inflar eventos: Lucía no soporta a Eva; Marta sale con Eva → diario / crisis.

## Dos registros

- `relacion_social` (incluye parentesco)
- `relacion_romantica` (las 4 variables)

El parentesco vetado no genera registro romántico legal.

Árbol familiar: mínimo, **con** las fichas. Sugerencia de esqueleto en `FASE_DISENO_POOL_16.md`. No un clan de 16.

Ningún parentesco de esta lista puede aparecer como opción romántica. Se comprueba en auditoría nivel 1 (ficha) y nivel 2 (pool): `CHECKLIST_AUDITORIA_PERSONAJES.md`.
