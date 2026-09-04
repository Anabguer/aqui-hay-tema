# Diseño Funcional — Eventos Interactivos / Expansión del Loop

**Versión:** 2026-09-01 (Diseño funcional · NO implementar · Pendiente revisión Neni + ChatGPT)
**Estado:** PENDIENTE — BLOQUEADO POR PLAYTEST/CALIBRACIÓN (§25.16)

---

## A. Qué dice actualmente el Plan Maestro sobre eventos interactivos

### §25.3 — Eventos grandes interactivos (texto canónico)

> AHT YA TIENE eventos. No crear más eventos simplemente para solucionar la falta de jugabilidad. El objetivo futuro es HACER JUGABLES ALGUNOS EVENTOS QUE YA EXISTEN.

El Plan Maestro identifica el problema central: una vez el jugador tiene parejas favoritas y ha organizado citas, puede aparecer la sensación «¿Ahora qué hago?» (§25.1). La expansión debe utilizar los motores que AHT ya posee, no crear sistemas paralelos.

### §25.3 — Propuesta conceptual (resumen)

- Cuando exista un evento social compatible, mostrar `[ ENTRAR AL EVENTO ]`.
- El jugador entra en una escena especial donde aparecen los participantes.
- Celestine puede realizar intervenciones limitadas:
  - **A)** Acercar a dos personas.
  - **B)** Formar mesa/equipo.
  - **C)** Proponer un brindis/acción grupal.
  - **D)** Sembrar una conversación.
- Principio: Celestine EMPUJA. Los motores existentes resuelven.

### §25.4 — Los eventos deben producir historia real

Las intervenciones no desaparecen al cerrar la escena. Deben alimentar: relación direccional, memoria, emoción, predisposición futura, diario/hitos, cotilleos y futuros encuentros.

### §25.14 — Loop de juego objetivo (5 capas)

Los eventos interactivos forman parte de la **Capa 2 — Intervención de Celestine**: el jugador observa, investiga, propone, interviene en eventos e intenta ayudar o provocar situaciones.

### §25.15 — Orden futuro propuesto

Eventos interactivos aparecen como punto 4 de 4 en el orden conceptual:
1. Feedback de intervención / entrar en la mente
2. Historia del Pueblo
3. Misión semanal
4. **Eventos grandes interactivos**

### §25.16 — Gate obligatorio

> NO empezar esta expansión hasta: terminar playtests, estabilizar romance, validar densidad social, disponer de estadísticas longitudinales, conocer ventanas temporales reales.

### §19.5 — Eventos comunitarios (ya decidido)

Los residentes pueden proponer acontecimientos a Celestine mediante Mensajitos. Celestine decide: aceptar/rechazar, lugar, fecha/hora, lista de invitados. Los eventos tienen aforo limitado. Los eventos NO regalan amistad/romance: crean oportunidades.

### §19.7 — Personalidad social de los lugares

Cada tipo de lugar sesga los microeventos posibles. La tabla canónica define sesgos por lugar (bar → colegueo/coqueteo; karaoke → humor/vergüenza compartida; discoteca → química/impulsividad, etc.).

---

## B. Infraestructura real que ya existe y puede reutilizarse

### B1. Motor de eventos del pueblo (COMPLETO y operativo)

| Componente | Archivo | Estado |
|---|---|---|
| `EventosPuebloEngine` | `src/Engine/EventosPuebloEngine.php` (1185 líneas) | 🟢 Operativo |
| `EventosPuebloBridge` | `src/Engine/EventosPuebloBridge.php` | 🟢 Operativo |
| `EventosPuebloAnuncioEngine` | `src/Engine/EventosPuebloAnuncioEngine.php` | 🟢 Operativo |
| `EventosPuebloCierreEngine` | `src/Engine/EventosPuebloCierreEngine.php` | 🟢 Operativo |
| Catálogo `eventos_pueblo.json` | `data/catalogos/eventos_pueblo.json` | 🟢 6 eventos definidos |

**Qué hace actualmente:**
- Al comenzar el día, con probabilidad configurable (0.22), elige un evento del catálogo.
- Selecciona lugar, franja horaria, participantes disponibles.
- Celestine puede elegir asistentes (o el motor selecciona autónomamente).
- Crea un encuentro canónico (tipo `otro`, intención `evento_pueblo`).
- Anuncia el evento mediante Mensajito al buzón.
- Al terminar el encuentro, emite cierre narrativo.

**Qué NO hace hoy:**
- El evento es un encuentro multi-participante resuelto automáticamente.
- NO hay escena temporal jugable dentro del evento.
- NO hay intervención de Celestine durante el evento.
- NO hay zonas/contextos internos.
- NO hay microobjetivos.
- NO hay entrada/salida del evento.

### B2. Encuentros (COMPLETO y operativo)

| Componente | Archivo | Estado |
|---|---|---|
| `EncuentroEngine` | `src/Engine/EncuentroEngine.php` | 🟢 |
| `EncuentroLifecycle` | `src/Engine/EncuentroLifecycle.php` | 🟢 |
| `EncuentroResolver` | `src/Engine/EncuentroResolver.php` | 🟢 |
| `EncuentroExperiencia` | `src/Engine/EncuentroExperiencia.php` | 🟢 |
| `EncuentroPonderacion` | `src/Engine/EncuentroPonderacion.php` | 🟢 |
| `EncuentroDeltasReales` | `src/Engine/EncuentroDeltasReales.php` | 🟢 |
| `EncuentroOperations` | `src/Engine/EncuentroOperations.php` | 🟢 |

**Tipos de encuentro soportados:** `conocerse`, `quedar`, `amistad`, `primera_cita`, `cita`, `romantico`, `conflicto`, `otro`, `individual`.

**Estados:** `programado → en_curso → terminado/cancelado`.

La infraestructura de encuentros es robusta: validación de contexto, programación con agenda, resolución por participante, experiencia ponderada con RNG, deltas de relación por canal.

### B3. Intervención de Celestine durante encuentros (MENTES — COMPLETO)

| Componente | Archivo | Estado |
|---|---|---|
| `EncuentroIntervencion` | `src/Engine/EncuentroIntervencion.php` (1110 líneas) | 🟢 |
| `MentesTemas` | `src/Engine/MentesTemas.php` | 🟢 |
| API `intervencionEjecutar` | `api/handlers/EncuentrosHandler.php` | 🟢 |
| UI 2-pasos | `assets/js/play-v3.js` | 🟢 |
| CSS | `assets/css/play-v3-shell-art.css` | 🟢 |

**Qué hace:** Celestine elige quién rompe el hielo y de qué habla. El motor evalúa la elección (afinidad/neutro/aversión según hobbies conocidos). Influye, no determina, el resultado.

**Restricción actual:** Solo funciona en encuentros tipo `celeste_organizado`, con exactamente 2 participantes, en estado `en_curso`, una sola intervención por encuentro.

### B4. Lugares (COMPLETO)

| Componente | Archivo | Estado |
|---|---|---|
| `LugaresCanonicos` | `src/Engine/LugaresCanonicos.php` | 🟢 9 lugares |
| `LugarAtributos` | `src/Engine/LugarAtributos.php` | 🟢 aforo + duración |
| `LugarValidator` | `src/Engine/LugarValidator.php` | 🟢 |
| `LugarAutonomo` | `src/Engine/LugarAutonomo.php` | 🟢 |
| `AforoEngine` | `src/Engine/AforoEngine.php` | 🟢 |
| `PresenciaEngine` | `src/Engine/PresenciaEngine.php` | 🟢 |

**Lugares canónicos:** cafeteria, biblioteca, gimnasio, restaurante, parque, bar, cine, discoteca, bingo.

**Aforos:** cafeteria(8), parque(40), biblioteca(12), cine(16), restaurante(16), bar(14), discoteca(24), bingo(20), gimnasio(12).

### B5. Señal narrativa HayTema (COMPLETO)

`HayTema` detecta situaciones donde una persona en un lugar merece atención. Señales: cotilleo, patrón, discusión, señal romántica. Funciona como indicador visual en el mapa.

### B6. Sistemas narrativos (parcialmente apagados)

