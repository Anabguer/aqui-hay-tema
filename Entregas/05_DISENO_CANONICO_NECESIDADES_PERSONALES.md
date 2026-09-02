# AHT — DISEÑO CANÓNICO DEL SISTEMA DE NECESIDADES PERSONALES

**Estado:** DISEÑO PRODUCTO APROBADO — FASE DE IMPLEMENTACIÓN
**Fecha:** 2026-09-02
**Alcance:** Diseño de producto + arquitectura basada en código real
**Decisiones cerradas:** 4 necesidades, 9 lugares canónicos, principal/secundaria, peticiones, visibilidad global

---

## 0. Principios ya cerrados

| # | Principio |
|---|-----------|
| P1 | Necesidades NO es Tamagotchi |
| P2 | Celestine NO mantiene barras vitales de NPC |
| P3 | Los NPC satisfacen necesidades autónomamente |
| P4 | Necesidad ≠ emoción |
| P5 | Socializar ≠ tener amigos |
| P6 | Necesidades profundas NO se convierten automáticamente en barras |
| P7 | Personalidad y hobbies importan |
| P8 | Necesidad dice QUÉ falta; actividad/lugar/compañía ayudan a decidir CÓMO cubrirlo |
| P9 | Edificios son data-driven |
| P10 | Actividad ≠ edificio |
| P11 | Necesidad baja crea tendencia/oportunidad, no castigo de supervivencia |
| P12 | El jugador necesita información útil pero no una hoja Excel |
| P13 | Ficha Vecinos debe permitir entender qué le vendría bien |
| P14 | Edificios deben poder explicar qué necesidades ayudan a cubrir |
| P15 | Relaciones es autoridad sobre amistad/romance/conflicto/intenciones |
| P16 | Necesidades SOLO CONSUME el contrato de Relaciones |
| P17 | Regalitos no crea una necesidad de regalos |
| P18 | Nueva partida no empieza con todos en rojo |

---

## 1. Cuáles son finalmente las necesidades cotidianas

**Catálogo cerrado: 4 necesidades.**

| ID | Nombre | Icono | Descripción de producto |
|----|--------|-------|------------------------|
| `social` | Socializar | 🫂 | Necesidad de contacto social cotidiano: hablar con alguien, quedar, sentirse parte de algo. |
| `diversion` | Diversión | 🎉 | Necesidad de ocio, experiencias agradables, entretenimiento. |
| `actividad` | Actividad | 💪 | Necesidad de moverse, salir, hacer algo con el cuerpo o con las manos. |
| `calma` | Calma | ☕ | Necesidad de desconexión, estar tranquilo, no tener agobios ni ruido. |

**Por qué 4 y no más:**

- Cubren el espectro de vida social y personal sin fragmentarse.
- Cada una se distingue claramente de las demás en el jugador.
- Se alinean con los lugares/hobbies ya existentes en el catálogo (`data/catalogos/aficiones.json`, `data/lugares/lugares.json`).
- Menos barras = menos mantenimiento = más decisiones.

**Qué NO es necesidad:**

- Amistad → la gestiona Relaciones.
- Romance → la gestiona Relaciones.
- Contacto profundo → la gestiona Relaciones.
- Recibir regalos → herramienta de Regalitos, no necesidad.
- Higiene/sueño → AHT no es simulador físico.

---

## 2. Qué significa cada una jugando

### 🫂 Socializar

**Significa:** "Llevo tiempo sin hablar con nadie / sin estar en compañía."

**En juego:**
- Marta lleva 3 días sin quedar con nadie → necesita socializar.
- José sale cada día pero siempre solo → necesita socializar.
- Un NPC que acaba de llegar al pueblo y no conoce a nadie → necesita socializar.

**Regla aprobada:** Un lugar social puede cubrir algo de Socializar incluso estando solo. Estar acompañado puede cubrir más. Una interacción real puede cubrir todavía más. Necesidades y Relaciones son independientes: alguien puede cubrir Socializar y a la vez empeorar su relación con la persona con la que interactúa.

**NO significa:**
- Que esté triste (puede estar alegre y necesitar contacto).
- Que tenga pocos amigos (puede tener 5 amigos profundos y necesitar socializar igualmente).
- Que sea sociable (un reservado también necesita contacto humano).

### 🎉 Diversión

**Significa:** "Llevo tiempo sin hacer nada que me guste / me divierta."

**En juego:**
- Víctor lleva días yendo al trabajo y a casa → necesita diversión.
- Laura se lo pasa bien pero siempre haciendo lo mismo → necesita diversión.
- Un NPC que siempre va al bar pero ya no le divierte → necesita diversión.

**NO significa:**
- Que esté aburrido (puede estar ocupado y seguir necesitando diversión).
- Que haga falta mandarlo a un plan divertido cada día.

### 💪 Actividad

**Significa:** "Llevo tiempo sin moverme / salir / hacer algo físico o manual."

**En juego:**
- Marta lleva días en casa → necesita actividad.
- José va al bar sentado toda la tarde → necesita actividad.
- Un NPC muy activo que lleva 2 días sin salir → necesita actividad.

