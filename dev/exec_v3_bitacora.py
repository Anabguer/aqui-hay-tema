#!/usr/bin/env python3
from pathlib import Path

p = Path(__file__).resolve().parent.parent / "docs" / "PLAN_ALINEACION_V3.md"
t = p.read_text(encoding="utf-8", errors="replace")
t = t.replace("**Estado:** **listo para ejecución completa**", "**Estado:** **ejecución completada 2026-08-21**")
t = t.replace("- [ ] **pendiente**", "- [x] **realizada**")
log = """
### Ejecución 2026-08-21 (agente Cursor)
- [x] Fases 0-9 implementadas en rama playtest-01-php74
- **Archivos:** CapacidadViviendas, SchemaMigrator v3, LugaresCanonicos, play.php, play-v3.js, juego_v1.json, motores misiones/llegadas/generador, tests v3
- **Tests:** smoke, viviendas_pool_v3, llegadas_curva_v3, juego_v1, vista_pueblo_v3, viviendas OK
- **Pendiente Neni:** placement misiones, validación visual
"""
if "Ejecución 2026-08-21 (agente Cursor)" not in t:
    t = t.replace("### Log\n", "### Log\n" + log + "\n")
p.write_text(t, encoding="utf-8")
print("ok")
