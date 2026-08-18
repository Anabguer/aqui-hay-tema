<?php
declare(strict_types=1);

/**
 * Falla si el codigo reintroduce sintaxis > PHP 7.4.
 */
$root = dirname(__DIR__);
$failures = 0;

function php74_ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

function php74_php_files(string $root): array
{
    $out = [];
    $dirs = [$root . '/src', $root . '/api', $root . '/tests', $root . '/dev'];
    $extra = [$root . '/play.php', $root . '/index.php', $root . '/dev.php'];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $out[] = $file->getPathname();
            }
        }
    }
    foreach ($extra as $f) {
        if (is_file($f)) {
            $out[] = $f;
        }
    }
    sort($out);
    return $out;
}

function php74_scan_file(string $path): array
{
    $src = file_get_contents($path);
    if ($src === false) {
        return ['no se pudo leer'];
    }
    $hits = [];
    $tokens = token_get_all($src);
    $n = count($tokens);
    $inConstruct = false;
    $paren = 0;
    $forbiddenConst = [
        'T_MATCH' => defined('T_MATCH') ? T_MATCH : -1,
        'T_READONLY' => defined('T_READONLY') ? T_READONLY : -1,
        'T_NEVER' => defined('T_NEVER') ? T_NEVER : -1,
        'T_NULLSAFE_OBJECT_OPERATOR' => defined('T_NULLSAFE_OBJECT_OPERATOR') ? T_NULLSAFE_OBJECT_OPERATOR : -1,
        'T_ENUM' => defined('T_ENUM') ? T_ENUM : -1,
    ];
    $prevCode = null;
    $prevText = '';
    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $code = is_array($t) ? $t[0] : null;
        $text = is_array($t) ? $t[1] : $t;
        $line = is_array($t) ? (string) $t[2] : '?';
        foreach ($forbiddenConst as $name => $tok) {
            if ($tok >= 0 && $code === $tok) {
                $hits[] = "L{$line} {$name}";
            }
        }
        if ($code === T_STRING && strcasecmp($text, 'mixed') === 0 && ($prevText === ',' || $prevText === '(' || $prevText === ':')) {
            $hits[] = "L{$line} tipo mixed";
        }
        if ($inConstruct && $paren === 1 && in_array($code, [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
            $hits[] = "L{$line} constructor promotion";
        }
        if ($code === T_FUNCTION) {
            $j = $i + 1;
            while ($j < $n && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $j++;
            }
            $nameTok = $tokens[$j] ?? null;
            $inConstruct = is_array($nameTok) && $nameTok[1] === '__construct';
            $paren = 0;
        }
        if ($text === '(') {
            $paren++;
        }
        if ($text === ')') {
            $paren--;
            if ($paren <= 0) {
                $inConstruct = false;
                $paren = 0;
            }
        }
        $typeWords = ['int', 'float', 'string', 'bool', 'array', 'object', 'iterable', 'callable', 'mixed', 'null', 'self', 'parent', 'static', 'false', 'true'];
        if ($text === '|' && in_array(strtolower($prevText), $typeWords, true)) {
            $hits[] = "L{$line} union type |";
        }
        $prevCode = $code;
        $prevText = $text;
    }
    if (preg_match_all('/^use\s+(?!function\b|const\b)[A-Za-z\\\\]+\\\\([a-z][A-Za-z0-9_]*)\s*;/m', $src, $mm)) {
        $hits[] = 'use de función sin "use function": ' . implode(', ', array_unique($mm[1]));
    }
    return $hits;
}

$files = php74_php_files($root);
php74_ok($files !== [], 'hay fuentes PHP que escanear');
$bad = [];
foreach ($files as $file) {
    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $file);
    $hits = php74_scan_file($file);
    if ($hits !== []) {
        $bad[$rel] = $hits;
    }
}
if ($bad === []) {
    php74_ok(true, 'ninguna sintaxis >7.4 en src/api/tests/dev/play/index');
} else {
    foreach ($bad as $rel => $hits) {
        php74_ok(false, $rel . ' -> ' . implode('; ', $hits));
    }
}

require_once $root . '/src/php74_compat.php';
php74_ok(function_exists('str_starts_with'), 'polyfill/nativo str_starts_with');
php74_ok(function_exists('str_contains'), 'polyfill/nativo str_contains');
php74_ok(function_exists('str_ends_with'), 'polyfill/nativo str_ends_with');
php74_ok(str_starts_with('playtest-01', 'play'), 'str_starts_with funciona');
php74_ok(str_contains('playtest-01', 'test'), 'str_contains funciona');
php74_ok(str_ends_with('playtest-01', '01'), 'str_ends_with funciona');

exit($failures > 0 ? 1 : 0);
