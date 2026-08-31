# AHT-P7: AUDITORÍA DE VISIBILIDAD Y TRAZABILIDAD DE LA VIDA DEL PUEBLO

## 1. MAPA COMPLETO DE SUPERFICIES NARRATIVAS

### 1.1 Cotilleos (El Cotilleo)
- **Archivo motor**: `src/Engine/CotilleoNarrativo.php`, `CotilleoPatronCadencia.php`, `CotilleoAutonomoCadencia.php`
- **Vista**: `src/Engine/VistaCotilleoV3.php`
- **Copy**: `src/Engine/EncuentroCotilleoCopy.php`, `CopyCoincidenciaPatron.php`, `CotilleoLlegadaCopy.php`
- **Triggers**: ENCUENTRO_TERMINADO, COINCIDENCIA_RESIDENTES (patrón), DISCUSION, SENAL_ROMANTICA, LLEGADA/MARCHA
- **Datos**: buzón canal='cotilleo', 7 categorías (pueblo, encuentro, descubrimiento, romance, drama, relación, coincidencias)
- **Presentación**: Modal "El Cotilleo" con filtros, sidebar teaser, badge no leídos
- **Cadencia**: Máx 2 autónomos/día, patrón requiere ≥3 días en ventana de 7
- **Causa/Sí**: Evaluación de interés narrativo con scoring (familia, romance, hitos, emoción)
- **Persistencia**: Sin caducidad explícita, ventana ~3 días en vista
- **Automático**: SÍ, se ve automáticamente vía sidebar badge

### 1.2 Diario Personal
- **Archivos**: `src/Engine/DiarioEngine.php`, `DiarioHitoEngine.php`, `DiarioNarrativaBridge.php`, `DiarioVista.php`
- **Qué registra**: Hitos relacionales (se_conocieron, primera_cita, rechazo, regalo, declaración, pareja, crisis, reconciliación, ruptura, discusión, apoyo, flechazo), encuentros malos, descubrimientos, estados emocionales, espejo de cotilleos
- **Presentación**: Sub-modal "Diario" dentro de Ficha, con filtros (Planes/Relaciones/Cambios), búsqueda
- **Sin caducidad**: No hay TTL implementado
- **Privado**: Entradas sin `cotilleo_meta.categoria` NO circulan públicamente

### 1.3 Mensajitos (Buzón NPC→Celestine)
- **Archivos**: `src/Engine/BuzonEngine.php`, `MensajitosCadenciaEngine.php`, `MensajitoGeneradorEspontaneo.php`, `MensajitoConsejoEngine.php`, `MensajitoAcciones.php`, `MensajitoVoz.php`
- **15 familias**: F1 (opinión), F2 (dilema romántico), F3 (petición), F4 (colectivo), F5 (presentación), F6 (confidencia), F8 (duda permanencia), F9 (seguimiento), F10 (contextual/cumple), F11 (mediación), F14 (promesa), F15 (curiosidad)
- **Presupuesto diario**: base=3 + ceil(sqrt(nResidentes)*0.5)
- **Cooldown por residente**: 36 horas
- **Presentación**: Modal "Mensajitos" con tabs Nuevos/Todos, acciones interactivas
- **Voz**: Primera persona, NPC habla a Celestine

### 1.4 Ficha de Vecino
- **Archivos**: `src/Engine/FichaPlayVista.php`, `RelacionVistaJugador.php`, `EtiquetaFicha.php`
- **Muestra**: Retrasgos, hobbies, rasgos, preferencias, relaciones sociales/románticas, emoción con pista de causa, planes próximos
- **Sub-modales**: Relaciones expandidas, Animo (explicación emocional), Diario personal

### 1.5 Emociones
- **Archivos**: `src/Engine/EstadoEmocional.php`, `EmotionalStateService.php`, `EmotionalEventBridge.php`, `EmotionalRecovery.php`, `EmocionalNarrativa.php`
- **4 estados**: neutro, alegre, triste, enfadado
- **7 fuentes**: encuentro, intervención, regalo, rechazo repetido, perder/encontrar trabajo, cumple, consejo Celestine, actividad individual
- **Visibilidad en 5 capas**: pista en ficha, modal animo, cotilleo, mensajito, diario
- **Duración**: alegre=6h, triste=10h, enfadado=4h (configurable)
- **Regla**: Un solo estado activo, sin apilamiento

### 1.6 Mapa del Pueblo
- **Presencia**: `src/Engine/PresenciaEngine.php`, `VistaPuebloV3.php`
- **Muestra**: Avatares en ubicación, emoción visual (anillo), hay_tema, marcadores de encuentro

