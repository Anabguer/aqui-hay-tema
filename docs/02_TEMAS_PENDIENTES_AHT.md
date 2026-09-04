# Temas Pendientes AHT

**Propósito:** Documento canónico de diseño pendiente. Referencia única para decidir cuándo y cómo implementar Reto Semanal e Historia del Pueblo.

**Nota:** Este archivo sustituye la referencia genérica que antes agrupaba ambos conceptos bajo un mismo pendiente. A partir de ahora son dos sistemas independientes.

**Estado general:** EXCLUSIVAMENTE documental. NO implementar código, NO diseñar condiciones definitivas, NO fijar días de aparición, NO cambiar balance.

---

## A) 🎯 RETO SEMANAL

**Estado:** 🔴 NO IMPLEMENTAR — pendiente de PlayTest longitudinal.

**Concepto:**

Es el equivalente de largo recorrido de las Misiones Diarias. NO es un recuerdo ni un achievement.

El juego propone al jugador un objetivo que requiere intervenir activamente durante varios días de juego.

**Características aprobadas en concepto:**

- Normalmente un reto activo a medio plazo;
- Puede durar aproximadamente varios días / una semana de juego;
- El plazo definitivo dependerá del PlayTest y del pacing real;
- Puede requerir varias acciones y varios motores;
- Puede mostrar progreso/contador cuando tenga sentido;
- Debe adaptarse al estado real de la partida;
- No debe pedir algo todavía imposible por progresión.

**Ejemplos conceptuales (NO condiciones definitivas):**

- Conseguir que determinadas personas desarrollen una amistad fuerte;
- Conseguir una pareja dentro del plazo;
- Conseguir que X vecinos estén felices;
- Conseguir determinada situación social en el pueblo;
- Reunir X personas o conseguir determinadas interacciones/lugares;
- Mejorar una situación relacional durante varios días.

**Cómo debe sentirse el jugador:**

Observar → decidir qué necesita hacer → organizar varios planes/intervenciones → utilizar Relaciones, Necesidades, lugares, emociones, etc. → intentar alcanzar el objetivo antes del plazo.

**Contador/progreso:**

Puede existir cuando sea representable. Ejemplo conceptual: "20 vecinos felices" → progreso 13 / 20.

**Gate de implementación:**

NO implementar ni cerrar catálogo todavía. El PlayTest deberá ayudar a determinar:

- Qué retos son realmente posibles;
- Cuándo pueden aparecer;
- Dificultad;
- Duración;
- Condiciones;
- Contadores;
- Pacing.

Mantener APARCADO hasta disponer de suficiente información longitudinal del PlayTest.

**Referencia en PLAN_MAESTRO:** §25.2 (misión semanal conceptual), §25.12 (capa 4 de progresión), §25.15 (orden futuro propuesto, punto 3).

---

## B) 📸 HISTORIA DEL PUEBLO

**Estado:** 🔴 NO IMPLEMENTAR — pendiente de PlayTest longitudinal.

**Concepto:**

Sistema independiente del Reto Semanal. Es un ÁLBUM DE LA HISTORIA DE ESA PARTIDA.

NO pide al jugador que consiga nada. Registra acontecimientos importantes que han sucedido real y autónomamente en el pueblo.

**Características aprobadas en concepto:**

- Álbum con muchas fotografías/recuerdos;
- Puede contener 50, 60 o más si existen suficientes acontecimientos reales;
- NO fijar artificialmente un número;
- Inicialmente las fotografías aparecen "sin revelar";
- Visualmente deben parecer fotografías todavía ocultas/sin revelar, con textura;
- Cuando sucede el acontecimiento correspondiente, la fotografía se revela;
- Aparece la imagen real correspondiente al capítulo;
- Se registra quién/quiénes lo consiguieron/protagonizaron;
- Se registra el día real de la partida;
- Puede conservar contexto adicional si existe de forma fiable.

**Ejemplos conceptuales de hitos:**

- Primera cita;
- Primera pareja;
- Primera amistad profunda / mejores amigos;
- Primera ruptura;
- Primera reconciliación;
- etc.

