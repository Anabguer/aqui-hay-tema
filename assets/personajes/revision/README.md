# Revisión humana de retratos

Nombres **legibles sin abrir el archivo**. IDs técnicos (`per_i02`) van en `.meta.json`.

## Convención

```text
<SLOT>_<Nombre>_<estado>[_NN].png
```

| Patrón | Significado |
| --- | --- |
| `I02_Dani_revision_01.png` | Borrador en revisión (no aprobado) |
| `I02_Dani_aprobado.png` | Aprobado por Neni (futuro) |
| `I02_Dani_identidad_valida_estilo_incorrecto.png` | Energía/identidad OK, estilo no 06b |
| `I08_Rocio_aprobado.png` | Cuando exista Rocío definitiva |

## Estructura por personaje

```text
revision/I02_Dani/
  I02_Dani_revision_NN.png
  I02_Dani_revision_NN.meta.json
  comparativas/
  evidencia/
```

Enlace técnico: `assets/personajes/_revision/per_i02/` (symlink lógico; borrador de trabajo puede apuntar aquí).

No acumular `borrador.png` genéricos sin slot ni nombre.
