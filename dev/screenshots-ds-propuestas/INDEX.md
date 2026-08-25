# INDEX — Propuestas visuales DS (revisión nocturna)

Estado de TODAS las propuestas: **PROPUESTA — PENDIENTE DE OK VISUAL**.
Ninguna está implementada en runtime. Nada de esto está enlazado desde `play.php`.
Arnés: `dev/propuestas/arnes.html` + `propuestas.css` (exclusivos de revisión).

Regla cromática nueva aprobada por dirección (aplicada aquí y en Inicio v3):
**coral/naranja protagonista de la referencia → ROSA AHT** (familia Nuevo Plan/corazones/Parejas).
Lavandas, verdes, azules y beige siguen existiendo como complementarios.

| # | Pantalla | Fichero | Estado que representa | Mocks | Decisiones visuales | Dudas para OK |
|---|---|---|---|---|---|---|
| 02 | Mensajitos | `02-mensajitos.png` | 1 carta NUEVA + 1 caduca + 1 vista, tabs NUEVOS/TODOS (D3) | Contenidos de ejemplo | Carta nueva rosa con flag+ticket XP; CTA ABRIR rosa; VER lavanda; hint lavanda | ¿Tabs con badge dentro? ¿Copy "caduca en 15 h"? |
| 03 | Cotilleos | `03-cotilleos.png` | Filtros + cabecera de día + llegada + relación | Textos de ejemplo | Cards tintadas por categoría (verde/lavanda), flag NUEVO, filtro GUARDADOS visible como propuesta (D8 posterior) | ¿Incluimos filtro GUARDADOS en la migración visual o solo tras D8? |
| 04 | Vecinos | `04-vecinos.png` | Tabs D4 + buscador + 2 conocidos + 1 enfadado con alerta + 1 "Por conocer" | Estados de ejemplo | Pill contador rosa, anillos de emoción por pill, "Por conocer" punteado | ¿Alerta "!" solo donde el juego ya lo calcula (sí)? |
| 05 | Relaciones | `05-relaciones.png` | Pestaña RELACIONES: chips emoji + select + tarjeta A↔B bidireccional + simétrica | Datos de ejemplo | Filas direccionales con barras por sentido; separador "DIFERENTE EN CADA SENTIDO" | ¿Orden de chips? ¿"MALAS" visible siempre? |
| 06 | Ficha de vecino | `06-ficha-vecino.png` | Perfil + ánimo + rasgos/aficiones con slots + relaciones + agenda vacía + CTA | Slots y estados de ejemplo | Ribbons 4 colores; slots descubierta ✓ / bloqueada 🔒?; nav ‹ › + botón DIARIO (D5); CTA rosa | ¿Avatar XL a la izquierda o centrado arriba? |
| 07 | Estado de ánimo | `07-estado-animo.png` | Causa (animo_explicacion) + "Desde hoy" + bloque consecuencias | Consecuencias = estructura de la ref; **solo se renderizará con datos reales (D6)** | Banner emoción + causa en tarjeta + CTA organizar rosa | ¿Aprobada la sección "Mientras siga así" si se aprueba el puente de vista? |
| 08 | Diario del vecino | `08-diario-vecino.png` | Timeline con nodos por tipo, chips filtro, cabeceras de día (D7) | Entradas de ejemplo | Nodos emoji por categoría, borde de tarjeta por tipo, tag de categoría | ¿FAB "IR A FECHA" se descarta definitivamente? |
| 09 | Nuevo Plan | `09-nuevo-plan.png` | Pasos 1-4 + resumen TU PLAN + CTA (mapea 1:1 con data-org-*) | Selección de ejemplo | Pasos numerados rosa, lugar con "Cerrado ahora" real, resumen pegado con tape | ¿Sticky el CTA Crear plan en scroll largo? |
| 10 | Agenda | `10-agenda.png` | Planes agrupados por día | 2 planes de ejemplo | Cabeceras de día tipo cinta, cards con ⭐ | ¿Agrupar también "pasados"? |
| 11 | Misiones (modal) | `11-misiones-modal.png` | Hoy en el pueblo con hechas/pendiente | 3 misiones de ejemplo | Checks verdes + pill Hecha/Pendiente | ¿Contador arriba "2 de 3"? |
| 12 | Vida del pueblo | `12-vida-pueblo.png` | Modal corazón + valor + copy canónico | Valor 72/100 ejemplo | Barra de progreso + copy original | ¿Añadir historial de subidas/bajadas? |
| 13 | Intervención de encuentro | `13-intervencion-encuentro.png` | Panel dentro de EN CURSO: tema + 2 acciones + resultado | Tema/acciones de ejemplo | Panel lavanda dentro de la tarjeta; acciones CTA rosa/lavanda | ¿Las 2 acciones visibles siempre o colapsadas? |
| 14 | Notas de mapa | `14-notas-mapa.png` | Selector de lugar + ¿Quién está? (con horario real y presencia) | Lugares/vecinos de ejemplo | Chincheta rosa, pill "Cerrado ahora" real, lista con pills de emoción | ¿Botón "Quedarme un rato" dentro de la nota? |
| 15 | Tutorial | `15-tutorial.png` | Intro con checklist + finale | Copy de ejemplo | Papel con tape, checklist de pasos, CTA final rosa | ¿Checklist visible o solo texto? |
| 16 | Avisos + Derrota | `16-avisos-derrota.png` | plan-notif rosa + aviso mostaza + derrota | Textos de ejemplo | Toast como tarjeta papel; derrota con corazón apagado | ¿Tono de la derrota más suave o más dramático? |

## Pendientes sin propuesta esta noche
- **Lab/debug**: permanece técnico (sin diseño DS), según D10.
- **Colección / Ajustes**: no existen (D1, fuera de alcance).
- **Desktop completo**: se diseñará tras el OK del móvil (misma estética, composición de shell).

## Notas técnicas
- Avatares en gris: el arnés de propuestas no carga retratos reales; en runtime los pone el motor (`retrato_url`).
- Los mocks de contenido (nombres, textos) son ilustrativos; el copy real sale del motor.
- Fuente de verdad visual: `docs/design-system/` + `assets/css/design-system/`. Estas propuestas NO modifican nada de eso en runtime.
