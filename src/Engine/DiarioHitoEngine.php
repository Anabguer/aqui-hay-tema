<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Memoria personal del Diario: hitos y acontecimientos relevantes del residente.
 * Lee fuentes canónicas (bitácora, encuentros, descubrimientos); no duplica
 * historial paralelo ni espeja el texto del cotilleo.
 */
final class DiarioHitoEngine
{
    /** @var list<string> */
    private const HITOS_RELEVANTES = [
        RelacionBitacora::SE_CONOCIERON,
        RelacionBitacora::PRIMERA_CITA,
        RelacionBitacora::RECHAZO_IMPORTANTE,
        RelacionBitacora::REGALO,
        RelacionBitacora::DECLARACION,
        RelacionBitacora::INICIO_PAREJA,
        RelacionBitacora::CRISIS,
        RelacionBitacora::RECONCILIACION,
        RelacionBitacora::RUPTURA,
        RelacionBitacora::VUELTA,
        RelacionBitacora::DISCUSION_FUERTE,
        RelacionBitacora::APOYO_IMPORTANTE,
        RelacionBitacora::HITO_ROMANTICO,
        RelacionBitacora::FLECHAZO,
    ];

    /** Hitos unilaterales: solo el actor afectado recibe entrada de diario. */
    private const DIRECCIONALES = [
        RelacionBitacora::FLECHAZO,
        RelacionBitacora::RECHAZO_IMPORTANTE,
    ];

    /** @var list<string> */
    private const CUERPOS_SE_CONOCIERON = [
        'Hoy he conocido a {otro}. Ya nos conocemos.',
        'Por fin he hablado de verdad con {otro}.',
        'Hoy {otro} y yo nos hemos presentado por fin.',
    ];

    /** @var list<string> */
    private const CUERPOS_PRIMERA_CITA = [
        'Hemos tenido nuestra primera cita.',
        'Primera cita oficial. No estuvo mal.',
        'Nos hemos visto fuera del edificio con intención.',
    ];

    /** @var list<string> */
    private const CUERPOS_REGALO = [
        'Intercambiamos un detalle que no pasó desapercibido.',
        'Hubo un gesto bonito: flores o algo parecido.',
        'Nos hicimos un regalo que dejó huella.',
    ];

    /** @var list<string> */
    private const CUERPOS_RECHAZO = [
        'Me acerqué a {otro} y la respuesta fue un no contundente.',
        'La cosa dolió entre {otro} y yo. Fue un rechazo claro.',
        '{otro} dejó claro que no a mí.',
    ];

    /** @var list<string> */
    private const CUERPOS_FLECHAZO = [
        'No puedo dejar de mirar a {otro}. Las miradas ya no se pueden disimular.',
        'Hemos cruzado una mirada que ya no podíamos ocultar.',
        'Hay chispa con {otro}. Y no es imaginación.',
    ];

    /** @var list<string> */
    private const CUERPOS_INICIO_PAREJA = [
        'Hemos empezado algo más que una amistad.',
        'Oficialmente ya somos pareja.',
        'Lo nuestro por fin tiene nombre: somos pareja.',
    ];

    /** @var list<string> */
    private const CUERPOS_VUELTA = [
        'Nos hemos dado otra oportunidad.',
        'Volvemos a intentarlo. Segunda ronda.',
        'Hemos vuelto a juntarse. Otra oportunidad.',
    ];

    /** @var list<string> */
    private const CUERPOS_RECONCILIACION = [
        'Hemos dejado las diferencias a un lado.',
        'Nos hemos hablado y hemos arreglado las cosas.',
        'Paz entre nosotros: lo que pasó, quedó arreglado.',
    ];

    /** @var list<string> */
    private const CUERPOS_RUPTURA = [
        'Lo hemos dejado.',
        'Hemos roto nuestra relación.',
        'Se acabó. Cada uno por su lado.',
    ];