| Sistema | Estado | Relevancia para eventos |
|---|---|---|
| Cotilleos | 🟢 Operativo | Los eventos alimentan cotilleos |
| Mensajitos 2.0 | 🟢 Operativo | Anuncio y cierre de eventos |
| Emociones | 🟣 Apagado | Los eventos deberían modificar emociones |
| Diario | 🟣 Apagado | Los eventos deberían generar entradas |
| Discovery | 🟣 Apagado | Los eventos podrían revelar info |

### B7. Infraestructura técnica de soporte

- **RNG centralizado** (`RngService`): determinista, reproducible.
- **Domain Events + EventBus** (35 eventos):允许后端订阅事件。
- **CanalDeduplicador**: evita spam narrativo cruzando canales.
- **MemoriaEventos**: cooldowns por familia, recientes por residente.
- **RelacionBitacora**: hitos关系日志（常量REGALO已存在）。
- **PersistenciaCaps**: patrón de límites de guardado.

### B8. UI existente para eventos

- **CSS screens:** `inicio-evento-pueblo-mobile.css` y `inicio-evento-pueblo-desktop.css` ya existen.
- **play.php:** Slot `data-proximo-evento-slot` con card, tag, título, meta, icono y CTA.
- **play-v3.js:** Funciones `eventoPuebloCatalogoId`, `eventoPuebloImgSrc`, `eventoPuebloIcoHtml`, renderizado de cards de evento, CTA `PARTICIPAR`.
- **Assets:** 6 imágenes de eventos en `assets/play-v3/eventos/`.

---

## C. Qué falta realmente

### C1. Capa de escena temporal (NUEVA — nada similar existe)

No existe ninguna infraestructura para:
- Mantener un "estado de evento activo" que el jugador pueda entrar/salir.
- Renderizar una vista temporal diferente al mapa principal.
- Representar zonas internas de un evento (barra, mesa, escenario…).
- Gestionar la duración temporal de la experiencia.
- Controlar aforo visual de participantes dentro de la escena.

### C2. Acciones interactivas dentro del evento (NUEVA)

No existe un sistema de acciones específicas de evento. MENTES funciona solo para encuentros 1:1. Las acciones propuestas en §25.3 (acercar personas, formar mesa, brindis, sembrar conversación) no tienen implementación.

### C3. Microobjetivos (NUEVA)

No existe ningún sistema de objetivos temporales dentro de una escena.

### C4. Modo de entrada/salida (NUEVA)

No existe la concepto de "estar dentro de un evento" como estado de UI separado del mapa.

### C5. Feedback visual de resultado (PARCIAL §25.5-25.7)

§25.5-25.7 describen un "resultado visual de intervención" que tampoco está implementado ni para encuentros normales. Los eventos interactivos lo necesitan aún más.

---

## D. Loop completo del evento

### D1. Aparece

```
Reloj avanza → Al comenzar día → EventosPuebloEngine::alComenzarDia()
  → Probabilidad 0.22 → Elige evento del catálogo
  → Busca lugar + franja + participantes suficientes
  → Evento se registra en eventos_pueblo.programados[]
  → EventosPuebloAnuncioEngine emite Mensajito al buzón
```

**En el mapa (independiente de la escena):**
- El evento aparece en el slot `data-proximo-evento-slot` de play.php.
- Card con: icono del evento, nombre, día/hora, Participantes.
- CTA dinámico según estado:
  - `¿Quién va?` → si falta seleccionar asistentes.
  - `PARTICIPAR ›` → si el evento está en curso y el jugador puede entrar.

### D2. Celestine prepara

```
Celestine pulsa "¿Quién va?" → Modo organización
  → API evento_pueblo.elegibles → Lista de vecinos elegibles
  → Celestine selecciona asistentes (dentro de aforo)
  → confirmarAsistentes() → Crea encuentro canónico
  → Selecciona modo: 'celestine' o 'autonomo'
```

**Restricciones de selección:**
- Solo residentes activos.
- Solo disponibles en la franja del evento.
- Respeta aforo del lugar.
- Mínimo de participantes según catálogo.
- Se recomiendan vecinos por afinidad con el tipo de evento (ordenRecomendados).

### D3. El evento empieza (reloj cruza hora)

```
Reloj avanza → hora del evento
  → EncuentroEngine → estado: en_curso
  → [NUEVO] El evento se marca como "entrable"
  → [NUEVO] Aparece CTA "ENTRAR AL [EVENTO]" en la card del mapa
  → [NUEVO] Si hay participantes pendientes → selección autónoma
```

### D4. El jugador entra al evento

```
Jugador pulsa "ENTRAR AL [EVENTO]"
  → [NUEVO] Transición de UI: mapa → escena de evento
  → [NUEVO] Se carga la escena temporal
  → [NUEVO] El evento puede ocupar prácticamente toda la pantalla (móvil)
  → [NUEVO] Se muestran participantes, zonas, acciones disponibles
  → [NUEVO] Timer visible: tiempo restante del evento
```

### D5. El jugador interactúa

```
Dentro de la escena:
  → Celestine ve a los participantes distribuidos en zonas
  → Puede pulsar acciones contextuales:
     - Acercar a dos personas
     - Formar grupo/mesa
     - Proponer brindis/acción grupal
     - Sembrar conversación (tema)
     - Cada acción tiene coste: 1 intervención por evento (o más si se diseña)
  → Cada acción invoca motores canónicos existentes
  → Feedback narrativo inmediato
  → Resultado afecta a motores reales (social, emoción, memoria)
```

### D6. Motores resuelven

```
Cada intervención:
  → EncuentroExperiencia o sistema equivalente para evento grupal
  → Evaluación por participante: compatibilidad, relación previa, emoción, contexto
  → RNG modificado por carga de la intervención
  → Resultado: experiencia por participante (muy_mal…muy_bien)
  → Deltas sociales/románticos por canal direccional
  → Emociones (si el flag está activo)
  → MemoriaEventos: registro de familia/cooldown
```

### D7. Feedback

```
Feedback inmediato por intervención:
  → Tarjeta narrativa: "Carmen habló con Hugo sobre deportes. Hugo se animó."
  → Cambios emocionales visibles (si aplican)
  → actualización de barra de progreso del evento (si existe)
  → Posible Mensajito posterior (F9 seguimiento)
```

### D8. Termina

```
Reloj avanza → fin de duración del evento
  → EncuentroEngine → estado: terminado
  → [NUEVO] Se muestra resumen del evento
  → [NUEVO] Transición de escena → mapa (o resumen antes de volver)
  → EventosPuebloCierreEngine → emite Mensajito de cierre
  → Posible entrada de Diario (si flag activo)
  → Posible Cotilleo (si aplica)
```

### D9. Consecuencias

```
Post-evento:
  → Relaciones modificadas (duraderas)
  → Emociones resultantes (temporales)
  → MemoriaEventos: registro del evento + participantes
  → Posible hito en RelacionBitacora
  → Posible descubrimiento (DiscoveryEngine)
  → Posible Mensajito F9 (seguimiento)
  → Posible entrada de Diario (si flag activo)
  → Los cotilleos pueden mencionar el evento
  → Historia del Pueblo (cuando exista): hito si fue significativo
```

---

## E. Propuesta UX móvil

### E1. Transición de entrada

Cuando el evento está activo y el jugador está en el mapa:

1. La card del evento en el mapa cambia de estado: **fondo vibrante, pulso suave, CTA "ENTRAR"**.
2. Al pulsar: **transición tipo "cortina"** — el mapa se oscurece levemente, la escena del evento se "abre" desde abajo (coherente con la metáfora de entrar a un local).
3. La escena ocupa **~90% de la pantalla**. Arriba queda una barra delgada con: nombre del evento + tiempo restante + botón de salir.

### E2. Estructura de pantalla del evento

