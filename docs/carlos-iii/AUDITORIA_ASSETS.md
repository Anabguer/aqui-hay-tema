# Auditoría de assets — Carlos III

**Fecha:** 2026-08-19
**Autor:** CARLOS III — ASSETS VISUALES
**Alcance:** inventario de lo que hay en disco. Sin producción, sin tocar P001-P200, PLAY, motor ni CSS de producción.
**Canon de personajes:** la carpeta `assets/personajes/aprobados/` manda. El campo `status` de los `*_meta.json` **no** decide si están aprobados.

Zona de esta documentación: `docs/carlos-iii/` (no es el prototipo de Carlos II en `design/`, ni el motor de Carlos I).

---

## 0. Resumen ejecutivo

Cuando Carlos II entregue el mockup aprobado, sabremos de inmediato:

- **Qué tenemos:** 200 packs de retrato (800 PNG con transparencia) + identidad de landing + una brújula de tono. Cero edificios de producción.
- **Qué se reutiliza:** los retratos canónicos en ficha; paleta papel/rosa de la landing; referencia 01 como tono, no como layout.
- **Qué falta:** tablero, edificios, decoración, objetos de HUD, thumbs de cabeza para el mapa.
- **Qué producirá Carlos III:** todo lo anterior, **después** de perspectiva/escala/tamaños de Carlos II. No antes.

| Área | En disco | Para el pueblo |
|---|---|---|
| A. Personajes | P001-P200, 4 expresiones RGBA | Ficha: sí. Mapa: derivados, sin tocar originales |
| B. Edificios | Cero sprites (sí hay datos) | Falta todo |
| C. Mapa | Brújula + favicon + maqueta de II | Falta tablero de juego |
| D. Decoración | No | Falta todo |
| E. HUD/UI | CSS/emoji/texto | Falta el objeto gráfico |
| F. Estados | Flags del motor | Falta cómo se pintan |

---

## 1. Personajes

