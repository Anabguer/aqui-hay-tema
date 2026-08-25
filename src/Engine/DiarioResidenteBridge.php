<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Alimenta partida['diario'] con acontecimientos claramente significativos.
 * Solo lectura de sistemas existentes; escritura idempotente vía DiarioEngine.
 * No registra rutina ni duplica Cotilleo/Buzón.
 */
final class DiarioResidenteBridge
{
    /** Bancos de cuerpo por familia narrativa. Título canónico estable; varía solo el cuerpo. */

    /** @var list<string> */
    private const CUERPOS_FLECHAZO = [
        '{par} se han lanzado miradas imposibles de disimular.',
        '{par} han cruzado una mirada que ya no podían ocultar.',
        '{par} se han quedado mirando un poco más de lo normal.',
        'Un cruce de miradas entre {par} lo ha contado todo.',
        '{par} han chispeado a primera vista.',
        'Había chispa en el aire con {par}. Y no era imaginación.',
    ];

    /** @var list<string> */
    private const CUERPOS_INICIO_PAREJA = [
        '{par} han empezado algo más que una amistad.',
        'Oficialmente {par} ya son pareja.',
        '{par} han dado el paso: ahora son pareja.',
        'Lo de {par} por fin tiene nombre: son pareja.',
        'Entre {par} hay algo oficial. Menos secreto y más planes.',
    ];

    /** @var list<string> */
    private const CUERPOS_VUELTA = [
        '{par} se han dado otra oportunidad.',
        '{par} vuelven a intentarlo.',
        'Segunda ronda para {par}: han vuelto a juntarse.',
        '{par} han decidido dejarlo atrás y volver a empezar.',
    ];

    /** @var list<string> */
    private const CUERPOS_RECONCILIACION = [
        '{par} han dejado las diferencias a un lado.',
        '{par} se han hablado y han arreglado sus cosas.',
        'Paz entre {par}: lo que pasó, quedó arreglado.',
        '{par} han cerrado el conflicto por la buena.',
    ];

    /** @var list<string> */
    private const CUERPOS_RUPTURA = [
        '{par} lo han dejado.',
        '{par} han roto su relación.',
        'Se acaba lo de {par}: han roto.',
        '{par} han decidido seguir caminos separados.',
        'Hay ruptura entre {par}. Cada uno por su lado.',
    ];

    /** @var list<string> */
    private const CUERPOS_CRISIS = [
        'La relación entre {par} atraviesa momentos difíciles.',
        'Lo de {par} pasa por un bache complicado.',
        '{par} atraviesan un bache y se nota.',
        'La pareja que forman {par} anda tensionada.',
    ];

    /** @var list<string> */
    private const CUERPOS_DISCUSION_FUERTE = [
        '{par} tuvieron una discusión que se notó.',
        '{par} han tenido un enfado serio.',
        'Ha habido un buen enfrentamiento entre {par}.',
        '{par} cruzaron palabras duras.',
    ];

    /** @var list<string> */
    private const CUERPOS_DECLARACION_RECHAZADA = [
        '{declara} se declaró a {rechaza}, pero no fue correspondido.',
        '{declara} puso el corazón sobre la mesa ante {rechaza}. La respuesta fue no.',
        '{declara} se jugó el todo por el todo con {rechaza} y recibió un no.',
        'Declaración de {declara} a {rechaza}: rechazada.',
    ];

    /** @var list<string> */
    private const CUERPOS_LLEGADA = [
        '{nombre} se mudó al edificio. El pueblo ya no vuelve a ser igual.',
        'Nueva cara en el edificio: {nombre} ya vive aquí.',
        'El edificio estrena residente: {nombre} se ha mudado.',
        '{nombre} forma parte del edificio desde hoy.',
    ];

    /** @var list<string> */
    private const CUERPOS_SIN_TRABAJO = [
        '{nombre} ha perdido el trabajo. La moral por los suelos.',
        '{nombre} se queda sin empleo y con la moral baja.',
        'Despido para {nombre}. Hoy toca asimilarlo.',
    ];

    /** @var list<string> */
    private const CUERPOS_TRABAJO_NUEVO = [
        '{nombre} ha encontrado trabajo. Hoy se le ve por las nubes.',
        '{nombre} estrena empleo. La cara lo dice todo.',
        '{nombre} ha encontrado empleo. Le ha venido genial para el ánimo.',
    ];

    /** @var list<string> */
    private const CUERPOS_RECHAZO_REPETIDO = [
        '{hacia} le ha dicho que no demasiadas veces. A la larga, pesa.',
        'Los «no» repetidos de {hacia} le pasan factura a {rid}.',
        'Otro «no» de {hacia}: la suma ya pesa.',
        '{hacia} y sus continuos «no» han dejado huella en {rid}.',
        'Tantos planes rechazados por {hacia} acaban doliendo.',
    ];