```
┌─────────────────────────────────┐
│  🎰 Noche de Bingo    ⏱ 1:32   │  ← Barra superior (fija)
│  [× Salir]                      │
├─────────────────────────────────┤
│                                 │
│  ┌─────────┐  ┌─────────┐      │
│  │ 🧑‍🤝‍🧑 Barra  │  │ 🎲 Mesa 1│      │  ← Zonas
│  │ Carmen  │  │ Hugo    │      │     (cards tapizables)
│  │ Fernando│  │ Laura   │      │
│  └─────────┘  └─────────┘      │
│                                 │
│  ┌─────────┐  ┌─────────┐      │
│  │ 🎤 Escen.│  │ ☕ Mesa 2│      │
│  │ (vacío) │  │ Manuel  │      │
│  └─────────┘  └─────────┘      │
│                                 │
├─────────────────────────────────┤
│  [🗣 Acercar] [🍻 Brindis]      │  ← Acciones disponibles
│  [💬 Convers.] [👋 Grupo]       │     (hasta 4 visibles)
├─────────────────────────────────┤
│  ░░░░░░░░░░░░░░░░░░░░ 2/5      │  ← Barra progreso (opcional)
│  Objetivos: 😄×2  🍻×1         │
└─────────────────────────────────┘
```

### E3. Representación de residentes

- **Cara/icono + nombre** en miniatura dentro de su zona.
- Si tiene emoción visible: pequeño emoji junto a la cara.
- Si tiene "hay_tema": indicador sutil (punto brillante o borde).
- Al pulsar un residente: tooltip rápido con su estado emocional actual + relación con otro residente seleccionable.
- **NO ficha completa** — eso sigue siendo del mapa. Aquí solo información contextual relevante para el evento.

### E4. Zonas

- Cada zona es una **card rectangular** con fondo sutil diferenciado.
- Nombre de zona + icono contextual.
- Participantes en esa zona (cara + nombre).
- Capacidad máxima visible (ej: "3/4").
- Al pulsar la zona: se amplía y muestra participantes con más detalle + acciones disponibles en esa zona.
- El número de zonas varía según evento:
  - Bingo: zona bingo, zona barra, zona mesa.
  - Bar: barra, mesa, pista, terraza.
  - Mercadillo: puestos, zona común, cafetería.
  - Fiesta: pista, barra, mesa, jardín/terraza.

### E5. Acciones

- **Botones de acción** en la parte inferior de la pantalla.
- Máximo 4 visibles simultáneamente; más acciones disponibles al desplazar horizontalmente.
- Cada acción muestra: icono + nombre corto + (si aplica) indicador de coste.
- Al pulsar una acción: se abre un **sub-selector** para elegir objetivo(s):
  - "¿A quién acercar?" → selector de 2 personas.
  - "¿Quién brinda?" → selector de 1 persona o grupo.
  - "¿De qué hablar?" → selector de temas (reutiliza MENTES si hay 2 personas).
- Feedback de la acción: **tarjeta narrativa superpuesta** que se cierra automáticamente tras ~2 segundos o al pulsar.

### E6. Tiempo restante

- **Barra de progreso lineal** en la parte superior.
- Color que cambia gradualmente: verde → amarillo → naranja → rojo (últimos 30s).
- Texto: "Quedan X:XX".
- Sin presión excesiva: el tiempo es generoso (~2 minutos reales como referencia, pero configurable).

### E7. Objetivos (si existen)

- Debajo de la barra de tiempo, una línea compacta:
  - `Objetivos: 😄×2  🍻×1  🗣×3`
  - Se actualizan en tiempo real.
  - Cuando se completa un objetivo: mini-animación sutil + sonido breve (si hay audio).
  - No saturar: máximo 3-4 objetivos visibles.

### E8. Salida y re-entrada

- Botón `[× Salir]` siempre visible arriba a la izquierda.
- Al salir: transición inversa → mapa.
- El evento **sigue activo** en el mapa (card pulsante).
- Puede volver a entrar cuantas veces quede tiempo.
- Las acciones ya realizadas persisten.
- Los participantes pueden moverse entre zonas autónomamente (el jugador no controla esto).

### E9. Cómo sabemos qué está ocurriendo sin saturar

- **Feed narrativo compacto** al pie de la escena (últimas 3-4 acciones/resoluciones).
- Se actualiza con cada acción del jugador y con eventos autónomos ocasionales.
- Los eventos autónomos (NPCs moviéndose, hablando, riendo) se muestran como **pequeños labels animados** sobre las zonas: "Carmen y Hugo están charlando", "Fernando se ríe".
- NO cada movimiento: solo los narrativamente relevantes.

---

## F. Propuesta desktop

### Estructura

La versión desktop sigue la misma lógica pero con más espacio:

```
┌──────────────────────────────────────────────────────────────┐
│  🎰 Noche de Bingo — Bar Villaborde     ⏱ 1:32  [× Salir]  │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────────┐  ┌──────────────────┐                  │
│  │   🎲 ZONA BINGO  │  │   🍻 ZONA BARRA  │                  │
│  │                   │  │                   │                  │
│  │  [Carmen] [Hugo]  │  │  [Fernando]       │                  │
│  │  [Laura]          │  │  [Manuel]         │                  │
│  │                   │  │                   │                  │
│  │  3/8 plazas       │  │  2/6 plazas       │                  │
│  └──────────────────┘  └──────────────────┘                  │
│                                                              │
│  ┌──────────────────┐  ┌──────────────────┐                  │
│  │   🎤 ESCENARIO   │  │   ☕ ZONA CAFÉ   │                  │
│  │  (vacío)          │  │  [Rafa]           │                  │
│  │                   │  │                   │                  │
│  └──────────────────┘  └──────────────────┘                  │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│  ACCIONES                                                    │
│  [🗣 Acercar personas] [🍻 Proponer brindis] [💬 Sembrar tema] [👋 Formar grupo] │
├──────────────────────────────────────────────────────────────┤
│  Feed narrativo:                                              │
│  "Carmen se acercó a Hugo en la mesa de bingo..."            │
│  "Fernando pidió una copa en la barra."                      │
├──────────────────────────────────────────────────────────────┤
│  ░░░░░░░░░░░░░░░░░░░░░░░░░ 2/5 objetivos completados        │
└──────────────────────────────────────────────────────────────┘
```

### Diferencias con móvil

- **Más zonas visibles simultáneamente** (no necesita scroll).
- **Feed narrativo siempre visible** (no superpuesto).
- **Acciones en fila horizontal** con más espacio para labels.
- **Tooltips en hover** sobre residentes (en móvil: toque largo o toque en cara).
- **Barra de progreso más detallada** con desglose de objetivos.

---

## G. Sistema de acciones/interacciones

### G1. Catálogo de acciones base

Acciones disponibles en cualquier evento interactivo:

| Acción | Icono | Objetivo | Coste | Efecto motor | Restricciones |
|---|---|---|---|---|---|
| **Acercar personas** | 🗣 | 2 residentes | 1 intervención | EncuentroExperiencia entre los 2 + compatibilidad oculta + relación previa | Los 2 deben estar en el evento; no son la misma persona |
| **Formar grupo/mesa** | 👋 | 2-4 residentes | 1 intervención | Crea sub-encuentro temporal: co-presencia forzada + experiencia grupal | Mínimo 2; máximo aforo de zona |
| **Proponer brindis** | 🍻 | 1+ residentes | 0.5 intervención (acción leve) | Microdelta social positivo a todos los participantes; posible emoción alegre | Al menos 2 personas en la zona |
| **Sembrar conversación** | 💬 | 2 residentes + tema | 1 intervención | Reutiliza MENTES: afín/neutro/aversión según hobbies conocidos | Requiere 2 personas + tema conocido por el jugador |
| **Intentar reír** | 😂 | 1 residente | 1 intervención | Depende de personalidad bromista, emoción actual, relación con Celestine | Solo si el residente no está enfadado/triste severo |
| **Proponer actividad** | ⚡ | 1+ residentes + actividad | 1 intervención | Crea contexto de actividad colectiva; posibles micro-interacciones | Actividad coherente con el lugar |

### G2. Acciones específicas por tipo de evento