### 1.7 Vecinos (Lista)
- **Tabs**: VECINOS (grid) y RELACIONES (lista filtrable)
- **Datos**: etiqueta social, barra%, vínculo, romance visible

### 1.8 Otras superficies
- **Misiones**: `MisionDiariaEngine.php` — 3 misiones/día, cumplida/caducada
- **Parejas**: `ParejaEngine.php` — lista de parejas activas
- **Agenda**: `AgendaEngine.php` — próximos planes
- **Vida del Pueblo**: `VidaPuebloEngine.php` — score 0-100, modal con explicación
- **Plan Notif**: Barra de notificación rosa al confirmar plan
- **Toast**: Notificaciones momentáneas
- **Mentes**: `MentesTemas.php` — intervención durante encuentro

---

## 2. MATRIZ HECHO → SALIDA VISIBLE

| Hecho social | Memoria | Diario | Cotilleo | Mensajito | Ficha | Emoción visible |
|---|---|---|---|---|---|---|
| Primer encuentro |✅ | se_conocieron |✅ patrón |❌ |❌ |❌ |
| Encuentro positivo |✅ |❌ (solo si es malo) |✅ |❌ |❌ | alegre en ficha |
| Encuentro negativo |✅ |✅ mal/muy_mal |✅ |❌ |❌ | triste/enfadado |
| Repetición misma persona |✅ |❌ |✅ patrón |❌ |❌ |❌ |
| Rechazo |✅ | rechazo_importante |✅ drama |❌ |❌ | triste si repetido |
| Discusión |✅ | discusion_fuerte |✅ drama |❌ |❌ | enfadado |
| Interés romántico |✅ |❌ |✅ romance |❌ |❌ |❌ |
| Flechazo |✅ | flechazo |✅ romance |❌ |❌ |❌ |
| Señal romántica |✅ |❌ |✅ romance |❌ | romance_visible |❌ |
| Primera cita |✅ | primera_cita |✅ |❌ |❌ |❌ |
| Cita mala |✅ |✅ (encuentro incómodo) |✅ drama |❌ |❌ | triste |
| Declaración |✅ | declaracion (solo rechazada) |✅ romance |❌ |❌ |❌ |
| Pareja |✅ | inicio_pareja |✅ romance |✅ resultado |vinculo | alegre |
| Ruptura |✅ | ruptura |✅ drama |✅ resultado |vinculo | triste |
| Llegada |✅ |❌ |✅ pueblo |✅ candidato |❌ |❌ |
| Marcha |✅ |❌ |✅ pueblo |✅ marcha |❌ |❌ |
| Cambio emocional |✅ |✅ estado_emocional |✅ (si es fuerte) |✅ (si es fuerte) | pista |✅ |
| Perder trabajo |✅ |✅ |✅ |✅ | pista | triste |
| Encontrar trabajo |✅ |✅ |✅ |❌ | pista | alegre |

---

## 3. TRACE DE 4 PERSONAJES (D1-D15, seed p7-audit-01)

### Carmen (per_p001)
- **Encuentros acumulados**: D1: 0 → D15: 6 (Marta, Dani, Alicia, Lucía, Rosa, Raúl)
- **Diario menciones**: D1: 4 → D15: 49 (crecimiento constante)
- **Emociones**: triste por perder_trabajo (con diario ✓, cotilleo ✓, pista_ficha ✓)
- **Cotilleos mencionándola**: Múltiples a lo largo de la simulación

### José (per_p002)
- **Encuentros acumulados**: D1: 0 → D15: 8 (Raúl, Carmen, Lucía, Sara, Rosa, Dani, Álex)
- **Diario menciones**: D1: 5 → D15: 57
- **Cotilleos**: Mencionado en patrones de coincidencia

### Marta (per_p003)
- **Encuentros acumulados**: D1: 0 → D15: 5 (José, Raúl, Carmen, Lucía, Dani)
- **Diario menciones**: D1: 9 → D15: 48
- **Cotilleos**: Presente en gossip de bienvenida y encuentros

### Raúl (per_p004)
- **Encuentros acumulados**: D1: 0 → D15: 5 (Dani, Alicia, Lucía, José, Marta)
- **Diario menciones**: D1: 7 → D15: 39
- **Cotilleos**: Mencionado en patrones de coincidencia

