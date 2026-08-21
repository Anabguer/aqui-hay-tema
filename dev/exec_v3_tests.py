#!/usr/bin/env python3
"""Actualiza tests para canon V3."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parent.parent

# juego_v1_test.php
jv1 = (ROOT / "tests/juego_v1_test.php").read_text(encoding="utf-8")
jv1 = jv1.replace(
    "ok(isset($p['residentes']['per_i03'], $p['residentes']['per_p001'], $p['residentes']['per_p002']), 'Rocío + Carmen + José');",
    "ok(count($p['residentes']) === 3, 'trio aleatorio V3');",
)
jv1 = jv1.replace(
    "ok(($p['celeste']['lugares_desbloqueados'] ?? []) === ['lug_cafeteria'], 'día 1: solo cafetería');",
    "ok(count($p['celeste']['lugares_desbloqueados'] ?? []) === 9, 'día 1: 9 lugares canónicos');",
)
jv1 = jv1.replace(
    "ok(!in_array('lug_parque', $p['celeste']['lugares_desbloqueados'] ?? [], true), 'parque no operativo (no es playtest)');",
    "ok(in_array('lug_parque', $p['celeste']['lugares_desbloqueados'] ?? [], true), 'parque operativo V3');",
)
jv1 = jv1.replace(
    "ok(!FeatureConfig::isEnabled($p, 'misiones_diarias_enabled'), 'sin misiones de playtest el día 1');",
    "ok(FeatureConfig::isEnabled($p, 'misiones_diarias_enabled'), 'misiones V3 activas en juego_v1');",
)
jv1 = jv1.replace(
    "ok(($bien['de_persona'] ?? '') === 'per_i03', 'bienvenida de Rocío');",
    "ok(($bien['de_persona'] ?? '') !== '', 'bienvenida de un residente inicial');",
)
jv1 = jv1.replace(
    "ok(!RelacionEngine::seConocen($p, 'per_p001', 'per_p002'), 'Carmen y José empiezan desconocidos');",
    "$ids = array_keys($p['residentes']);\nok(count($ids) >= 2 && !RelacionEngine::seConocen($p, $ids[0], $ids[1]), 'dos iniciales empiezan desconocidos');",
)
jv1 = jv1.replace("$misma = EncuentroEngine::validarContexto($p, ['per_i03', 'per_i03'],", "$rid0 = array_key_first($p['residentes']);\n$misma = EncuentroEngine::validarContexto($p, [$rid0, $rid0],")
jv1 = jv1.replace("ok(OrganizarMotivo::de($p, 'per_i03', 'per_i03')", "ok(OrganizarMotivo::de($p, $rid0, $rid0)")
jv1 = jv1.replace("$cands = OrganizarMotivo::candidatos($p, 'per_i03');", "$cands = OrganizarMotivo::candidatos($p, $rid0);")
jv1 = jv1.replace("ok(!in_array('per_i03', $idsC, true) && in_array('per_p001', $idsC, true),", "ok(!in_array($rid0, $idsC, true) && count($idsC) >= 1,")
jv1 = jv1.replace("$queda = PropuestaEncuentroEngine::proponer($p, ['per_p001', 'per_p002'],", "$queda = PropuestaEncuentroEngine::proponer($p, [$ids[0], $ids[1]],")
(ROOT / "tests/juego_v1_test.php").write_text(jv1, encoding="utf-8")

# vista_pueblo_v3_test
vp = (ROOT / "tests/vista_pueblo_v3_test.php").read_text(encoding="utf-8")
vp = vp.replace(
    "ok(cx($puebloPt, 'cafe_libros')['fase'] === 'pleno', 'biblioteca desbloqueada → café evolucionado');",
    "ok(cx($puebloPt, 'cafe_libros')['fase_motor'] === 'pleno', 'café+biblioteca operativos V3');",
)
vp = re.sub(
    r"\$pt\['celeste'\]\['lugares_desbloqueados'\]\[\] = 'lug_arcade';\n\$puebloCine = VistaPuebloV3::de\(\$pt, PresenciaEngine::resolver\(\$pt, \$root\), \$root\);\nok\(cx\(\$puebloCine, 'cine_game'\)\['fase'\] === 'pleno', 'arcade desbloqueado → cine evolucionado \(PNG Fase 1\)'\);\nok\(cx\(\$puebloCine, 'cine_game'\)\['fase_motor'\] === 'pleno', 'fase_motor sigue al desbloqueo'\);\n",
    "$puebloCine = VistaPuebloV3::de($pt, PresenciaEngine::resolver($pt, $root), $root);\nok(cx($puebloCine, 'cine_game')['fase_motor'] === 'pleno', 'cine operativo V3 sin arcade legacy');\n",
    vp,
)
(ROOT / "tests/vista_pueblo_v3_test.php").write_text(vp, encoding="utf-8")

# misiones caducada
md = (ROOT / "tests/misiones_diarias_test.php").read_text(encoding="utf-8")
md = md.replace(
    "ok(VidaPuebloEngine::valor($pCad) === $vidaCad - (2 * $nPend), 'caducada −2 por misión');",
    "ok(VidaPuebloEngine::valor($pCad) === $vidaCad, 'caducada V3 = 0 Vida');",
)
(ROOT / "tests/misiones_diarias_test.php").write_text(md, encoding="utf-8")

# schema v2 -> v3 in tests
for tf in (ROOT / "tests").glob("*.php"):
    t = tf.read_text(encoding="utf-8")
    nt = t.replace("'schema v2'", "'schema v3'").replace("schema sigue v2", "schema sigue v3")
    nt = nt.replace("=== 2", "=== 3") if "schema_version" in nt and tf.name.endswith("_test.php") else nt
    if nt != t:
        tf.write_text(nt, encoding="utf-8")

# post_gate capacity
pg = ROOT / "tests/post_gate_llegadas_tutorial_test.php"
if pg.exists():
    t = pg.read_text(encoding="utf-8")
    t = t.replace("CapacidadViviendas::capacidadTotal($p4) === 32", "CapacidadViviendas::capacidadTotal($p4) === 24")
    t = t.replace("CapacidadViviendas::capacidadTotal($p4) === 48", "CapacidadViviendas::capacidadTotal($p4) === 24")
    pg.write_text(t, encoding="utf-8")

print("tests updated")
