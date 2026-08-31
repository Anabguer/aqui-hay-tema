<?php declare(strict_types=1);

/**
 * Audio E2E — arquitectura, assets, controles, persistencia, autoplay, dedup.
 *
 * Tests lado servidor que verifican integridad del sistema de audio:
 * assets existen, JS expone API correcta, HTML tiene controles,
 * keys de localStorage consistentes, y el sistema no depende de server-side.
 */

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

// ── Assets ──────────────────────────────────────────────────────────────

$audioDir = $root . '/assets/audio';
ok(is_dir($audioDir), 'assets/audio/ existe');

$requiredAssets = [
    'musica-fondo.mp3' => 'Música de fondo (loop)',
    'mensajito.mp3'    => 'Feedback mensajito',
    'cotilleo.mp3'     => 'Feedback cotilleo',
    'romance.mp3'      => 'Feedback romance',
    'conflicto.mp3'    => 'Feedback conflicto',
];

foreach ($requiredAssets as $file => $desc) {
    $path = $audioDir . '/' . $file;
    ok(is_file($path), "asset $file existe ($desc)");
    if (is_file($path)) {
        $size = filesize($path);
        ok($size > 100, "asset $file tiene peso razonable ($size bytes)");
        ok($size < 10 * 1024 * 1024, "asset $file no excede 10MB ($size bytes)");
    }
}

// ── JS: play-v3-audio.js ────────────────────────────────────────────────

$audioJs = $root . '/assets/js/play-v3-audio.js';
ok(is_file($audioJs), 'play-v3-audio.js existe');
$audioJsContent = is_file($audioJs) ? file_get_contents($audioJs) : '';

ok(strpos($audioJsContent, 'AhtAudioFeedback') !== false, 'AhtAudioFeedback API expuesta');
ok(strpos($audioJsContent, 'play') !== false, 'API incluye play()');
ok(strpos($audioJsContent, 'pauseAll') !== false, 'API incluye pauseAll()');
ok(strpos($audioJsContent, 'setEffects') !== false, 'API incluye setEffects()');
ok(strpos($audioJsContent, 'getVolume') !== false, 'API incluye getVolume()');
ok(strpos($audioJsContent, 'setVolume') !== false, 'API incluye setVolume()');
ok(strpos($audioJsContent, 'isEnabled') !== false, 'API incluye isEnabled()');

// Autoplay handling
ok(strpos($audioJsContent, 'promise.catch(noop)') !== false || strpos($audioJsContent, '.catch(noop)') !== false, 'autoplay: play() error handled con catch(noop)');
ok(strpos($audioJsContent, 'visibilitychange') !== false, 'pausa en visibilitychange');
ok(strpos($audioJsContent, 'pagehide') !== false, 'pausa en pagehide');

// Dedup / max concurrent
ok(strpos($audioJsContent, 'maxActive') !== false, 'limite de reproducciones concurrentes (maxActive)');
ok(strpos($audioJsContent, 'active >= maxActive') !== false, 'dedup por limite de reproducciones');

// Effects persistence
ok(strpos($audioJsContent, 'aht_efectos_sonido') !== false, 'persistencia de mute de efectos en localStorage');
ok(strpos($audioJsContent, 'aht_sfx_vol') !== false, 'persistencia de volumen de efectos en localStorage');

// Patterns (Web Audio synthesis)
ok(strpos($audioJsContent, 'mision') !== false, 'patrón Web Audio: mision');
ok(strpos($audioJsContent, 'descubrimiento') !== false, 'patrón Web Audio: descubrimiento');
ok(strpos($audioJsContent, 'llegada') !== false, 'patrón Web Audio: llegada');
ok(strpos($audioJsContent, 'nuevo_dia') !== false, 'patrón Web Audio: nuevo_dia');

// Event observation via fetch interception
ok(strpos($audioJsContent, 'installFetchObserver') !== false, 'observer de fetch para detectar eventos');
ok(strpos($audioJsContent, 'mision_cumplida') !== false, 'detecta evento mision_cumplida');
ok(strpos($audioJsContent, 'descubrimiento_registrado') !== false, 'detecta evento descubrimiento_registrado');
ok(strpos($audioJsContent, 'senal_romantica') !== false, 'detecta evento senal_romantica');
ok(strpos($audioJsContent, 'residente_incorporado') !== false, 'detecta evento residente_incorporado');
ok(strpos($audioJsContent, 'discusion') !== false, 'detecta evento discusion');