    /** @var list<string> */
    private const CUERPOS_ENCUENTRO_MUY_MAL = [
        '{par} se encontraron y la cosa no pudo salir peor. Aquello quedó a fuego lento en la memoria.',
        'El encuentro entre {par} fue un desastre. Así de claro.',
        '{par} compartieron un rato nefasto. Mejor no rememorarlo.',
        'Todo lo que podía salir mal entre {par}, salió mal.',
    ];

    /** @var list<string> */
    private const CUERPOS_ENCUENTRO_MAL = [
        '{par} compartieron un rato que se torció más de lo esperado.',
        'El plan de {par} se torció. No fue para tanto, pero se torció.',
        '{par} no acabaron de entenderse en su último rato juntos.',
        'Hubo roces entre {par}. El rato quedó raro.',
    ];

    /** @var list<string> */
    private const CUERPOS_ENCUENTRO_CALENTADO = [
        'Entre {par} surgió tensión de la buena… o de la mala. Del tipo que se comenta.',
        'Entre {par} hubo chispas. Del tipo que dan tema de conversación.',
        'Ambiente tenso entre {par}. Aquí hay tema.',
        'El aire entre {par} se cargó un poco.',
    ];

    /** @return list<string> */
    public static function eventos(): array
    {
        return [
            DomainEvents::RESIDENTE_INCORPORADO,
            DomainEvents::ENCUENTRO_TERMINADO,
            DomainEvents::ESTADO_EMOCIONAL_CAMBIADO,
        ];
    }

