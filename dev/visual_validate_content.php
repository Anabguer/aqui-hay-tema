<?php

declare(strict_types=1);

/**

 * DEV ONLY — validación visual con datos reales (sin API HTTP ni DB local).

 * Uso: /dev/visual_validate_content.php?partida_id=e2erit-part_5af4821&capa=buzon&view=mobile|desktop

 */

if (!isset($_SERVER['HTTP_HOST']) || str_contains((string) $_SERVER['HTTP_HOST'], '127.0.0.1') || str_contains((string) $_SERVER['HTTP_HOST'], 'localhost')) {
    $_SERVER['HTTP_HOST'] = 'visual-validate.internal';
}

require_once dirname(__DIR__) . '/src/autoload.php';

require_once dirname(__DIR__) . '/api/bootstrap.php';

require_once __DIR__ . '/VisualApiContext.php';



use AquiHayTema\Api\Handlers\PartidaHandler;

use AquiHayTema\Dev\VisualApiContextFactory;



$root = dirname(__DIR__);

$partidaId = (string) ($_GET['partida_id'] ?? 'e2erit-part_5af4821');

$capa = (string) ($_GET['capa'] ?? 'inicio');

$view = ($_GET['view'] ?? 'mobile') === 'desktop' ? 'desktop' : 'mobile';



$ahtBusterFile = $root . '/assets/aht-cache-buster.txt';

$ahtUi = is_file($ahtBusterFile) ? trim((string) file_get_contents($ahtBusterFile)) : 'v3-static';

if ($ahtUi === '') {

    $ahtUi = 'v3-static';

}



$ctx = VisualApiContextFactory::create($root);

try {

    $partida = $ctx->service->cargarLigero($partidaId);

    $ctx->partidaCargadaSincronizada = true;

    $refresh = PartidaHandler::refrescar($ctx, [], $partida);

} catch (Throwable $e) {

    http_response_code(404);

    header('Content-Type: text/plain; charset=utf-8');

    echo 'No se pudo cargar partida: ' . $e->getMessage();

    exit;

}



$fichaId = '';

$bestScore = -1;

foreach (($refresh['partida']['residentes'] ?? []) as $rid => $res) {

    if (!is_array($res)) {

        continue;

    }

    $score = count($res['diario'] ?? []) * 3

        + count($res['descubrimientos'] ?? []) * 2

        + (($res['animo'] ?? $res['emocion'] ?? '') !== '' && ($res['animo'] ?? $res['emocion'] ?? '') !== 'neutral' ? 5 : 0);

    if ($score > $bestScore) {

        $bestScore = $score;

        $fichaId = (string) $rid;

    }

}

if ($fichaId === '') {

    $keys = array_keys($refresh['partida']['residentes'] ?? []);

    $fichaId = $keys[0] ?? '';

}



$capaJs = match ($capa) {

    'inicio' => '',

    'vecinos_rel' => 'vecinos',

    default => $capa,

};



