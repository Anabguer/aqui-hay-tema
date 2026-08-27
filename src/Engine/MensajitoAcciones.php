<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Catálogo canónico de acciones interactivas en Mensajitos.
 * El frontend pinta botones desde acciones_ui; no hardcodea por tipo.
 */
final class MensajitoAcciones
{
    public const ACEPTAR_CANDIDATO = 'aceptar_candidato';
    public const RECHAZAR_CANDIDATO = 'rechazar_candidato';
    public const DEJAR_MARCHAR = 'dejar_marchar';
    public const PEDIR_QUEDARSE = 'pedir_quedarse';
    public const ELEGIR_PERSONA = 'elegir_persona';
    public const RESPONDER_CONSEJO = 'responder_consejo';
    public const RESPONDER_ESCUCHAR = 'responder_escuchar';
    public const RESPONDER_CELESTINE = 'responder_celestine';
    public const INVESTIGAR = 'investigar';
    public const ORGANIZAR_ALGO = 'organizar_algo';
    public const NO_METERSE = 'no_meterse';
    public const ACEPTAR_EVENTO = 'aceptar_evento';
    public const DECLINAR_EVENTO = 'declinar_evento';

    /** @var array<string, array{id: string, etiqueta: string, estilo: string, api: string}> */
    private const DEFS = [
        self::ACEPTAR_CANDIDATO => [
            'id' => self::ACEPTAR_CANDIDATO,
            'etiqueta' => 'Dejarle hueco',
            'estilo' => 'primario',
            'api' => 'llegada.aceptar',
        ],
        self::RECHAZAR_CANDIDATO => [
            'id' => self::RECHAZAR_CANDIDATO,
            'etiqueta' => 'Ahora no',
            'estilo' => 'suave',
            'api' => 'llegada.rechazar',
        ],
        self::DEJAR_MARCHAR => [
            'id' => self::DEJAR_MARCHAR,
            'etiqueta' => 'Dejar que se vaya',
            'estilo' => 'suave',
            'api' => 'marcha.dejar',
        ],
        self::PEDIR_QUEDARSE => [
            'id' => self::PEDIR_QUEDARSE,
            'etiqueta' => 'Pedirle que se quede',
            'estilo' => 'primario',
            'api' => 'marcha.quedarse',
        ],
        self::ELEGIR_PERSONA => [
            'id' => self::ELEGIR_PERSONA,
            'etiqueta' => 'Elegir persona',
            'estilo' => 'primario',
            'api' => 'buzon.resolver',
        ],
        self::INVESTIGAR => [
            'id' => self::INVESTIGAR,
            'etiqueta' => 'Ver ficha',
            'estilo' => 'primario',
            'api' => 'buzon.resolver',
        ],
        self::ORGANIZAR_ALGO => [
            'id' => self::ORGANIZAR_ALGO,
            'etiqueta' => 'Organizar',
            'estilo' => 'primario',
            'api' => 'buzon.resolver',
        ],
        self::NO_METERSE => [
            'id' => self::NO_METERSE,
            'etiqueta' => 'Dejarlo por ahora',
            'estilo' => 'suave',
            'api' => 'buzon.resolver',
        ],
        self::ACEPTAR_EVENTO => [
            'id' => self::ACEPTAR_EVENTO,
            'etiqueta' => 'Me apunto',
            'estilo' => 'primario',
            'api' => 'buzon.resolver',
        ],
        self::DECLINAR_EVENTO => [
            'id' => self::DECLINAR_EVENTO,
            'etiqueta' => 'Esta vez no',
            'estilo' => 'suave',
            'api' => 'buzon.resolver',
        ],
    ];

    /**
     * @return array<string, array{id: string, etiqueta: string, estilo: string, api: string}>
     */
    public static function catalogo(): array
    {
        return self::DEFS;
    }

    /**
     * @return array{id: string, etiqueta: string, estilo: string, api: string}|null
     */
    public static function def(string $accionId): ?array
    {
        return self::DEFS[$accionId] ?? null;
    }

