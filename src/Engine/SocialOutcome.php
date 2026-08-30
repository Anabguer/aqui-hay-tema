<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Resultado social atómico de un encuentro.
 *
 * Se produce UNA vez en EncuentroResolver::resolver y se almacena
 * en $encuentro['social_outcome']. Todos los consumidores (emociones,
 * diario, cotilleos) DEBEN leer este objeto en lugar de recalcular
 * independientemente.
 *
 * Este objeto NO reemplaza el array $resultado existente.
 * Es una capa adicional que garantiza coherencia narrativa.
 */
final class SocialOutcome
{
    /** @var string */
    public string $encuentro_id;

    /** @var int */
    public int $dia;

    /** @var int */
    public int $hora;

    /** @var list<string> */
    public array $participantes;

    /** @var string */
    public string $tipo;

    /** Resultado global del encuentro: muy_mal/mal/normal/bien/muy_bien */
    public string $resultado_global;

    /** @var array<string, array{resultado: string, texto: ?string, carga: ?float}> */
    public array $por_participante;

    /** Directional: ['a_hacia_b' => int, 'b_hacia_a' => int, 'calidad_a' => string, 'calidad_b' => string] */
    public array $delta_social;

    /** Directional: ['a_hacia_b' => int, 'b_hacia_a' => int] o vacío */
    public array $delta_romance;

    /** ?int conflicto intensity */
    public ?int $conflicto;

    /** @var list<array> descubrimientos generados */
    public array $descubrimientos;

    /** Narrativa del encuentro (EncuentroExperienciaNarrativa) */
    public ?array $narrativa;

    /** true si es hito de relación (primera_cita, declaracion, etc.) */
    public bool $es_hito;

    /** Tipo de hito si aplica (PRIMERA_CITA, DECLARACION, etc.) */
    public ?string $hito_tipo;

    /** true si el resultado es positivo y significativo (bien/muy_bien) */
    public bool $es_positivo_significativo;

    /** true si el resultado es negativo (mal/muy_mal o conflicto) */
    public bool $es_negativo;

    /** Causas emocionales por participante: ['per_id' => ['estado' => string, 'motivo' => string, 'hobby_match' => bool]] */
    public array $emociones_causales;

    /** Tags narrativos para consumidores */
    public array $tags;

    private function __construct(
        string $encuentro_id,
        int $dia,
        int $hora,
        array $participantes,
        string $tipo,
        string $resultado_global,
        array $por_participante,
        array $delta_social,
        array $delta_romance,
        ?int $conflicto,
        array $descubrimientos,
        ?array $narrativa,
        bool $es_hito,
        ?string $hito_tipo,
        bool $es_positivo_significativo,
        bool $es_negativo,
        array $emociones_causales,
        array $tags,
    ) {
        $this->encuentro_id = $encuentro_id;
        $this->dia = $dia;
        $this->hora = $hora;
        $this->participantes = $participantes;
        $this->tipo = $tipo;
        $this->resultado_global = $resultado_global;
        $this->por_participante = $por_participante;
        $this->delta_social = $delta_social;
        $this->delta_romance = $delta_romance;
        $this->conflicto = $conflicto;
        $this->descubrimientos = $descubrimientos;
        $this->narrativa = $narrativa;
        $this->es_hito = $es_hito;
        $this->hito_tipo = $hito_tipo;
        $this->es_positivo_significativo = $es_positivo_significativo;
        $this->es_negativo = $es_negativo;
        $this->emociones_causales = $emociones_causales;
        $this->tags = $tags;
    }