$payload = json_encode($refresh, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

if ($payload === false) {

    http_response_code(500);

    echo 'JSON encode fail';

    exit;

}



header('Content-Type: text/html; charset=utf-8');

?>

<!DOCTYPE html>

<html lang="es">

<head>

  <meta charset="utf-8"/>

  <base href="../"/>

  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>

  <title>Visual validate — <?= htmlspecialchars($capa, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="assets/css/play-v3.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-capas.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-app.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-shell-ui.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-shell-art.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-capas-shell.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-mensajitos.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-mapa-canonico.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-bloques-residencias.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/design-system/tokens.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/design-system/components.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/design-system/screens/inicio.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/design-system/screens/modals.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/design-system/screens/capas-ds.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-vecinos.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-ficha.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-cotilleos.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-organizar.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-agenda.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-misiones.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-vida.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-regalos.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-enc-int.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-desktop-shell.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-responsive.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-inicio-override.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-visual-review.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-visual-interior.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <link rel="stylesheet" href="assets/css/play-v3-visual-replica.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"/>

  <style>

    body { margin: 0; background: #6b5a4a; }

    .dev-bar { position: sticky; top: 0; z-index: 300; display: flex; flex-wrap: wrap; gap: 6px; padding: 6px 8px; background: #1a1a1a; color: #eee; font: 12px/1.3 monospace; }

    .dev-bar a { color: #9cf; }

    .dev-bar .on { color: #ff9; font-weight: bold; }

    .play-v3.dev-validate .play-root.phone { width: 393px; max-width: 100%; margin: 0 auto; }

    .play-v3.dev-validate .play-root.pc { width: min(1280px, 100vw); margin: 0 auto; }

    .play-v3.dev-validate:has(.game-shell) .play-root[data-capa]:not([data-capa=""]) .capa { display: flex !important; opacity: 1 !important; pointer-events: auto !important; }

    .play-v3.dev-validate .play-root[data-capa]:not([data-capa=""]) .velo { display: block !important; opacity: 1 !important; }

    .play-v3.dev-validate .play-root[data-capa]:not([data-capa=""]) .board-scroll,

    .play-v3.dev-validate .play-root[data-capa]:not([data-capa=""]) .mesa,

    .play-v3.dev-validate .play-root[data-capa]:not([data-capa=""]) .dock { visibility: hidden !important; height: 0 !important; overflow: hidden !important; }

    .play-v3.dev-validate .play-root[data-capa=""] .velo { display: none !important; }

  </style>

</head>

<body class="play-v3 dev-validate" data-ui="v3" data-partida-id="<?= htmlspecialchars($partidaId, ENT_QUOTES, 'UTF-8') ?>" data-capa-target="<?= htmlspecialchars($capaJs, ENT_QUOTES, 'UTF-8') ?>" data-ficha-id="<?= htmlspecialchars($fichaId, ENT_QUOTES, 'UTF-8') ?>" data-vecinos-rel="<?= $capa === 'vecinos_rel' ? '1' : '0' ?>">

  <div class="dev-bar">

    <span><?= htmlspecialchars($partidaId, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($capa, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($view, ENT_QUOTES, 'UTF-8') ?></span>

    <?php

    $caps = ['inicio','buzon','vecinos','vecinos_rel','ficha','ficha_diario','diario','organizar','agenda','misiones','vida_pueblo','inventario'];

    foreach ($caps as $c) {

        foreach (['mobile', 'desktop'] as $v) {

            $q = '?partida_id=' . rawurlencode($partidaId) . '&capa=' . rawurlencode($c) . '&view=' . $v;

            $cls = ($c === $capa && $view === $v) ? ' class="on"' : '';

            echo '<a href="' . htmlspecialchars($q, ENT_QUOTES, 'UTF-8') . '"' . $cls . '>' . htmlspecialchars($c . '-' . substr($v, 0, 1), ENT_QUOTES, 'UTF-8') . '</a>';

        }

    }

    ?>

  </div>

  <?php

  $snippet = __DIR__ . '/_visual_validate_shell_snippet.php';

  if (!is_file($snippet)) {

      echo '<p>Falta shell: ejecutar php dev/_gen_visual_shell_snippet.php</p>';

  } else {

      // Ajustar clase play-root según viewport

      ob_start();

      include $snippet;

      $shell = ob_get_clean();

      $shell = str_replace('class="play-root pc"', 'class="play-root ' . ($view === 'desktop' ? 'pc' : 'phone') . '" data-capa="' . htmlspecialchars($capaJs, ENT_QUOTES, 'UTF-8') . '"', $shell);

      echo '<div class="game-shell">';

      echo $shell;

      echo '</div>';

  }

  ?>

  <script id="aht-refresh-payload" type="application/json"><?= $payload ?></script>

  <script src="dev/visual_validate_boot.js?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"></script>

  <script src="assets/js/hobby-icons.js?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"></script>

  <script src="assets/js/play-v3.js?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, 'UTF-8') ?>"></script>

</body>

</html>