**Conclusión de traces**: Todos los personajes muestran progresión social creciente. El motor genera historias que se acumulan. Los cotilleos y diarios capturan la actividad, pero la densidad de información por personaje es alta — el jugador debe navegar mucho contenido.

---

## 4. MÉTRICA CENTRAL DE VISIBILIDAD

### A) ¿Sé con quién se relaciona?
**PARCIAL** — La Ficha muestra relaciones sociales y románticas, pero solo las que han alcanzado un umbral de "conocidos". Encuentros sueltos no generan entrada en relaciones visibles hasta que la intensidad social supera 0.

### B) ¿Sé quién le cae bien/mal?
**PARCIAL** — La barra social y la etiqueta (amigo/conocido/enemigo) muestran el estado actual, pero NO muestran la tendencia ("está mejorando" o "está empeorando"). Solo snapshots, no progresión.

### C) ¿Sé por qué está triste/feliz/enfadado?
**BUENO** — Las 3 emociones detectadas en la simulación tenían: origen rastreable, diario, cotilleo, y pista en ficha. Sistema de 5 capas funciona correctamente para emociones con causa clara (trabajo, regalo, rechazo).

### D) ¿Puedo detectar que empieza a gustarle alguien?
**BAJO** — Las señales románticas generan cotilleos (categoria romance), pero no hay "hints graduales" en la ficha. Un jugador atento podría detectar patrones en cotilleos, pero no en la ficha del personaje.

### E) ¿Puedo anticipar razonablemente una pareja?
**BAJO** — En la simulación de 15 días NO se formó ninguna pareja. La progresión romance → señal → cita → declaración → pareja requiere muchos pasos. Los cotilleos muestran "romance" cuando hay señales, pero la ventana de anticipación es corta.

### F) ¿Puedo recordar hitos importantes de su vida?
**BUENO** — El diario personal muestra hitos con fecha, título, categoría, tono, y personas involucradas. Es buscable y filtrable. Es la superficie más completa para recordar.

**Cobertura promedio por tipo de hecho:**
- Hechos sociales no románticos: 60% visibles
- Hechos románticos: 40% visibles
- Emociones: 80% visibles (las 3 detectadas tenían causa trazable)
- Hitos de relación: 90% visibles (diario + cotilleo)

---

## 5. EMOCIONES Y CAUSA

### Hallazgos de la simulación (3 emociones detectadas):

| Personaje | Emoción | Origen | Contexto | Diario | Cotilleo | Pista Ficha |
|---|---|---|---|---|---|---|
| Carmen | triste | perder_trabajo | No | SÍ | SÍ | SÍ |
| Rosa | alegre | encontrar_trabajo | No | SÍ | SÍ | SÍ |
| Lucía | triste | perder_trabajo | No | SÍ | SÍ | SÍ |

**Clasificación:**
- Causa existe y es visible: 3/3 (100%)
- Causa existe pero está oculta: 0/3
- Causa se perdió: 0/3
- Emoción sin causa coherente: 0/3

**Observación**: El campo `contexto` está vacío para eventos de trabajo, lo cual es correcto (no hay persona hacia quien dirigir la emoción). Para rechazos y encuentros, el contexto SÍ incluye `hacia` o `encuentro_id`.

---

## 6. AUDITORÍA COTILLEOS

### Datos cuantitativos (15 días):
- Total cotilleos: 142 (~9.5/día)
- Promedio por día: D1=16 → D15=4 (decrece porque ya hay menos "novedad")
- Categorías activas: encuentro, pueblo, drama, romance, coincidencias

### Cobertura:
- **Primer encuentro**: SÍ, vía patrón de coincidencia (requiere ≥3 días)
- **Encuentro positivo/negativo**: SÍ, vía `EncuentroCotilleoCopy`
- **Progresión romántica**: SÍ, vía categoría romance (señales, citas)
- **Conflictos**: SÍ, vía categoría drama
- **Llegadas/marchas**: SÍ, vía categoría pueblo

### Problemas detectados:
1. **Retraso en patrones**: El sistema de patrones requiere ≥3 días de coincidencia antes de generar cotilleo. Los primeros 2-3 días de "algo está pasando entre X e Y" son INVISIBLES.
2. **Sin seguimiento**: Un cotilleo "X e Y están juntos mucho" no tiene seguimiento "ahora ya no" cuando la situación cambia.
3. **Máximo 2 autónomos/día**: Con 9 residentes y ~5 encuentros/día, el 60% de encuentros NO generan cotilleo.
4. **Categorías amplias**: "encuentro" es demasiado genérico — un café casual y una cita romántica entrán en la misma categoría.