| Evento | Acciones adicionales | Notas |
|---|---|---|
| **Bingo** | "Apuntar a alguien en la mesa", "Hacer equipo con...", "Proponer ronda de broma" | El bingo genera pique y cotilleo natural |
| **Bar/Tardeo** | "Invitar a una copa", "Presentar a dos desconocidos", "Animar la música" | El bar desinhibe; las acciones pueden ser más atrevidas |
| **Mercadillo** | "Llevar a alguien al puesto de...", "Compartir compra", "Descubrir afición en un puesto" | El mercadillo permite discovery por hobbies |
| **Karaoke** | "Proponer que cante X", "Hacer coro con X", "Elegir canción para Y" | El karaoke genera humor/vergüenza compartida |
| **Cine** | "Sentar junto a...", "Comentar la película con...", "Proponer debate" | El cine favorece intimidad o debate grupal |
| **Fiesta** | "Sacar a bailar a...", "Crear grupo de baile", "Proponer juego" | La fiesta maximiza interacción social/romántica |
| **Gimnasio** | "Hacer pareja de entrenamiento", "Proponer reto", "Animar a..." | El gimnasio genera compañerismo/pique |

### G3. Cómo se resuelven las acciones

**Principio reutilizar, no duplicar:**

Cada acción invoca un motor canónico existente. No hay un "motor de acciones de evento" nuevo.

| Acción | Motor invocado | Datos de entrada |
|---|---|---|
| Acercar personas | `EncuentroExperiencia::resolver()` (simula mini-encuentro) | Los 2 residentes + evento como contexto |
| Formar grupo | `EncuentroExperiencia::resolver()` (experiencia grupal) | N participantes + evento |
| Brindis | `SeleccionSocialPeso` + micro-delta social | Todos los presentes en zona |
| Sembrar conversación | `EncuentroIntervencion` (MENTES) adaptado a evento | Los 2 residentes + tema elegido |
| Intentar reír | `EncuentroExperiencia::resolver()` con carga de personalidad | Residente objetivo + personalidad |
| Proponer actividad | `PropuestaEncuentroEngine` simplificada | Actividad + participantes |

### G4. Límite de intervenciones por evento

**Hipótesis de diseño:** 3-4 intervenciones por evento como máximo.

**Razones:**
- Un evento dura ~2 minutos reales. 3-4 acciones dan ritmo sin saturar.
- Cada acción tiene peso narrativo: más de 4 diluye la importancia.
- Permite al jugador sentir que cada decisión importa.
- Evita "farmear" interacciones en un solo evento.

**Alternativa:** energía por tipo de acción:
- Acciones "pesadas" (acercar, formar grupo): 1 carga.
- Acciones "medianas" (sembrar, reír): 1 carga.
- Acciones "leves" (brindis): 0.5 carga.
- Total: 3-4 cargas por evento.

---

## H. Restricciones según relaciones/contexto

### H1. Matriz de disponibilidad de acciones

| Condición | Acercar | Grupo | Brindis | Conversación | Reír | Actividad |
|---|---|---|---|---|---|---|
| **Desconocidos** (social=0, conocidos=false) | ✅ | ⚠️ Solo si zona compartida | ✅ | ⚠️ Solo temas generales | ✅ | ⚠️ Limitada |
| **Conocidos** (social≥1, conocidos=true) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Amigos** (social≥20) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Conflicto activo** | ⚠️ Solo si coherente | ❌ | ⚠️ | ⚠️ | ❌ | ⚠️ |
| **Enfadado** (emoción) | ⚠️ | ❌ | ⚠️ | ⚠️ | ❌ | ⚠️ |
| **Triste** (emoción) | ✅ (animar) | ⚠️ | ⚠️ | ✅ (temas positivos) | ⚠️ | ⚠️ |
| **Pareja** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

### H2. Desbloqueos contextuales

- **Conocidos vs desconocidos:** Si dos personas no se conocen, "Sembrar conversación" solo ofrece temas generales (no hobbies específicos que Celestine no podría saber razonablemente en público).
- **Relación previa fuerte:** "Acercar" a dos buenos amigos no tiene el mismo impacto que acercar a dos desconocidos con afinidad. El motor pondera automáticamente.
- **Conflicto activo:** "Brindis" entre dos personas en conflicto puede tener resultado irónico (coherente con el tono AHT) o neutro.
- **Personalidad:** Una persona con rasgo "tímido" puede reaccionar peor a "Proponer que cante" que una "extrovertida".
- **Emoción actual:** Alguien enfadado no es buen candidato para "Intentar reír". Alguien triste puede beneficiarse de "Brindis".
- **Acciones anteriores del evento:** Si Celestine ya intentó acercar a X e Y y salió mal, reintentar inmediatamente tiene penalización (cooldown implícito dentro del evento).
- **Lugar:** Las acciones deben ser coherentes con la zona. No se puede "Proponer que cante" en la zona de bingo.

### H3. Qué NO puede hacer Celestine en eventos

- **Forzar romance:** Ninguna acción crea interés romántico directo.
- **Romper relaciones:** Ninguna acción provoca conflicto directo.
- **Controlar a NPCs:** Celestine no mueve a los NPCs entre zonas.
- **Garantizar resultados:** Cada acción modifica probabilidades, no resultados.
- **Acciones íntimas sin contexto:** No se puede "Besar" en un evento grupal (eso es para encuentros 1:1 privados).

---

## I. Microobjetivos

### I1. Concepto

Cada evento puede tener 2-4 microobjetivos que aparecen al entrar. Son retos pequeños, no misiones obligatorias.

### I2. Tipos de microobjetivo

| Tipo | Ejemplo | Cómo se mide | Recompensa |
|---|---|---|---|
| **Emoción** | "Consigue que alguien se ría" | `EmotionalStateService` → alegre en un participante | Progreso + recuerdo posible |
| **Social** | "Consigue que dos personas hablen" | Acción "Acercar" con resultado ≥ normal | Progreso + microdelta social |
| **Descubrimiento** | "Descubre un gusto de alguien" | Acción que revela hobby/gusto vía Discovery | Progreso + info jugable |
| **Coordinación** | "Consigue que 3 personas estén en la misma zona" | Estado de zonas en el momento | Progreso + ambiance |
| **Especial** | "Haz que alguien tome la palabra" | Acción "Proponer que cante/hable" con éxito | Progreso + posible hito |

### I3. Reglas de microobjetivos

- **Son opcionales:** El jugador puede ignorarlos y disfrutar del evento igual.
- **Son variables:** Cada ejecución del mismo evento puede tener microobjetivos diferentes.
- **Se desbloquean al completarse:** No hay castigo por no completar.
- **Algunos son raros:** Objetivos especiales que solo aparecen en combinaciones específicas de participantes.
- **No revelan la fórmula:** "Consigue que alguien se ría" no dice "necesitas que X tenga emoción alegre".

### I4. Generación de microobjetivos

La selección de microobjetivos depende de:
- **Quiénes están:** Si hay desconocidos → objetivo de conocimiento. Si hay amigos → objetivo de profundización.
- **El lugar:** Bar → "brindis grupal". Bingo → "hacer equipo". Karaoke → "que cante alguien".
- **El estado del pueblo:** Si hay alguien triste → "animar a alguien".
- **Historial:** Si Celestine nunca ha hablado con X → "inicia una conversación con X".

---

## J. Recompensas/progreso SIN economía monetaria

### J1. Recompensas internas al evento

- **Barra de progreso** (opcional): muestra objetivos completados.
- **Feedback narrativo satisfactorio:** cada acción bien ejecutada produce una frase/escena que el jugador disfruta.
- **Descubrimientos:** informaciones nuevas sobre residentes (hobbies, gustos, personalidad).
- **Emociones positivas:** ver a residentes contentos/transitando emociones.

### J2. Recompensas que persisten fuera del evento

| Recompensa | Motor | Duración |
|---|---|---|
| **Cambios sociales** | `RelacionEngine` (canales direccional social/romance/conflicto) | Permanent |
| **Emociones resultantes** | `EmotionalStateService` | Temporal (horas-juego) |
| **Memoria del evento** | `MemoriaEventos` (familia: evento_pueblo_interactivo) | Persistente (cooldowns) |
| **Hito relacional** | `RelacionBitacora` (si aplica) | Permanent |
| **Descubrimiento** | `DiscoveryEngine` | Permanent |
| **Entrada de Diario** | `DiarioEngine` (cuando exista) | Permanent |
| **Cotilleo** | `CotilleoAutonomoCadencia` | Temporal |
| **Mensajito F9** | `BuzonEngine` (seguimiento) | Temporal |