**NO significa:**
- Que haga falta deporte (caminar, manualidades, pasear también cuentan).
- Que esté gordo o sedentario (es un concepto de bienestar, no de forma física).

### ☕ Calma

**Significa:** "Llevo días agobiado / sin un momento para mí / demasiado ruido."

**En juego:**
- Laura trabaja intensamente toda la semana → necesita calma.
- Víctor ha tenido varios encuentros tensos → necesita calma.
- Un NPC muy sociable que lleva días saliendo cada noche → necesita calma.

**NO significa:**
- Que esté estresado (puede estar bien pero necesitar desconectar).
- Que sea introvertido (un extrovertido también necesita calma).

---

## 3. Cómo evolucionan

### Modelo de decay y recuperación

```
Estado actual (0-100) → Decay horario → Actividad en lugar adecuado → Recuperación
```

**Decay horario:**
- Cada necesidad pierde puntos cada hora de juego.
- El decay es UNIFORME para todas las necesidades en un principio (calibración posterior).
- NO se fija tasa definitiva aquí (se calibrará en PlayTest).
- Referencia conceptual: ~2-4 puntos/hora es rango razonable a explorar.

**Recuperación:**
- Estar en un lugar que cubra la necesidad → acumula recuperación por hora.
- La recuperación depende de: lugar + actividad + compañía + hobbies.
- Ejemplo: estar en el gimnasio con un amigo con hobbies compatibles = recuperación alta.
- Ejemplo: estar en el cine solo = recuperación media-baja.

### Bandas de estado

| Banda | Rango | Significado | Qué ve Celestine |
|-------|-------|-------------|------------------|
| Bien | 75-100 | Todo OK | Sin indicador |
| Le vendría bien | 50-74 | Podría mejorar | Indicador sutil |
| Lo necesita | 25-49 | Debería atenderlo | Indicador visible |
| En rojo | 0-24 | Lleva mucho tiempo así | Indicador urgente |

**Regla crítica:** Estar en "En rojo" NO produce castigo automático. Produce tendencia.

### Tendencia cuando está baja

| Necesidad baja | Tendencia generada |
|----------------|-------------------|
| Social bajo | NPC sale más, busca compañía, acepta más planes sociales |
| Diversión baja | NPC busca planes de ocio, se muestra más receptivo a salir |
| Actividad baja | NPC sale a caminar, busca cosas que hacer fuera de casa |
| Calma baja | NPC rechaza planes ruidosos, busca sitios tranquilos, cancela planes |

**NO hay:**
- Daño automático.
- Maldiciones.
- Penas de muerte.
- Consecuencias negativas directas.

---

## 4. Cómo personalidad/hobbies modifican su comportamiento

### Regla aprobada

Todos los NPC tienen las 4 necesidades. Personalidad y hobbies modifican **intensidad y forma de cubrirlas**, pero no eliminan ninguna.

### Personalidad (rasgos)

Los rasgos existentes en `data/catalogos/rasgos.json` ya tienen ejes (social, humor, vinculo, ego, afecto, cognicion, ritmo). Se reutilizan directamente.

| Rasgo | Efecto sobre necesidades |
|-------|--------------------------|
| `sociable` | Social se recupera más rápido con compañía, cae más rápido sin ella |
| `timido` | Social se recupera mejor en parejas pequeñas que en grupos |
| `reservado` | Social cae más lento (necesita menos contacto) |
| `tranquilo` | Calma se recupera más rápido, cae más lento |
| `nervioso` | Calma cae más rápido, recuperación más lenta |
| `ansioso` | Calma y Social afectados: necesita ambos regularmente |
| `directo` | Diversión más fácil de cubrir (menos selectividad) |
| `bromista` | Diversión se recupera con más tipos de actividades |
| `perezoso` | Actividad cae más lento (menos necesidad de moverse) |
| `observador` | Calma se recupera en más lugares (está bien donde esté) |
| `practico` | Actividad se recupera mejor haciendo cosas útiles |
| `vanidoso` | Actividad y Diversión relacionados con imagen/apariencia |
| `leal` | Social se recupera más con personas de confianza |
| `cabezota` | Social cae más rápido si hay conflicto pendiente |
| `empatico` | Social se recupera mejor con personas que lo necesitan |

**Regla:** NO se crean coeficientes nuevos. Se reutilizan los ejes existentes con pesos simples.

### Hobbies

Los hobbies (`data/catalogos/aficiones.json`) ya tienen `lugar_ids`, `individual`, `social`, `especificidad`.

| Propiedad del hobby | Efecto |
|---------------------|--------|
| `social: true` | Cubre Social mejor que uno individual |
| `social: false` | Cubre Calma mejor que uno social |
| `individual: true` | Cubre Calma y Actividad |
| `especificidad: alta` | Cubre la necesidad más intensamente (pero menos tipos de personas lo valoran) |
| `especificidad: baja` | Cubre la necesidad menos intensamente (pero más gente lo valoran) |

**Ejemplo concreto:**
- `leer` (individual, baja especificidad) → Cubre Calma para casi cualquiera.
- `cine` (social, baja especificidad) → Cubre Diversión y algo de Social.
- `deporte` (social, baja especificidad) → Cubre Actividad y algo de Social.
- `bingo` (social, media especificidad) → Cubre Diversión y Social, pero solo para quien le gusta.