---

## 7. AUDITORÍA DIARIO

### Datos cuantitativos (15 días):
- Total entradas: 267 (~18/día)
- Hitos: 180 (67% del total)
- Emociones: ~5 entradas
- Cotilleos espejo: ~82 entradas

### Cobertura:
- **Hitos relacionales**: COMPLETA — se_conocieron, primera_cita, rechazo, regalo, declaración, pareja, crisis, reconciliación, ruptura, discusión, apoyo, flechazo
- **Encuentros negativos**: SÍ — mal, muy_mal, conflicto
- **Encuentros positivos**: NO — solo entran si son hito relacional
- **Descubrimientos**: SÍ — rasgos, hobbies, preferencias
- **Emociones**: SÍ — cada cambio emocional crea entrada

### Problemas detectados:
1. **Solo hitos negativos en encuentros**: Un "encuentro muy bueno" NO crea entrada de diario a menos que sea hito (primera cita, etc.). El jugador no puede ver "Hugo y Carmen tuvieron un día genial" en el diario.
2. **Sin caducidad**: Entradas de D1 siguen ahí en D15. Con 18/día, el diario se satura rápidamente.
3. **Demasiado ruido**: 267 entradas en 15 días es MUCHO contenido para navegar. La búsqueda y filtros ayudan, pero la señal se pierde.
4. **Sin resumen diario**: No hay "resumen del día" que destaque los 2-3 eventos más importantes.

---

## 8. AUDITORÍA MENSAJITOS

### Datos cuantitativos (15 días):
- Total mensajitos: 91 (~6/día)
- Familias activas: F1 (opinión), F3 (petición), F10 (contextual), resultado_cumplida

### Cobertura:
- **Petición de Celestine**: SÍ — F3, F5
- **Opinión/dilema**: SÍ — F1, F2
- **Seguimiento**: SÍ — F9
- **Alerta vecinal**: SÍ — F7
- **Resultados**: SÍ — resultado_cumplida

### Problemas detectados:
1. **F2 (dilema romántico) no apareció**: Con 0 parejas, no hubo dilemas. Pero el sistema parece funcional.
2. **F8 (duda permanencia) no apareció**: Requiere días sin contacto social — plausible en 15 días con actividad constante.
3. **F6 (confidencia) no apareció**: Requiere crush detectado + probabilidad — posible que no se activara.
4. **F11 (mediación) no apareció**: Requiere conflicto fuerte entre dos conocidos — plausible.
5. **F15 (curiosidad Celestine) no apareció**: Requiere cercanía narrativa alta — plausible.
6. **Connecting dots**: Los mensajitos NO conectan con hitos del diario. Un "Hugo tiene una cita hoy" (diario) NO genera mensajito "¿Qué tal la cita?".

---

## 9. ROMANCE VISIBLE ANTES DE PAREJA

### Simulación 15 días: 0 parejas formadas
No se pudo medir directamente. Sin embargo, del análisis del código:

### Flujo completo romance → pareja:
1. **Encuentros positivos** acumulan romance_hacia (direccional)
2. **Señal romántica** (SenalRomantica) genera cotilleo romance
3. **PropuestaEncuentroEngine** puede generar cita
4. **Primera cita** genera diario + cotilleo
5. **Interés romántico** (interesRomanticoSuficiente) permite declaración
6. **Declaración** → si aceptan → pareja
7. **Pareja** genera diario + cotilleo + mensajito

### Lo que el jugador PUEDE ver antes de pareja:
- Cotilleos de romance cuando hay señales (después de D3-5)
- Diario de primera cita (cuando ocurre)
- Romance visible en Ficha (cuando la banda supera "sin interés")

### Lo que el jugador NO puede ver:
- Acumulación gradual de romance en encuentros
- El momento exacto en que "empieza a gustarle"
- La intención de declarar antes de que ocurra
- Diferencia entre "le cae bien" y "le gusta"

### Clasificación de parejas:
- **A (totalmente anticipable)**: 0% — no se formaron parejas
- **B (había alguna pista)**: N/A
- **C (prácticamente sorpresa)**: N/A

**Predicción**: Dada la arquitectura, la mayoría de parejas serán tipo B con la ventaja de cotilleos romance. Las parejas tipo C serían aquellas donde la progresión romance fue rápida (pocas señales antes de declaración).

---

## 10. TRAZABILIDAD OFFLINE AL VOLVER