### J3. Recompensa máxima por evento

**NO existen recompensas de "alto impacto":**
- No se crea pareja por un evento.
- No se rompe relación por un evento.
- No se obtiene objeto/regalo por un evento.
- No se desbloquea contenido especial.

Los eventos son **oportunidades de interacción**, no fábricas de resultados.

---

## K. Rejugabilidad

### K1. Participantes variables

- El pool de participantes depende de: quiénes están disponibles en esa franja (agenda), quiénes están en ese lugar, y la selección de Celestine.
- Un bingo el martes puede tener 5 participantes; el jueves, otros 8.
- Los recomendados por el motor varían según: afinidad con el tipo de evento, relación con otros participantes, hobbies relacionados.

### K2. Objetivos variables

- Los microobjetivos se generan dinámicamente según: participantes, lugar, hora, estado emocional del pueblo, historial reciente.
- Un mismo tipo de evento nunca se siente idéntico.

### K3. Oportunidades contextuales

- Las acciones disponibles varían según: quién está presente, en qué zona, qué se ha hecho antes en el evento.
- Si Hugo y Carmen están en la misma zona → "Acercar a Hugo y Carmen" aparece.
- Si Hugo se fue a la barra → la acción desaparece o cambia de objetivo.

### K4. Sucesos espontáneos

- Los NPCs pueden moverse entre zonas autónomamente durante el evento.
- Puede ocurrir un micro-evento espontáneo: alguien dice algo gracioso, alguien se cae, alguien pide la palabra.
- Estos sucesos generan feeds narrativos y posibles nuevas oportunidades.

### K5. Combinaciones de vecinos

- Cada combinación de participantes genera dinámicas sociales distintas.
- La compatibilidad oculta entre participantes affecta la experiencia global.
- Dos grupos de personas diferentes en el mismo tipo de evento producirán historias diferentes.

### K6. Consecuencias posteriores

- Los cambios sociales/románticos del evento afectan a encuentros futuros.
- Si Carmen y Hugo tuvieron buena experiencia en el bingo, su próxima interacción puede ser mejor.
- Si Fernando y Manuel discutieron en el bar, esa tensión persiste.

### K7. Rarezas/eventos especiales

- **Evento sorpresa:** combinación rara de participantes que genera un momento único (ej: todos los presentes comparten un hobby).
- **Momento épico:** resultado "muy_bien" en una acción con carga alta → momento narrativo especial.
- **Flechazo durante evento:** si la compatibilidad + química + contexto se alinean, puede surgir interés romántico (vía motores canónicos, no por botón).
- **Conflicto inesperado:** dos personas con conflicto en el mismo evento → momento tenso que alimenta historias.

### K8. Diferencias por lugar

- El mismo tipo de evento en lugares diferentes produce sensación distinta.
- Bar vs parque para un "tardeo" → diferente ambiente, diferentes acciones posibles.
- Bingo en la biblioteca vs en el bingo → diferente tono.

---

## L. Integración con motores existentes

### L1. Diagrama de integración

```
EVENTO INTERACTIVO
  │
  ├──→ EncuentroEngine (programar, lifecycle, resolver)
  │      ├──→ EncuentroExperiencia (resolución por participante)
  │      ├──→ EncuentroPonderacion (snapshot de factores)
  │      └──→ EncuentroDeltasReales (cambios sociales/románticos)
  │
  ├──→ EncuentroIntervencion (MENTES) — adaptado a evento grupal
  │      └──→ MentesTemas (selección de temas por conocimiento)
  │
  ├──→ RelacionEngine (canales independientes: social, romance, conflicto)
  │      └──→ RelacionBitacora (hitos: REGALO, cita, discusión…)
  │
  ├──→ EmotionalStateService (cambios emocionales)
  │
  ├──→ MemoriaEventos (registro: familia, día, participantes, cooldown)
  │
  ├──→ DiscoveryEngine (descubrimientos durante el evento)
  │
  ├──→ BuzonEngine (Mensajitos: anuncio, cierre, F9 seguimiento)
  │
  ├──→ CotilleoAutonomoCadencia (lo que se comenta del evento)
  │
  ├──→ DiarioEngine (cuando exista: entrada personal del evento)
  │
  └──→ DomainEvents (notifica a suscriptores: DOMAIN_EVENT_EVENTO_INTERACTIVO_ACCION)
```

### L2. Regla de no duplicación

- Un hecho del evento se registra en **un solo canal narrativo primario**.
- El evento puede alimentar múltiples SISTEMAS (social + memoria + emoción), pero no múltiples CANALES con el mismo contenido.
- Ejemplo: "Carmen y Hugo hablaron de deporte" puede ser: Diario de Carmen ✓, Cotilleo ✓, Mensajito solo si alguien se lo cuenta a Celestine ✓. No los tres con el mismo texto.

### L3. Alimentación de sistemas futuros

| Sistema futuro | Cómo lo alimenta el evento |
|---|---|
| **Historia del Pueblo** (§25.8) | Hito si el evento fue significativo (muchas intervenciones, resultado特别 bueno/malo, combinación rara de personas) |
| **Misión semanal** (§25.2) | "Consigue que X participe en 2 eventos esta semana" |
| **Móvil NPC** (§21.3 APARCADO) | Micro-interacciones post-evento: emoji de agradecimiento, mensaje entre participantes |
| **Vínculo Celestine** (§22.4 APARCADO) | La reputación de Celestine podría afectar a cuántos quieren ir a eventos que ella organiza |

---

## M. 3 ejemplos COMPLETOS

### M1. Noche de Bingo

**Contexto:** Martes 19:00. Evento programado automáticamente. Lugar: lug_bingo (aforo 20). Participantes: 6 (seleccionados por Celestine).

**Participantes:** Carmen, Hugo, Laura, Fernando, Manuel, Rafa.

**Escena al entrar:**

```
┌─────────────────────────────────────────┐
│  🎰 Noche de Bingo    ⏱ 1:45           │
│  [× Salir]                              │
├─────────────────────────────────────────┤
│                                         │
│  ┌──────────────┐  ┌──────────────┐    │
│  │ 🎲 MESA BINGO │  │ 🍻 BARRA     │    │
│  │ Carmen  😊   │  │ Fernando  😐 │    │
│  │ Hugo    🙂   │  │ Manuel   🙂  │    │
│  │ Laura   😊   │  │              │    │
│  └──────────────┘  └──────────────┘    │
│                                         │
│  ┌──────────────┐                       │
│  │ ☕ MESA 2    │                       │
│  │ Rafa    🙂   │                       │
│  └──────────────┘                       │
│                                         │
├─────────────────────────────────────────┤
│  [🗣 Acercar] [🍻 Brindis] [💬 Tema]    │
│  [🎲 Hacer equipo]                      │
├─────────────────────────────────────────┤
│  Objetivos: 🍻×2  😄×1  🗣×1           │
│  ░░░░░░░░░░░░░░░░░░░░ 0/4              │
└─────────────────────────────────────────┘
```

**Acciones del jugador:**

1. **Pulsa "🍻 Brindis"** → Selecciona la zona mesa bingo (Carmen, Hugo, Laura).
   - Motor: microdelta social positivo a los 3.
   - Resultado: "Carmen propuso un brindis. Hugo y Laura aceptaron encantados. El ambiente mejoró."
   - Emoción: Carmen → alegre. Hugo → alegre. Laura → alegre.
   - Progreso: 🍻×1 (1/2 completado).

2. **Pulsa "🗣 Acercar"** → Selecciona Fernando (barra) y Rafa (mesa 2).
   - Motor: mini-encuentro entre Fernando y Rafa.
   - Evaluación: compatibilidad oculta + relación previa (se conocen, social bajo).
   - RNG: resultado "bien".
   - Resultado: "Fernando se acercó a Rafa. La conversación fluyó sorprendentemente bien."
   - Fernando: emoción → alegre. Rafa: emoción → alegre.
   - Social Fernando→Rafa: +2. Rafa→Fernando: +2.
   - Progreso: 🗣×1 (1/1 completado).

