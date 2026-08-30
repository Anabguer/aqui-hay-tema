<?php
/**
 * TYPOGRAPHY CAVEAT GUARD
 * 
 * Detects prohibited Caveat usage on reading-text selectors in CSS files.
 * Reading text MUST use Nunito (--ds-font-ui).
 * Caveat (--ds-font-hand) is ONLY for decorative: names, titles, buttons.
 * 
 * Exit 0 = OK, Exit 1 = FAIL (regression detected)
 */

$cssDir = __DIR__ . '/../assets/css';
$dsDir  = $cssDir . '/design-system';

// Selectors that MUST NOT have Caveat (reading/text elements)
$forbiddenPatterns = [
    // Mensajitos
    '.carta-msg .cuerpo',
    '.carta-cuerpo-wrap .cuerpo',
    '.mensajitos-hint',
    '.carta-cuando',
    // Cotilleos/Diario
    '.coti-item-txt',
    '.obj-cotilleo-txt',
    // Misiones
    '.mis-txt',
    '.mision-strip-txt',
    // Tutorial
    '.tut-bloque-txt',
    '.tut-intro-line',
    '.tut-cierre',
    // Ficha (reading body)
    '.ficha-seccion-body',
    '.animo-modal-causa p',
    '.animo-modal-hint',
    // Vida
    '.vida-copy p',
    '.vida-latido',
];

// Scan all CSS files in assets/css (recursively)
$cssFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($cssDir, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->getExtension() === 'css') {
        $cssFiles[] = $file->getPathname();
    }
}

$errors = [];
$scanned = 0;

foreach ($cssFiles as $cssFile) {
    $content = file_get_contents($cssFile);
    if ($content === false) continue;
    $scanned++;
    
    $lines = explode("\n", $content);
    $relPath = str_replace(__DIR__ . '/../', '', $cssFile);
    
    foreach ($lines as $lineNum => $line) {
        $trimmed = trim($line);
        
        // Skip comments
        if (strpos($trimmed, '/*') === 0 || strpos($trimmed, '*') === 0) continue;
        
        // Must contain Caveat to be flagged
        if (stripos($trimmed, 'caveat') === false) continue;
        
        // Check if this line matches a forbidden selector pattern
        foreach ($forbiddenPatterns as $pattern) {
            if (stripos($trimmed, $pattern) !== false) {
                // Check it's actually a font-family/font declaration with Caveat
                if (preg_match('/font-family\s*:.*caveat/i', $trimmed) ||
                    preg_match('/font\s*:.*caveat/i', $trimmed)) {
                    $errors[] = sprintf(
                        "CAVEAT FORBIDDEN on reading text: %s (line %d)\n  Selector: %s\n  File: %s",
                        $trimmed,
                        $lineNum + 1,
                        $pattern,
                        $relPath
                    );
                }
            }
        }
    }
}

// Also check typography-reading.css has the guard rules
$trFile = $dsDir . '/typography-reading.css';
$trContent = file_get_contents($trFile) ?: '';
$hasGuard = strpos($trContent, 'GUARD') !== false || 
            strpos($trContent, 'font-family: var(--ds-font-ui') !== false;

echo "=== TYPOGRAPHY CAVEAT GUARD ===\n";
echo "CSS files scanned: $scanned\n";
echo "Reading-text selectors checked: " . count($forbiddenPatterns) . "\n";
echo "Guard rules in typography-reading.css: " . ($hasGuard ? 'OK' : 'MISSING') . "\n\n";

if (empty($errors)) {
    echo "RESULT: PASS\n";
    echo "No prohibited Caveat usage found on reading-text selectors.\n";
    exit(0);
} else {
    echo "RESULT: FAIL — " . count($errors) . " regression(s) detected:\n\n";
    foreach ($errors as $err) {
        echo "  ⚠ $err\n\n";
    }
    exit(1);
}