    public static function register(): void
    {
        foreach (self::eventos() as $evento) {
            EventBus::on($evento, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
                return self::handle($partida, $envelope, $logger);
            });
        }
    }

    /**
     * Hitos relacionales (llamado desde RelacionBitacora::registrar).
     *
     * @param array<string, mixed> $hito
     */
    public static function alHito(array &$partida, array $hito): ?array
    {
        $tipo = (string) ($hito['tipo'] ?? '');
        $actores = is_array($hito['participantes'] ?? null)
            ? array_values(array_filter((array) $hito['participantes'], static fn($i) => is_string($i) && $i !== ''))
            : [];
        if ($tipo === '' || count($actores) < 2) {
            return null;
        }

        switch ($tipo) {
            case RelacionBitacora::FLECHAZO:
                $titulo = 'Un flechazo';
                $texto = self::cuerpo($partida, 'flechazo', $actores, self::CUERPOS_FLECHAZO, [
                    'par' => self::nombresPar($partida, $actores),
                ]);
                break;
            case RelacionBitacora::INICIO_PAREJA:
                $titulo = 'Nueva pareja';
                $texto = self::cuerpo($partida, 'inicio_pareja', $actores, self::CUERPOS_INICIO_PAREJA, [
                    'par' => self::nombresPar($partida, $actores),
                ]);
                break;
            case RelacionBitacora::VUELTA:
                $titulo = 'Segunda oportunidad';
                $texto = self::cuerpo($partida, 'vuelta', $actores, self::CUERPOS_VUELTA, [
                    'par' => self::nombresPar($partida, $actores),
                ]);
                break;
            case RelacionBitacora::RECONCILIACION:
                $titulo = 'Reconciliación';
                $texto = self::cuerpo($partida, 'reconciliacion', $actores, self::CUERPOS_RECONCILIACION, [
                    'par' => self::nombresPar($partida, $actores),
                ]);
                break;
            case RelacionBitacora::RUPTURA:
                $titulo = 'Ruptura';
                $texto = self::cuerpo($partida, 'ruptura', $actores, self::CUERPOS_RUPTURA, [
                    'par' => self::nombresPar($partida, $actores),
                ]);
                $consecuencias = ['Ya no son pareja.'];
                break;
            case RelacionBitacora::CRISIS:
                $titulo = 'Crisis de pareja';
                $texto = self::cuerpo($partida, 'crisis', $actores, self::CUERPOS_CRISIS, [
                    'par' => self::nombresPar($partida, $actores),
                ]);
                break;
            case RelacionBitacora::DISCUSION_FUERTE:
                $titulo = 'Una discusión fuerte';
                $texto = self::cuerpo($partida, 'discusion_fuerte', $actores, self::CUERPOS_DISCUSION_FUERTE, [
                    'par' => self::nombresPar($partida, $actores),
                ]);
                break;
            case RelacionBitacora::DECLARACION:
                $res = is_array($hito['resultado'] ?? null) ? (array) $hito['resultado'] : [];
                $aceptaA = (bool) ($res['acepta_a'] ?? true);
                $aceptaB = (bool) ($res['acepta_b'] ?? true);
                if ($aceptaA && $aceptaB) {
                    return null; // declarar y aceptarse pasa por INICIO_PAREJA/VUELTA
                }
                $titulo = 'Una declaración rechazada';
                // R2 fix: convención de formar() = participantes [a, b] con flags
                // acepta_a/acepta_b alineados. Si acepta_b=false, B rechaza a A
                // (A declara). El mapeo anterior invertía declara/rechaza.
                $rechazaId = !$aceptaA ? (string) $actores[0] : (string) $actores[1];
                $declaraId = !$aceptaA ? (string) $actores[1] : (string) $actores[0];
                $texto = self::cuerpo($partida, 'declaracion_rechazada', $actores, self::CUERPOS_DECLARACION_RECHAZADA, [
                    'declara' => IdentidadPublica::nombre($partida, $declaraId),
                    'rechaza' => IdentidadPublica::nombre($partida, $rechazaId),
                ]);
                $consecuencias = [IdentidadPublica::nombre($partida, $declaraId) . ' recibió un no.'];
                break;
            default:
                return null;
        }

        return self::escribir($partida, [
            'tipo' => 'hito_relacion',
            'titulo' => $titulo,
            'texto' => $texto,
            'consecuencias' => $consecuencias ?? [],
            'actores' => $actores,
            'origen' => [
                'evento_id' => 'hito:' . (string) ($hito['id'] ?? ''),
                'tipo_evento' => 'relacion_hito',
                'es_narrativo' => true,
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ]);
    }

    private static function handle(array &$partida, array $envelope, ?GameLogger $logger): array
    {
        $evento = (string) ($envelope['evento'] ?? '');
        $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];

        if ($evento === DomainEvents::RESIDENTE_INCORPORADO) {
            self::alLlegada($partida, $payload);
            return ['ok' => true];
        }
        if ($evento === DomainEvents::ENCUENTRO_TERMINADO) {
            self::alEncuentroTerminado($partida, $payload);
            return ['ok' => true];
        }
        if ($evento === DomainEvents::ESTADO_EMOCIONAL_CAMBIADO) {
            self::alEmocionalCambiado($partida, $payload);
            return ['ok' => true];
        }
        return ['ok' => true];
    }

    private static function alLlegada(array &$partida, array $payload): void
    {
        $rid = (string) ($payload['residente_id'] ?? '');
        if ($rid === '' || !isset($partida['residentes'][$rid]) || !empty($payload['placeholder'])) {
            return;
        }
        $nombre = IdentidadPublica::nombre($partida, $rid);
        if ($nombre === '') {
            return;
        }
        self::escribir($partida, [
            'tipo' => 'llegada',
            'titulo' => 'Llegada al edificio',
            'texto' => self::cuerpo($partida, 'llegada', [$rid], self::CUERPOS_LLEGADA, [
                'nombre' => $nombre,
            ]),
            'consecuencias' => [],
            'actores' => [$rid],
            'origen' => [
                'evento_id' => 'llegada:' . $rid,
                'tipo_evento' => 'residente_incorporado',
                'es_narrativo' => true,
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function alEncuentroTerminado(array &$partida, array $payload): void
    {
        $enc = is_array($payload['encuentro'] ?? null) ? $payload['encuentro'] : [];
        $res = is_array($payload['resultado'] ?? null) ? $payload['resultado'] : [];
        $encId = (string) ($enc['id'] ?? '');
        $actores = array_values(array_filter(
            is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [],
            static fn($i) => is_string($i) && $i !== ''
        ));
        if ($encId === '' || count($actores) < 2) {
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
            return; // encuentro sin consecuencia relevante: fuera del diario
        }

        $nombres = [];
        foreach ($actores as $id) {
            $nombres[] = IdentidadPublica::nombre($partida, (string) $id);
        }
        $par = implode(' y ', array_values(array_filter($nombres, static fn($n) => $n !== '')));

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
                $consecuencias[] = $nom . ' terminó ' . ($emo === EstadoEmocional::TRISTE ? 'triste' : 'enfadad' . self::oA($partida, (string) $id)) . '.';
            }
        }

        self::escribir($partida, [
            'tipo' => 'encuentro',
            'titulo' => $titulo,
            'texto' => $texto,
            'consecuencias' => $consecuencias,
            'actores' => $actores,
            'origen' => [
                'evento_id' => 'encuentro:' . $encId,
                'tipo_evento' => 'encuentro_terminado',
                'es_narrativo' => true,
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function alEmocionalCambiado(array &$partida, array $payload): void
    {
        $despues = is_array($payload['despues'] ?? null) ? $payload['despues'] : [];
        $antes = is_array($payload['antes'] ?? null) ? $payload['antes'] : [];
        $rid = (string) ($payload['residente_id'] ?? '');
        if ($rid === '' || !isset($partida['residentes'][$rid])) {
            return;
        }
        $origen = (string) ($despues['origen'] ?? '');
        $ctx = is_array($despues['contexto'] ?? null) ? $despues['contexto'] : [];

        // Expiración / vuelta a neutro: no es acontecimiento.
        if ((string) ($despues['id'] ?? '') === EstadoEmocional::NEUTRO) {
            return;
        }

        if ($origen === 'perder_trabajo') {
            $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
            self::escribir($partida, [
                'tipo' => 'trabajo',
                'titulo' => 'Sin trabajo',
                'texto' => self::cuerpo($partida, 'sin_trabajo', [$rid], self::CUERPOS_SIN_TRABAJO, [
                    'nombre' => IdentidadPublica::nombre($partida, $rid),
                ]),
                'consecuencias' => ['Se queda sin horario y sin sueldo.'],
                'actores' => [$rid],
                'origen' => [
                    'evento_id' => 'trabajo_perder:' . $rid . ':' . $dia,
                    'tipo_evento' => 'estado_emocional_cambiado',
                    'es_narrativo' => true,
                    '_placeholder' => false,
                ],
                '_placeholder_contenido' => false,
            ]);
            return;
        }

        if ($origen === 'encontrar_trabajo') {
            $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
            self::escribir($partida, [
                'tipo' => 'trabajo',
                'titulo' => 'Trabajo nuevo',
                'texto' => self::cuerpo($partida, 'trabajo_nuevo', [$rid], self::CUERPOS_TRABAJO_NUEVO, [
                    'nombre' => IdentidadPublica::nombre($partida, $rid),
                ]),
                'consecuencias' => [],
                'actores' => [$rid],
                'origen' => [
                    'evento_id' => 'trabajo_encontrar:' . $rid . ':' . $dia,
                    'tipo_evento' => 'estado_emocional_cambiado',
                    'es_narrativo' => true,
                    '_placeholder' => false,
                ],
                '_placeholder_contenido' => false,
            ]);
            return;
        }

        if ($origen === 'rechazo_repetido') {
            $hacia = (string) ($ctx['hacia'] ?? '');
            $nombreOtro = $hacia !== '' ? IdentidadPublica::nombre($partida, $hacia) : '';
            if (!in_array($hacia, [$rid], true) && $nombreOtro !== '' && $nombreOtro !== null) {
                $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
                self::escribir($partida, [
                    'tipo' => 'rechazo',
                    'titulo' => 'Demasiados planes rechazados',
                    'texto' => self::cuerpo($partida, 'rechazo_repetido', [$rid], self::CUERPOS_RECHAZO_REPETIDO, [
                        'hacia' => IdentidadPublica::nombre($partida, $hacia),
                        'rid' => IdentidadPublica::nombre($partida, $rid),
                    ]),
                    'consecuencias' => [IdentidadPublica::nombre($partida, $rid) . ' está tocado por los continuos «no».'],
                    'actores' => [$rid],
                    'origen' => [
                        'evento_id' => 'rechazo_repetido:' . $rid . ':' . $hacia . ':' . $dia,
                        'tipo_evento' => 'estado_emocional_cambiado',
                        'es_narrativo' => true,
                        '_placeholder' => false,
                    ],
                    '_placeholder_contenido' => false,
                ]);
            }
        }
    }

    /**
     * Escritura centralizada e idempotente. Devuelve la entrada (existente o nueva).
     *
     * @param array<string, mixed> $entrada
     */
    private static function escribir(array &$partida, array $entrada): ?array
    {
        $entrada['visible_en_cotilleo'] = false;
        $r = DiarioEngine::crear($partida, $entrada);
        return ($r['ok'] ?? false) ? ($r['entrada'] ?? null) : null;
    }

    /** @param array<string, mixed> $res */
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
     * Cuerpo variado y determinista para una entrada de diario. El título
     * queda estable (es el ancla semántica); varía solo la redacción.
     *
     * @param list<string> $actores
     * @param list<string> $pool
     * @param array<string, string> $vars
     */
    private static function cuerpo(array &$partida, string $claveTipo, array $actores, array $pool, array $vars): string
    {
        $ids = array_values(array_filter($actores, static fn($i) => is_string($i) && $i !== ''));
        sort($ids);
        $clave = 'diario:' . $claveTipo . ':' . implode('|', $ids);
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
            return 'Dos vecinos';
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