---

## 5. Cómo las cubren actividades/lugares/compañía

### Contrato data-driven de lugares

Cada lugar (`data/lugares/lugares.json`) debe poder asociarse con un **perfil de necesidades**:

```json
{
  "id": "lug_gimnasio",
  "nombre": "Gimnasio",
  "necesidades": {
    "actividad": 3,
    "social": 1,
    "diversion": 1,
    "calma": 0
  }
}
```

**Escala conceptual (NO definitiva):** 0 = no cubre, 1 = poco, 2 = moderado, 3 = mucho.

### Perfil de necesidades por lugar (9 canónicos aprobados)

**Regla:** principal/secundaria define qué necesidades PUEDE cubrir el lugar; la recuperación real se modula por actividad, compañía, personalidad, hobbies y contexto. Entrar en el edificio no otorga siempre exactamente el mismo beneficio.

| Lugar | Principal | Secundaria |
|-------|-----------|------------|
| 🏛️ Biblioteca | ☕ Calma | — |
| ☕ Cafetería | 🫂 Socializar | ☕ Calma |
| 🌳 Parque | 💪 Actividad | ☕ Calma |
| 🏋️ Gimnasio | 💪 Actividad | 🎉 Diversión |
| 🍽️ Restaurante | 🫂 Socializar | 🎉 Diversión |
| 🍺 Bar | 🫂 Socializar | 🎉 Diversión |
| 🎬 Cine | 🎉 Diversión | ☕ Calma |
| 🪩 Discoteca | 🎉 Diversión | 🫂 Socializar |
| 🎱 Bingo | 🫂 Socializar | 🎉 Diversión |

### Cómo se calcula la recuperación

```
Recuperación = base_lugar × modificador_actividad × modificador_compañía × modificador_hobby
```

**Base lugar:** Valor del perfil de necesidades del lugar (0-3).

**Modificador actividad:**
- Actividad que hace el NPC en ese lugar × hobby asociado.
- Ejemplo: ir al gimnasio y hacer deporte = modifier alto.
- Ejemplo: ir al gimnasio y solo sentarse = modifier bajo.

**Modificador compañía:**
- Solo = modifier 1.0
- Con alguien = modifier 1.2 (si la relación es buena)
- Con alguien con quien hay conflicto = modifier 0.6

**Modificador hobby:**
- Si el lugar es afín al hobby del NPC = bonus +20-40%
- Si el lugar no tiene relación con sus hobbies = sin bonus

### Actividad ≠ Edificio

**Regla crítica:** Que un NPC esté en un edificio NO significa que esté cubriendo la necesidad.

Ejemplo:
- Marta está en el bar → ¿cubre Social? Depende de si está hablando con alguien o sentada sola.
- Víctor está en el parque → ¿cubre Actividad? Depende de si pasea o está sentado en un banco.

**Cómo se resuelve:**
- El sistema de encuentros ya registra actividad por participante.
- Se puede inferir "está haciendo algo" vs "está pasando el rato" del contexto del encuentro.
- NO se necesita un nuevo sistema: se reutiliza la info del encuentro en curso.

---

## 6. Cómo las gestiona la autonomía

### Principio

Los NPC actúan por sí mismos. Celestine NO es su cuidadora.

### Qué hace la autonomía

| Acción autónoma | Necesidad que atiende |
|-----------------|----------------------|
| Salir a pasear | Actividad + Social |
| Ir a un bar/café | Social + Diversión |
| Quedar con otro NPC | Social |
| Hacer deporte | Actividad + Diversión |
| Ir a leer a la biblioteca | Calma |
| Ir al cine | Diversión + Social |
| Estar en casa | Calma |

### Autonomía + Necesidades

**Flujo:**

```
MotorVidaDiaria::tickHora()
  → NPC tiene hueco libre
  → Evalúa sus necesidades actuales
  → Elige actividad/lugar que más le convenga
  → Puede buscar compañía (IniciativaSocial)
  → Ejecuta plan autónomo
  → Necesidad se actualiza
```

**Cómo IniciativaSocial se nutre de Necesidades:**
- `IniciativaSocial::quizasDelTick()` → ya busca con quién quedar.
- Se le puede añadir: "priorizar personas que necesiten socializar".
- NO es un cambio de behavior: es un peso más en la ponderación.

**Cómo LugarAutonomo se nutre de Necesidades:**
- `LugarAutonomo::elegir()` → ya pondera hobbies, conocimiento, rechazos.
- Se le puede añadir: "priorizar lugares que cubran las necesidades actuales".
- El campo `necesidades` del lugar alimenta directamente esta evaluación.

### Regla crítica

**La autonomía NO es malintencionada.**

- NO se diseña para que "la autonomía cubra mal las necesidades para que Celestine tenga trabajo".
- Los NPC deben poder cubrir sus necesidades por sí mismos.
- Celestine interviene porque quiere **orientar**, no porque los NPC sean incapaces.
- A veces la autonomía lo hace bien. A veces no. Eso es jugabilidad.

---

## 7. Qué ve Celestine y qué no

### Qué SÍ ve Celestine

