<?php
$files = glob("dev/sim_hitos_relacionales.php");
$files = array_merge($files, glob("src/Engine/Hito*.php"), glob("src/Engine/SimuladorHitos*.php"), glob("tests/hitos_relacionales_test.php"));
foreach ($files as $f) {
  $c = file_get_contents($f);
  $o = $c;
  if (strncmp($c, "\xEF\xBB\xBF", 3) === 0) $c = substr($c, 3);
  $i = strpos($c, "<?php");
  if ($i !== false && $i > 0) $c = substr($c, $i);
  if ($c !== $o) { file_put_contents($f, $c); echo "fixed $f\n"; }
}
echo "done\n";