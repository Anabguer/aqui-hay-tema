# Arquitectura catálogos creativos

**Fuente de verdad:** `data/catalogos/*.json`  
**No** listas de hobbies/rasgos/voces en PHP.

| Tipo | Archivo | Cerrado / extensible |
|------|---------|----------------------|
| Hobbies | `aficiones.json` | Extensible. Campos extra opcionales. |
| Rasgos | `rasgos.json` | Extensible. Alias `observadora`/`practica`/`socarrona`. |
| Estilo | `estilos_sociales.json` | Etiqueta UI + `ejes`. |
| Voces (registro) | `voces.json` | Extensible, no techo. |
| Ocupación / franja | `profesiones.json`, `franjas_disponibilidad.json` | Cerrado técnico (agenda). |
| Enums motor | `enums_tecnicos.json` | Cerrado (género, ejes, severidad). |

Añadir `"hacer barcos dentro de botellas"` = nuevo item JSON, sin tocar el motor.

## Estilo social

Ficha puede llevar solo etiqueta (`sociable_selectiva`). Los ejes se derivan del catálogo. Si un día hay `estilo_social_ejes` en ficha, tienen prioridad.

Rocío: **no se reescribió**. Etiqueta intacta; ejes = alta / selectiva / equilibrado vía catálogo.

## Voz (propuesta sencilla)

String legado `narrativa.voz: "seca"` = solo **registro**.

Perfil opcional (sin rellenar ahora en Rocío):

```json
{
  "registro": "seca",
  "verbosidad": "baja",
  "humor": null,
  "frontalidad": "directa",
  "calidez": "baja",
  "muletilla": "Bueno, veremos."
}
```

Evita 8 plantillas para 100 personas: el registro es catálogo; verbosidad/calidez/muletilla diferencian. **BLOQUEADO_DECISION:** cuántos registros y si muletilla es texto libre o id de coletilla.

## Rocío

Identidad creativa intacta. `debug_v0` vuelve a cargar `per_i03`.
