# patch script
import re
from pathlib import Path
path = Path(r"W:/juegos/aqui-hay-tema/docs/PLAN_ALINEACION_V3.md")
text = path.read_text(encoding="utf-8", errors="replace")
print(len(text))