| Información | Cómo se muestra |
|-------------|-----------------|
| Necesidad actual | Banda: Bien / Le vendría bien / Lo necesita / En rojo |
| Tendencia | Flecha ↑↓→ (sube / baja / estable) |
| Última vez que se cubrió | "Hace 2 días que no socializa" |
| Lugar sugerido | "Podría ir al gimnasio" (si se tiene suficiente info) |

### Qué NO ve Celestine

| Información | Por qué NO |
|-------------|-----------|
| Número exacto (ej: Social = 42) | Convierte el juego en Excel |
| Decay rate | Información irrelevante para decisiones |
| Cómo se calcula | Negocio interno del motor |
| Necesidades de otros NPC en tiempo real | Solo ve las del NPC seleccionado |

### Forma de presentación

**NO son barras siempre visibles.**

Son **indicadores contextuales** que aparecen cuando son relevantes:

1. **En ficha de Vecino:** sección "Qué le vendría bien" con iconos de necesidad.
2. **Al planificar un plan:** si uno de los participantes tiene una necesidad baja, se muestra como "Le vendría bien" junto al lugar sugerido.
3. **En eventos narrativos:** "Marta lleva días sin salir" → pista de que necesita actividad.
4. **En catch-up:** resumen de qué necesidades acumularon.
5. **Vista global "Necesidades":** filtros Socializar / Diversión / Actividad / Calma para localizar quién necesita qué y usar esa información al organizar planes (similar conceptualmente al acceso de Relaciones).

### Peticiones desde Necesidades

Una necesidad baja puede ser **CAUSA** para generar una petición coherente:
- Diversión baja → puede pedir cine/plan.
- Social bajo → puede pedir quedar con alguien.

**NO** debe generar automáticamente una petición cada vez que baja. Solo cuando el motor lo considere oportuno (contexto, personalidad, situación).

---

## 8. Qué aparece en ficha Vecino

### Propuesta de ficha

```
Marta — 28 años

Hobbies: 🎬 Cine, 📚 Leer, 🏃 Correr

QUÉ LE VENDRÍA BIEN:
  🫂 Socializar    (lleva 3 días sin quedar con nadie)
  💪 Actividad     (últimamente no sale mucho)

NO LE GUSTA:
  🎵 Música en vivo
  🍺 Bares concurridos
```

### Reglas de la ficha

| Regla | Detalle |
|-------|---------|
| Solo mostrar necesidades en banda "Lo necesita" o "En rojo" | No saturar con info innecesaria |
| Mostrar contexto narrativo | "lleva X días sin..." en vez de "Social = 34" |
| Actualizar al cerrar día | NO en tiempo real (evitar spam) |
| NO mostrar necesidades cubiertas | Si está bien de Social, no aparece |
| Máximo 2-3 necesidades visibles | Si tiene 4 bajas, mostrar las 2 más urgentes |
| Incluir "NO le gusta" si se conoce | Para que el jugador no cometa errores |

### Cuándo se actualiza

- Al abrir la ficha → se lee el estado actual.
- NO se actualiza en vivo (evita distracción).
- Se recalcula al cerrar día / al hacer catch-up.

---

## 9. Qué información ofrece cada edificio

### Panel de info del edificio

Al tocar un edificio en el mapa:

```
GIMNASIO

Deporte, movimiento, sudar.

Cubre especialmente:
  💪 Actividad ★★★
  🎉 Diversión ★

También ayuda con:
  🫂 Social ★ (si vas con alguien)

Hobbies relacionados: Deporte, Correr
```

### Reglas

| Regla | Detalle |
|-------|---------|
| Datos vienen de `lugares.json` + `necesidades` | Data-driven, NO hardcodeado |
| Estrellas ≈ intensidad (1-3) | Visual, NO numérica |
| Mostrar hobbies relacionados | Del catálogo `aficiones.json` → `lugar_ids` |
| NO mostrar fórmulas | Solo "cubre mucho/poco/medio" |
| Actualizable al desbloquear lugares | Primeros 3 abiertos, resto con candado |

---

## 10. Contrato exacto que necesita de Relaciones

### Qué Necesidades SOLICITA a Relaciones

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `relaciones_sociales[].conocidos` | bool | ¿Se conocen? |
| `relaciones_sociales[].intensidad` | int 0-100 | Fuerza del vínculo social |
| `relaciones_sociales[].tipo` | string | 'amistad' / 'conocidos' / 'compañeros' |
| `relaciones_sociales[].ultimo_contacto` | {dia, hora} | Última interacción social |
| `relaciones_sociales[].a_hacia_b` / `b_hacia_a` | {valor, banda} | Sentimiento direccional |
| `relaciones_romanticas[].vinculo` | int | Profundidad del vínculo romántico |
| `relaciones_romanticas[].conflicto` | int | Nivel de conflicto |
| `relaciones_romanticas[].estado_actual` | string | 'pareja' / 'solteros_con_interes' / etc. |

### Qué Necesidades NO toca

- No modifica `vinculo`, `conflicto`, `atraccion`.
- No crea relaciones.
- No destruye relaciones.
- No gestiona amistad profunda.
- No gestiona romance.
- Solo **consume** la información de estado.

### Ejemplo de consumo

