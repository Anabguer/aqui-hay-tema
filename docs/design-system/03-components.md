# 03 · Catálogo de componentes

Convención: los componentes base del DS usan clases `.ds-*` (opt-in, sin colisión con el CSS actual). Durante la migración, cada pantalla aplica `.ds-*` a su HTML existente **sin cambiar contratos `data-*`**. Los overrides de selectores heredados viven en `assets/css/design-system/screens/<pantalla>.css`.

Estados estándar de todo componente interactivo: `normal · hover (solo desktop) · pressed · selected · disabled · locked · unread/NUEVO · completed · active · warning/error`. Se indican los específicos de cada uno.

---

## 1. Superficies

### PaperModal (`ds-sheet`)
Hoja de papel para capas y modales.
- **Anatomía**: superficie `--ds-paper-modal`, radio 28, `--ds-border`, `--ds-shadow-raised`; cabecera (icono + título display + badge opcional + CloseButton); cuerpo scrollable; pie opcional (Hint o CTA).
- **Variantes**: `ds-sheet--full` (pantalla completa móvil: ficha, diario, nuevo plan, cotilleos), `ds-sheet--card` (caja centrada: ánimo), cajón lateral desktop (mecánica `data-capa-origin` actual se conserva).
- **Base real**: `.capa` + `.velo`. Decoración: cinta en esquinas o chincheta según pantalla (máx. 1).

### PaperCard (`ds-card`)
Tarjeta de papel base.
- `--ds-paper-card`, radio 20, `--ds-border`, `--ds-shadow-rest`.
- Variantes: `--tint-green|lavender|pink|mustard` (fondo suave por categoría, como GossipCard), `--dashed` (borde punteado para slots vacíos/pendientes), `--tape` (con cinta), `--tilt-l|tilt-r` (±1.5°, solo feed).

## 2. Acción

### PrimaryButton / CTA (`ds-btn ds-btn--primary`)
- Coral, texto blanco Caveat 700 MAYÚS. 20–22 px, altura 56, radio 16, `--ds-border-strong` + costura interior (`--ds-stitch`) + `--ds-shadow-cta`.
- Con icono ilustrado opcional (calendario, sobre…). Rayitas de énfasis opcionales fuera del borde.
- Press: `scale(.97)` + sombra presionada. Disabled: `--ds-disabled` + texto `#FFF` al 70%.
- **Base real**: `.cta`.

### SecondaryButton (`ds-btn--secondary`)
- Lavanda (`--ds-lavender`), mismo cuerpo que el primario pero altura 48 y sin costura. Para VER, ABRIR en cartas no nuevas, acciones secundarias.

### GhostButton / LinkButton (`ds-btn--ghost`)
- Transparente, texto `--ds-lavender-deep` subrayado a mano (Caveat 600 ≥16 px o Nunito 800). Para "VER TODAS ›", "VER EN SU DIARIO".

### CloseButton (`ds-close`)
- Círculo 44 px papel, `--ds-border-strong`, X en tinta. Esquina sup. derecha del modal. **Base real**: `.capa-cerrar`.

### StepNumber (`ds-step`)
- Círculo coral 32 px con número blanco + título de sección Caveat. Para Nuevo Plan (1 ¿Quiénes van? / 2 ¿Qué harán? / 3 ¿Dónde? / 4 ¿Cuándo?).

## 3. Navegación

### Tab cosida (`ds-tab`)
- Píldora 48 px con icono + label Caveat 700. Inactiva: papel + borde lavanda punteado. Activa: relleno coral (o lavanda para RELACIONES), costura interior, texto blanco.
- Grupos aprobados: `NUEVOS | TODOS` (Mensajitos), `VECINOS | RELACIONES` (una ventana, D4).
- **Base real**: ninguna (nuevo). Estado en el host: `data-vecinos-tab`, `data-buzon-tab` (nuevos atributos de UI, sin tocar contratos de API).

### Chip filtro (`ds-chip`)
- 36–40 px, radio 12, icono opcional. Estados: normal (papel), activa (tinte de familia: coral/lavanda/verde/mostaza), disabled.
- **Base real**: `[data-coti-filtro]`, chips de modo `[data-org-modo]`.

### Chip circular con emoji (`ds-chip-emoji`)
- Círculo 48 px blanco con emoji + microtexto debajo. Filtros de relaciones (PAREJAS 💞, AMISTAD 🤝, CONOCIDOS 👋, MALA RELACIÓN 💔) y "TODAS" (tinta rellena).

### Select / Dropdown (`ds-select`)
- Campo papel radio 16 con label Caveat + chevron. **Base real**: dropdowns custom `[data-org-dd-*]` (se reestilan, no se sustituyen).

