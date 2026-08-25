# 01 · Foundations — ADN visual de Aquí Hay Tema

Extraído del proyecto real + las 10 referencias aprobadas de `Diseño_Ayuda/news/`.

---

## 1. Principio rector

> **"Esto es un JUEGO, no una página web decorada."**

La interfaz es un pueblo de papel habitado. Cada ventana es un objeto físico: una carta, una ficha, una nota, un plan pegado con cinta. El jugador toca cosas, no enlaces.

### Anti-patrón prohibido

Queda explícitamente vetado derivar hacia:

- "interfaz web limpia y minimalista con cuatro colores parecidos";
- tarjetas planas grises con sombras genéricas;
- tipografía sans uniforme sin voz;
- densidad de dashboard.

Si un componente puede confundirse con una web de gestión, está mal.

## 2. Los 12 rasgos del ADN

1. **Superficie de papel crema cálido** con grano sutil. Nunca blanco puro. Las tarjetas son papel más claro apoyado sobre el papel de fondo.
2. **Trazo artesanal**: bordes marrón-tinta de 1.5–2 px, radios generosos, imperfección controlada (rotaciones ±1.5°, bordes que no son perfectamente rectos cuando el componente lo permite).
3. **Doble borde con costura** en CTAs y elementos importantes: contorno exterior + línea interior punteada (tipo puntada de tela/pegatina).
4. **Sombras suaves y cálidas**, cortas: el papel está *apoyado*, no flotando.
5. **Títulos-rotulador**: manuscrita gruesa en MAYÚSCULAS con mucha presencia (Caveat 700 mientras no se apruebe otra). Detalles de rayitas/destellos alrededor.
6. **Manuscrita narrativa**: nombres, mensajes, cotilleos y descripciones en caligrafía tipo bolígrafo. Los datos duros (horas, contadores, XP) van siempre en sans legible.
7. **Jerarquía enorme**: el avatar protagonista mide 96–120 px en ficha; los títulos dominan; hay aire entre secciones.
8. **Color = significado**: coral → acción principal; lavanda → secundario/informativo; mostaza → recompensa/tiempo; verde → positivo; rojo → alerta/NUEVO.
   **REGLA CROMÁTICA v3 (dirección, pasada 2 del piloto)**: cuando la referencia use **coral/naranja como color PROTAGONISTA**, traducirlo al **ROSA AHT** (familia Nuevo Plan `--ds-pink`/`--ds-pink-deep`, corazones, Parejas). No significa todo rosa: lavandas, verdes, mostazas y beige siguen existiendo como complementarios. El coral queda relegado a acentos secundarios no protagonistas.
9. **Pills de estado con emoji** siempre que exista emoción o estado (🙂 😐 😠 💔…).
10. **Decoración con significado** (ver §4): nada de adorno gratuito.
11. **Descubrimiento y progresión**: lo conocido = tarjeta sólida con ✓; lo desconocido = borde punteado + candado + "?". El juego se *ve* progresar.
12. **Vacíos con voz**: los estados vacíos llevan doodle + frase con personalidad ("Su agenda está sospechosamente tranquila."). Nunca "No hay datos".

## 3. Sensación de objeto físico

| Objeto real | Traducción UI |
|---|---|
| Carta de correo | MessageCard (Mensajitos) |
| Ficha de registro | Ficha de vecino |
| Nota adhesiva | Chips, hints, avisos |
| Polaroid / foto chinchetada | Próximo plan, cotilleo destacado |
| Ticket de quiosco | Día/hora, XP, recompensas |
| Sello / lacre | Marcas de importante, badges |
| Cinta adhesiva | Anclaje de tarjetas y cabeceras |
| Chincheta | Notas fijas (cotilleo del día) |
| Ribbon/tiras de sección | Títulos de sección dentro de fichas |
| Botón físico | CTAs con volumen (sombra interior inferior) y press |

## 4. Decoración — reglas de uso

La decoración **no puede** perjudicar legibilidad ni convertir la UI en ruido.

| Elemento | Dónde sí | Dónde nunca | Límite |
|---|---|---|---|
| Cinta adhesiva | Esquina sup. de tarjetas y modales, cabeceras | Dentro de formularios, sobre texto | 1 por tarjeta, rotación ±2–4°, semitransparente |
| Chincheta/pin | Cabeceras de nota destacada (cotilleo del día) | En listas | 1 por modal |
| Ribbon de sección | Título de sección en fichas/diario | Elementos sueltos | 1 por sección, siempre con título |
| Rayitas de énfasis | Junto a CTAs primarios y badges NUEVO | Texto corriente | 2–3 trazos |
| Doodles (corazones, estrellas, hojas) | Estados vacíos, hints, separadores | Sobre contenido | Máx. 1 por zona |
| Rotación de tarjeta | Feed de inicio, cartas | Inputs, listas densas, tablas | ±1.5° |
| Costura punteada | CTAs, tabs activas, tarjetas destacadas | Bordes de todo | Componentes clave |
| Estrella | Recompensa, XP, guardado (futuro) | Decoración suelta | Con significado |
| Candado + "?" | Estados bloqueados/desconocidos reales; en el mapa, **lugar cerrado ahora por horario** (D9) | Nada ficticio ni bloqueos progresivos inventados | Solo datos reales (`abierto_ahora`, slots de descubrimiento) |

**Regla de contraste**: todo texto funcional ≥ 4.5:1 sobre su fondo. La manuscrita decorativa puede bajar a 3:1 solo si es redundante.

## 5. Personalidad por pantalla (misma ADN, distinto cuerpo)

| Pantalla | Personalidad | Piezas propias |
|---|---|---|
| Inicio | El tablero del pueblo | Feed de módulos, mapa protagonista |
| Mensajitos | El buzón | Tabs cosidas, cartas, sellos NUEVO/VISTO, ticket XP |
| Vecinos/Relaciones | El registro del pueblo | Pestañas, grid de fichas, tarjetas A↔B |
| Ficha | El expediente | Ribbons de sección, slots de descubrimiento, CTA final |
| Ánimo | El parte médico | Banner de emoción, tarjeta causa, lista de efectos |
| Diario | La cronología | Timeline con nodos, cabeceras de día tipo cinta |
| Nuevo Plan | El formulario del organizador | Pasos numerados 1-4, resumen final pegado con cinta |
| Cotilleos | El periódico | Cabecera ilustrada, filtros-icono, cabeceras de fecha |

## 6. Voz de los estados

- **Vacío**: frase con personalidad + doodle. Ej.: "Aún no hay parejas registradas." + corazones.
- **Bloqueado/desconocido**: candado + "?", con microcopy honesto ("Por descubrir", "Por conocer").
- **Nuevo**: badge/flag rojo "NUEVO" con rayitas.
- **Error/aviso**: tinte rojo/ámbar + icono, nunca texto seco sin contexto.
- **Éxito**: pill verde con ✓ ("Todo listo").

## 7. Accesibilidad mínima

- Targets táctiles ≥ 44×44 px.
- Texto funcional ≥ 15 px en móvil; microtexto ≥ 11 px solo en MAYÚSCULAS espaciadas.
- La manuscrita nunca porta datos críticos por debajo de 16 px.
- Estados nunca solo por color: siempre emoji/icono/texto acompañante.
- `prefers-reduced-motion`: sin animaciones de entrada (ya existe en el proyecto, se respeta).