**Ruta canónica:** `W:\juegos\aqui-hay-tema\assets\personajes\aprobados\`

### 1.1 Inventario

| Dato | Valor |
|---|---|
| Carpetas | P001 ... P200 (200/200) |
| PNG de expresión | **800** (4 por pack) |
| Peso | **748,1 MB** |
| Comparativas internas | **192** (`Pxxx_comparativa_4_expresiones.png` en P009-P200). P001-P008 no las tienen |
| Meta + ficha | 200 `Pxxx_meta.json` + 200 `Pxxx_ficha_visual.md` |
| Formato | PNG válido, **RGBA**, transparencia sí |
| Expresiones | `neutral` 200, `alegre` 200, `triste` 200, `enfadada` 97, `enfadado` 103 |
| Faltantes de esas 4 | ninguno |
| Firmas PNG rotas | 0 |

El motor mapea `enfadada.png` a la expresión `enfadado`. No unificar nombres de archivo.

### 1.2 Dimensiones (expresiones, no comparativas)

| Lienzo | Archivos | Packs |
|---|---|---|
| 1024x1024 | 768 | P009-P200 |
| 1254x1254 | 24 | P003-P008 |
| Irregular ~1303-1429 x 1100-1207 | 8 | P001 (4 tamaños distintos entre expresiones) y P002 |

Comparativas: RGB **sin alpha**, ejemplo P009 = 2168x624, fondo opaco. Láminas de control, no juego.

### 1.3 Margen transparente (muestra de neutrales)

Medido con alpha > 8.

| Pack | Lienzo | Contenido útil | Fill | Margen L/T/R/B % |
|---|---|---|---|---|
| P001 | 1408x1117 | 995x1083 | 51% | 15 / 1 / 14 / 2 |
| P002 | 1303x1207 | 1183x1203 | 45% | 5 / 0 / 5 / **0** (toca el borde inferior) |
| P008 | 1254x1254 | 1181x1205 | 56% | 3 / 3 / 3 / 1 |
| P009 | 1024x1024 | 1007x1003 | 56% | ~1% los cuatro lados |
| P040 | 1024x1024 | 851x961 | 47% | 9 / 3 / 8 / 3 |
| P075 | 1024x1024 | 1013x1003 | 48% | **0 / 0** / 1 / 2 |
| P150 | 1024x1024 | 801x931 | 52% | 11 / 3 / 10 / 6 |
| P175 | 1024x1024 | 743x911 | 41% | 14 / 4 / 14 / 7 |
| P200 | 1024x1024 | 935x913 | 45% | 5 / 6 / 4 / 5 |

El recorte **no es estable** ni entre packs ni dentro de un pack. P200: neutro ~935 px de ancho útil; alegre/enfadado ~650 px. P001 cambia de canvas en cada expresión.

Lectura: son **bustos** (cabeza + hombros + pecho), no tokens de cabeza. Margen típico 0-15%. En ficha, a 72-240 px, valen. En tablero, a 24-48 px, el PNG nativo es ilegible y pesado (~0,9 MB cada 1024).

### 1.4 Cabezas de mapa

| Uso | Veredicto |
|---|---|
| Ficha / cara a cara | **Sí.** PLAY ya usa `url_relativa`. |
| Cabeza sobre edificio | **No en crudo.** Hace falta derivado (crop de cabeza + tamaño mapa) **sin tocar originales**. |
| Directorio de vecinos | Mismo derivado o un thumb medio. Lo cierra Carlos II. |

### 1.5 Campo `status` (no manda)

- `aprobado` en meta: P001-P009, P011, P019 (11)
- `candidato` en meta: el resto (189)

La carpeta `aprobados/` es la fuente. No regenerar ni filtrar por este campo. Estilo declarado: `AHT_PERSONAJES_V1`, `visual_identity_version: 1`.

### 1.6 Expresiones que el motor nombra y no están en disco

Provisionales del motor: `neutral`, `alegre`, `entusiasmado`, `pensativo`, `enfadado`, `triste`, `sorprendido`, `esceptico`, `complice`.

En aprobados hay **4 de 9**. No producir las otras 5 hasta que se pida. Packs parciales son válidos; fallback `neutral`.

### 1.7 Fuera de canon

| Ruta | Qué es | Juego |
|---|---|---|
| `assets/personajes/_laboratorio/LAB_01_Teo/` | Pipeline. RGB **sin alpha**, 1536x1024 | No |
| `assets/personajes/_revision/` | Estilos 01-06b, I02/I03 inválidos, Rocío de prueba | No reciclar caras |
| `assets/referencias_visuales/` | Solo README; PNG de lámina **no están en disco** (gitignore) | No identidades |

`assets/personajes/README.md` describe rutas antiguas (`<id>/retrato.png`), obsoletas.

---

## 2. Lugares / edificios

Datos: `data/lugares/lugares.json` (14 ítems). `coordenadas: null`. **Ningún campo de imagen.**

| Lugar | id | Asset | Notas |
|---|---|---|---|
| Cafetería | lug_cafeteria | NO EXISTE | Día 1 operativo en datos |
| Parque | lug_parque | NO EXISTE | Día 1 operativo |
| Biblioteca | lug_biblioteca | NO EXISTE | Día 1 operativo |
| Cine | lug_cine | NO EXISTE | Candado conceptual V0 |
| Plaza | lug_plaza | NO EXISTE | |
| Restaurante | lug_restaurante | NO EXISTE | Uno solo |
| Bar | lug_bar | NO EXISTE | Candado conceptual V0 |
| Discoteca | lug_discoteca | NO EXISTE | |
| Bingo | lug_bingo | NO EXISTE | |
| Arcade | lug_arcade | NO EXISTE | Candado conceptual V0 |
| Tienda de ropa | lug_tienda_ropa | NO EXISTE | |
| Gimnasio | lug_gimnasio | NO EXISTE | |
| Mirador | lug_mirador | NO EXISTE | |
| Viviendas / casas | lug_casa + Bloque A | NO EXISTE | Casa distinto de apartamentos. Bloque A hoy es grid CSS, no edificio |

No hay `assets/lugares/` ni `assets/edificios/`.

Lo que parece edificio y no lo es:

| Ruta | Formato | Estilo | Reutilizable |
|---|---|---|---|
| `cover.svg` y `public-door/cover.svg` | SVG 640x640, 6 rectángulos + 4 círculos | Favicon papel | No como edificios. Sí paleta y metáfora tablero+cabezas |
| `design/prototipo-pantalla-principal/mapa.svg` | SVG 1600x1000, maqueta Carlos II (calles, persianas, labels) | Prototipo aislado | **No producción.** No extraer tiles hasta ok de Neni + cierre de II |
| `docs/referencias_visuales/REFERENCIA_VISUAL_01_AQUI_HAY_TEMA.png` | PNG RGB 1448x1086, ~2,0 MB, sin alpha | Brújula de tono | No copiar píxel a píxel |

Habrá que **producir de cero** el corte de edificios que II cierre.

---

## 3. Mapa y decoración

No hay tileset de producción.

| Elemento | EXISTE | Dónde | Estado |
|---|---|---|---|
| Tablero jugable | NO | Maqueta II: `design/prototipo-pantalla-principal/mapa.svg` | Prototipo, no enganchado a PLAY |
| Fondos | NO | | Falta |
| Suelo | solo patrón cobble en maqueta II | prototipo | No extraer aún |
| Calles | rectángulos en cover.svg y maqueta II | identidad / prototipo | Falta set |
| Árboles / arbustos | NO | manchas verdes en maqueta II | Falta |
| Bancos | mención en maqueta II | prototipo | Falta |
| Farolas | NO | | Falta |
| Carteles | dirección en referencia 01 | no hay PNG suelto | Falta |
| Coches, papeleras, flores | NO | | Falta |
| Vallas | patrón en maqueta II | prototipo | Falta |

Previews de II (no producción): `design/prototipo-pantalla-principal/previews/pc.png` (1560x980), `pc-ficha.png`, `movil.png`, `movil-ficha.png`.

---

## 4. Interfaz visual

PLAY no carga PNG de HUD. Cero `url()` en `assets/css/app.css`.

| Pieza | Gráfico | Hoy | Aprovechable |
|---|---|---|---|
| Corazón / Vida del Pueblo | No como objeto | PLAY: emoji + clip CSS `--fill`. Landing: corazón `#e56b8a`. cover.svg: path rosa | Color y metáfora sí. El glyph de lab no. Hay que hacer objeto rellenable sin cifra |
| Dinero | NO | Referencia 01; PLAY no | Si II lo pone en el pulso |
| Fama | NO | Igual | II no ha cerrado sitio |
| Buzón | NO | Texto / conteo | Objeto + número de no leídos |
| Cartas / sobres | NO | | Falta |
| Post-it / notas | NO | | Falta (misión = nota del pueblo) |
| Chinchetas | NO | | Falta |
| Bocadillos | NO | | Falta (avisos sobre el pueblo) |
| Diario / cotilleo | NO | Panel de texto | Falta; voz distinta del buzón |
| Calendario | NO | Texto | Quizá solo tipografía |
| Candados / estados | NO | Badges de texto | Deben verse en el edificio |
| Identidad landing | CSS + SVG | papel `#fbfaf6`, tinta `#2b2b2b`, muted `#8a7f74`, línea `#e8e2d6`, rosa `#e56b8a`, Georgia | **Norte cromático** |