3. **Pulsa "💬 Tema"** → Selecciona Hugo + Laura. Tema: "deporte" (hobby conocido de Hugo, categoría del catálogo).
   - Motor: MENTES adaptado a evento. Hugo es beneficiario del tema.
   - Evaluación: Hugo tiene hobby "deporte" → afinidad.
   - Resultado: "Hugo se animó hablando de deporte. Laura escuchó con interés."
   - Hugo: 😊 → 😄. Laura: 🙂 → 😊.
   - Social Hugo→Laura: +1. Laura→Hugo: +1.
   - Posible discovery: "Laura parece interesada en el deporte."

4. **Pulsa "🎲 Hacer equipo"** → Selecciona Carmen + Hugo.
   - Motor: mini-experiencia grupal.
   - Resultado: "Carmen y Hugo hicieron equipo para el bingo. Se rieron mucho."
   - Progreso: 😄×1 (1/1 completado).

**Evento termina (reloj cruza fin):**

Resumen:
```
🎰 NOCHE DE BINGO — RESUMEN

Participantes: Carmen, Hugo, Laura, Fernando, Manuel, Rafa

Momentos destacados:
• Carmen brindó con Hugo y Laura → buen ambiente
• Fernando y Rafa conectaron en la barra
• Hugo y Laura hablaron de deporte
• Carmen y Hugo hicieron buen equipo en el bingo

Cambios:
Carmen: 😊 → 😄 (+ social con Hugo y Laura)
Hugo: 🙂 → 😄 (+ social con Carmen, Laura)
Fernando: 😐 → 😊 (+ social con Rafa)
Rafa: 🙂 → 😊 (+ social con Fernando)
Laura: 😊 → 😊 (sin cambio significativo)
Manuel: 🙂 → 🙂 (no participó directamente)

Posible cotilleo: "Carmen y Hugo hicieron buena pareja en el bingo 👀"
```

**Consecuencias post-evento:**
- MemoriaEventos: familia `evento_pueblo_interactivo`, día, participantes.
- MemoriaEventos: cooldown `noche_bingo` para ese grupo.
- Si la relación Carmen→Hugo subió lo suficiente → posible señal romántica en días posteriores.
- Fernando→Rafa: nueva relación social que antes no existía.
- Hugo: posible Mensajito F9: "Estuvo genial lo del bingo, ¿hay otro pronto?"

---

### M2. Tardeo en el Bar

**Contexto:** Viernes 19:00. Evento organizado por petición de Mensajito (F4). Lugar: lug_bar (aforo 14). Participantes: 5 (Celestine eligió).

**Participantes:** Jaime, David, Tamara, Alba, Sergio.

**Escena al entrar:**

```
┌─────────────────────────────────────────┐
│  🍻 Tardeo en el Bar   ⏱ 1:52          │
│  [× Salir]                              │
├─────────────────────────────────────────┤
│                                         │
│  ┌──────────────┐  ┌──────────────┐    │
│  │ 🍻 BARRA     │  │ 🪑 TERRAZA   │    │
│  │ Jaime   🙂   │  │ David   😊   │    │
│  │ Tamara  😊   │  │ Sergio  🙂   │    │
│  │              │  │              │    │
│  └──────────────┘  └──────────────┘    │
│                                         │
│  ┌──────────────┐                       │
│  │ 🎵 MÚSICA   │                       │
│  │ Alba    🙂   │                       │
│  └──────────────┘                       │
│                                         │
├─────────────────────────────────────────┤
│  [🗣 Acercar] [🍻 Brindis] [💬 Tema]    │
│  [🎵 Poner música] [👋 Presentar]       │
├─────────────────────────────────────────┤
│  Objetivos: 🍻×2  👋×1  😄×1           │
│  ░░░░░░░░░░░░░░░░░░░░ 0/4              │
└─────────────────────────────────────────┘
```

**Acciones del jugador:**

1. **"👋 Presentar"** → Selecciona a David (terraza) y Alba (música).
   - Alba y David no se conocen (conocidos=false).
   - Motor: propuesta nivel PRESENTAR simplificada.
   - Resultado: "Celestine presentó a David y Alba. David preguntó por la música que estaba sonando."
   - David→Alba: +1 social. Alba→David: +1 social.
   - Conocidos=true para ambos.
   - Progreso: 👋×1 (1/1 completado).

2. **"🎵 Poner música"** → Selecciona a Tamara.
   - Motor: micro-delta social + emoción.
   - Resultado: "Tamara puso una canción que gustó a todos en la barra."
   - Todos en zona barra: microdelta positivo.
   - Progreso: 🍻×1 (1/2 completado).

3. **"🍻 Brindis"** → Zona barra completa (Jaime, Tamara).
   - Resultado: "Jaime y Tamara brindaron. El ambiente estaba genial."
   - Progreso: 🍻×2 (2/2 completado).

4. **"💬 Tema"** → Jaime + David. Tema: "baile" (afinidad detectada).
   - Motor: MENTES. David es beneficiario. Afinidad: David tiene "baile" en hobbies.
   - Resultado: "Jaime sacó el tema del baile. David se animó y empezó a contar sus experiencias."
   - David: 😊 → 😄. Jaime: 🙂 → 😊.
   - Progreso: 😄×1 (1/1 completado).

**Evento termina:**

- David y Alba se conocieron (conocidos=true, social bajo).
- Jaime→David: nueva relación social significativa.
- Tamara: emoción positiva持久几个小时.
- Posible cotilleo: "David y Alba se conocieron en el bar. Interesante组合."
- Posible Mensajito de David: "Lo del bar estuvo bien. ¿Hay karaoke algún día?"

---

### M3. Fiesta del Pueblo

**Contexto:** Sábado 20:00. Evento especial quincenal. Lugar: lug_discoteca (aforo 24). Participantes: 10. Evento más largo (3 horas).

**Participantes:** Carmen, Hugo, Jaime, David, Tamara, Alba, Sergio, Laura, Fernando, Manuel.

**Zonas:**
- Pista de baile
- Barra
- Mesa VIP
- Terraza

**Microobjetivos:**
- 🕺×2 (que dos personas bailen juntas)
- 💃×1 (alguien salga a bailar)
- 😄×3 (tres personas acaben alegres)
- 👀×1 (señal romántica surgida)

**El jugador planifica durante 2 horas de evento real:**

1. **"🗣 Acercar"** → Laura + Sergio en la pista.
   - Resultado: "Laura y Sergio bailaron juntos un momento. La química fue..."
   - RNG + compatibilidad → resultado "bien".
   - Laura: 🙂 → 😊. Sergio: 🙂 → 😊.
   - Social: Laura→Sergio +2.

2. **"🎵 Poner música"** → Todos en la pista.
   - Resultado: "La música animó a varios. Fernando se unió a la pista."
   - Microdelta grupal.

3. **"💬 Tema"** → Carmen + Hugo. Tema: "viajes" (hobby desconocido de ambos pero general).
   - Motor: neutro (sin afinidad detectada por el jugador).
   - Resultado: "La conversación fue amable pero no fluyó mucho."
   - Resultado "normal" → sin cambio significativo.
   - **Lección para el jugador:** "Quizá debería conocer mejor a Carmen antes de intervenir."

4. **"🍻 Brindis"** → Zona terraza (David, Alba, Tamara).
   - Resultado: "Brindaron bajo las estrellas. Alba sonrió."
   - Alba: 😊 → 😄.

5. **"🗣 Acercar"** → Fernando + Manuel en la barra.
   - Resultado: "Fernando y Manuel charlaron de su semana."
   - Social: +1 ambos.

**Resumen del evento:**
- 🕺×2 completado (Laura+Sergio, Fernando+Manuel).
- 💃×1 completado (Fernando se unió).
- 😄×3 completado (Alba, Laura, Hugo).
- 👀×1: posiblementeLaura→Sergio si la compatibilidad + química lo favorecen (el jugador no controla esto, pero la buena intervención creó la oportunidad).

