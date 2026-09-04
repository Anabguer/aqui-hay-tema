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

    /** @var list<string> */
    private const CUERPOS_SE_CONOCIERON = [
        '{par} por fin se han presentado de verdad.',
        'Ha quedado claro que {par} ya no son desconocidos.',
        '{par} han dado el primer paso para conocerse.',
    ];

    /** @var list<string> */
    private const CUERPOS_PRIMERA_CITA = [
        '{par} han tenido su primera cita.',
        'Primera cita oficial entre {par}.',
        '{par} se han visto fuera del edificio con intención.',
    ];

    /** @var list<string> */
    private const CUERPOS_REGALO = [
        '{par} intercambiaron un detalle que no pasó desapercibido.',
        'Hubo un gesto bonito entre {par}: flores o algo parecido.',
        '{par} se hicieron un regalo que dejó huella.',
    ];

    /** @var list<string> */
    private const CUERPOS_RECHAZO = [
        '{declara} se acercó a {rechaza} y la respuesta fue un no contundente.',
        'Entre {declara} y {rechaza} hubo un rechazo que dolió.',
        '{rechaza} dejó claro que no a {declara}.',
    ];

    /** @var list<string> */
    private const CUERPOS_FLECHAZO = [
        '{par} se han lanzado miradas imposibles de disimular.',
        '{par} han cruzado una mirada que ya no podían ocultar.',
        'Había chispa en el aire con {par}. Y no era imaginación.',
    ];

    /** @var list<string> */
    private const CUERPOS_INICIO_PAREJA = [
        '{par} han empezado algo más que una amistad.',
        'Oficialmente {par} ya son pareja.',
        'Lo de {par} por fin tiene nombre: son pareja.',
    ];

    /** @var list<string> */
    private const CUERPOS_VUELTA = [
        '{par} se han dado otra oportunidad.',
        '{par} vuelven a intentarlo.',
        'Segunda ronda para {par}: han vuelto a juntarse.',
    ];

    /** @var list<string> */
    private const CUERPOS_RECONCILIACION = [
        '{par} han dejado las diferencias a un lado.',
        '{par} se han hablado y han arreglado sus cosas.',
        'Paz entre {par}: lo que pasó, quedó arreglado.',
    ];

    /** @var list<string> */
    private const CUERPOS_RUPTURA = [
        '{par} lo han dejado.',
        '{par} han roto su relación.',
        'Hay ruptura entre {par}. Cada uno por su lado.',
    ];

    /** @var list<string> */
    private const CUERPOS_CRISIS = [
        'La relación entre {par} atraviesa momentos difíciles.',
        'Lo de {par} pasa por un bache complicado.',
        'La pareja que forman {par} anda tensionada.',
    ];

    /** @var list<string> */
    private const CUERPOS_DISCUSION_FUERTE = [
        '{par} tuvieron una discusión que se notó.',
        '{par} han tenido un enfado serio.',
        '{par} cruzaron palabras duras.',
    ];

    /** @var list<string> */
    private const CUERPOS_DECLARACION_RECHAZADA = [
        '{declara} se declaró a {rechaza}, pero no fue correspondido.',
        '{declara} puso el corazón sobre la mesa ante {rechaza}. La respuesta fue no.',
        'Declaración de {declara} a {rechaza}: rechazada.',
    ];

    /** @var list<string> */
    private const CUERPOS_ROMANTICO = [
        'Algo importante ha pasado entre {par}.',
        'Entre {par} ha habido un momento romántico que marca.',
    ];

    /** @var list<string> */
    private const CUERPOS_APOYO = [
        '{par} se apoyaron cuando más lo necesitaban.',
        'Entre {par} hubo un gesto de apoyo que importa.',
    ];

    /** @var list<string> */
    private const CUERPOS_ENCUENTRO_MUY_MAL = [
        '{par} se encontraron y la cosa no pudo salir peor.',
        'El encuentro entre {par} fue un desastre. Así de claro.',
        'Todo lo que podía salir mal entre {par}, salió mal.',
    ];

    /** @var list<string> */
    private const CUERPOS_ENCUENTRO_MAL = [
        '{par} compartieron un rato que se torció más de lo esperado.',
        'El plan de {par} se torció. No fue para tanto, pero se torció.',
        '{par} no acabaron de entenderse en su último rato juntos.',
    ];

    /** @var list<string> */
    private const CUERPOS_ENCUENTRO_CALENTADO = [
        'Entre {par} surgió tensión. Del tipo que se comenta.',
        'Ambiente tenso entre {par}. Aquí hay tema.',
        'El aire entre {par} se cargó un poco.',
    ];

    public static function register(): void
    {
        EventBus::on(DomainEvents::ENCUENTRO_TERMINADO, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
            self::alEncuentroTerminado($partida, $envelope);
            return ['ok' => true];
        });
        EventBus::on(DomainEvents::DESCUBRIMIENTO_REGISTRADO, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
            self::alDescubrimiento($partida, $envelope);
            return ['ok' => true];
        });
    }

    public static function ensure(array &$partida): void
    {
        $partida['diario_hitos_registrados'] ??= [];
    }

    /**
     * Llamado desde RelacionBitacora::registrar con la entrada canónica del hito.
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
            return DiarioEngine::entradaPorEvento($partida, self::eventoIdDeClave($clave));
        }

        $built = self::construirEntradaHito($partida, $tipo, $actores, $hito);
        if ($built === null) {
            return null;
        }

        $entrada = self::escribir($partida, $built);
        if ($entrada !== null) {
            $partida['diario_hitos_registrados'][$clave] = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
            DomainEventDispatcher::emit($partida, DomainEvents::DIARIO_ENTRADA, [
                'entrada' => $entrada,
                'origen' => 'diario_hito',
                'hito_tipo' => $tipo,
            ]);
        }
        return $entrada;
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

        $peor = 'normal';
        foreach ($res['por_participante'] ?? [] as $row) {
            $r = (string) (is_array($row) ? ($row['resultado'] ?? '') : '');
            if ($r === 'muy_mal') {
                $peor = 'muy_mal';
                break;
            }
            if ($r === 'mal') {
                $peor = 'mal';
            }
        }
        $huboConflicto = (($res['conflicto'] ?? null) !== null) && (int) ($res['conflicto'] ?? 0) !== 0;
        if (!$huboConflicto && $peor !== 'mal' && $peor !== 'muy_mal') {
            return;
        }

        $eventoId = 'diario_hito:encuentro:' . $encId;
        if (DiarioEngine::entradaPorEvento($partida, $eventoId) !== null) {
            return;
        }

        $par = self::nombresPar($partida, $actores);
        $consecuencias = [];
        if ($peor === 'muy_mal') {
            $titulo = 'Un encuentro incómodo';
            $texto = self::cuerpo($partida, 'encuentro_muy_mal', $actores, self::CUERPOS_ENCUENTRO_MUY_MAL, [
                'par' => ucfirst($par),
            ]);
        } elseif ($peor === 'mal') {
            $titulo = 'Un encuentro torcido';
            $texto = self::cuerpo($partida, 'encuentro_mal', $actores, self::CUERPOS_ENCUENTRO_MAL, [
                'par' => ucfirst($par),
            ]);
        } else {
            $titulo = 'Las cosas se calentaron';
            $texto = self::cuerpo($partida, 'encuentro_calentado', $actores, self::CUERPOS_ENCUENTRO_CALENTADO, [
                'par' => $par,
            ]);
        }
        foreach ($actores as $id) {
            $emo = self::emocionAnotadaDelEncuentro($res, (string) $id);
            if ($emo !== '') {
                $nom = IdentidadPublica::nombre($partida, (string) $id);
                $consecuencias[] = $nom . ' terminó ' . ($emo === EstadoEmocional::TRISTE ? 'triste' : 'enfadad' . GeneroConcordancia::oa($partida, (string) $id)) . '.';
            }
        }

        $entrada = self::escribir($partida, [
            'tipo' => 'diario_hito',
            'subtipo' => 'encuentro',
            'titulo' => $titulo,
            'texto' => $texto,
            'consecuencias' => $consecuencias,
            'actores' => $actores,
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

    /**
     * @param array<string, mixed> $envelope
     */
    private static function alDescubrimiento(array &$partida, array $envelope): void
    {
        $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
        $envelope = array_merge($envelope, $payload);
        $residenteId = (string) ($envelope['residente_id'] ?? '');
        $campo = (string) ($envelope['campo'] ?? '');
        $origen = (string) ($envelope['origen'] ?? '');
        if ($residenteId === '' || $campo === '' || !isset($partida['residentes'][$residenteId])) {
            return;
        }
        if (!in_array($origen, ['interaccion_casual', 'encuentro', 'encuentro_intervencion'], true)) {
            return;
        }

        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $eventoId = 'diario_hito:descubrimiento:' . $residenteId . ':' . $campo . ':' . $dia;
        if (DiarioEngine::entradaPorEvento($partida, $eventoId) !== null) {
            return;
        }

        $nombre = IdentidadPublica::nombre($partida, $residenteId);
        $genero = (string) ($partida['residentes'][$residenteId]['identidad_publica']['genero'] ?? '');
        $valor = CopyDescubrimiento::idDeCampo($campo);
        $catalog = new Catalog(dirname(__DIR__, 2));
        $texto = CopyDescubrimiento::textoCotilleo(
            $nombre,
            $campo,
            $valor,
            $catalog->store(),
            $genero !== '' ? $genero : null
        );
        if ($texto === null || trim($texto) === '') {
            return;
        }

        self::escribir($partida, [
            'tipo' => 'diario_hito',
            'subtipo' => 'descubrimiento',
            'titulo' => 'Algo nuevo sobre ' . $nombre,
            'texto' => $texto,
            'actores' => [$residenteId],
            'origen' => [
                'evento_id' => $eventoId,
                'tipo_evento' => 'descubrimiento',
                'es_narrativo' => true,
                'hito_tipo' => 'descubrimiento',
                'informacion_revelada' => [
                    'residente_id' => $residenteId,
                    'campo' => $campo,
                ],
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ]);
    }

    /**
     * @param list<string> $actores
     * @param array<string, mixed> $hito
     * @return array<string, mixed>|null
     */
    private static function construirEntradaHito(array $partida, string $tipo, array $actores, array $hito): ?array
    {
        $par = self::nombresPar($partida, $actores);
        $consecuencias = [];
        $titulo = 'Un hito en el edificio';
        $texto = '';

        switch ($tipo) {
            case RelacionBitacora::SE_CONOCIERON:
                $titulo = 'Primer contacto';
                $texto = self::cuerpo($partida, 'se_conocieron', $actores, self::CUERPOS_SE_CONOCIERON, ['par' => $par]);
                break;
            case RelacionBitacora::PRIMERA_CITA:
                $titulo = 'Primera cita';
                $texto = self::cuerpo($partida, 'primera_cita', $actores, self::CUERPOS_PRIMERA_CITA, ['par' => $par]);
                break;
            case RelacionBitacora::REGALO:
                $titulo = 'Un detalle especial';
                $texto = self::cuerpo($partida, 'regalo', $actores, self::CUERPOS_REGALO, ['par' => $par]);
                break;
            case RelacionBitacora::RECHAZO_IMPORTANTE:
                $titulo = 'Un rechazo importante';
                $dir = self::direccionDeHito($hito, $actores);
                $texto = self::cuerpo($partida, 'rechazo_importante', $actores, self::CUERPOS_RECHAZO, [
                    'declara' => IdentidadPublica::nombre($partida, $dir['desde']),
                    'rechaza' => IdentidadPublica::nombre($partida, $dir['hacia']),
                ]);
                break;
            case RelacionBitacora::FLECHAZO:
                $titulo = 'Un flechazo';
                $texto = self::cuerpo($partida, 'flechazo', $actores, self::CUERPOS_FLECHAZO, ['par' => $par]);
                break;
            case RelacionBitacora::INICIO_PAREJA:
                $titulo = 'Nueva pareja';
                $texto = self::cuerpo($partida, 'inicio_pareja', $actores, self::CUERPOS_INICIO_PAREJA, ['par' => $par]);
                break;
            case RelacionBitacora::VUELTA:
                $titulo = 'Segunda oportunidad';
                $texto = self::cuerpo($partida, 'vuelta', $actores, self::CUERPOS_VUELTA, ['par' => $par]);
                break;
            case RelacionBitacora::RECONCILIACION:
                $titulo = 'Reconciliación';
                $texto = self::cuerpo($partida, 'reconciliacion', $actores, self::CUERPOS_RECONCILIACION, ['par' => $par]);
                break;
            case RelacionBitacora::RUPTURA:
                $titulo = 'Ruptura';
                $texto = self::cuerpo($partida, 'ruptura', $actores, self::CUERPOS_RUPTURA, ['par' => $par]);
                $consecuencias = ['Ya no son pareja.'];
                break;
            case RelacionBitacora::CRISIS:
                $titulo = 'Crisis de pareja';
                $texto = self::cuerpo($partida, 'crisis', $actores, self::CUERPOS_CRISIS, ['par' => $par]);
                break;
            case RelacionBitacora::DISCUSION_FUERTE:
                $titulo = 'Una discusión fuerte';
                $texto = self::cuerpo($partida, 'discusion_fuerte', $actores, self::CUERPOS_DISCUSION_FUERTE, ['par' => $par]);
                break;
            case RelacionBitacora::DECLARACION:
                $res = is_array($hito['resultado'] ?? null) ? (array) $hito['resultado'] : [];
                $aceptaA = (bool) ($res['acepta_a'] ?? true);
                $aceptaB = (bool) ($res['acepta_b'] ?? true);
                if ($aceptaA && $aceptaB) {
                    return null;
                }
                $titulo = 'Una declaración rechazada';
                $rechazaId = !$aceptaA ? (string) $actores[1] : (string) $actores[0];
                $declaraId = !$aceptaA ? (string) $actores[0] : (string) $actores[1];
                $texto = self::cuerpo($partida, 'declaracion_rechazada', $actores, self::CUERPOS_DECLARACION_RECHAZADA, [
                    'declara' => IdentidadPublica::nombre($partida, $declaraId),
                    'rechaza' => IdentidadPublica::nombre($partida, $rechazaId),
                ]);
                $consecuencias = [IdentidadPublica::nombre($partida, $declaraId) . ' recibió un no.'];
                break;
            case RelacionBitacora::HITO_ROMANTICO:
                $titulo = 'Un momento romántico';
                $texto = self::cuerpo($partida, 'hito_romantico', $actores, self::CUERPOS_ROMANTICO, ['par' => $par]);
                break;
            case RelacionBitacora::APOYO_IMPORTANTE:
                $titulo = 'Apoyo entre vecinos';
                $texto = self::cuerpo($partida, 'apoyo_importante', $actores, self::CUERPOS_APOYO, ['par' => $par]);
                break;
            default:
                return null;
        }

        if ($texto === '') {
            return null;
        }

        $clave = self::claveHito($tipo, $actores);
        return [
            'tipo' => 'diario_hito',
            'subtipo' => $tipo,
            'titulo' => $titulo,
            'texto' => $texto,
            'consecuencias' => $consecuencias,
            'actores' => $actores,
            'origen' => [
                'evento_id' => self::eventoIdDeClave($clave),
                'tipo_evento' => 'relacion_hito',
                'es_narrativo' => true,
                'hito_tipo' => $tipo,
                'bitacora_id' => (string) ($hito['id'] ?? ''),
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ];
    }

    /**
     * @param list<string> $actores
     */
    private static function claveHito(string $tipo, array $actores): string
    {
        $ids = $actores;
        sort($ids);
        return $tipo . ':' . implode('|', $ids);
    }

    private static function eventoIdDeClave(string $clave): string
    {
        return 'diario_hito:' . $clave;
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
