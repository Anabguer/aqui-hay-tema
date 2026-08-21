import io


def edit(path, encoding, ops):
    with io.open(path, "r", encoding=encoding, newline="") as f:
        text = f.read()
    for old, new, expected in ops:
        count = text.count(old)
        if count != expected:
            raise SystemExit("%s: esperaba %d de %r, hay %d" % (path, expected, old[:60], count))
        text = text.replace(old, new)
    with io.open(path, "w", encoding=encoding, newline="") as f:
        f.write(text)
    print("OK", path)


edit(
    "assets/css/play-v3-mapa-canonico.css",
    "utf-8-sig",
    [
        (
            ".play-v3 .mapa-canonico {\n  position: absolute;\n  inset: 0;\n  z-index: 2;\n}",
            ".play-v3 .mapa-canonico {\n  position: absolute;\n  inset: 0;\n  z-index: 2;\n  background: #fff;\n}",
            1,
        )
    ],
)

edit(
    "assets/css/play-v3-capas-shell.css",
    "utf-8-sig",
    [
        (
            ".play-v3 .capa:not(.capa-buzon) .capa-cerrar-pesta\u00f1a {",
            ".play-v3 .capa .capa-cerrar-pesta\u00f1a {",
            1,
        ),
        (
            ".play-v3 .capa:not(.capa-buzon) .capa-cerrar-pesta\u00f1a::before {",
            ".play-v3 .capa .capa-cerrar-pesta\u00f1a::before {",
            1,
        ),
    ],
)

print("listo")