### SearchField (`ds-search`)
- Píldora papel 44 px con lupa, placeholder `--ds-text-muted`. **Base real**: `[data-vec-busca]`, `[data-org-busca]`.

## 4. Identidad

### Avatar (`ds-avatar`)
- Círculo con anillo de emoción (`--ds-emo-*`) de 2.5–3 px + borde blanco interior. Tamaños token/sm/md/lg/xl (ver tokens §6).
- Recorte facial canónico intacto: `object-fit:cover; object-position:50% 20%` (⚠️ riesgo R5: no cambiar sin revisión).
- **Base real**: `.cara-token`, avatar de carta, `.ficha-cara-ring`.

### AvatarGroup (`ds-avatar-group`)
- 2–3 avatares solapados −8 px con borde papel. Para parejas/planes (Paula·Sergio).

### EmotionBadge (`ds-pill ds-pill--emo`)
- Pill con emoji + texto (Feliz/Neutral/Triste/Enfadado/Contenta/Nuevo). Fondo `--ds-emo-*-soft`/familia, texto `-deep`. **Base real**: pills de emoción con `data-emocion`.

### StatusPill (`ds-pill`)
- Estados de flujo: `EN CURSO` (verde), `Cerrado ahora` (rosa), `Todo listo` (verde ✓), `HOY · 20:00` (lavanda, Nunito 800). Siempre con icono/emoji si acompaña a un estado.

### NotificationBadge (`ds-badge`)
- Círculo rojo con contador blanco Nunito 800, anillo papel. Sobre tiles y pestañas. **Base real**: `[data-buzon-badge]`.

### AlertBadge (`ds-alert`)
- Círculo rojo "!" en esquina de tarjeta (relaciones tensas, avisos).

### XP / Reward ticket (`ds-ticket`)
- Chip mostaza con borde punteado interior, icono estrella/corazón + "+5 XP" en Nunito 800.

## 5. Contenido

### MessageCard (`ds-card--message`) — Mensajitos
- Avatar md + nombre Caveat + timestamp Nunito + texto manuscrita + fila de acciones (ticket XP / caducidad + botón).
- Estados: **NUEVO** (fondo rosa, pill NUEVO con rayitas, CTA coral ABRIR, esquina doblada), **caduca** (chip mostaza ⏳ + VER lavanda), **VISTO** (papel, "Visto" lavanda con sobre✓, sin CTA).
- **Base real**: `.carta-msg` + `[data-accion]` (contrato intacto).

### PlanCard (`ds-card--plan`)
- AvatarGroup + nombres + lugar + StatusPill + acción (`INTERVENIR ›` coral / estrella). Chip `HOY · 20:00` arriba. Borde punteado en próximos.
- **Base real**: cards de `[data-proximo-plan]`, `[data-encursos-track]`, `[data-agenda-list]`.

### RelationshipCard (`ds-card--rel`)
- Cabecera: Avatar + nombre ↔ Avatar + nombre. Filas direccionales: `A → B` + pill sentimiento + barra de progreso coloreada. Separador punteado con nota ("DIFERENTE EN CADA SENTIDO"). Link `VER RELACIÓN ›`.
- **Base real**: filas de `[data-ficha-relaciones]` / overlay relaciones (se reorganiza bajo la pestaña RELACIONES, D4).

### NeighborCard (`ds-card--vecino`)
- Grid 2 col.: Avatar xl-relativo (96) con anillo emoción + mini-emoji, nombre Caveat, EmotionBadge, corazón+número+barra progreso, `VER FICHA ›`. AlertBadge "!" opcional. Estado **Por conocer**: corazón vacío + barra vacía.
- **Base real**: `.vecino` en `[data-vecinos-list]`.

### GossipCard (`ds-card--coti`)
- Tinte por categoría (verde llegada, lavanda relación…), cinta esquina, Avatar(s), chip categoría, texto manuscrita, timestamp, `VER … ›`. Cabeceras de día tipo ribbon con hojas (`HOY · 24/08`).
- **Estados reservados para D8 (feature posterior, NO en la primera migración visual)**: botón circular ⭐ en esquina (normal/guardado-relleno) y filtro `GUARDADOS`. La primera migración los omite; el layout de la tarjeta ya deja sitio para no rehacerlo después. Persistencia futura por el patrón `cotilleoVisto`.
- **Base real**: items de `[data-coti-list]`.

### MissionCard (`ds-card--mision`)
- Fila con icono ilustrado, texto, radio/check circular (✓ verde al completar). Papel con cinta.
- **Base real**: `.mision-*` + `[data-mision-accion]`.