```php
// Necesidades consulta para valorar "¿Marta necesita socializar?"
$relaciones = RelacionEngine::obtenerTodas($partida, 'marta');
$contactosRecientes = array_filter($relaciones, function($r) use ($ahora) {
    $uc = $r['ultimo_contacto'] ?? null;
    return $uc !== null && ($ahora - self::horaEnJuego($uc)) < 72; // 3 días
});
$tieneContactoReciente = count($contactosRecientes) > 0;
// → Si no tiene contacto reciente, Social baja más rápido
```

---

## 11. Qué reutilizamos del código actual

### Sistemas que se REUTILIZAN directamente

| Sistema | Archivo | Reutilización |
|---------|---------|---------------|
| **EstadoEmocional** | `src/Engine/EstadoEmocional.php` | Se mantiene. Necesidades NO lo reemplaza. Necesidad ≠ emoción. |
| **EmotionalRecovery** | `src/Engine/EmotionalRecovery.php` | Se reutiliza para recuperación emocional post-encuentro. |
| **AgendaEngine** | `src/Engine/AgendaEngine.php` | Programa actividades. Necesidades consume slots resultantes. |
| **AgendaConjunta** | `src/Engine/AgendaConjunta.php` | Busca franjas libres. Se usa para programar actividades que cubran necesidades. |
| **IniciativaSocial** | `src/Engine/IniciativaSocial.php` | NPC→NPC deliberada. Se le puede ponderar con necesidades. |
| **LugarAutonomo** | `src/Engine/LugarAutonomo.php` | Elige lugar autónomo. Se le añade perfil de necesidades como peso. |
| **PlanAfinidad** | `src/Engine/PlanAfinidad.php` | Ya evalúa hobby ↔ lugar. Se extiende con necesidades. |
| **RelacionDesgaste** | `src/Engine/RelacionDesgaste.php` | Se mantiene. Las necesidades lo consumen (si falta contacto → Social cae). |
| **RelacionEngine** | `src/Engine/RelacionEngine.php` | Autoridad sobre relaciones. Necesidades solo lee. |
| **PeticionEngine** | `src/Engine/PeticionEngine.php` | Genera peticiones desde necesidades bajas (opcional). |
| **MotorVidaDiaria** | `src/Engine/MotorVidaDiaria.php` | Tick horario. Necesidades se actualiza aquí. |
| **CatchUpEngine** | `src/Engine/CatchUpEngine.php` | Maneja tiempo offline. Necesidades se recalcula al volver. |
| **ConocimientoNpc** | `src/Engine/ConocimientoNpc.php` | Qué sabe un NPC de otro. Se usa para elegir compañía. |
| **EncuentroExperiencia** | `src/Engine/EncuentroExperiencia.php` | Evaluación de experiencia. Necesidades puede modular resultado. |
| **HobbyAccionable** | `src/Engine/HobbyAccionable.php` | Hobby ↔ lugares jugables. Se reutiliza para mapear hobby→necesidad. |
| **EstiloSocial** | `src/Engine/EstiloSocial.php` | Ejes de estilo social. Se reutiliza para modular necesidades. |

### Sistemas que se MODIFICAN (levemente)

| Sistema | Cambio mínimo |
|---------|---------------|
| `LugarAutonomo` | Añadir peso de "necesidad actual del NPC" al ponderar lugares |
| `IniciativaSocial` | Añadir peso de "priorizar NPC que necesiten socializar" |
| `MotorVidaDiaria` | Añadir tick de actualización de necesidades por hora |
| `CatchUpEngine` | Recalcular necesidades acumuladas en tiempo offline |
| `PeticionEngine` | Opción de generar petición cuando necesidad está "En rojo" X días |

### Sistemas que NO se tocan

| Sistema | Por qué |
|---------|---------|
| `RelacionEngine` | Autoridad sobre relaciones. Necesidades solo lee. |
| `RelacionFase` | Autoridad sobre fases relacionales. |
| `RelacionBandas` | Autoridad sobre bandas de relación. |
| `CompatibilidadCalculator` | Autoridad sobre compatibilidad. |
| `RegaloEngine` | Herramienta independiente. |
| `DiarioEngine` | Narrativa, no necesita. |
| `CotilleoEngine` | Narrativa, no necesita. |
| `BuzonEngine` | Notificaciones, no necesita cambio. |

---

## 12. Piezas futuras de implementación, pequeñas y ordenadas

### Pieza 1: Definición de datos

**Tamaño:** Pequeña (1-2 días)
**Archivos:**
- Añadir campo `necesidades` a `data/lugares/lugares.json`
- Crear `data/catalogos/necesidades.json` con las 4 necesidades y sus bandas

**Dependencias:** Ninguna.
**Validación:** Los catálogos cargan sin errores.

### Pieza 2: Estado de necesidades por residente

**Tamaño:** Pequeña (2-3 días)
**Archivos:**
- Crear `src/Engine/NecesidadEstado.php` (estado por residente, decay, recuperación)
- Añadir `runtime.necesidades` al schema de residente
- Asegurar `EstadoEmocional::ensureResidente()` mantenga coherencia

**Dependencias:** Pieza 1.
**Validación:** Un residente nuevo tiene necesidades en banda "Bien".