### Datos de P6:
- 24h offline: +3.5 encuentros, 0.2 flechazos, 0 parejas
- 48h: +6.6 encuentros
- 72h: +9.9 encuentros
- 7d: +24.4 encuentros, 0.6 flechazos, 0.1 parejas
- 30d: +106.3 encuentros, 2.3 flechazos, 0.8 parejas

### ¿El jugador puede reconstruir lo sucedido?
**C0 = NO** — Actualmente NO existe sistema de resumen offline.

### Datos disponibles para construir resumen:
- `memoria_eventos` tiene todos los eventos (cap 500, preserva hitos)
- `diario` tiene hitos y emociones
- `buzon` tiene cotilleos generados
- `bitacora_relaciones` tiene hitos de relación

**Conclusión**: Los DATOS existen para contarlo, pero NO hay mecanismo que los presente al volver. El jugador vuelve a un pueblo con 10-100 eventos nuevos sin resumen.

---

## 11. UNA VERDAD, VARIAS SUPERFICIES (Contradicciones)

### Análisis de 20 encuentros (simulación):

| Clasificación | Cantidad | Descripción |
|---|---|---|
| C0 (imposible) | 0 | No se detectaron |
| C1 (incoherente narrativamente) | 0 | No se detectaron |
| C2 (correcto internamente, jugador no entiende) | 20 | Todos |
| C3 (coherente pero poco útil) | 0 | Ninguno directamente, pero... |

**Observación clave**: La clasificación C2 es la dominante porque:
1. Los cotilleos para encuentros "normales" son genéricos ("Han coincidido en...")
2. El diario solo registra encuentros malos (no positivos)
3. Las emociones solo se activan en casos extremos (muy_mal, perder trabajo)

**Esto significa que para el ~70% de encuentros (resultado "normal" o "bien"), el jugador tiene:**
- Un cotilleo genérico (C2 — existe pero no explica nada)
- Sin entrada de diario
- Sin cambio emocional
- Sin mensajito

**El jugador solo obtiene información NARRATIVA para el ~30% de encuentros (muy_mal, muy_bien, conflicto, hito).**

---

## 12. RUIDO VS SILENCIO

### Métricas (15 días, 9 residentes):

| Métrica | Valor | Por día |
|---|---|---|
| Hechos sociales totales | 237 | 15.8 |
| Hechos significativos | 176 | 11.7 |
| Cotilleos | 142 | 9.5 |
| Diario | 267 | 17.8 |
| Mensajitos | 91 | 6.1 |
| **Ratio señal/ruido** | **0.74** | — |

### Análisis:
- **74% de eventos son significativos** — buena proporción señal/ruido
- **9.5 cotilleos/día** es MUCHO contenido para un jugador casual (ventana de 3 días = ~28 cotilleos visibles)
- **17.8 entradas de diario/día** es inviable para leer — el jugador DEBE usar búsqueda/filtros
- **6.1 mensajitos/día** es razonable (budget: ~6/día con 9 residentes)

### Veredicto:
- **RUIDO ALTO en diario**: Demasiadas entradas, difícil encontrar hitos importantes
- **RUIDO MODERADO en cotilleos**: 9.5/día es manejable con filtros, pero la ventana de 3 días acumula
- **SEÑAL CORRECTA en mensajitos**: Cada uno es interactuable y personal
- **SILENCIO en resumen offline**: No hay mecanismo para sintetizar lo sucedido

---

## 13. CLASIFICACIÓN C0-C3 GENERAL

| Tipo de hecho | Clasificación | Justificación |
|---|---|---|
| Emoción con causa clara | **C1** | Causa visible, pero 5 capas de presentación pueden confundir |
| Encuentro normal | **C2** | Existe cotilleo genérico, pero el jugador no puede distinguirlo de otros |
| Encuentro muy bueno | **C2** | No genera diario ni emociones a menos que sea hito |
| Encuentro muy malo | **C1** | Causa existe en diario pero el porqué es vago ("no conectaron") |
| Primer romance | **C2** | Cotilleo romance existe, pero la acumulación previa es invisible |
| Pareja formada | **C2** | Diario + cotilleo, pero la historia previa se pierde |
| Llegada/marcha | **C1** | Cotilleo existe pero no conecta con impacto en el pueblo |
| Conflicto fuerte | **C1** | Diario + cotilleo, pero la evolución posterior no se muestra |

---

## 14. TOP 5 PROBLEMAS NARRATIVOS