### TimelineItem (`ds-tl-item`) — Diario (D7)
- Nodo circular con emoji/icono sobre línea vertical + tarjeta con borde del color de categoría, título Caveat, texto Nunito, Avatar+nombre opcional, tag de categoría, chevron.
- Cabecera de día: cinta/ribbon coloreado (`DÍA 4 · 27/08`).
- Categorías reales mapeadas: `encuentro/rechazo`→PLANES, `hito_relacion`→RELACIONES, `trabajo`+`estado_emocional_cambiado`→CAMBIOS, `llegada`→HISTORIA.

### InfoNote / Hint (`ds-hint`)
- Tira lavanda con icono dorado + texto Caveat ≥16 px. Pie de modales ("Abrir mensajitos puede desbloquear planes y cotilleos").

### EmptyState (`ds-empty`)
- Doodle ilustrado + frase con voz + acción opcional. Ej.: parejas vacías, agenda tranquila.

### LockedState / UnknownState (`ds-locked`, `ds-unknown`)
- **Locked**: tarjeta punteada + candado + "?" grande (rasgos/aficiones no descubiertas — datos reales de `rasgos_slots`/`hobbies_slots`; en el mapa, lugar **cerrado ahora por horario** según D9).
- **Unknown**: variante tenue "Por descubrir/Por conocer" (vecinos sin relación).
- ⚠️ Nunca ficticio: candado solo con datos reales (`abierto_ahora`/`operativo` en el mapa, slots reales en ficha). Prohibido el bloqueo progresivo inventado.

### EventCard / EventBanner — **COMPONENTE FUTURO (EV, no implementar aún)**
Banner compacto integrado en el feed del inicio (entre el mapa y el cotilleo, ver `04-screens.md` §1). **No siempre visible: desaparece completamente cuando no existe evento.** Sin retrato de vecino; usa iconografía del lugar/ambiente. Aspecto de acontecimiento especial: más fino que una tarjeta normal (padding reducido, una línea de texto), con más personalidad que una card corriente y bastante más fino que la antigua "Misión activa" ficticia.

| Estado | Cuándo aparece | Contenido | Acción |
|---|---|---|---|
| `ds-event--proximo` | El día anterior al evento o cuando el juego lo determine (reglas futuras) | Cinta pequeña "SE ACERCA UN EVENTO" (mostaza) + nombre del evento + lugar · cuándo | Futura: `PARTICIPAR` (y eventualmente selección de participantes) |
| `ds-event--en-marcha` | Mientras el evento está ocurriendo | Cinta "EVENTO EN MARCHA" (coral) + nombre del evento + lugar · ahora | Futura: `ENTRAR`/`JUGAR`/intervenir en el evento |

- **Fuera de alcance en FASE 2/3**: motor, endpoints, cadencia, lógica de aparición, flujo de participación. Solo existe el componente visual documentado (CSS listo en `components.css` como `.ds-event*`, sin uso en el DOM).
- Referencia visual: tira "SÉ APROXIMA EVENTO · Noche de música · Discoteca · Mañana 21:00 · PARTICIPAR" de `inicio-mobile.png`.

### Divider con doodle (`ds-divider`)
- Línea punteada + garabato central (estrella, rayitas) para separar secciones.

### Progress (`ds-progress`)
- Barra 8–10 px papel-dim con relleno de color de emoción/familia, extremos redondeados. Para relación y ánimo-resumen. Variantes de color: emoción (`--alegre/--triste/--enfadado`), `--mustard` (CONOCIDOS, como en `relaciones-mobile`), `--pink`/`--lavender`.

### Toast / Aviso (`ds-toast`)
- Papel oscuro-tinta o coral según severidad, radio 16, icono + texto Nunito. Sin referencia aún (D10): se definirá cuando toque; mientras se conserva el actual.
- **Base real**: `.feedback-toast`.

## 6. Matriz de estados (resumen)

| Componente | normal | hover | pressed | selected | disabled | locked | unread | completed | activo | error |
|---|---|---|---|---|---|---|---|---|---|---|
| PrimaryButton | ✓ | lift | scale .97 | — | gris | — | — | — | — | — |
| Tab | papel | — | — | ✓ rellena | — | — | badge NUEVO | — | ✓ | — |
| Chip | papel | — | — | tinte | opacidad | — | — | — | ✓ | — |
| Card | papel | lift suave | — | ✓ anillo | — | punteada+candado | flag NUEVO | ✓ verde | — | tinte rojo |
| Avatar | anillo emoción | — | — | check esquina | grayscale | — | — | — | — | — |
| Select/Search | papel | — | — | — | — | — | — | — | focus anillo lavanda | borde rojo |
| TimelineItem | papel | — | — | — | — | — | flag NUEVO | — | — | — |