---

## 5. Inventario de producción

### A. Personajes

| ASSET | EXISTE | REUTILIZABLE | REHACER | FALTA | ESPERA II |
|---|---|---|---|---|---|
| Retratos P001-P200 x 4 | Sí | Sí, ficha | No los originales | | Tamaño en ficha |
| Thumb / cabeza de mapa | No | | | Sí (derivado nuevo) | Recorte, px, una expresión o varias |
| Thumb directorio | No | | | Probable | Si hay lista secundaria |
| 5 expresiones extra | No | | | Solo si se piden | ¿bastan 4? |
| Comparativas 4 expr. | Sí (192) | No en juego | | | |
| Lab / revision / 06b | Sí | No como habitantes | | | |

### B. Edificios

| ASSET | EXISTE | REUTILIZABLE | REHACER | FALTA | ESPERA II |
|---|---|---|---|---|---|
| 13 lugares sociales | No | | | Sí | Perspectiva, escala, corte V0 |
| lug_casa | No | | | Sí | Distinto de Bloque A |
| Bloque A | No (grid CSS) | No | | Sí como un edificio del mapa | Cómo se lee vs el pueblo |
| cover.svg cajas | Sí | No | | | |

### C. Mapa

| ASSET | EXISTE | REUTILIZABLE | REHACER | FALTA | ESPERA II |
|---|---|---|---|---|---|
| Tablero jugable | No | | | Sí | Proporción PC/móvil |
| Referencia 01 | Sí | Tono, no layout | | | |
| Maqueta design/.../mapa.svg | Sí (II) | No hasta aprobación | Probable al pasar a producción | | Cierre II + Neni |

### D. Decoración

| ASSET | EXISTE | REUTILIZABLE | FALTA | ESPERA II |
|---|---|---|---|---|
| Suelo, calles, árboles, bancos, farolas, carteles, coches, papeleras, flores, vallas | No (salvo patrones de maqueta II) | No | Todo | Lista mínima V0 |

### E. HUD/UI

| ASSET | EXISTE | REUTILIZABLE | REHACER | FALTA | ESPERA II |
|---|---|---|---|---|---|
| Corazón objeto | No (emoji CSS) | Color `#e56b8a` | El de PLAY | Sí | Forma, tamaño, cómo se llena |
| Dinero | No | | | Si va al pulso | ¿icono o cifra? |
| Fama | No | | | ¿? | Si vive en HUD |
| Buzón / sobre | No | | | Sí | Objeto + badge |
| Nota / post-it | No | | | Sí | |
| Chincheta / aviso / bocadillo | No | | | Sí | |
| Paleta landing | Sí | Norte cromático | | | Paleta cerrada vs provisional |