    /**
     * Fábrica principal: construye el SocialOutcome a partir del resultado
     * del encuentro ya resuelto.
     *
     * @param array $partida Estado de la partida
     * @param array $encuentro Encuentro terminado
     * @param array $resultado Resultado de EncuentroResolver::resolver
     */
    public static function desdeResultado(array $partida, array $encuentro, array $resultado): self
    {
        $encId = (string) ($encuentro['id'] ?? '');
        $dia = (int) ($encuentro['dia'] ?? ($partida['reloj']['dia_pueblo'] ?? 1));
        $hora = (int) ($encuentro['hora'] ?? ($partida['reloj']['hora_actual'] ?? 0));
        $participantes = array_values(array_filter(
            is_array($encuentro['participantes'] ?? []) ? $encuentro['participantes'] : [],
            static fn($i) => is_string($i) && $i !== ''
        ));
        $tipo = (string) ($encuentro['tipo'] ?? 'conocerse');

        // Resultado global = peor resultado entre participantes
        $resultadoGlobal = self::calcularResultadoGlobal($resultado);

        // Por participante
        $porParticipante = [];
        foreach ($resultado['por_participante'] ?? [] as $pid => $row) {
            $porParticipante[(string) $pid] = [
                'resultado' => (string) ($row['resultado'] ?? 'normal'),
                'texto' => $row['texto'] ?? null,
                'carga' => $row['carga'] ?? null,
            ];
        }

        // Deltas
        $deltaSocial = $resultado['delta_social'] ?? [];
        $deltaRomance = $resultado['delta_romance'] ?? [];
        $conflicto = isset($resultado['conflicto']) && $resultado['conflicto'] !== null && $resultado['conflicto'] !== false
            ? (int) $resultado['conflicto']
            : null;

        // Hitos de relación
        $tipoEnc = PropuestaNivel::aliasTipo($tipo);
        $esHito = $tipoEnc === PropuestaNivel::PRIMERA_CITA
            && !SenalRomantica::yaHuboPrimeraCita($partida, $participantes[0] ?? '', $participantes[1] ?? '');
        $hitoTipo = $esHito ? RelacionBitacora::PRIMERA_CITA : null;

        // Significativo = bien/muy_bien para al menos uno, sin conflicto
        $esPositivoSignificativo = self::esPositivoSignificativo($resultado, $conflicto);

        // Negativo = muy_mal/mal para alguno, o conflicto
        $esNegativo = self::esNegativo($resultado, $conflicto);

        // Narrativa
        $narrativa = $resultado['experiencia_narrativa'] ?? null;

        // Emociones causales (pre-calculadas, no dependen de EmotionalEventBridge)
        $emocionesCausales = self::calcularEmocionesCausales($partida, $encuentro, $resultado);

        // Tags para consumidores
        $tags = self::calcularTags($tipo, $resultadoGlobal, $esPositivoSignificativo, $esNegativo, $conflicto, $esHito, $emocionesCausales);

        return new self(
            $encId,
            $dia,
            $hora,
            $participantes,
            $tipo,
            $resultadoGlobal,
            $porParticipante,
            $deltaSocial,
            $deltaRomance,
            $conflicto,
            $resultado['descubrimientos'] ?? [],
            $narrativa,
            $esHito,
            $hitoTipo,
            $esPositivoSignificativo,
            $esNegativo,
            $emocionesCausales,
            $tags,
        );
    }

    /**
     * Serializa a array para persistencia en $encuentro['social_outcome'].
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'encuentro_id' => $this->encuentro_id,
            'dia' => $this->dia,
            'hora' => $this->hora,
            'participantes' => $this->participantes,
            'tipo' => $this->tipo,
            'resultado_global' => $this->resultado_global,
            'por_participante' => $this->por_participante,
            'delta_social' => $this->delta_social,
            'delta_romance' => $this->delta_romance,
            'conflicto' => $this->conflicto,
            'descubrimientos' => $this->descubrimientos,
            'narrativa' => $this->narrativa,
            'es_hito' => $this->es_hito,
            'hito_tipo' => $this->hito_tipo,
            'es_positivo_significativo' => $this->es_positivo_significativo,
            'es_negativo' => $this->es_negativo,
            'emociones_causales' => $this->emociones_causales,
            'tags' => $this->tags,
        ];
    }

    /**
     * Reconstruye un SocialOutcome desde un array persistido.
     */
    public static function fromArray(array $data): ?self
    {
        if (empty($data['encuentro_id'])) {
            return null;
        }
        return new self(
            (string) $data['encuentro_id'],
            (int) ($data['dia'] ?? 1),
            (int) ($data['hora'] ?? 0),
            is_array($data['participantes'] ?? null) ? $data['participantes'] : [],
            (string) ($data['tipo'] ?? 'conocerse'),
            (string) ($data['resultado_global'] ?? 'normal'),
            is_array($data['por_participante'] ?? null) ? $data['por_participante'] : [],
            is_array($data['delta_social'] ?? null) ? $data['delta_social'] : [],
            is_array($data['delta_romance'] ?? null) ? $data['delta_romance'] : [],
            isset($data['conflicto']) ? (int) $data['conflicto'] : null,
            is_array($data['descubrimientos'] ?? null) ? $data['descubrimientos'] : [],
            is_array($data['narrativa'] ?? null) ? $data['narrativa'] : null,
            (bool) ($data['es_hito'] ?? false),
            isset($data['hito_tipo']) ? (string) $data['hito_tipo'] : null,
            (bool) ($data['es_positivo_significativo'] ?? false),
            (bool) ($data['es_negativo'] ?? false),
            is_array($data['emociones_causales'] ?? null) ? $data['emociones_causales'] : [],
            is_array($data['tags'] ?? null) ? $data['tags'] : [],
        );
    }