    /** @var list<string> */
    private const CUERPOS_CRISIS = [
        'Nuestra relación atraviesa momentos difíciles.',
        'Lo nuestro pasa por un bache complicado.',
        'Estamos tensionados. No es fácil.',
    ];

    /** @var list<string> */
    private const CUERPOS_DISCUSION_FUERTE = [
        'Tuvimos una discusión que se notó.',
        'Hemos tenido un enfado serio.',
        'Cruzamos palabras duras.',
    ];

    /** @var list<string> */
    private const CUERPOS_DECLARACION_RECHAZADA = [
        'Me declaré a {otro}, pero no fue recíproco.',
        'Puse el corazón sobre la mesa ante {otro}. La respuesta fue no.',
        'Declaración a {otro}: no aceptada.',
    ];

    /** @var list<string> */
    private const CUERPOS_ROMANTICO = [
        'Algo importante ha pasado entre {otro} y yo.',
        'Ha habido un momento romántico con {otro} que marca.',
    ];

    /** @var list<string> */
    private const CUERPOS_APOYO = [
        '{otro} y yo nos apoyamos cuando más lo necesitábamos.',
        'Un gesto de apoyo de {otro} que me importó.',
    ];

    /** @var list<string> */
    private const CUERPOS_ENCUENTRO_MUY_MAL = [
        'Mi encuentro con {otro} no pudo salir peor.',
        'El encuentro con {otro} fue un desastre. Así de claro.',
        'Todo lo que podía salir mal con {otro}, salió mal.',
    ];

    /** @var list<string> */
    private const CUERPOS_ENCUENTRO_MAL = [
        'Compartí un rato con {otro} que se torció más de lo esperado.',
        'El plan con {otro} se torció. No fue para tanto, pero se torció.',
        'No acabamos de entendernos en el último rato con {otro}.',
    ];

    /** @var list<string> */
    private const CUERPOS_ENCUENTRO_CALENTADO = [
        'Con {otro} surgió tensión. Del tipo que se comenta.',
        'El ambiente con {otro} se puso denso.',
        'El aire con {otro} se cargó un poco.',
    ];

