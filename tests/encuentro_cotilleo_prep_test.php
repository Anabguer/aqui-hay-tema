<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroCotilleoCopy;
use AquiHayTema\Engine\PartidaService;

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

DomainBootstrap::boot();
$service = new PartidaService($root);

// --- 1) prepLugarEstancia usa "en la" para Cafetería ---
$prep1 = EncuentroCotilleoCopy::prepLugarEstancia('lug_cafeteria', 'Cafetería');
ok($prep1 === 'en la Cafetería', "1. Cafetería → 'en la Cafetería' (got: '$prep1')");

// --- 2) prepLugarEstancia usa "en el" para Cine ---
$prep2 = EncuentroCotilleoCopy::prepLugarEstancia('lug_cine', 'Cine');
ok($prep2 === 'en el Cine', "2. Cine → 'en el Cine' (got: '$prep2')");

// --- 3) prepLugarEstancia usa "en la" para Biblioteca ---
$prep3 = EncuentroCotilleoCopy::prepLugarEstancia('lug_biblioteca', 'Biblioteca');
ok($prep3 === 'en la Biblioteca', "3. Biblioteca → 'en la Biblioteca' (got: '$prep3')");

// --- 4) prepLugarEstancia usa "en" para lugar con artículo ---
$prep4 = EncuentroCotilleoCopy::prepLugarEstancia('lug_parque', 'El Parque');
ok($prep4 === 'en El Parque', "4. El Parque → 'en El Parque' (got: '$prep4')");

// --- 5) prepLugarEstancia usa "en el" para Gimnasio ---
$prep5 = EncuentroCotilleoCopy::prepLugarEstancia('lug_gimnasio', 'Gimnasio');
ok($prep5 === 'en el Gimnasio', "5. Gimnasio → 'en el Gimnasio' (got: '$prep5')");

// --- 6) prepLugar (direccional) NO se usa para estancia ---
// Verificamos que prepLugar SÍ dice "a la" (direccional), para confirmar que el cambio a prepLugarEstancia es efectivo
$prep6 = EncuentroCotilleoCopy::prepLugarPublico('lug_cafeteria', 'Cafetería');
ok($prep6 === 'a la Cafetería', "6. prepLugar original dice 'a la' (direccional, correcto) (got: '$prep6')");

echo $failures === 0 ? "OK encuentro_cotilleo_prep\n" : "FAIL encuentro_cotilleo_prep ({$failures})\n";
exit($failures > 0 ? 1 : 0);