    /**
     * Obtiene el SocialOutcome de un encuentro persistido.
     */
    public static function deEncuentro(array $encuentro): ?self
    {
        if (!is_array($encuentro['social_outcome'] ?? null)) {
            return null;
        }
        return self::fromArray($encuentro['social_outcome']);
    }

    /**
     * Resultado global = peor resultado entre todos los participantes.
     */
    private static function calcularResultadoGlobal(array $resultado): string
    {
        $orden = ['muy_mal' => 0, 'mal' => 1, 'normal' => 2, 'bien' => 3, 'muy_bien' => 4];
        $peor = 'normal';
        $peorRank = 2;
        foreach ($resultado['por_participante'] ?? [] as $row) {
            $r = (string) ($row['resultado'] ?? 'normal');
            $rank = $orden[$r] ?? 2;
            if ($rank < $peorRank) {
                $peorRank = $rank;
                $peor = $r;
            }
        }
        return $peor;
    }

    private static function esPositivoSignificativo(array $resultado, ?int $conflicto): bool
    {
        if ($conflicto !== null && $conflicto > 0) {
            return false;
        }
        foreach ($resultado['por_participante'] ?? [] as $row) {
            $r = (string) ($row['resultado'] ?? 'normal');
            if ($r === 'bien' || $r === 'muy_bien') {
                return true;
            }
        }
        return false;
    }

    private static function esNegativo(array $resultado, ?int $conflicto): bool
    {
        if ($conflicto !== null && $conflicto > 0) {
            return true;
        }
        foreach ($resultado['por_participante'] ?? [] as $row) {
            $r = (string) ($row['resultado'] ?? 'normal');
            if ($r === 'mal' || $r === 'muy_mal') {
                return true;
            }
        }
        return false;
    }

    /**
     * Pre-calcula las consecuencias emocionales basándose en el resultado canónico.
     * Esto reemplaza el cálculo independiente de EmotionalEventBridge para encuentros.
     *
     * @return array<string, array{estado: string, motivo: string, hobby_match: bool}>
     */
    private static function calcularEmocionesCausales(array $partida, array $encuentro, array $resultado): array
    {
        $emociones = [];
        $actores = is_array($encuentro['participantes'] ?? null) ? $encuentro['participantes'] : [];
        $lugarId = isset($encuentro['lugar']) ? (string) $encuentro['lugar'] : null;

        foreach ($actores as $rid) {
            $rid = (string) $rid;
            if ($rid === '' || !isset($partida['residentes'][$rid])) {
                continue;
            }
            EstadoEmocional::ensureResidente($partida['residentes'][$rid], $partida['reloj'] ?? null);
            $antes = $partida['residentes'][$rid]['runtime']['estado_emocional'];
            $estadoAntes = (string) ($antes['id'] ?? EstadoEmocional::NEUTRO);
            $resExp = (string) ($resultado['por_participante'][$rid]['resultado'] ?? 'normal');

            $catalog = new Catalog(dirname(__DIR__, 2));
            $afin = PlanAfinidad::paraParticipante($partida, $rid, $lugarId, $catalog);
            $hobbyMatch = !empty($afin['relacionado']);

            $eval = EmotionalRecovery::evaluar($estadoAntes, $resExp, $hobbyMatch);
            if ($eval === null) {
                continue;
            }

            $emociones[$rid] = [
                'estado' => (string) $eval['estado'],
                'motivo' => (string) $eval['motivo'],
                'hobby_match' => $hobbyMatch,
                'estado_antes' => $estadoAntes,
                'resultado_experiencia' => $resExp,
            ];
        }

        return $emociones;
    }

    /**
     * Calcula tags narrativos para que consumidores puedan filtrar rápidamente.
     */
    private static function calcularTags(
        string $tipo,
        string $resultadoGlobal,
        bool $esPositivoSignificativo,
        bool $esNegativo,
        ?int $conflicto,
        bool $esHito,
        array $emocionesCausales,
    ): array {
        $tags = [];
        $tags[] = 'tipo:' . $tipo;
        $tags[] = 'resultado:' . $resultadoGlobal;

        if ($esPositivoSignificativo) {
            $tags[] = 'positivo_significativo';
        }
        if ($esNegativo) {
            $tags[] = 'negativo';
        }
        if ($conflicto !== null && $conflicto > 0) {
            $tags[] = 'conflicto';
        }
        if ($esHito) {
            $tags[] = 'hito';
        }
        foreach ($emocionesCausales as $rid => $emo) {
            $tags[] = 'emocion:' . $rid . ':' . $emo['estado'];
        }

        return $tags;
    }
}