    public static function register(): void
    {
        EventBus::on(DomainEvents::ENCUENTRO_TERMINADO, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
            self::alEncuentroTerminado($partida, $envelope);
            return ['ok' => true];
        });
    }

    public static function ensure(array &$partida): void
    {
        $partida['diario_hitos_registrados'] ??= [];
    }

    /**
     * Llamado desde RelacionBitacora::registrar con la entrada canónica del hito.
     * Genera una entrada por actor en primera persona.
     *
     * @param array<string, mixed> $hito
     */
    public static function alHito(array &$partida, array $hito): ?array
    {
        $tipo = (string) ($hito['tipo'] ?? '');
        if ($tipo === '' || !in_array($tipo, self::HITOS_RELEVANTES, true)) {
            return null;
        }
        $actores = is_array($hito['participantes'] ?? null)
            ? array_values(array_filter((array) $hito['participantes'], static fn($i) => is_string($i) && $i !== ''))
            : [];
        if (count($actores) < 2) {
            return null;
        }

        $clave = self::claveHito($tipo, $actores);
        self::ensure($partida);
        if (!empty($partida['diario_hitos_registrados'][$clave])) {
            return self::buscarEntradaHito($partida, $tipo, $actores);
        }

        $nAntes = count($partida['diario'] ?? []);
        self::construirEntradasHito($partida, $tipo, $actores, $hito);
        $nDespues = count($partida['diario'] ?? []);

        if ($nDespues > $nAntes) {
            $partida['diario_hitos_registrados'][$clave] = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
            for ($i = $nAntes; $i < $nDespues; $i++) {
                DomainEventDispatcher::emit($partida, DomainEvents::DIARIO_ENTRADA, [
                    'entrada' => $partida['diario'][$i],
                    'origen' => 'diario_hito',
                    'hito_tipo' => $tipo,
                ]);
            }
            return $partida['diario'][$nAntes];
        }

        return null;
    }

    /**
     * Backfill idempotente para saves con bitácora previa sin entradas de diario propias.
     */
    public static function sincronizarDesdeBitacora(array &$partida): int
    {
        $n = 0;
        foreach ($partida['bitacora_relaciones'] ?? [] as $hito) {
            if (!is_array($hito)) {
                continue;
            }
            if (self::alHito($partida, $hito) !== null) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * Genera una entrada por actor afectado, en primera persona.
     * Solo crea entrada si el actor tuvo resultado negativo.
     *
     * @param array<string, mixed> $envelope
     */
    private static function alEncuentroTerminado(array &$partida, array $envelope): void
    {
        $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
        $envelope = array_merge($envelope, $payload);
        $enc = is_array($envelope['encuentro'] ?? null) ? $envelope['encuentro'] : [];
        $res = is_array($envelope['resultado'] ?? null) ? $envelope['resultado'] : [];
        if ($res === [] && is_array($enc['resultado'] ?? null)) {
            $res = $enc['resultado'];
        }
        $encId = (string) ($enc['id'] ?? '');
        $actores = array_values(array_filter(
            is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [],
            static fn($i) => is_string($i) && $i !== ''
        ));
        if ($encId === '' || count($actores) < 2 || ($enc['tipo'] ?? '') === 'individual') {
            return;
        }

        $huboConflicto = (($res['conflicto'] ?? null) !== null) && (int) ($res['conflicto'] ?? 0) !== 0;

        foreach ($actores as $actorId) {
            $resultadoActor = 'normal';
            foreach ($res['por_participante'] ?? [] as $row) {
                if (is_array($row) && (string) ($row['residente_id'] ?? '') === $actorId) {
                    $resultadoActor = (string) ($row['resultado'] ?? 'normal');
                    break;
                }
            }

            if (!$huboConflicto && $resultadoActor !== 'mal' && $resultadoActor !== 'muy_mal') {
                continue;
            }

            $eventoId = 'diario_hito:encuentro:' . $encId . ':' . $actorId;
            if (DiarioEngine::entradaPorEvento($partida, $eventoId) !== null) {
                continue;
            }

            $yo = IdentidadPublica::nombre($partida, $actorId);
            $otrosIds = array_values(array_filter($actores, static fn($i) => (string) $i !== $actorId));
            $otrosNombres = [];
            foreach ($otrosIds as $oid) {
                $n = IdentidadPublica::nombre($partida, (string) $oid);
                if ($n !== '') {
                    $otrosNombres[] = $n;
                }
            }
            $otro = $otrosNombres !== [] ? implode(' y ', $otrosNombres) : 'otra persona';

            $vars = ['yo' => $yo, 'otro' => $otro];
            if ($resultadoActor === 'muy_mal') {
                $titulo = 'Un encuentro incómodo';
                $texto = self::cuerpo($partida, 'encuentro_muy_mal', $actores, self::CUERPOS_ENCUENTRO_MUY_MAL, $vars);
            } elseif ($resultadoActor === 'mal') {
                $titulo = 'Un encuentro torcido';
                $texto = self::cuerpo($partida, 'encuentro_mal', $actores, self::CUERPOS_ENCUENTRO_MAL, $vars);
            } else {
                $titulo = 'Las cosas se calentaron';
                $texto = self::cuerpo($partida, 'encuentro_calentado', $actores, self::CUERPOS_ENCUENTRO_CALENTADO, $vars);
            }

            $consecuencias = [];
            $emo = self::emocionAnotadaDelEncuentro($res, $actorId);
            if ($emo !== '') {
                $consecuencias[] = 'Estoy ' . ($emo === EstadoEmocional::TRISTE ? 'triste' : 'alterad' . GeneroConcordancia::oa($partida, $actorId)) . '.';
            }

            $entrada = self::escribir($partida, [
                'tipo' => 'diario_hito',
                'subtipo' => 'encuentro',
                'titulo' => $titulo,
                'texto' => $texto,
                'consecuencias' => $consecuencias,
                'actores' => [$actorId],
                'ts_juego' => [
                    'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
                    'hora' => (int) ($enc['hora'] ?? $enc['hora_inicio'] ?? $partida['reloj']['hora_actual'] ?? 12),
                ],
                'origen' => [
                    'evento_id' => $eventoId,
                    'tipo_evento' => 'encuentro_terminado',
                    'es_narrativo' => true,
                    'hito_tipo' => 'encuentro_significativo',
                    '_placeholder' => false,
                ],
                '_placeholder_contenido' => false,
            ]);
            if ($entrada !== null) {
                DomainEventDispatcher::emit($partida, DomainEvents::DIARIO_ENTRADA, [
                    'entrada' => $entrada,
                    'origen' => 'diario_hito',
                    'hito_tipo' => 'encuentro_significativo',
                ]);
            }
        }
    }

    /**
     * Genera una entrada de diario por cada actor, en primera persona.
     * Cada residente ve el hito desde su propia perspectiva.
     *
     * @param list<string> $actores
     * @param array<string, mixed> $hito
     */
    private static function construirEntradasHito(array &$partida, string $tipo, array $actores, array $hito): void
    {
        $consecuencias = [];
        $titulo = 'Un hito en el edificio';
        $textoPool = [];
        $varsBase = [];

        switch ($tipo) {
            case RelacionBitacora::SE_CONOCIERON:
                $titulo = 'Primer contacto';
                $textoPool = self::CUERPOS_SE_CONOCIERON;
                break;
            case RelacionBitacora::PRIMERA_CITA:
                $titulo = 'Primera cita';
                $textoPool = self::CUERPOS_PRIMERA_CITA;
                break;
            case RelacionBitacora::REGALO:
                $titulo = 'Un detalle especial';
                $textoPool = self::CUERPOS_REGALO;
                break;
            case RelacionBitacora::RECHAZO_IMPORTANTE:
                $titulo = 'Un rechazo importante';
                $textoPool = self::CUERPOS_RECHAZO;
                $dir = self::direccionDeHito($hito, $actores);
                $varsBase = [
                    'yo' => IdentidadPublica::nombre($partida, $dir['desde']),
                    'otro' => IdentidadPublica::nombre($partida, $dir['hacia']),
                ];
                break;
            case RelacionBitacora::FLECHAZO:
                $titulo = 'Un flechazo';
                $textoPool = self::CUERPOS_FLECHAZO;
                $dir = self::direccionDeHito($hito, $actores);
                $varsBase = [
                    'yo' => IdentidadPublica::nombre($partida, $dir['desde']),
                    'otro' => IdentidadPublica::nombre($partida, $dir['hacia']),
                ];
                break;
            case RelacionBitacora::INICIO_PAREJA:
                $titulo = 'Nueva pareja';
                $textoPool = self::CUERPOS_INICIO_PAREJA;
                break;
            case RelacionBitacora::VUELTA:
                $titulo = 'Segunda oportunidad';
                $textoPool = self::CUERPOS_VUELTA;
                break;
            case RelacionBitacora::RECONCILIACION:
                $titulo = 'Reconciliación';
                $textoPool = self::CUERPOS_RECONCILIACION;
                break;
            case RelacionBitacora::RUPTURA:
                $titulo = 'Ruptura';
                $textoPool = self::CUERPOS_RUPTURA;
                $consecuencias = ['Ya no somos pareja.'];
                break;
            case RelacionBitacora::CRISIS:
                $titulo = 'Crisis de pareja';
                $textoPool = self::CUERPOS_CRISIS;
                break;
            case RelacionBitacora::DISCUSION_FUERTE:
                $titulo = 'Una discusión fuerte';
                $textoPool = self::CUERPOS_DISCUSION_FUERTE;
                break;
            case RelacionBitacora::DECLARACION:
                $res = is_array($hito['resultado'] ?? null) ? (array) $hito['resultado'] : [];
                $aceptaA = (bool) ($res['acepta_a'] ?? true);
                $aceptaB = (bool) ($res['acepta_b'] ?? true);
                if ($aceptaA && $aceptaB) {
                    return;
                }
                $titulo = 'Una declaración rechazada';
                $textoPool = self::CUERPOS_DECLARACION_RECHAZADA;
                $rechazaId = !$aceptaA ? (string) $actores[1] : (string) $actores[0];
                $declaraId = !$aceptaA ? (string) $actores[0] : (string) $actores[1];
                $varsBase = [
                    'yo' => IdentidadPublica::nombre($partida, $declaraId),
                    'otro' => IdentidadPublica::nombre($partida, $rechazaId),
                ];
                $consecuencias = [IdentidadPublica::nombre($partida, $declaraId) . ' recibió un no.'];
                break;
            case RelacionBitacora::HITO_ROMANTICO:
                $titulo = 'Un momento romántico';
                $textoPool = self::CUERPOS_ROMANTICO;
                break;
            case RelacionBitacora::APOYO_IMPORTANTE:
                $titulo = 'Apoyo entre vecinos';
                $textoPool = self::CUERPOS_APOYO;
                break;
            default:
                return;
        }

        if ($textoPool === []) {
            return;
        }

        if (in_array($tipo, self::DIRECCIONALES, true)) {
            $dir = self::direccionDeHito($hito, $actores);
            $actores = array_values(array_filter($actores, static fn($id) => (string) $id === $dir['desde']));
        }

        $clave = self::claveHito($tipo, $actores);
        $eventoIdBase = self::eventoIdDeClave($clave);

        foreach ($actores as $actorId) {
            $eventoId = $eventoIdBase . ':' . $actorId;
            if (DiarioEngine::entradaPorEvento($partida, $eventoId) !== null) {
                continue;
            }

            $yo = IdentidadPublica::nombre($partida, $actorId);
            $otrosIds = array_values(array_filter($actores, static fn($i) => (string) $i !== $actorId));
            $otrosNombres = [];
            foreach ($otrosIds as $oid) {
                $n = IdentidadPublica::nombre($partida, (string) $oid);
                if ($n !== '') {
                    $otrosNombres[] = $n;
                }
            }
            $otro = $otrosNombres !== [] ? implode(' y ', $otrosNombres) : 'otro';

            $vars = array_merge($varsBase, ['yo' => $yo, 'otro' => $otro]);
            $texto = self::cuerpo($partida, $tipo, $actores, $textoPool, $vars);
            if ($texto === '') {
                continue;
            }

            $hitoFecha = is_array($hito['fecha'] ?? null) ? $hito['fecha'] : [];
            $tsHito = [
                'dia' => (int) ($hitoFecha['dia'] ?? $partida['reloj']['dia_pueblo'] ?? 1),
                'hora' => (int) ($hitoFecha['hora'] ?? $partida['reloj']['hora_actual'] ?? 12),
            ];

            self::escribir($partida, [
                'tipo' => 'diario_hito',
                'subtipo' => $tipo,
                'titulo' => $titulo,
                'texto' => $texto,
                'consecuencias' => $consecuencias,
                'actores' => [$actorId],
                'ts_juego' => $tsHito,
                'origen' => [
                    'evento_id' => $eventoId,
                    'tipo_evento' => 'relacion_hito',
                    'es_narrativo' => true,
                    'hito_tipo' => $tipo,
                    'bitacora_id' => (string) ($hito['id'] ?? ''),
                    '_placeholder' => false,
                ],
                '_placeholder_contenido' => false,
            ]);
        }
    }

    /**
     * @param list<string> $actores
     */
    private static function claveHito(string $tipo, array $actores): string
    {
        if (in_array($tipo, self::DIRECCIONALES, true)) {
            return $tipo . ':' . implode('|', array_map('strval', $actores));
        }
        $ids = $actores;
        sort($ids);
        return $tipo . ':' . implode('|', $ids);
    }

    private static function eventoIdDeClave(string $clave): string
    {
        return 'diario_hito:' . $clave;
    }

    /**
     * Busca una entrada de hito existente para los actores dados.
     * Usado para idempotencia cuando la entrada ya fue creada.
     *
     * @param list<string> $actores
     */
    private static function buscarEntradaHito(array $partida, string $tipo, array $actores): ?array
    {
        foreach ($partida['diario'] ?? [] as $e) {
            if (!is_array($e)) {
                continue;
            }
            if (($e['tipo'] ?? '') !== 'diario_hito') {
                continue;
            }
            if (($e['subtipo'] ?? '') !== $tipo) {
                continue;
            }
            $actoresEntrada = is_array($e['actores'] ?? null) ? $e['actores'] : [];
            if (count($actoresEntrada) !== 1) {
                continue;
            }
            if (in_array((string) $actoresEntrada[0], $actores, true)) {
                return $e;
            }
        }
        return null;
    }

    /**
     * @param list<string> $actores
     * @param array<string, mixed> $hito
     * @return array{desde: string, hacia: string}
     */
    private static function direccionDeHito(array $hito, array $actores): array
    {
        $d = (string) ($hito['direccion'] ?? '');
        if (str_contains($d, '>')) {
            [$desde, $hacia] = explode('>', $d, 2);
            if ($desde !== '' && $hacia !== '') {
                return ['desde' => $desde, 'hacia' => $hacia];
            }
        }
        return ['desde' => (string) $actores[0], 'hacia' => (string) $actores[1]];
    }

    /**
     * @param array<string, mixed> $entrada
     */
    private static function escribir(array &$partida, array $entrada): ?array
    {
        $r = DiarioEngine::crear($partida, $entrada);
        return ($r['ok'] ?? false) ? ($r['entrada'] ?? null) : null;
    }

    /**
     * @param array<string, mixed> $res
     */
    private static function emocionAnotadaDelEncuentro(array $res, string $rid): string
    {
        foreach ($res['emociones'] ?? [] as $e) {
            if (is_array($e) && (string) ($e['residente_id'] ?? '') === $rid) {
                $estado = (string) ($e['estado'] ?? '');
                if (in_array($estado, [EstadoEmocional::TRISTE, EstadoEmocional::ENFADADO], true)) {
                    return $estado;
                }
            }
        }
        return '';
    }

    /**
     * @param list<string> $actores
     * @param list<string> $pool
     * @param array<string, string> $vars
     */
    private static function cuerpo(array &$partida, string $claveTipo, array $actores, array $pool, array $vars): string
    {
        $ids = array_values(array_filter($actores, static fn($i) => is_string($i) && $i !== ''));
        sort($ids);
        $clave = 'diario_hito:' . $claveTipo . ':' . implode('|', $ids);
        $seed = $claveTipo . '|' . implode('|', $ids) . '|' . ($partida['rng']['cursor'] ?? 0);
        $plantillas = array_map(static fn($k) => '{' . $k . '}', array_keys($vars));
        return str_replace(
            $plantillas,
            array_values($vars),
            CopyVariante::elegir($partida, $clave, $pool, $seed)
        );
    }

    /** @param list<string> $ids */
    private static function nombresPar(array $partida, array $ids): string
    {
        $ns = [];
        foreach ($ids as $id) {
            $n = IdentidadPublica::nombre($partida, (string) $id);
            if ($n !== '') {
                $ns[] = $n;
            }
        }
        if ($ns === []) {
            return 'dos vecinos';
        }
        if (count($ns) === 2) {
            return $ns[0] . ' y ' . $ns[1];
        }
        return implode(', ', array_slice($ns, 0, -1)) . ' y ' . end($ns);
    }

    private static function oA(array $partida, string $rid): string
    {
        $g = (string) ($partida['residentes'][$rid]['identidad_publica']['genero'] ?? '');
        return $g === 'mujer' ? 'a' : 'o';
    }
}
