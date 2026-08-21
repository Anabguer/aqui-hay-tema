#!/usr/bin/env python3
"""Restaura play.php desde backup sin BOM y corrige mojibake UTF-8."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
src = ROOT / "dev" / "_play_nobom.php"
dst = ROOT / "play.php"

text = src.read_text(encoding="utf-8")

replacements = [
    ("Ã­", "í"),
    ("Ã³", "ó"),
    ("Ã¡", "á"),
    ("Ã©", "é"),
    ("Ãº", "ú"),
    ("Ã±", "ñ"),
    ("Ã\u008d", "Í"),  # AQUÃ\u008d style
    ("Â·", "·"),
    ("Ã—", "×"),
    ("Â¿", "¿"),
    ("Ã¢â‚¬â€œ", "—"),
    ("Ã¢â‚¬â€", "—"),
    ("Ã¢â‚¬Â¦", "…"),
    ("Ã¢ËœÂ\u0090", "☐"),
    ("Ã¢Ëœâ€˜", "☑"),
    ("Ã°Å¸Â§Âª", "🧪"),
    ("AQUÃ\u008d", "AQUÍ"),
    ("pestaÃ±a", "pestaña"),
    ("estÃ¡", "está"),
    ("sÃ©", "sé"),
    ("QuiÃ©n", "Quién"),
    ("aquÃ­", "aquí"),
    ("CafeterÃ­a", "Cafetería"),
    ("RincÃ³n", "Rincón"),
    ("buzÃ³n", "buzón"),
    ("BUZÃ\u0083N", "BUZÓN"),
    ("tÃ©cnico", "técnico"),
    ("aÃºn", "aún"),
    ("CÃ³mo", "Cómo"),
    ("AtrÃ¡s", "Atrás"),
    ("PrÃ³ximo", "Próximo"),
    ("dÃ­a", "día"),
    ("prÃ³ximo", "próximo"),
]

for old, new in replacements:
    text = text.replace(old, new)

# UTF-8 sin BOM
dst.write_bytes(text.encode("utf-8"))

checks = [
    "Aquí Hay Tema",
    "Próximo plan",
    "🧪 Playtest",
    "Quién vive aquí",
    "shell-grupo",
]
for c in checks:
    print(f"{c!r}: {c in text}")

print(f"Written {dst} ({dst.stat().st_size} bytes)")