### Pieza 3: Actualización por tick horario

**Tamaño:** Pequeña (1-2 días)
**Archivos:**
- Modificar `MotorVidaDiaria::tickHora()` para actualizar necesidades
- Aplicar decay + recuperación según lugar actual del NPC

**Dependencias:** Pieza 2.
**Validación:** Avanzar 24h produce decay visible en necesidades.

### Pieza 4: Visualización en ficha Vecino

**Tamaño:** Pequeña (1-2 días)
**Archivos:**
- Añadir sección "Qué le vendría bien" a la ficha de vecino
- Solo mostrar necesidades en banda "Lo necesita" o "En rojo"

**Dependencias:** Pieza 2.
**Validación:** Al abrir ficha de un NPC con necesidad baja, se muestra el indicador.

### Pieza 5: Perfil de necesidades en lugares

**Tamaño:** Pequeña (1 día)
**Archivos:**
- Añadir campo `necesidades` a `data/lugares/lugares.json`
- Crear panel informativo del edificio que muestre qué cubre

**Dependencias:** Pieza 1.
**Validación:** Al tocar un edificio se muestra qué necesidades cubre.

### Pieza 6: Integración con autonomía

**Tamaño:** Media (2-3 días)
**Archivos:**
- Modificar `LugarAutonomo::elegir()` para ponderar necesidades actuales
- Modificar `IniciativaSocial` para ponderar necesidades de socialización
- Añadir peso de necesidad a `PlanAfinidad`

**Dependencias:** Pieza 2, Pieza 5.
**Validación:** Un NPC con Social bajo tiende a elegir lugares sociales.

### Pieza 7: Catch-up y tiempo offline

**Tamaño:** Pequeña (1-2 días)
**Archivos:**
- Modificar `CatchUpEngine` para recalcular necesidades
- Mostrar resumen de necesidades en catch-up

**Dependencias:** Pieza 2.
**Validación:** Volver después de 3 días produce necesidades acumuladas.

### Pieza 8: Peticiones desde necesidades

**Tamaño:** Pequeña (1-2 días)
**Archivos:**
- Añadir opción a `PeticionEngine` para generar petición desde necesidad baja
- Integrar con `MensajitoPeticionEngine` para copy narrativo

**Dependencias:** Pieza 2.
**Validación:** Un NPC con necesidad "En rojo" genera petición en buzón.

### Orden de implementación recomendado

```
Pieza 1 (datos) → Pieza 2 (estado) → Pieza 3 (tick)
                                       ↓
                              Pieza 4 (ficha)
                              Pieza 5 (edificios)
                                       ↓
                              Pieza 6 (autonomía)
                              Pieza 7 (catch-up)
                                       ↓
                              Pieza 8 (peticiones)
```

**Cada pieza se playtestea antes de siguiente.**

---

## A. Decisiones pendientes para Neni

Solo 5 decisiones reales que necesitan input:

### Decisión 1: ¿Social o Contacto?

**Opción A:** `social` (como está diseñado) → "Necesito hablar con alguien, estar en compañía."
**Opción B:** `contacto` → "Necesito interacción humana, sea hablando, sea en silencio junto a alguien."

**Pregunta:** ¿Social cubre "estar sentado en un café con alguien sin hablar" o eso es Calma?

### Decisión 2: ¿Separar Diversión y Ocio?

**Opción A:** `diversion` cubre todo ocio (cine, bar, bingo, discoteca, videojuegos).
**Opción B:** Separar en `diversion` (experiencias activas) y `ocio` (pasar el rato).

**Pregunta:** ¿El bar es Diversión o es Social? ¿El cine es Diversión o es Ocio?

### Decisión 3: ¿Las 4 necesidades son para todos o varían por personalidad?

**Opción A:** Todos tienen las 4 igual (como Los Sims clásico).
**Opción B:** Algunas personas no tienen alguna necesidad (ej: un NPC muy tranquilo no necesita Diversión).

**Pregunta:** ¿Hacemos que personalidad modifique intensidad o eliminamos alguna?

### Decisión 4: ¿Qué unción tiene PeticionEngine con Necesidades?

**Opción A:** PeticionEngine se alimenta de Necesidades (NPC pide lo que necesita).
**Opción B:** PeticionEngine sigue independiente (NPC pide cosas随机, sin relación con necesidades).

**Pregunta:** ¿Una necesidad baja debería poder generar automáticamente una petición en el buzón?

### Decisión 5: ¿Dónde se ve la info de Necesidades?

**Opción A:** Solo en ficha Vecino (sección "Qué le vendría bien").
**Opción B:** También al planificar (si el NPC que incluyes tiene necesidad baja, se muestra).
**Opción C:** También en eventos narrativos ("Marta lleva días sin salir").

**Pregunta:** ¿Cuánta visibilidad queremos dar al sistema al principio?

---

## B. Resolución de casos obligatorios

### CASO 1: Marta lleva tiempo sin diversión, pero tiene buena vida social

