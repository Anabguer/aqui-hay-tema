# Dirección HUD / UX — maqueta ChatGPT (APROBADA)

**Estado:** dirección visual aprobada por Neni. **NO implementada en PLAY todavía.**
**Referencia principal:** `ref/maqueta_chatgpt_hud.png` (= recorte UI de REFERENCIA_VISUAL_01).
**También:** `ref/REFERENCIA_VISUAL_01.png` (lámina completa + grid de colocación).

## Qué se aprueba

### Lenguaje visual (sí)
- Papelitos / tarjetas / pegatinas
- Contorno ligeramente imperfecto, sensación dibujada a mano
- Pequeñas pestañas y etiquetas
- Rosa, amarillo, verde, blanco roto
- Sombras muy discretas
- Aspecto cómic / tablero ilustrado

### Lenguaje visual (no)
- Panel administrativo
- UI web genérica
- Grandes cajas beige
- 14 paneles permanentes sobre el mapa

### Principio UX
```
PUEBLO LIMPIO → veo señal → pulso → tarjeta/papel → consulto/actúo → cierro → pueblo
```
El mapa es el protagonista. La info secundaria aparece bajo demanda.

---

## Piezas de la maqueta (qué conservar)

### 1. Cabecera — pegatinas del tablero
- Título AQUÍ HAY TEMA + ♥
- Día / estación / día semana + hora (cartelito)
- Dinero (tarjeta)
- Fama (tarjeta) — **solo si el motor la expone en la vista PLAY**; no inventar cifra

No barra de aplicación convencional.

### 2. RESUMEN — lista + acceso (muy importante)
Filas tipo:
- Parejas
- Citas hoy
- Problemas
- Rumores
- Solitarios

Cada fila es **toggle**: abre/cierra su panel o tira. No es solo informativo.

**Regla de datos:** no inventar categorías ni contadores que el motor no exponga.
Primero reutilizar información real (parejas activas, encuentros del día, etc.).
Si una fila de la maqueta no tiene fuente real → se omite o se deja fuera hasta que C1/motor la dé.

### 3. Panel Parejas (ejemplo de capa)
Al pulsar Parejas: tira horizontal de tarjetas
`[ cara ♥ cara | nombres | estado | color ]`
Seleccionable. Cerrable. **No** ocupa espacio permanente del mapa.

Estados visuales de la maqueta (Bien / Genial / Regular / En crisis / Mal) son **referencia de tono**.
En implementación: mapear a estados **reales** del motor (`pareja` / `crisis` / fases de relación, etc.). No crear mecánica nueva para imitar etiquetas.

### 4. Ficha / cita seleccionada
Composición de referencia (UX):
- pareja
- relación (barra u otro indicador real)
- próxima cita: lugar / hora / actividad / …

Campos definitivos = lo que el motor ya sepa pintar. La maqueta no dicta schema.

### 5. Coletilla del día
Post-it rosa. Personalidad del juego, no panel técnico.
Usar coletillas reales del juego si ya existen en config/motor.

---

## Secuencia de trabajo (cerrada)

1. **AHORA — composición del pueblo**  
   `assets/play-v3/prueba-composicion-pueblo/`  
   PARA revisión (inicial + evolucionado + medidas). C3 parado.

2. **Cuando el pueblo esté aprobado** — una sola maqueta PLAY completa (no código aún):  
   mapa nuevo + edificios + personajes + corazón/vida + día/hora + dinero/(fama si toca)  
   + Resumen plegable + Parejas desplegadas (ejemplo) + ficha/cita seleccionada (ejemplo).  
   Pantalla completa para Neni + ChatGPT.

3. **Después** — implementar en PLAY por capas, sin abrir todo a la vez.

---

## Notas motor (para no mentir en la maqueta)

| Pieza maqueta | ¿Hay algo real hoy? | Nota |
|---|---|---|
| Corazón / vida | Sí (PLAY V3) | Conservar |
| Día / hora | Sí | Ticket/pegatina |
| Dinero | Sí (`economia.dinero` / celeste) | Tarjeta |
| Fama | Ledger existe; UI V1 a confirmar | No inventar; mostrar solo si vista la trae |
| Parejas (lista) | Motor de parejas / crisis | Contar activas reales |
| Citas hoy | `encuentros_hoy` / ResumenDia | Mapear a “citas” con cuidado de copy |
| Problemas / rumores / solitarios | No asumir | Solo si hay fuente; si no, fuera del Resumen V1 |
| Coletilla | Docs/config coletillas | Post-it |
| Estados Bien/Genial/… | Maqueta | Mapear a etiquetas reales del motor |

## Qué NO hacer ahora

- No rediseñar PLAY a ciegas
- No implementar Resumen plegable en producción todavía
- No pedir PNG HUD definitivos a C3 todavía
- No fabricar el resto de edificios (C3 PARADO hasta composición pueblo OK)