**Consecuencias ricas:**
- Laura→Sergio: posiblemente el inicio de una señal romántica (si compatibilidad alta + buena experiencia).
- David→Alba: nuevo conocimiento que puede evolucionar.
- Fernando→Manuel: nueva amistad potencial.
- Cotilleo: "La fiesta estuvo movida. Laura y Sergio no se separaron de la pista 👀"
- Diario de Laura: "La fiesta estuvo bien. Me lo pasé genial bailando con Sergio."
- Memoria: "primera_fiesta_pueblo" → posible hito en Historia del Pueblo.

---

## N. Riesgos de balance/explotación

### N1. Farmeo de interacciones

**Riesgo:** El jugador repite eventos para acumular microdelta social infinito.

**Mitigación:**
- Límite de intervenciones por evento (3-4).
- Cooldown de eventos idénticos (MemoriaEventos: no repetir el mismo evento con el mismo grupo en ~3 días-juego).
- Los microdelta son PEQUEÑOS. Un evento da como mucho +2 a una relación, no +10.
- La fatiga narrativa: repetir el mismo evento se siente repetitivo.

### N2. Manipulación de emociones

**Riesgo:** El jugador usa eventos para poner a todos contentos y facilitar citas.

**Mitigación:**
- Las emociones resultantes son temporales (horas-juego, no días).
- La emoción no modifica directamente la voluntad de aceptar citas.
- El jugador no controla qué emoción resultado每一个NPC.

### N3. Exploración de compatibilidad oculta

**Riesgo:** El jugador usa eventos para revelar compatibilidades mediante prueba y error.

**Mitigación:**
- Los descubrimientos son limitados (1-2 por evento como máximo).
- No se revela compatibilidad oculta directamente.
- El jugador descubre hobbies/gustos, no la fórmula interna.

### N4. Evento como "checklist"

**Riesgo:** El jugador trata los microobjetivos como una lista de tareas obligatoria.

**Mitigación:**
- Los microobjetivos son OPCIONALES y no dan recompensas de alto impacto.
- No hay "estrellas" ni "puntuación perfecta".
- El evento puede disfrutarse sin completar ningún objetivo.

### N5. Sobrecarga de persistencia

**Riesgo:** Cada evento genera demasiados datos guardados.

**Mitigación:**
- Reutiliza sistemas existentes con sus caps (MemoriaEventos, RelacionBitacora, PersistenciaCaps).
- No crea nuevos tipos de persistencia.
- Los cotilleos y diarios siguen sus propios límites.

### N6. Monopolización de tiempo real

**Riesgo:** El evento demasiado largo aburre o impide hacer otras cosas.

**Mitigación:**
- Duración configurable (~1.5-3 horas-juego = ~1.5-3 minutos reales).
- El jugador puede salir y volver.
- Las acciones son breves (cada una toma ~15-20 segundos reales).

---

## O. Qué decisiones necesita tomar Neni

### O1. Duración temporal

| Decisión | Hipótesis | Notas |
|---|---|---|
| Duración real de un evento en minutos de juego | 1.5-3 horas-juego | Equivale a ~1.5-3 minutos reales. §25 menciona "aproximadamente 2 minutos" como referencia |
| Si la duración es fija o variable por tipo de evento | Variable | Bingo 2h, fiesta 3h, tardeo 2h, karaoke 2.5h |
| Si hay límite real de tiempo o solo in-game | Solo in-game | El reloj avanza; si el jugador sale y vuelve, el evento puede haber terminado |

### O2. Intervenciones

| Decisión | Hipótesis | Notas |
|---|---|---|
| Número de intervenciones por evento | 3-4 | Limita la profundidad sin saturar |
| Si hay intervenciones "leves" que no consumen carga | Sí (brindis = 0.5) | Permite variedad |
| Si el límite es por evento o por día | Por evento | Un evento ≠ todo el día |

### O3. Acciones disponibles

| Decisión | Hipótesis | Notas |
|---|---|---|
| Acciones base vs acciones por tipo de evento | Mixto | 6 base + 1-2 específicas por evento |
| Si "Sembrar conversación" reutiliza MENTES 100% | Sí | Reutilizar, no duplicar |
| Si "Acercar personas" crea mini-encuentro o solo microdelta | Mini-encuentro (ligero) | Más rico narrativamente |

### O4. Objetivos

| Decisión | Hipótesis | Notas |
|---|---|---|
| Si hay barra de progreso visible | Opcional / configurable | Neni planteó esta idea en §25 |
| Número de microobjetivos por evento | 2-4 | Según tipo y participantes |
| Si se muestran todos de golpe o se desbloquean | Se muestran todos | Transparencia |
| Si hay recompensa por completar todos | Narrativa, no mecánica | Feedback emocional |

### O5. UX

| Decisión | Hipótesis | Notas |
|---|---|---|
| Si la escena ocupa toda la pantalla o deja visible el mapa | Casi toda (móvil); sidebar (desktop) | "Sensación de haber entrado al evento" |
| Si hay transición animada | Sí, suave | Tipo "cortina" |
| Si el feed narrativo es scroll o líneas fijas | Líneas fijas (últimas 3-4) | Anti-saturación |
| Si se muestra tiempo restante | Sí, barra + texto | Sin presión excesiva |

### O6. Rejugabilidad

| Decisión | Hipótesis | Notas |
|---|---|---|
| Frecuencia de eventos | 1-2 por semana-juego | Basado en prob 0.22 diaria × 7 días |
| Si hay eventos "especiales" raros | Sí, 1-2 veces al mes-juego | Combinación rara de participantes/lugar |
| Si los mismos participantes pueden repetir | Sí, pero con cooldown | No todos los días |

### O7. Integración con sistemas apagados

| Decisión | Notas |
|---|---|
| ¿Los eventos interactivos requieren Emociones (flag OFF)? | Sí para máximo potencial; parcialmente funcional sin ellas |
| ¿Requieren Diario (flag OFF)? | Para entrada narrativa personal; no bloqueante |
| ¿Requieren Discovery (flag OFF)? | Para descubrimientos; no bloqueante |
| ¿Requieren Misiones Diarias (flag OFF)? | Podrían generar misiones; no bloqueante |

---

## P. Propuesta de implementación por fases (NO implementar)

### Fase 0 — Preparación (requerida)

1. Activar `emotional_state_from_events_enabled` (Emociones).
2. Activar `discovery_enabled` (Discovery).
3. Validar que ambos funcionan en playtest.
4. Investigar §25.5-25.7 (feedback de intervención) y prototipar tarjeta de resultado.

### Fase 1 — Escena temporal mínima (MVP del evento interactivo)

**Objetivo:** El jugador puede entrar a un evento en curso y ver participantes.

1. **Motor nuevo mínimo:**
   - `EventoInteractivoEngine.php`: gestión de estado "entrable" del evento.
   - Campo en `eventos_pueblo.programados[]`: `interactivo_habilitado: true/false`.
   - Gate: solo eventos tipo `ocio_colectivo` o seleccionados manualmente.

2. **Transición de UI:**
   - CTA "ENTRAR" en la card del evento del mapa.
   - Transición CSS: mapa → escena temporal.
   - Escena: vista simple con participantes en zona única (sin zonas múltiples aún).

3. **Timer visible:**
   - Barra de progreso basada en la duración del encuentro.
   - Cálculo: `hora_inicio + duracion_horas - hora_actual`.

4. **Salida:**
   - Botón salir → vuelve al mapa.
   - El evento sigue activo.

5. **Persistencia:**
   - `evento_interactivo_estado` en `eventos_pueblo.programados[]`.
   - `evento_interactivo_acciones[]` (log de acciones realizadas).

**Entregable:** El jugador entra, ve a los participantes, puede salir y volver.

### Fase 2 — Acciones básicas

**Objetivo:** Celestine puede intervenir durante el evento.

1. **Acciones base:**
   - "Acercar personas" (2 personas, mini-encuentro).
   - "Brindis" (zona completa, microdelta leve).
   - "Sembrar conversación" (2 personas + tema, reutiliza MENTES).

2. **Límite de intervenciones:**
   - 3 por evento (configurable en calibración).

3. **Feedback narrativo:**
   - Tarjeta superpuesta tras cada acción.
   - Texto generado por motor (no hardcodeado).