- **Necesidad relevante:** Diversión.
- **Señales:** Social alto, Diversión bajo.
- **Evolución:** Diversión sigue bajando si no se atiende.
- **Posible intervención:** Mandarla al cine, arcade, o a un plan de ocio con alguien.
- **Autonomía:** Ella mesma puede decidir salir a divertirse si la autonomía lo detecta.
- **Resultado razonable:** Marta sigue siendo sociable pero aburrida. Necesita que la invite alguien a algo divertido.

### CASO 2: José sale mucho, pero no tiene ningún vínculo profundo

- **Necesidad relevante:** Social (está bien), pero Relaciones no tiene vínculos.
- **Señales:** Social alto, pero `relaciones_sociales` sin tipo 'amistad'.
- **Evolución:** Social se mantiene alto (sale mucho), pero falta profundidad.
- **Posible intervención:** El jugador debe crear encuentros repetidos con la misma persona.
- **Autonomía:** José puede ir al bar con cualquiera, pero no forja amistad así.
- **Resultado razonable:** Social cubierto, pero el jugador detecta que falta algo más profundo. Eso es trabajo de Relaciones, no de Necesidades.

### CASO 3: Laura es muy casera y lleva un día sola

- **Necesidad relevante:** Calma (probablemente alta), Social (depende).
- **Señales:** Si es `tranquila`/`reservado`, Calma no baja rápido.
- **Evolución:** Social puede bajar un poco, pero para un reservado es normal.
- **Posible intervención:** No hacer nada. Laura está bien.
- **Autonomía:** Laura se queda en casa leyendo. Calma cubierta.
- **Resultado razonable:** Para Laura, un día sola no es problema. Si fuera 5 días, quizá Social bajaría.

### CASO 4: Víctor necesita movimiento y odia/ama ciertos hobbies

- **Necesidad relevante:** Actividad.
- **Señales:** Actividad bajo. Hobbies como `deporte` o `pasear` le vienen bien.
- **Evolución:** Si le mandas al gimnasio (que no le gusta), Actividad baja igual.
- **Posible intervención:** Elegir lugar que cubra Actividad Y que sea compatible con sus hobbies.
- **Autonomía:** Víctor puede decidir ir a caminar al parque (actividad + hobby).
- **Resultado razonable:** La elección informada del jugador importa. Mandarlo al gimnasio sin saber que odia el deporte es un error.

### CASO 5: Marta tiene necesidad de socialización pero está enfadada con la persona con quien Celestine la manda

- **Necesidad relevante:** Social.
- **Señales:** Social bajo, pero `conflicto` alto con la persona objetivo.
- **Evolución:** Si se juntan en esas condiciones, el encuentro puede ir mal.
- **Posible intervención:** Buscar otra compañía o esperar a que el conflicto se resuelva.
- **Autonomía:** La autonomía NO los juntaría (evita conflictos conocidos).
- **Resultado razonable:** El jugador debe ser consciente de que enviar a alguien con quien tiene un conflicto es arriesgado. Necesidades no override de Relaciones.

### CASO 6: NPC satisface su necesidad autónomamente antes de que Celestine actúe

- **Necesidad relevante:** Cualquiera.
- **Señales:** NPC decide salir por su cuenta.
- **Evolución:** La necesidad sube por acción autónoma.
- **Posible intervención:** Celestine no necesita hacer nada.
- **Autonomía:** Funciona como debe.
- **Resultado razonable:** El jugador ve que el NPC ya se la arregló. Eso es bueno. Celestine puede intervenir para optimizar, no para suplir.

### CASO 7: Celestine organiza una actividad adecuada al tipo de necesidad pero mala para los hobbies

- **Necesidad relevante:** Cualquiera.
- **Señales:** Lugar cubre la necesidad, pero no es afín a los hobbies del NPC.
- **Evolución:** La necesidad se cubre parcialmente (por el lugar), pero el NPC no lo disfruta tanto.
- **Posible intervención:** El jugador aprende que elegir un lugar adecuado a hobbies es mejor.
- **Autonomía:** Un NPC autónomo elegiría un lugar afín a sus hobbies.
- **Resultado razonable:** Es una decisión del jugador con consecuencias legibles. No es un error grave, pero sí subóptimo.

### CASO 8: Persona sin pareja durante bastante tiempo pero satisfecha con su vida

- **Necesidad relevante:** Social (puede estar bien), pero Relaciones no tiene romance.
- **Señales:** Social alto, pero `relaciones_romanticas` vacía o sin pareja.
- **Evolución:** Para esta persona, no tener pareja no es un problema.
- **Posible intervención:** Si el jugador quiere, puede intentar crear oportunidades románticas.
- **Autonomía:** La persona sigue viviendo bien.
- **Resultado razonable:** No crear la sensación de que "falta algo". Simplemente, esa persona está bien así.

### CASO 9: Persona con pareja pero con carencia de amistad profunda

- **Necesidad relevante:** Social (puede estar alto), pero Relaciones no tiene amistades.
- **Señales:** Social alto (sale con pareja), pero `tipo: 'amistad'` no aparece.
- **Evolución:** Social se cubre, pero la persona puede sentir que solo tiene a su pareja.
- **Posible intervenir:** Crear oportunidades para que forje amistades.
- **Autonomía:** Puede quedar con la pareja, pero no busca amigos por sí misma.
- **Resultado razonable:** El jugador detecta "tiene pareja pero no amigos". Eso es trabajo de Relaciones + NecesidadesWorking together.

