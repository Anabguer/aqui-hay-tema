import re
import pathlib

ROOT = pathlib.Path(r"W:\juegos\aqui-hay-tema")

p = ROOT / "play.php"
text = p.read_text(encoding="utf-8")
m = re.search(
    r'      <div class="top-center">.*?</div>\s*</div>\s*<div class="top-vida"',
    text,
    re.S,
)
if not m:
    raise SystemExit("play.php block not found")
new_block = """      <div class=\"top-center\">
        <div class=\"top-reloj\">
          <div class=\"obj-dia\" style=\"--rot:-2deg\">
            <div class=\"obj-dia-placa\">
              <span class=\"obj-dia-num\" data-dia-num>D\u00cdA \u00b7</span>
            </div>
            <div class=\"obj-dia-cuerpo\">
              <span class=\"obj-dia-estacion\" data-dia-estacion>Primavera</span>
              <span class=\"obj-dia-meta\" data-dia-meta>\u00b7</span>
            </div>
            <span class=\"sr-only\" data-fecha></span>
          </div>
          <div class=\"obj-hora\" style=\"--rot:3deg\" aria-label=\"Hora del pueblo\">
            <span class=\"obj-hora-val\" data-hora>\u2014</span>
          </div>
        </div>
      </div>
      <div class=\"top-vida\""""
text = text[: m.start()] + new_block + text[m.end() - len('<div class="top-vida"'):]
text = text.replace("v3-20260822bloq", "v3-20260822reloj")
p.write_text(text, encoding="utf-8")
print("play.php ok")