4. **Persistencia de acciones:**
   - Cada acción se registra en `evento_interactivo_acciones[]`.
   - Se conecta a motores canónicos (social, emoción, memoria).

**Entregable:** El jugador puede intervenir y ver resultados narrativos.

### Fase 3 — Zonas múltiples + microobjetivos

**Objetivo:** La escena tiene zonas y objetivos.

1. **Zonas:**
   - Cada evento define sus zonas en el catálogo (`eventos_pueblo.json`).
   - Distribución de participantes por zona (auto o manual).
   - Las acciones dependen de la zona seleccionada.

2. **Microobjetivos:**
   - Generación dinámica según participantes/lugar.
   - Barra de progreso (si Neni decide incluirla).
   - Feedback de completado.

3. **Sucesos espontáneos:**
   - NPCs se mueven entre zonas.
   - Pequeños labels narrativos.

**Entregable:** Escena rica con zonas, objetivos y movimiento.

### Fase 4 — Rejugabilidad + eventos específicos

**Objetivo:** Cada evento se siente único.

1. **Acciones específicas por tipo de evento:**
   - Bingo: "Hacer equipo", "Proponer ronda".
   - Bar: "Invitar copa", "Poner música".
   - Fiesta: "Sacar a bailar", "Crear grupo".
   - Karaoke: "Proponer que cante", "Hacer coro".

2. **Participantes variables:**
   - Recomendación inteligente según affinity con el tipo de evento.
   - Combinaciones que generan momentos raros.

3. **Eventos especiales:**
   - 1-2 veces al mes-juego: combinación rara de participantes/lugar que genera momento único.

**Entregable:** Rejugabilidad real y momentos memorables.

### Fase 5 — Integración profunda

**Objetivo:** Los eventos alimentan todos los sistemas narrativos.

1. **Diario** (cuando exista): entrada personal del evento.
2. **Historia del Pueblo** (cuando exista): hito si significativo.
3. **Misión semanal** (si se decide): objetivos que usan eventos.
4. **Feedback de intervención mejorado** (§25.5-25.7): tarjetas de resultado enriquecidas.
5. **Mensajitos F9**: seguimiento post-evento.
6. **Cotilleos enriquecidos**: menciones específicas del evento.

**Entregable:** Eventos como pieza central de la experiencia social del pueblo.

---

## Q. Ideas adicionales

### Q1. "El pueblo no duerme" — eventos nocturnos

Si el reloj avanza de noche y hay participantes disponibles, podría haber **micro-eventos espontáneos** en el bar o la discoteca. No como evento organizado, sino como coincidencia: "Hugo y Alba se encontraron en el bar anoche." Esto alimenta cotilleos y puede generar Mensajitos al día siguiente.

### Q2. "El evento que no salió" — eventos cancelados

Si un evento se cancela por falta de participantes, eso TAMBIÉN es historia. "La noche de bingo se canceló porque solo había dos personas." Esto puede provocar: decepción en quien quería ir, chascarrillos en cotilleos, o una petición de Mensajito: "¿Podemos reprogramarlo?"

### Q3. "Sorpresa de cumpleaños" — eventos especiales por petición

Si es el cumpleaños de alguien (§19.6) y Celestine organiza un evento especial, las acciones dentro del evento pueden ser **específicas**: "Cantar felicidad", "Entregar regalo" (si existe en el cajón), "Proponer brindis por el cumpleañero". El motor puede reconocer el contexto de cumpleaños y ofrecer acciones adicionales.

### Q4. "El cotilleo del día siguiente" — consecuencias narrativas

Después de un evento interactivo, al día siguiente los NPC que participaron pueden:
- Tener emociones resultantes que afecten su comportamiento.
- Enviar Mensajitos a Celestine: "Lo de anoche estuvo genial" o "No me lo pasé bien con X".
- Aparecer en cotilleos: "Hugo y Carmen no se separaron del bingo 👀".
- Tener nueva disposición a aceptar invitaciones (si el evento fue bueno).

### Q5. "La Barra" — lugar dentro del lugar

Cada evento podría tener una **zona "barra"** implícita donde las acciones sociales son más fáciles pero menos profundas. La barra es el lugar de las interacciones ligeras: brindis, charla casual, conocidos nuevos. Las zonas más específicas (mesa, escenario, pista) son para interacciones más profundas. Esto da una capa de estrategia blanda: "¿La llevo a la barra o a la mesa?"

### Q6. "El observador" — eventos donde Celestine solo observa

Algunos eventos podrían no permitir intervención directa, solo observación. El jugador entra, ve cómo interactúan los NPC autónomamente, y aprende. Esto es valioso para: descubrir compatibilidades, entender dinámicas sociales, o simplemente disfrutar del pueblo vivo. No todo tiene que ser "pulsar botón → resultado".

### Q7. "El回忆 (recuerdo)" — álbum de eventos

Cuando exista el Espacio de Celestine (§23), los eventos interactivos podrían generar **recuerdos especiales**: una foto/representación del momento más destacado del evento. "La noche que Carmen y Hugo hicieron equipo en el bingo." Esto alimenta el álbum de recuerdos (§23.7) y da motivación para participar en eventos.

### Q8. "Evento temático" — variación por calendario

Eventos podriand ser temáticos según la época: "Noche de terror" (cine,alloween), "Gala benéfica" (restaurante), "Fiesta de verano" (parque). Estos eventos especiales podrían tener: participantes limitados (solo los que encajan temáticamente), acciones especiales, y recompensas narrativas más ricas.

### Q9. "La chain" — cadena de eventos

Si un evento sale especialmente bien, podría desencadenar un **micro-evento posterior**: "Después del bingo, Carmen y Hugo fueron a tomar algo al bar." Este segundo evento sería más breve (1 hora-juego) y solo incluiría a los involucrados en el momento destacado. Esto crea historias encadenadas naturales.

### Q10. "Pueblo pequeño" — efecto reputación

Si Celestine organiza eventos que suelen salir bien, los NPC podrían mostrar mayor disposición a participar en eventos futuros. Si suelen salir mal, podrían dudar más. Esto no es un contador numérico (§22.4 aparcado), sino un comportamiento emergente: "Celestine sabe organizar eventos" → copys y actitudes que lo reflejan.

---

## Checklist de diseño (resumen ejecutivo)

| # | Pregunta clave | Estado |
|---|---|---|
| A | ¿Qué dice el Plan Maestro? | §25.3-25.4, §19.5, §19.7 |
| B | ¿Qué infraestructura existe? | EventosPuebloEngine completo, EncuentroEngine, MENTES, 9 lugares, AforoEngine |
| C | ¿Qué falta? | Escena temporal, acciones interactivas, microobjetivos, zonas, entrada/salida |
| D | Loop completo | 9 pasos documentados (aparece → prepara → empieza → entro → interactúo → motores → feedback → termina → consecuencias) |
| E | UX móvil | ~90% pantalla, zonas cards, acciones abajo, timer arriba, feed narrativo |
| F | UX desktop | Mismo layout, más espacio, hover tooltips, feed siempre visible |
| G | Acciones | 6 base + 2-3 específicas por evento; resolución vía motores existentes |
| H | Restricciones | Matriz completa por relación/emoción/contexto |
| I | Microobjetivos | 2-4 opcionales, variables, sin castigo |
| J | Recompensas | Solo sociales/emocionales/memoria; sin economía |
| K | Rejugabilidad | Participantes variables, objetivos variables, sucesos espontáneos, rarezas |
| L | Integración | Diagrama completo, reutilización obligatoria, no duplicación |
| M | 3 ejemplos | Bingo, Bar/Fiesta completos con participantes, acciones, resultados, consecuencias |
| N | Riesgos | 6 riesgos identificados con mitigaciones |
| O | Decisiones Neni | 7 categorías de decisiones abiertas |
| P | Fases | 5 fases progresivas (MVP → acciones → zonas → rejugabilidad → integración) |
| Q | Ideas | 10 ideas adicionales para hacer la mecánica diferencial |

---

**Estado final:** Este documento es DISEÑO FUNCIONAL. NO implementa nada. Pendiente de revisión por Neni + ChatGPT. Los eventos interactivos NO se marcan como cerrados.

**Firma:** opencode — 2026-09-01
