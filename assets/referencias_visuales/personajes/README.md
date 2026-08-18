# Referencia visual maestra de personajes (PROVISIONAL)

**Estado:** REFERENCIA DE ESTILO · **NO PERSONAJE CANON** · **NO RECICLAR CARAS COMO IDENTIDADES DEL POOL**

Fecha de archivo: 2026-08-18.

Esta carpeta guarda el **lenguaje gráfico** de habitantes. No guarda el pueblo. Nadie de estas láminas es Rocío, Dani, I10, I06 ni ningún slot del pool, aunque el texto de la lámina ponga esos nombres.

```text
REFERENCIA MAESTRA  = ESTILO  (trazo, paleta, ojos, sombreado, variedad)
CABEZA MAESTRA      = IDENTIDAD (una persona concreta, de su ficha)
CARAS DE LA LÁMINA  = MANIQUÍES DE ESTILO, no habitantes
```

## Archivos

| Archivo | Qué es | Qué no es |
| --- | --- | --- |
| `REFERENCIA_MAESTRA_PERSONAJES_v1.png` | Lámina **Estilos de rostro**. Ancla provisional del lenguaje gráfico. | Identidades del pool. |
| `REFERENCIA_MAESTRA_EXPRESIONES_v1.png` | Lámina **Residentes × expresiones**. Ancla del sistema modular de caras. | Identidades del pool. Tampoco es el set V0 obligatorio de 9 emociones. |

Reglas extraídas: `docs/REGLAS_VISUALES_PERSONAJES_v1.md`  
Pipeline: `docs/PIPELINE_CABEZA_MAESTRA_EXPRESIONES.md`

## Prohibido

- Reciclar una cara de estas láminas (Rocío/Marta/Lucas/Daniela/Álvaro/Inés/Jorge/Elena/Miguel/Iván u otras) como habitante.
- Usar un recorte de una sola cara como `reference_image` de identidad.
- Tratar los nombres escritos en la lámina como fichas canon.
- Generar el pool de 16, Rocío, Dani, I10 o I06 con esta referencia hasta que Neni valide el laboratorio.

## Permitido

- Usar **la lámina completa** `REFERENCIA_MAESTRA_PERSONAJES_v1.png` como referencia de **estilo**.
- Usar `REFERENCIA_MAESTRA_EXPRESIONES_v1.png` como referencia de **cómo cambia una cara**, no de quién es.
- Generar una **cabeza maestra nueva** solo desde ficha + lámina de estilo.
- Derivar expresiones solo desde la **cabeza maestra aprobada de ese personaje**.