NO asumir este catálogo como definitivo. Un agente de PlayTest realizará una auditoría específica de todos los acontecimientos reales que el juego puede producir/detectar.

**Orden del álbum:**

El álbum puede tener un orden temático/editorial, pero NO obliga a que los acontecimientos ocurran en ese orden.

Ejemplo: La casilla "Primera amistad profunda" puede estar antes que "Primera pareja", pero una partida puede conseguir primero la pareja. En ese caso:

- Se revela primero la fotografía de Primera pareja;
- La anterior permanece sin revelar;
- Cada recuerdo muestra el día real en que ocurrió.

Esto es comportamiento correcto.

**Cabecera / progreso:**

Debe existir un resumen conceptual tipo: "Historias descubiertas: X / TOTAL" o copy AHT equivalente. El número final dependerá del catálogo real.

**Recompensa (idea de producto aprobada para estudiar):**

Cuando se descubre un nuevo capítulo/hito de Historia del Pueblo → Celestine puede recibir un pequeño regalo → reutilizando Regalitos V2 / inventario existente → para posteriormente regalárselo a un vecino.

NO crear economía paralela. NO implementar todavía. Auditar primero si puede reutilizarse limpiamente Regalitos V2.

**Gate de implementación:**

NO implementar nada. NO crear código preparatorio. NO crear schemas. NO añadir flags. NO modificar balance. NO modificar tests salvo que exista un test puramente documental obligatorio por gobernanza. Esta sección es EXCLUSIVAMENTE documental.

El gate obligatorio antes de implementar (§25.16 del PLAN_MAESTRO):

1. Terminar la cola actual de playtests funcionales;
2. Estabilizar romance y declaraciones;
3. Validar densidad social/autonomía;
4. Disponer de estadísticas longitudinales suficientes;
5. Conocer aproximadamente en qué días/rangos suelen ocurrir: conocidos, amistades, interés, citas, parejas, eventos y otros hitos importantes;
6. Revisar estos datos con Neni;
7. Decidir entonces requisitos y dificultad.

**Principio fundamental:** NO CALIBRAR LA HISTORIA DEL PUEBLO POR INTUICIÓN. UTILIZAR LAS DISTRIBUCIONES REALES DEL PLAYTEST.

**Referencia en PLAN_MAESTRO:** §25.8–25.11 (concepto y tipos de hitos), §25.12 (capa 5 de progresión), §25.15 (orden futuro propuesto, punto 2), §25.16 (gate obligatorio), §25.17 (criterio de éxito).

---

## Principio fundamental

**RETO SEMANAL:**
"Intenta conseguir esto."

**HISTORIA DEL PUEBLO:**
"Esto ha ocurrido en tu pueblo y queda para siempre como recuerdo."

**No volver a fusionar ambos conceptos.**

---

## Relación con otros sistemas

| Sistema | Relación con Reto Semanal | Relación con Historia del Pueblo |
|---------|--------------------------|----------------------------------|
| Misiones Diarias | Reto es su equivalente de largo plazo | Sin relación directa |
| Relaciones | Reto puede pedir mejoras relacionales | Hist. registra hitos关系ales reales |
| Necesidades | Reto puede usar estado emocional como condición | Hist. puede registrar situaciones Necesidades |
| Lugares | Reto puede requerir acciones en lugares | Hist. puede registrar eventos en lugares |
| Autonomía NPC | Reto depende del estado real autónomo | Hist. registra consecuencias de autonomía |
| Regalitos V2 | Sin relación directa | Posible recompensa al descubrir hito |

---

## Pendientes derivados (NO implementar hasta PlayTest)

- Catálogo cerrado de Retos Semanales posibles →取决于 PlayTest.
- Catálogo cerrado de hitos de Historia del Pueblo →取决于 auditoría de PlayTest.
- Dificultad y pacing de Reto Semanal →取决于 datos longitudinales.
- Condiciones de aparición de Reto Semanal →取决于 progresión real.
- Contadores/progreso representables →取决于 MVP jugable.
- Recompensa concreta de Historia del Pueblo →取决于 Regalitos V2 auditados.
- UI de ambos sistemas →取决于 Design System.
