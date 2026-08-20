# Muestra de recorte de tokens (Carlos III)

**No son los 200.** No se tocan los originales P001–P200. 56/44 px es el tamaño en el mapa, no un recorte idéntico.

## Criterio propuesto
1. Máscara de silueta (alfa; se ignora el negro opaco de fondo).
2. Cortar hombros/pecho; conservar cabeza + pelo (moños, afro, coleta, gorra, flequillo, rapado asimétrico).
3. Círculo que cubre esa silueta, centrado en su caja, con poco margen.
4. Master 256×256, círculo, fuera alfa 0. El anillo de tinta lo pone la UI.
5. En juego se escala a **56 px PC / 44 px móvil**.

Eso iguala el **peso de cabeza+pelo**, no el tamaño de la cara. Un rapado se verá con la cara más grande; un afro, más pequeña. Si se igualara la cara, se cortarían peinados.

## Muestra (10 extremos)
| Id | Por qué está |
|---|---|
| P001 | Canvas irregular + pelo de volumen |
| P009 | Rapado + melena (el que disparó el matiz) |
| P010 | Moños altos y lazos |
| P016 | Pelo corto / poco volumen |
| P018 | Coleta alta |
| P031 | Gorra + pelo largo |
| P109 | Rizo alto |
| P117 | Faux hawk |
| P121 | Melena larga, más aire en el lienzo |
| P138 | Afro ancho |

La lámina compara: original | recorte idéntico tipo P009 (falla) | normalizado 256 | 56 px.

## Qué no es
No va a `assets/personajes/tokens/` hasta que Neni + ChatGPT validen este criterio.
