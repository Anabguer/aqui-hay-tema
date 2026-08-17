# Vida de los habitantes

# El jugador propone. Los personajes viven.

Simulación **sencilla**. Existe para disponibilidad, contexto, encuentros, humor, pequeñas historias y sesgos ligeros en las citas.

## Mínimo por personaje (cerrado)

- ocupación
- franja habitual de disponibilidad
- hobby principal
- estado de ánimo simple
- lugar o lugares preferentes

Sin esto no hay encuentros ni citas útiles. Auditoría bloques Vida y Juego: `CHECKLIST_AUDITORIA_PERSONAJES.md`.

## Agenda (propuesta — ampliación del mínimo)

Cada residente tiene **agenda propia** (slots, bloqueos, citas). No es Outlook ni Los Sims.

- **Granularidad candidata:** 1 h (07–23), jugador ve horas normales.
- **Capas:** estructural (trabajo, sueño, rutinas) · programado (citas) · autónomo · temporal.
- Celestine **propone**; aceptación/rechazo con **voz**. Rechazo puede **descubrir** rutinas.
- Citas **ocupan** slots. UI sugiere «primera hora compatible» — el jugador no revisa 16 calendarios.

Detalle completo: `PROPUESTA_AGENDA_DISPONIBILIDAD_RUTINAS.md`.

La `franja_disponibilidad` de ficha pasa a ser **semilla**, no el horario entero.

## Prohibido (cerrado)

No añadir:

- hambre
- higiene
- vejiga
- necesidades domésticas detalladas
- simulación laboral **compleja** (Excel de sueldos, productividad, ascensos)

Sí: una **franja de trabajo** que ocupa a la persona, y un **goteo tonto** de dinero a la comunidad. Eso no es un simulador de empleo.

## Autonomía (dirección)

Los NPC no esperan a Celestine para ir a un sitio. **Coincidir no es interactuar.** Aforo, lugar lleno y oportunidades: `MAPA_Y_LUGARES.md`, `ENCUENTROS_ESPONTANEOS.md`. El diario no loguea cada café.

## Qué más puede existir (ligero, no obligatorio en el primer prototipo)

Familia y amigos (otro sistema), ropa, historial reciente de 1–3 días, una rutina que explique por qué pisa la cafetería. Si no cambia disponibilidad, encuentro, diario o cita, se deja inerte.

El **cansancio** del texto anterior no es una barra Sims: como mucho, un flag o el propio ánimo («ha dormido fatal»).

## Ocupaciones de referencia (cerradas como catálogo, no como fichas)

Ver `data/catalogos/profesiones.json`:

- sanitario/a
- oficina
- camarero/a
- comercio
- docente
- estudiante adulto
- autónomo/freelance
- jubilado/a

No son clases rígidas. Sirven para mañana / tarde / noche / flexible y para mucha o poca disponibilidad. Al escribir los 16, usar este catálogo (repartir, no inventar un noveno oficio). Ver `FASE_DISENO_POOL_16.md`.

## Hobbies → lugares

Siguen empujando visitas. Si el sitio está 🔒, el comportamiento concreto es **pendiente** (diario, sustituto, frustración).

Con solo 3 sitios abiertos al inicio, los hobbies de cine/discoteca/arcade no pueden «vivir» ahí hasta el desbloqueo.