    /**
     * @param list<string> $acciones
     * @return list<array{id: string, etiqueta: string, estilo: string, api: string}>
     */
    public static function vistaDe(array $acciones, bool $decisionPendiente): array
    {
        if (!$decisionPendiente) {
            return [];
        }
        $out = [];
        foreach ($acciones as $aid) {
            if (!is_string($aid) || $aid === '') {
                continue;
            }
            if (in_array($aid, [self::RESPONDER_CONSEJO, self::RESPONDER_ESCUCHAR, self::RESPONDER_CELESTINE], true)) {
                continue;
            }
            $def = self::def($aid);
            if ($def !== null) {
                $out[] = $def;
            }
        }
        return $out;
    }

    /**
     * Resuelve semánticamente una acción sobre un mensaje del buzón.
     * $payload lleva datos estructurados extra (p. ej. personaje_id en elegir_persona).
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function resolver(
        array &$partida,
        string $mensajeId,
        string $accionId,
        string $root,
        ?GameLogger $logger = null,
        array $payload = []
    ): array {
        $mensaje = BuzonEngine::buscar($partida, $mensajeId);
        if ($mensaje === null) {
            return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
        }
        $mensaje = BuzonEngine::normalizar($mensaje);
        if (!BuzonEngine::tieneDecisionPendiente($mensaje)) {
            return ['ok' => false, 'error' => 'sin_decision_pendiente'];
        }
        $acciones = is_array($mensaje['acciones'] ?? null) ? $mensaje['acciones'] : [];
        if (!in_array($accionId, $acciones, true)) {
            return ['ok' => false, 'error' => 'accion_no_permitida', 'accion' => $accionId];
        }

        switch ($accionId) {
            case self::ACEPTAR_CANDIDATO:
                $r = CandidatoLlegadaEngine::aceptar(
                    $partida,
                    $root,
                    $mensajeId,
                    $logger
                );
                break;
            case self::RECHAZAR_CANDIDATO:
                $r = CandidatoLlegadaEngine::rechazar(
                    $partida,
                    $root,
                    $mensajeId
                );
                break;
            case self::DEJAR_MARCHAR:
                $r = MarchaEngine::dejarIr(
                    $partida,
                    $root,
                    $mensajeId,
                    $logger
                );
                break;
            case self::PEDIR_QUEDARSE:
                $r = MarchaEngine::pedirQuedarse(
                    $partida,
                    $root,
                    $mensajeId,
                    $logger
                );
                break;
            case self::ELEGIR_PERSONA:
                $r = PeticionPuebloEngine::elegirCandidato(
                    $partida,
                    $mensajeId,
                    (string) ($payload['personaje_id'] ?? ''),
                    $payload,
                    null,
                    $logger
                );
                break;
            case self::RESPONDER_CONSEJO:
            case self::RESPONDER_ESCUCHAR:
            case self::RESPONDER_CELESTINE:
                $r = MensajitoConsejoEngine::responderOpcion(
                    $partida,
                    $mensajeId,
                    (string) ($payload['opcion_id'] ?? ''),
                    $payload
                );
                break;
            case self::INVESTIGAR:
                $r = MensajitoConsejoEngine::investigar($partida, $mensajeId);
                break;
            case self::ORGANIZAR_ALGO:
                $r = MensajitoConsejoEngine::organizarAlgo($partida, $mensajeId);
                break;
            case self::NO_METERSE:
                $r = MensajitoConsejoEngine::noMeterse($partida, $mensajeId);
                break;
            case self::ACEPTAR_EVENTO:
                $r = MensajitoColectivoEngine::aceptar($partida, $mensajeId, $root, $logger);
                break;
            case self::DECLINAR_EVENTO:
                $r = MensajitoColectivoEngine::declinar($partida, $mensajeId);
                break;
            default:
                $r = ['ok' => false, 'error' => 'accion_sin_resolver', 'accion' => $accionId];
                break;
        }

        if ($r['ok'] ?? false) {
            BuzonEngine::resolverDecision($partida, $mensajeId);
            $r['no_leidos'] = BuzonEngine::contarNoLeidos($partida);
        }
        return $r;
    }
}
