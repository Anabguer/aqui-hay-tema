"""Quita un fondo negro solido/casi solido de una imagen y genera un PNG con
alpha, dejando intacto el resto del dibujo (solo se toca la zona de fondo
conectada con el borde del lienzo, para no comerse detalles oscuros del
propio dibujo).

Uso:
    python dev/bg_remove_black.py entrada.jpg salida.png [umbral]
"""
import sys

import numpy as np
from PIL import Image, ImageFilter
from scipy import ndimage


def quitar_fondo_negro(ruta_entrada, ruta_salida, umbral_duro=18, umbral_suave=70):
    im = Image.open(ruta_entrada).convert("RGB")
    arr = np.array(im)
    maxc = arr.max(axis=2)

    candidato = maxc <= umbral_suave
    etiquetas, n = ndimage.label(candidato, structure=np.ones((3, 3)))

    borde = set()
    if n:
        borde.update(np.unique(etiquetas[0, :]).tolist())
        borde.update(np.unique(etiquetas[-1, :]).tolist())
        borde.update(np.unique(etiquetas[:, 0]).tolist())
        borde.update(np.unique(etiquetas[:, -1]).tolist())
    borde.discard(0)

    fondo = np.isin(etiquetas, list(borde))

    alpha = np.full(maxc.shape, 255, dtype=np.float32)
    duro = fondo & (maxc <= umbral_duro)
    alpha[duro] = 0.0
    borde_suave = fondo & (maxc > umbral_duro)
    frac = np.clip((maxc.astype(np.float32) - umbral_duro) / max(1, (umbral_suave - umbral_duro)), 0, 1)
    alpha[borde_suave] = frac[borde_suave] * 255.0

    alpha_img = Image.fromarray(alpha.astype(np.uint8), mode="L").filter(ImageFilter.GaussianBlur(0.6))

    out = im.convert("RGBA")
    out.putalpha(alpha_img)
    out.save(ruta_salida)
    print("OK ->", ruta_salida, out.size)


if __name__ == "__main__":
    entrada = sys.argv[1]
    salida = sys.argv[2]
    umbral = int(sys.argv[3]) if len(sys.argv) > 3 else 18
    quitar_fondo_negro(entrada, salida, umbral_duro=umbral)
