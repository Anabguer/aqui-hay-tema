# Mapa y lugares

Tablero de juguete, más grande que la referencia 01. **No se diseña celda a celda en esta fase.**

## Inicio (cerrado 2026-08-18)

**Día 1:** solo la **cafetería** está **operativa** como lugar social. ~3 residentes (Rocío + B + C por decidir).

**Mapa base:** edificios y lugares del catálogo pueden **existir desde el principio** pero **cerrados** (persiana bajada, en obras, abandonado, sin actividad…). **No** aparecen mágicamente al desbloquear.

Al desbloquear un lugar:

1. Celestine recibe aviso contextual (buzón o similar).
2. El jugador invierte / rehabilita / compra (mecánica por definir).
3. El edificio **cambia visualmente** y pasa a **operativo**.

**No fijado todavía:** precio, día exacto, catálogo completo visible en mapa, número exacto de edificios dibujados.

En **datos**, los tres lugares iniciales del catálogo siguen siendo cafetería, parque, biblioteca. Parque y biblioteca: visibles cerrados hasta su desbloqueo.

En el **mapa de la V0** se ven además candados conceptuales: cine, bar, arcade. El resto del catálogo existe en datos para fases posteriores.

Precios y fama: **null**.

```text
🔒 Cine
500 € / Fama 10     ← ejemplo, no canon
```

## Catálogo base (cerrado)

| id | Nombre | Inicial | Mapa V0 | Notas |
| --- | --- | --- | --- | --- |
| `lug_biblioteca` | Biblioteca | sí | abierto | |
| `lug_cafeteria` | Cafetería | sí | abierto | |
| `lug_parque` | Parque | sí | abierto | |
| `lug_cine` | Cine | no | candado | |
| `lug_plaza` | Plaza | no | fuera | |
| `lug_restaurante` | Restaurante | no | fuera | Uno solo (no barato/bonito) |
| `lug_bar` | Bar | no | candado | |
| `lug_discoteca` | Discoteca | no | fuera | |
| `lug_bingo` | Bingo | no | fuera | |
| `lug_arcade` | Arcade | no | candado | |
| `lug_tienda_ropa` | Tienda de ropa | no | fuera | Ropa no jugable en V0 |
| `lug_gimnasio` | Gimnasio | no | fuera | |
| `lug_mirador` | Mirador | no | fuera | |
| `lug_casa` | Casa | no aplica | fuera | **No es parcela comercial.** Sitio de quedar / intimidad. Distinto de los **apartamentos** de cada bloque (`PROPUESTA_VIDA_POOL_Y_MARCHAS.md`). |

Sitios sociales (cafetería, biblioteca, cine, bingo…) son **compartidos** por residentes de todos los bloques abiertos. Candidato: Bloque A y, más adelante, Bloque B como **dos edificios-icono** en el mismo mapa de villa, no dos tableros. **No implementar B** en la primera prueba. Ver vivienda **no** es elegir portal: `PROPUESTA_VIDA_POOL_Y_MARCHAS.md`.

## Aforo (cerrado como principio; cifras no)

Cada lugar social tiene **aforo** propio: cuántas cabezas caben **a la vez** en esa parcela.

Motivo: **legibilidad visual** (PC y móvil) + jugabilidad. No queremos 16 ni 32 cabezas dentro de la cafetería.

Ejemplos de *intención* (NO cifras):

- cafetería: pequeña/media
- biblioteca: algo mayor
- bingo, cine: mayor
- mirador: pequeño

Los números se deciden cuando haya **prueba real del mapa**. La legibilidad manda.

### Lugar lleno

Un NPC autónomo que quiere entrar y no cabe **no** reserva mesa. Puede:

- ir a otro lugar disponible
- hacer otra actividad
- quedarse en su **apartamento**
- posponer el plan

Citas **organizadas por Celestine:** sí hay que comprobar **aforo + horario** antes de confirmar. Sin reservas de mesas ni microgestión del local.

## Coincidencia ≠ interacción (cerrado)

Que dos o más habitantes estén en el mismo lugar **no** implica que hablen.

El lugar genera **oportunidades**. El motor, según contexto y relaciones, puede:

- hablar · saludar · ignorar · sentarse juntos · conocer a alguien
- fortalecer amistad · sentir atracción · roce · discutir
- **no ocurrir nada**

El jugador puede ver «Rocío y José están otra vez juntos en la cafetería» y **sospechar**, sin que el juego garantice un hecho. El diario puede reportar coincidencia (👀) sin afirmar conversación.

Detalle de resultados: `ENCUENTROS_ESPONTANEOS.md`.

**Presencia:** la agenda alimenta qué cabezas hay en cada sitio (trabajo = off-map; cita = en lugar a la hora). Fade in/out; sin animación de calles. `PROPUESTA_AGENDA_DISPONIBILIDAD_RUTINAS.md`.

## Casa

No se compra con dinero/fama como el cine. Es opción de fases de relación, intimidad, convivencia o eventos. Umbral **pendiente**.

## Cuadrícula

Sigue siendo obligatoria en referencias importantes de mapa. Esta fase no dibuja coordenadas.

## Qué hace un lugar

Sesgo de perfiles, hobbies, **quién está presente** (hasta el aforo), clima. El desbloqueo abre opciones de cita **y** de vida NPC. Estar dentro = oportunidad, no encuentro automático.