// Sample references
ok(strpos($audioJsContent, "mensajito: 'assets/audio/mensajito.mp3'") !== false, 'sample mensajito referenciado');
ok(strpos($audioJsContent, "cotilleo: 'assets/audio/cotilleo.mp3'") !== false, 'sample cotilleo referenciado');
ok(strpos($audioJsContent, "romance: 'assets/audio/romance.mp3'") !== false, 'sample romance referenciado');
ok(strpos($audioJsContent, "conflicto: 'assets/audio/conflicto.mp3'") !== false, 'sample conflicto referenciado');

// SFX volume control
ok(strpos($audioJsContent, 'data-sfx-vol') !== false, 'bind de data-sfx-vol para slider de volumen');
ok(strpos($audioJsContent, 'function getVolume') !== false, 'función getVolume definida');
ok(strpos($audioJsContent, 'function setVolume') !== false, 'función setVolume definida');

// ── JS: play-v3.js (música) ────────────────────────────────────────────

$playJs = $root . '/assets/js/play-v3.js';
ok(is_file($playJs), 'play-v3.js existe');
$playJsContent = is_file($playJs) ? file_get_contents($playJs) : '';

ok(strpos($playJsContent, 'aht_musica_fondo') !== false, 'persistencia de mute de música en localStorage');
ok(strpos($playJsContent, 'aht_musica_vol') !== false, 'persistencia de volumen de música en localStorage');
ok(strpos($playJsContent, 'musica-fondo.mp3') !== false, 'música de fondo referenciada');
ok(strpos($playJsContent, 'iniciarMusicaFondo') !== false, 'función iniciarMusicaFondo existe');
ok(strpos($playJsContent, 'musicaPrimerGesto') !== false, 'autoplay: espera primer gesto del usuario');
ok(strpos($playJsContent, 'cambiarMusica') !== false, 'función cambiarMusica (toggle) existe');
ok(strpos($playJsContent, 'pausarAudioPorOculto') !== false, 'pausa audio cuando ventana se oculta');
ok(strpos($playJsContent, 'data-musica-toggle') !== false, 'bind de data-musica-toggle');
ok(strpos($playJsContent, 'data-musica-vol') !== false, 'bind de data-musica-vol');
ok(strpos($playJsContent, 'data-sfx-vol') !== false, 'bind de data-sfx-vol en play-v3.js');
ok(strpos($playJsContent, 'AhtAudioFeedback.setVolume') !== false, 'play-v3.js llama a AhtAudioFeedback.setVolume');
ok(strpos($playJsContent, 'AhtAudioFeedback.getVolume') !== false, 'play-v3.js llama a AhtAudioFeedback.getVolume');
ok(strpos($playJsContent, 'AhtAudioFeedback.pauseAll') !== false, 'play-v3.js llama a AhtAudioFeedback.pauseAll en visibilitychange');

// ── CSS ─────────────────────────────────────────────────────────────────

$audioCss = $root . '/assets/css/play-v3-audio.css';
ok(is_file($audioCss), 'play-v3-audio.css existe');
$audioCssContent = is_file($audioCss) ? file_get_contents($audioCss) : '';
ok(strpos($audioCssContent, 'control-efectos') !== false, 'CSS define control-efectos');
ok(strpos($audioCssContent, 'data-efectos="off"') !== false, 'CSS maneja estado off de efectos');
ok(strpos($audioCssContent, 'aht-sfx-debug') !== false, 'CSS define panel de debug de sonidos');

$musicaCss = $root . '/assets/css/play-v3-musica.css';
ok(is_file($musicaCss), 'play-v3-musica.css existe');
$musicaCssContent = is_file($musicaCss) ? file_get_contents($musicaCss) : '';
ok(strpos($musicaCssContent, 'control-musica') !== false, 'CSS define control-musica');
ok(strpos($musicaCssContent, 'data-musica="off"') !== false, 'CSS maneja estado off de música');

