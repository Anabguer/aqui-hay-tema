"""
Rango seguro de colores claros para filtro de producción AHT.

Filtro producción (simulado):
  - (R>=210 AND G>=210 AND B>=210)  -> transparente
  - OR luma>=230 AND min(R,G,B)>=195 -> transparente
  - OR luma>=225 AND saturación baja (max-min<=25) -> transparente

Límites SEGUROS para colores "claros / luminosos" (margen de seguridad):
  A) Nunca los 3 canales >= 205 a la vez.
  B) Si saturación baja (max-min<=30): luma <= 215.
  C) Si saturación media/alta: se permite un canal hasta 245,
     pero al menos un canal debe quedar <= 195 (tinte cálido).

Paleta luminosa recomendada (RGB):
  crema cálido     (228, 200, 155)
  vainilla         (235, 205, 130)
  arena clara      (220, 185, 130)
  mantequilla      (240, 200, 90)
  melocotón        (235, 175, 140)
  rosa empolvado   (220, 165, 170)
  salvia clara     (170, 200, 165)
  turquesa suave   (130, 185, 190)
  lavanda suave    (185, 165, 210)
  toldo rojo+arena (180, 55, 55) + (220, 185, 130)
"""

SAFE_ALL_CHANNEL_CAP = 205  # si los 3 superan esto => inseguro
SAFE_LOW_SAT_LUMA_MAX = 215
SAFE_ACCENT_MAX = 245
SAFE_MUST_HAVE_CHANNEL_LE = 195

PALETTE = {
    "crema": (228, 200, 155),
    "vainilla": (235, 205, 130),
    "arena": (220, 185, 130),
    "mantequilla": (240, 200, 90),
    "melocoton": (235, 175, 140),
    "rosa": (220, 165, 170),
    "salvia": (170, 200, 165),
    "turquesa": (130, 185, 190),
    "lavanda": (185, 165, 210),
}