### CASO 10: Catch-up de varios días

- **Necesidad relevante:** Todas.
- **Señales:** Varios días sin jugar.
- **Evolución:** Las necesidades acumulan decay.
- **Posible intervención:** Catch-up muestra resumen de qué necesita cada NPC.
- **Autonomía:** Los NPC han vivido su vida (han cubierto algunas necesidades, otras no).
- **Resultado razonable:** El jugador vuelve y ve: "Marta necesita diversión, José necesita calma, Laura está bien". Puede decidir a quién atender primero.

---

## C. Contrato con Regalitos

**Regalito es una herramienta. NO crea necesidad.**

| Relación | Cómo interactúa |
|----------|-----------------|
| Regalo + Social | Un regalo puede mejorar un momento social, pero no cubre Social. |
| Regalo + Diversión | Un regalo puede sorprender/divertir, pero no sustituye una actividad. |
| Regalo + Calma | Un regalo puede tranquilizar, pero no es un sustituto de descanso. |
| Regalo + Emoción | Un regalo puede cambiar estado emocional (via `EmotionalRecovery::evaluarRegalo()`). |
| Regalo + Relación | Un regalo puede mejorar `intensidad` de relación (si es bien recibido). |

**Regla:** `RegaloEngine` ya existe y funciona. Necesidades NO lo modifica. Solo coexisten.

---

## D. Nueva partida

### Estado inicial

| Condición | Valor inicial |
|-----------|---------------|
| Todas las necesidades | Banda "Bien" (75-100) |
| Razón | Los primeros días deben permitir aprender el sistema, no apagar incendios |
| Decay inicial | Más lento los primeros 3 días (tutorial implícito) |

### Por qué no empiezan en rojo

- El jugador no sabe qué es el sistema aún.
- Si todos aparecen en rojo, la primera sensación es de urgencia injustificada.
- Se quiere que el jugador descubra el sistema naturalmente.
- La primera necesidad que baje será una oportunidad de enseñanza.

---

## E. Conexión con Intenciones de Plan

El sistema de Intenciones (decidido por el agente paralelo de Relaciones) se alimenta de Necesidades:

| Necesidad detectada | Intención sugerida |
|---------------------|-------------------|
| Social bajo | 🫂 Ayudar a socializar |
| Diversión baja | 🎉 Pasarlo bien |
| Actividad baja | 💪 Salir / moverse |
| Calma baja | ☕ Dar un respiro |
| Vínculo débil (de Relaciones) | 💛 Fomentar amistad |
| Conflicto (de Relaciones) | 🩹 Intentar reconciliar |

**NO crear otra lista de intenciones.** Necesidades alimenta la lista existente.

---

## F. Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Se convierte en barras de mantenimiento | Bandas en vez de números. Info contextual, no siempre visible. |
| Se duplica con Emociones | Necesidad ≠ emoción. Ejemplos claros en documentación. |
| Se duplica con Relaciones | Contrato explícito: Necesidades solo LEE de Relaciones. |
| NPC dependen de Celestine | Autonomía fuerte. Celestine orienta, no suple. |
| Demasiada información | Máximo 2-3 indicadores visibles por NPC. Info contextual. |
| Decay demasiado agresivo | Calibración en PlayTest. Empezar conservador. |
| El jugador ignora el sistema | Peticiones desde necesidades + eventos narrativos como pista. |

---

## G. Qué NO hacer (resumen ejecutivo)

- ❌ Tamagotchi
- ❌ Barras punitivas
- ❌ Duplicar emociones
- ❌ Duplicar amistad
- ❌ Duplicar romance
- ❌ Necesidad de regalo
- ❌ Hardcodear edificios
- ❌ 8-10 necesidades
- ❌ Números arbitrarios en UI
- ❌ Hacer NPC dependientes de Celestine
- ❌ Diseñar intenciones relacionales
- ❌ Implementar antes de PlayTest

---

## H. Qué reutilizar / modificar / NO crear

| Acción | Sistemas |
|--------|----------|
| **REUTILIZAR** | EstadoEmocional, EmotionalRecovery, AgendaEngine, AgendaConjunta, IniciativaSocial, LugarAutonomo, PlanAfinidad, RelacionDesgaste, ConocimientoNpc, EncuentroExperiencia, HobbyAccionable, EstiloSocial, MotorVidaDiaria, CatchUpEngine, PeticionEngine |
| **MODIFICAR (leve)** | LugarAutonomo (añadir peso necesidad), IniciativaSocial (ponderar necesidades), MotorVidaDiaria (tick necesidades), CatchUpEngine (recalcular), PeticionEngine (generar desde necesidad) |
| **NO CREAR** | Nuevo motor de relaciones, nuevo sistema de amistad, barras de UI, fórmulas complejas, sistema paralelo de vínculos |

---

**FIN DEL DISEÑO CANÓNICO DE NECESIDADES PERSONALES**

**Estado:** Pendiente revisión Neni + ChatGPT.
**Siguiente paso:** Resolver las 5 decisiones pendientes (A.1-A.5) y proceder a Pieza 1.