### F. Estados y efectos

| ASSET | EXISTE | FALTA | ESPERA II |
|---|---|---|---|
| Abierto / candado / cerrado | No | Sí (o overlay) | ¿PNG extra o capa? |
| Cerrado por horario | El motor no expone aún un flag visual de horario | Necesidad funcional | Si V0 lo muestra |
| Seleccionado | No | Sí | |
| Con habitantes | Placeholder `_placeholder_visual` + iniciales | Cabezas reales | Escala y máximo visible |
| Encuentro próximo / en curso | Marcas de datos | Marca gráfica | |
| Aviso gordo sobre el pueblo | No | Sí | |

No asumo un PNG por estado.

---

## 6. Estados funcionales (sin diseñar)

Fuente: `PresenciaEngine`, `ResumenDia`, catálogo de lugares, docs de mapa.

Edificio: visible no operativo; candado; operativo; cerrado por horario (confirmar); seleccionado; con habitantes (aforo); encuentro próximo; en curso; cambio visual al desbloquear (no aparece de la nada).

Cabeza: presente en un lugar; clic a ficha; expresión en mapa (quizá solo neutro).

HUD: corazón sin cifra; buzón con no leídos; nota de hoy; aviso encima del pueblo.

Hueco: dinero, fama, plan grupal (caben en Organizar / mapa).

---

## 7. Qué se reutiliza / qué no / qué falta

**Reutilizar:** 800 PNG de `aprobados/` como retrato; paleta landing; referencia 01 como tono; contrato de 4 expresiones + fallback.

**No usar en producción del pueblo:** dashboard de PLAY; emoji de corazón de lab; grid Bloque A; cajas de cover.svg; LAB/revision/06b; comparativas internas. `docs/ESTADO_VISUAL_ACTUAL.md` (2026-08-18) dice que no hay nadie en `aprobados/`: **obsoleto**.

**Falta:** tablero; edificios; Bloque A como pieza de mapa; decoración; corazón/buzón/nota/avisos; dinero/fama si II los pone; marcas de estado; thumbs de cabeza.

**Orden provisional de producción (no arrancar):** spec de escala (II) -> thumbs de cabeza a carpeta nueva (`assets/personajes/thumbs/` propuesta, fuera de `aprobados/`) -> edificios V0 (cafetería, parque, biblioteca + candados cine/bar/arcade) -> resto cerrados en mapa -> Bloque A -> suelo/calles mínimos -> HUD -> decoración de segundo pase -> casa y extras.

---

## 8. Decisiones concretas que necesito de Carlos II

1. Perspectiva del tablero (cenital, 3/4, juguete plano).
2. Escala y px: edificio, cabeza, corazón; PC vs móvil.
3. Proporción cabeza/edificio y cuántas cabezas se leen (aforo vs legibilidad).
4. Recorte de cabeza: círculo, busto, marco. ¿una expresión en mapa?
5. Tamaños de derivados y carpeta destino.
6. Tratamiento de edificios: ¿misma familia que P001-P200 o más juguete/papel?
7. Corte V0 de edificios a producir primero.
8. Estados: overlay vs variante PNG; candado vs persiana en el edificio.
9. Lista cerrada de objetos HUD (corazón, dinero, fama, buzón, nota).
10. Bloque A como un edificio del pueblo, no cuadrícula de pisos.
11. Si `design/prototipo-pantalla-principal/` es la base a traducir a assets o solo estructura.

---

## 9. Rutas exactas

```
assets/personajes/aprobados/P001/ ... P200/     CANON retratos
data/lugares/lugares.json                       catalogo sin sprites
docs/referencias_visuales/REFERENCIA_VISUAL_01_AQUI_HAY_TEMA.png
css/home.css
cover.svg
public-door/cover.svg
public-door/css/home.css
assets/css/app.css                              PLAY lab
assets/js/play.js
design/prototipo-pantalla-principal/            zona Carlos II
assets/personajes/_laboratorio/LAB_01_Teo/      no canon
assets/personajes/_revision/                    no canon
docs/carlos-iii/                                esta auditoría
```

---

## 10. Lo que no he hecho

No he generado imágenes, no he producido edificios, no he decidido perspectiva/escala/proporciones, no he tocado P001-P200, PLAY, motor ni CSS de producción. No he rellenado huecos inventando assets.

**PARA.**
