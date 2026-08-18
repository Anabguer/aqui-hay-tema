<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\RngService;

$failures = 0;
function ok(bool $c, string $m): void
{
    global $failures;
    if (!$c) {
        echo "FAIL: $m\n";
        $failures++;
    } else {
        echo "OK: $m\n";
    }
}

$a = new RngService('test-seed');
$b = new RngService('test-seed');
$seqA = [$a->next(), $a->next(), $a->next()];
$seqB = [$b->next(), $b->next(), $b->next()];
ok($seqA === $seqB, 'misma seed misma secuencia');

$c = new RngService('otra-seed');
ok($c->next() !== $seqA[0] || $c->next() !== $seqA[1], 'seed distinta secuencia distinta');

$d = new RngService('persist', 99999);
ok($d->getState() === 99999, 'restaura state');

$partida = ['meta' => ['seed' => 'p1'], 'rng' => ['seed' => 'p1', 'state' => 12345]];
$e = RngService::fromPartida($partida);
ok($e->getState() === 12345, 'fromPartida state');
$n1 = $e->next();
$e->persistToPartida($partida);
$e2 = RngService::fromPartida($partida);
$n2 = $e2->next();
$f = new RngService('p1', 12345);
$f->next();
ok($n2 === $f->next(), 'save/load preserva secuencia RNG');

$g = new RngService('pick-unique');
$h = new RngService('pick-unique');
$pool = ['a', 'b', 'c', 'd', 'e'];
ok($g->pickUnique($pool, 3) === $h->pickUnique($pool, 3), 'pickUnique reproducible');
$g2 = new RngService('pick-unique');
$picked = $g2->pickUnique($pool, 3);
ok(count($picked) === 3 && count(array_unique($picked)) === 3, 'pickUnique sin duplicados');

exit($failures > 0 ? 1 : 0);