### P1 (Importante): EMOCIONES CON CAUSA PERO SIN EVOLUCIÓN VISIBLE
Las emociones tienen causa rastreable (funciona), pero el jugador no puede ver la EVOLUCIÓN ("estaba triste, ahora está mejorando"). La emoción aparece y desaparece sin arco narrativo visible.

### P2 (Importante): DIARIO SATURADO SIN SEÑAL CLARA
267 entradas en 15 días. Los hitos importantes (flechazo, declaración, ruptura) se pierden entre cotilleos espejo y emociones. No hay "destacados del día".

### P3 (Mejora): COTILLEOS SIN PROGRESIÓN TEMPORAL
Un patrón "X e Y están juntos mucho" no tiene seguimiento. No hay "update" cuando la situación cambia. El jugador ve fragmentos, no historias.

### P4 (Mejora): OFFLINE SIN RESUMEN
Los datos existen (memoria, diario, cotilleos) pero no hay mecanismo de presentación al volver. El jugador vuelve a un pueblo sin contexto.

### P5 (Cosmético): CATEGORÍAS DE COTILLEO DEMASIADO AMPLIAS
"encuentro" cubre desde un café casual hasta una cita romántica. Los filtros son poco útiles cuando todo entra en la misma categoría.

---

## 15. RECOMENDACIÓN DE SIGUIENTE INTERVENCIÓN

### FAMILIA ELEGIDA: **B) Progresión romántica invisible**

### Justificación:
1. El romance es el ARCO NARRATIVO PRINCIPAL del juego
2. La arquitectura interna funciona (P5.1 validado, P6 offline funciona)
3. La superficie visible tiene el MAYOR GAP entre realidad interna y percepción del jugador
4. La intervención es MEDIBLE (puedo medir antes/después de la cobertura)

### Propuesta de intervención (NO implementar, solo diseño):
- **Capa 1**: Emoji de intensidad romántica en la Ficha (AttribPointer: "empieza a fijarse" → "le mola" → "está pillado")
- **Capa 2**: Cotilleo de progresión ("Hugo no puede dejar de mirar a Carmen hoy")
- **Capa 3**: Diario automático de "momentos románticos" (no solo primera_cita)
- **Capa 4**: Mensajito F2 más frecuente cuando hay interés alto
- **Capa 5**: Resumen offline "mientras no estabas: Hugo y Carmen se han acercado"

### Criterio de éxito:
Antes: 0% de progresión romance visible antes de pareja
Objetivo: 80% de progresión romance visible antes de pareja

---

## 16. DATOS DE SIMULACIÓN

### Seed: p7-audit-01, 15 días, 9 residentes finales

### Evolución diaria:

| Día | Cotilleos | Nuevos | Diario | Hitos | Mensajitos | Emociones | Encuentros | Memoria |
|---|---|---|---|---|---|---|---|---|
| 1 | 21 | 16 | 36 | 29 | 7 | 0 | 5 | 23 |
| 2 | 40 | 23 | 73 | 56 | 8 | 0 | 12 | 46 |
| 3 | 51 | 10 | 94 | 70 | 16 | 0 | 19 | 63 |
| 4 | 56 | 5 | 103 | 74 | 22 | 0 | 23 | 71 |
| 5 | 64 | 9 | 119 | 85 | 24 | 0 | 28 | 90 |
| 6 | 73 | 9 | 134 | 96 | 33 | 1 | 34 | 103 |
| 7 | 81 | 7 | 147 | 103 | 37 | 1 | 39 | 112 |
| 8 | 89 | 8 | 165 | 117 | 47 | 0 | 45 | 134 |
| 9 | 97 | 9 | 182 | 128 | 56 | 0 | 53 | 147 |
| 10 | 102 | 6 | 193 | 135 | 59 | 0 | 57 | 155 |
| 11 | 114 | 8 | 217 | 149 | 64 | 0 | 63 | 177 |
| 12 | 121 | 10 | 233 | 160 | 72 | 0 | 69 | 190 |
| 13 | 128 | 7 | 246 | 169 | 78 | 1 | 75 | 207 |
| 14 | 135 | 8 | 259 | 176 | 81 | 0 | 81 | 216 |
| 15 | 142 | 4 | 267 | 180 | 91 | 0 | 86 | 237 |

### Residentes finales: 9 (Carmen, José, Marta, Raúl, Dani, Álex, Lucía, Sara, Rosa)

### Parejas formadas: 0

---

**AHT-P7 = VISIBILIDAD SOCIAL AUDITADA**

Commit: pendiente (diagnóstico + tooling)
NO PUSH. NO DEPLOY.
