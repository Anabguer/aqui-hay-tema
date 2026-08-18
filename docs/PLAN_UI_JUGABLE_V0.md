# PLAN UI JUGABLE V0

Objetivo: convertir la UI provisional en un primer prototipo que Neni pueda abrir, entender y recorrer sin depender de `dev.php`.

Fecha: 2026-08-18

## Principios

- Reutilizar APIs y lógica ya existentes.
- No decidir estilo gráfico final.
- No inventar narrativa definitiva.
- Preparar integración desacoplada para `personaje -> expression_id -> asset/fallback`.
- Priorizar claridad jugable sobre completitud.

## Estado tras este turno

Ya implementado en `play.php` / `assets/js/play.js`:

- resumen superior del día
- roster de residentes seleccionable
- bloque A clicable
- mapa con presencia NPC por lugar
- ficha visible ampliada
- encuentros activos / historial
- formulario de propuesta de encuentro
- paneles separados de buzón y diario
- soporte estructural para retrato / expresión usando `presentacion_visual.asset.url_relativa`
- fallback visual a placeholder si no hay pack o asset usable

## Pantalla principal

V0:

- header con reloj, acciones básicas y estado de partida
- bloque de resumen con métricas cortas
- layout en 3 columnas: residentes / pueblo / ficha y bandejas

V1:

- navegación por pestañas ligeras en móvil
- CTA principal contextual (`Proponer encuentro`, `Ver ficha`, `Avanzar tiempo`)

## Bloque / viviendas

V0:

- grid del Bloque A
- celdas libres / ocupadas
- click en vivienda ocupada selecciona residente

Pendiente:

- hover / focus con resumen rápido
- indicador de placeholder vs catálogo

## Residentes

V0:

- lista lateral con nombre, id, vivienda y ocupación
- sincronizada con la selección del bloque

Pendiente:

- filtros básicos (`todos`, `con relación`, `sin descubrir apenas`)
- orden por nombre / actividad / vivienda

## Selección de residente

V0:

- selección desde roster
- selección desde vivienda
- persistencia temporal en memoria de la página

Pendiente:

- recordar último residente entre recargas

## Ficha visible

V0:

- nombre, vivienda, edad, trabajo
- hobbies visibles
- estado emocional interno
- expresión visual resuelta
- flag de fallback
- agenda resumida del día
- último encuentro

Pendiente:

- desglose más legible de discovery por campos
- indicadores más amables de relación

## Reloj / día

V0:

- texto principal del reloj
- avance rápido `+1h`
- resumen del día

Pendiente:

- salto de día / hora en modo juego
- bloque de “qué cambió al avanzar”

## Mapa / lugares

V0:

- lista de lugares con estado (`abierto`, `candado`, `cerrado`)
- nombres visibles de residentes presentes

Pendiente:

- visualización más espacial
- enlace desde lugar a residentes presentes

## Propuesta de encuentro

V0:

- selección A/B, tipo, hora, lugar fijo actual
- feedback visible de éxito/error

Pendiente:

- sugerencias de slots compatibles
- selector de lugar operativo
- rechazo más legible por causa (`ocupado`, `límite`, `lugar cerrado`)

## Encuentros programados / en curso / finalizados

V0:

- columnas de abiertos y cerrados
- participantes, hora, estado, resumen si existe

Pendiente:

- CTA contextual desde cada encuentro
- filtro por residente

## Relaciones

V0:

- panel ligado al residente seleccionado
- resumen por otro residente (`social`, `romance`)

Pendiente:

- etiquetas más legibles de intensidad / vínculo
- feed de últimos cambios

## Buzón

V0:

- listado corto de mensajes recientes
- remitente, tipo, estado y texto

Pendiente:

- abrir / marcar leído desde UI jugable
- badges por estado

## Diario

V0:

- entradas del día actual
- tipo y texto

Pendiente:

- navegación por día
- agrupación por origen

## Feedback de errores / rechazos

V0:

- `enc-feedback` visible tras programar o avanzar tiempo
- usa `mensaje_ui` si backend lo ofrece

Pendiente:

- componente reutilizable de error / warning / éxito
- errores inline en formulario

## Presencia de NPC

V0:

- mapa lista residentes por lugar
- selección indirecta desde bloque / roster

Pendiente:

- resaltar si el residente seleccionado está presente en un lugar
- timeline simple de coincidencias técnicas

## Estados emocionales

V0:

- se muestran en ficha como estado interno
- separados de la expresión gráfica

Pendiente:

- texto UI más humano para estados placeholder
- historial breve de cambios emocionales si sigue siendo útil sin diseño definitivo

## Soporte futuro para retrato + expresión

Contrato de integración ya asumido:

- la UI no conoce nombres de archivo concretos
- consume `presentacion_visual`
- si `asset.url_relativa` existe y es usable, lo pinta
- si no existe, muestra placeholder claro
- la UI solo presenta `expression_id`, `fallback`, `motivo` y asset resultante

Esto permite conectar packs aprobados sin rehacer `play.php`.

## Qué puedo ejecutar yo solo

- mejorar legibilidad responsive de `play.php`
- añadir selector de slots compatibles reales antes de programar
- añadir resaltado del residente seleccionado en mapa / encuentros
- mostrar bandejas de buzón y diario con acciones básicas
- mejorar estados vacíos y feedback
- añadir tests backend mínimos si aparece nueva API necesaria

## Qué necesita Neni + ChatGPT

- jerarquía visual definitiva
- copy jugable definitivo
- qué métricas del residente deben verse o no
- diseño final de tarjetas / retratos / layout artístico
- qué errores deben sonar “duros” vs “amables”
- qué acciones deben tener prioridad en la pantalla principal

## Secuencia recomendada

1. consolidar esta UI jugable base
2. añadir slots sugeridos y encuentro guiado
3. mejorar ficha y bandejas
4. conectar retrato/expresión cuando lleguen packs aprobados
5. pulir responsive y primera experiencia de apertura