// ── HTML: play.php ──────────────────────────────────────────────────────

$playPhp = $root . '/play.php';
ok(is_file($playPhp), 'play.php existe');
$playPhpContent = is_file($playPhp) ? file_get_contents($playPhp) : '';

// Controles flotantes (bottom-right)
ok(strpos($playPhpContent, 'data-musica-toggle') !== false, 'HTML: botón data-musica-toggle presente');
ok(strpos($playPhpContent, 'data-efectos-toggle') !== false, 'HTML: botón data-efectos-toggle presente');
ok(strpos($playPhpContent, 'control-audio') !== false, 'HTML: contenedor control-audio presente');

// Ajustes panel
ok(strpos($playPhpContent, 'data-musica-vol') !== false, 'HTML: slider data-musica-vol en ajustes');
ok(strpos($playPhpContent, 'data-sfx-vol') !== false, 'HTML: slider data-sfx-vol en ajustes');

// Scripts cargados
ok(strpos($playPhpContent, 'play-v3-audio.js') !== false, 'HTML: play-v3-audio.js cargado');
ok(strpos($playPhpContent, 'play-v3-musica.css') !== false, 'HTML: play-v3-musica.css cargado');
ok(strpos($playPhpContent, 'play-v3-audio.css') !== false, 'HTML: play-v3-audio.css cargado');

// Debug panel de sonidos
ok(strpos($playPhpContent, 'aht-sfx-debug') !== false, 'HTML: panel de debug de sonidos presente');
ok(strpos($playPhpContent, 'data-aht-sfx="mensajito"') !== false, 'HTML: botón debug mensajito');
ok(strpos($playPhpContent, 'data-aht-sfx="cotilleo"') !== false, 'HTML: botón debug cotilleo');
ok(strpos($playPhpContent, 'data-aht-sfx="mision"') !== false, 'HTML: botón debug mision');
ok(strpos($playPhpContent, 'data-aht-sfx="descubrimiento"') !== false, 'HTML: botón debug descubrimiento');
ok(strpos($playPhpContent, 'data-aht-sfx="romance"') !== false, 'HTML: botón debug romance');
ok(strpos($playPhpContent, 'data-aht-sfx="conflicto"') !== false, 'HTML: botón debug conflicto');
ok(strpos($playPhpContent, 'data-aht-sfx="llegada"') !== false, 'HTML: botón debug llegada');
ok(strpos($playPhpContent, 'data-aht-sfx="nuevo_dia"') !== false, 'HTML: botón debug nuevo_dia');

// ── Consistencia de keys ────────────────────────────────────────────────

ok(strpos($audioJsContent, 'aht_efectos_sonido') !== false, 'key aht_efectos_sonido definida en JS');
ok(strpos($audioJsContent, 'aht_sfx_estado_v1:') !== false, 'prefijo de estado por partida definido');

// ── Separación de concerns ──────────────────────────────────────────────

// Música y efectos son sistemas independientes
ok(strpos($playJsContent, 'musicaFondo') !== false, 'sistema de música independiente (musicaFondo)');
ok(strpos($audioJsContent, 'AhtAudioFeedback') !== false, 'sistema de efectos independiente (AhtAudioFeedback)');

// No hay new Audio() dispersos fuera de los managers
$audioJsCount = substr_count($audioJsContent, 'new Audio(');
ok($audioJsCount <= 5, "new Audio() concentrado en play-v3-audio.js ($audioJsCount llamadas)");

$playJsAudioCount = substr_count($playJsContent, 'new Audio(');
ok($playJsAudioCount <= 3, "new Audio() concentrado en play-v3.js ($playJsAudioCount llamadas, música fondoloop)");

// ── Resumen ─────────────────────────────────────────────────────────────

echo "\n";
if ($failures === 0) {
    echo "AUDIO E2E: TODOS LOS TESTS PASARON\n";
} else {
    echo "AUDIO E2E: $failures TEST(S) FALLARON\n";
}
exit($failures > 0 ? 1 : 0);
