<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
$ref = 'docs/referencias_visuales/REFERENCIA_VISUAL_01_AQUI_HAY_TEMA.png';
$refOk = is_file(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ref));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Aquí Hay Tema</title>
  <meta name="description" content="Gestiona vínculos y convivencia en un pueblo entero. Celestine eres tú." />
  <link rel="icon" href="cover.svg" type="image/svg+xml" />
  <link rel="stylesheet" href="css/home.css" />
</head>
<body>
  <header class="top">
    <a class="back" href="/juegos/">Biblioteca</a>
    <h1>Aquí Hay Tema <span class="heart" aria-hidden="true">♥</span></h1>
    <p class="coletilla">Uy. Aquí hay tema.</p>
  </header>

  <main>
    <?php if ($refOk): ?>
      <figure class="board">
        <img src="<?= htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') ?>" alt="Referencia visual del pueblo: mapa, parejas, dinero y fama." width="1600" height="1200" />
      </figure>
    <?php endif; ?>
    <p class="nota">Esqueleto jugable PLAYTEST_01 (UI provisional, sin estética final).</p>
    <p class="nota"><a href="play.php">Abrir partida</a></p>
  </main>
</body>
</html>
