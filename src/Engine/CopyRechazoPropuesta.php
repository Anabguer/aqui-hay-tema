<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Copy de rechazo alineado con la causa real (disponibilidad vs voluntad).
 * Variantes por familia; sin fórmulas ni datos ocultos en UI.
 */
final class CopyRechazoPropuesta
{
    public const TIPO_NO_PUEDO = 'no_puedo';
    public const TIPO_NO_QUIERO = 'no_quiero';

    private const COOLDOWN_GENERICO = 'Necesitan un poco de espacio.';

    /** @var array<string, list<string>> */
    private const VARIANTES = [
        'sueno' => [
            'A esa hora ya estoy durmiendo.',
            'Esa hora me pillas en la cama.',
            'A esa hora ya descanso.',
        ],
        'trabajo' => [
            'Tengo que trabajar.',
            'Ese día trabajo y no me da la vida.',
            'Ese día tengo jornada y no puedo.',
        ],
        'otro_compromiso' => [
            'Ya tengo otro plan.',
            'Ese día tengo un compromiso.',
            'Esa hora ya la tengo ocupada con otra cosa.',
        ],
        'encuentro_programado' => [
            'Ya tengo algo programado a esa hora.',
            'A esa hora ya quedé con alguien.',
            'Esa franja ya la tengo reservada.',
        ],
        'lugar_cerrado' => [
            'A esa hora el sitio ya está cerrado.',
            'Ese local cierra antes de esa hora.',
            'A esa hora el lugar no abre.',
        ],
        'ocupado' => [
            'A esa hora no puedo.',
            'Esa hora no me viene bien.',
            'No puedo a esa hora.',
        ],
        'emocional' => [
            'Hoy no me encuentro con ánimo.',
            'No estoy con la cabeza para quedar.',
            'Hoy no me apetece socializar.',
        ],
        'relacional' => [
            'Prefiero no quedar ahora.',
            'Ahora mismo no me apetece.',
            'Hoy no me entra quedar.',
        ],
        'banal' => [
            'Hoy no me da la vida.',
            'Hoy no me apetece.',
            'Hoy paso.',
        ],
        'cooldown' => [
            'Todavía no quiero hablar de eso.',
            'Déjame un poco de margen, ¿vale?',
            'Ahora mismo no quiero plantearlo.',
        ],
    ];

  public static function tipoDeClase(?string $clase): string
    {
        if ($clase === PropuestaEncuentro::CLASE_INDISPONIBILIDAD) {
            return self::TIPO_NO_PUEDO;
        }
        return self::TIPO_NO_QUIERO;
    }

    /**
     * @param array<string, mixed> $reac
     */
    public static function familiaDeReaccion(array $reac, ?string $lugarId = null, ?int $hora = null): string
    {
        $clase = (string) ($reac['clase'] ?? '');
        if ($clase === PropuestaEncuentro::CLASE_COOLDOWN) {
            return 'cooldown';
        }
        if ($clase === PropuestaEncuentro::CLASE_INDISPONIBILIDAD) {
            return self::familiaIndisponibilidad($reac, $lugarId, $hora);
        }
        $motivo = (string) ($reac['motivo_tipo'] ?? '');
        if ($motivo !== '' && isset(self::VARIANTES[$motivo])) {
            return $motivo;
        }
        $tec = (string) ($reac['motivo_tecnico'] ?? '');
        if (str_contains($tec, 'emocional')) {
            return 'emocional';
        }
        if (str_contains($tec, 'relacional')) {
            return 'relacional';
        }
        if (str_contains($tec, 'cooldown')) {
            return 'cooldown';
        }
        return 'banal';
    }

    /**
     * @param array<string, mixed> $reac
     */
    private static function familiaIndisponibilidad(array $reac, ?string $lugarId, ?int $hora): string
    {
        $tec = (string) ($reac['motivo_tecnico'] ?? '');
        if ($tec === 'doble_reserva') {
            return 'encuentro_programado';
        }
        if ($lugarId !== null && $lugarId !== '' && $hora !== null && !ComplejoCatalog::estaAbierto($lugarId, $hora)) {
            return 'lugar_cerrado';
        }
        $detalle = is_array($reac['detalle'] ?? null) ? $reac['detalle'] : [];
        if ($detalle === [] && is_array($reac['factores']['detalle_agenda'] ?? null)) {
            $detalle = $reac['factores']['detalle_agenda'];
        }
        $tipo = (string) ($detalle['tipo'] ?? '');
        switch ($tipo) {
            case 'sueno':
                return 'sueno';
            case 'trabajo':
            case 'trabajo_blando':
            case 'trabajo_generico':
            case 'estudio':
                return 'trabajo';
            case 'compromiso':
                return 'otro_compromiso';
            case 'encuentro':
                return 'encuentro_programado';
            default:
                break;
        }
        $motivoAgenda = (string) ($reac['factores']['motivo_agenda'] ?? $tec);
        if ($motivoAgenda === 'trabaja_manana' || (string) ($detalle['motivo'] ?? '') === 'trabaja_manana') {
            return 'trabajo';
        }
        if ($motivoAgenda === 'doble_reserva') {
            return 'encuentro_programado';
        }
        return 'ocupado';
    }

    /**
     * @param array<string, mixed> $reac
     */
    public static function linea(array $partida, array $reac, string $seed = ''): string
    {
        $lugar = null;
        $hora = null;
        if (is_array($reac['propuesta_ctx'] ?? null)) {
            $lugar = $reac['propuesta_ctx']['lugar'] ?? null;
            $hora = isset($reac['propuesta_ctx']['hora']) ? (int) $reac['propuesta_ctx']['hora'] : null;
        }
        $familia = self::familiaDeReaccion($reac, is_string($lugar) ? $lugar : null, $hora);
        $pool = self::VARIANTES[$familia] ?? self::VARIANTES['ocupado'];
        $rid = (string) ($reac['residente_id'] ?? '');
        $key = $seed !== '' ? $seed : ($rid . ':' . $familia . ':' . ($partida['rng']['cursor'] ?? 0));
        $tpl = $pool[abs(crc32($key)) % count($pool)];
        return rtrim($tpl, '.');
    }

    /**
     * @param array<string, mixed> $propuesta
     * @param array<string, mixed>|null $contrapropuesta
     */
    public static function mensajeRechazo(array $partida, array $propuesta, ?array $contrapropuesta = null): string
    {
        $hablante = self::hablanteRechazo($partida, $propuesta);
        $nombre = (string) ($hablante['nombre'] ?? 'Alguien');
        $reac = is_array($hablante['reaccion'] ?? null) ? $hablante['reaccion'] : [];
        $reac['propuesta_ctx'] = [
            'lugar' => $propuesta['lugar'] ?? null,
            'hora' => $propuesta['hora'] ?? null,
        ];
        $seed = (string) ($propuesta['id'] ?? '') . ':' . ($reac['residente_id'] ?? '');
        $frase = self::fraseRechazoDeReaccion($partida, $reac, $seed);
        $msg = $nombre . ' ha rechazado la propuesta: "' . $frase . '."';
        if ($contrapropuesta !== null && !empty($contrapropuesta['texto'])) {
            $msg .= ' ' . (string) $contrapropuesta['texto'];
        }
        return $msg;
    }

    /**
     * Copy de Mensajito cuando un participante rechaza pero el otro habría aceptado.
     *
     * @param array<string, mixed> $hablante Reacción canónica del rechazador
     */
    public static function fraseCausaHumana(array $partida, array $hablante, string $otroId): string
    {
        $rid = (string) ($hablante['residente_id'] ?? '');
        $nombre = $rid !== ''
            ? IdentidadPublica::nombre($partida, $rid)
            : (string) ($hablante['nombre'] ?? '');
        if ($nombre === '') {
            return '';
        }
        $copyId = (string) ($hablante['copy_id'] ?? '');
        if ($copyId !== '' && isset(CopyVoluntad::TEXTOS[$copyId])) {
            $frase = rtrim(trim(CopyVoluntad::texto($copyId)), '.');
            $ctx = self::historialContexto($partida, $rid, $otroId);
            if ($ctx !== '') {
                return trim($nombre . ' ' . $frase . '. ' . $ctx . '.');
            }
            return trim($nombre . ' ' . $frase . '.');
        }
        $reac = $hablante;
        $reac['propuesta_ctx'] = is_array($hablante['propuesta_ctx'] ?? null) ? $hablante['propuesta_ctx'] : [];
        $frase = self::fraseRechazoDeReaccion($partida, $reac, $rid . ':buzon');
        $ctx = self::historialContexto($partida, $rid, $otroId);
        if ($ctx !== '') {
            return trim($nombre . ' ' . $frase . '. ' . $ctx . '.');
        }
        return trim($nombre . ' ' . $frase . '.');
    }

    /**
     * Contexto breve de la relación entre el rechazador y el proponente,
     * para enriquecer copy de rechazo cuando hay historia relevante.
     * NO inventa nada: solo describe lo que realmente ocurrió.
     *
     * @return string Frase en español sin puntuación final, o '' si no hay contexto.
     */
    public static function historialContexto(array $partida, string $rechazadorId, string $proponenteId): string
    {
        if ($rechazadorId === '' || $proponenteId === '' || $rechazadorId === $proponenteId) {
            return '';
        }
        $res = HistorialPar::resumen($partida, $rechazadorId, $proponenteId);

        if (!$res['se_conocen']) {
            return '';
        }
        if ($res['es_pareja']) {
            return 'A pesar de que son pareja';
        }
        if ($res['ha_habido_cita']) {
            return 'Aunque hemos salido juntos';
        }
        if ($res['total_encuentros'] > 2) {
            return 'Aunque nos hemos visto varias veces';
        }
        if ($res['total_encuentros'] >= 1) {
            return 'Aunque nos hemos visto';
        }
        return '';
    }

    /**
     * @param array<string, mixed> $propuesta
     */
    public static function lineaOtroDispuesto(array $partida, array $propuesta, string $seedPrefix = ''): string
    {
        unset($seedPrefix);
        $canon = PropuestaEncuentroEngine::rechazoCanonico($propuesta);
        $otro = $canon['habria_aceptado'];
        if (!is_array($otro)) {
            return '';
        }
        $otroId = (string) ($otro['residente_id'] ?? '');
        if ($otroId === '') {
            return '';
        }
        $nombre = IdentidadPublica::nombre($partida, $otroId);
        if ($nombre === '') {
            return '';
        }
        return 'Pero a ' . $nombre . ' le habría venido bien.';
    }

    /**
     * @param array<string, mixed> $reac
     */
    private static function fraseRechazoDeReaccion(array $partida, array $reac, string $seed): string
    {
        $copyId = (string) ($reac['copy_id'] ?? '');
        if ($copyId !== '' && isset(CopyVoluntad::TEXTOS[$copyId])) {
            return rtrim(CopyVoluntad::texto($copyId), '.');
        }
        return self::linea($partida, $reac, $seed);
    }

    /**
     * @param array<string, mixed> $propuesta
     * @return array{nombre: string, reaccion: array<string, mixed>|null}
     */
    private static function hablanteRechazo(array $partida, array $propuesta): array
    {
        $hablante = PropuestaEncuentroEngine::rechazoCanonico($propuesta)['hablante'];
        if (!is_array($hablante)) {
            return ['nombre' => '', 'reaccion' => null];
        }
        $rid = (string) ($hablante['residente_id'] ?? '');
        return [
            'nombre' => $rid !== '' ? IdentidadPublica::nombre($partida, $rid) : (string) ($hablante['nombre'] ?? ''),
            'reaccion' => $hablante,
        ];
    }

    /**
     * @param array<string, mixed> $slot
     */
    public static function lineaContrapropuesta(array $partida, array $slot, int $diaPedido, int $horaPedida): string
    {
        $reloj = $partida['reloj'] ?? [];
        $dia = (int) ($slot['dia'] ?? 0);
        $hora = (int) ($slot['hora'] ?? 0);
        $hh = str_pad((string) $hora, 2, '0', STR_PAD_LEFT);
        $diaActual = (int) ($reloj['dia_pueblo'] ?? 1);

        if ($dia === $diaPedido && $hora > $horaPedida) {
            return '¿Te va a las ' . $hh . ':00?';
        }
        if ($dia > $diaPedido) {
            $cuando = ($dia === $diaActual + 1) ? 'Mañana' : Reloj::diaSemanaUi($dia, $reloj);
            if ($diaPedido === $diaActual) {
                return 'Hoy no puedo. ¿' . $cuando . ' a las ' . $hh . ':00?';
            }
            return '¿' . $cuando . ' a las ' . $hh . ':00?';
        }
        $cuando = Reloj::diaSemanaUi($dia, $reloj);
        return '¿Te va el ' . $cuando . ' a las ' . $hh . ':00?';
    }

    /**
     * Copy neutro cuando un par tiene cooldown de propuesta activo (pre-chequeo en proponer).
     *
     * @param list<string> $participantes
     */
    public static function mensajeCooldownPar(array $partida, array $participantes, string $tipo = ''): string
    {
        if (count($participantes) < 2) {
            return self::COOLDOWN_GENERICO;
        }
        $a = (string) $participantes[0];
        $b = (string) $participantes[1];
        $mejor = null;
        $tsMejor = -1;
        foreach ($partida['rechazos_propuesta'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $q = (string) ($row['quien'] ?? '');
            $h = (string) ($row['hacia'] ?? '');
            $esPar = (($q === $a && $h === $b) || ($q === $b && $h === $a));
            if (!$esPar) {
                continue;
            }
            if ($tipo !== '' && (string) ($row['tipo'] ?? '') !== $tipo) {
                continue;
            }
            $ts = ((int) ($row['dia'] ?? 0)) * 24 + ((int) ($row['hora'] ?? 0));
            if ($ts > $tsMejor) {
                $tsMejor = $ts;
                $mejor = ['quien' => $q, 'hacia' => $h];
            }
        }
        if ($mejor === null) {
            return self::COOLDOWN_GENERICO;
        }
        $nq = IdentidadPublica::nombre($partida, $mejor['quien']);
        $nh = IdentidadPublica::nombre($partida, $mejor['hacia']);
        if ($nq === '' || $nh === '' || $nq === $mejor['quien'] || $nh === $mejor['hacia']) {
            return self::COOLDOWN_GENERICO;
        }
        return $nq . ' rechazó hace poco una propuesta de ' . $nh . '. Mejor dales un poco de espacio.';
    }

    /**
     * Motivo comprensible de bloqueo para una franja solicitada (UI horarios).
     *
     * @param list<string> $participantes
     */
    public static function motivoBloqueoFranjaUi(
        array $partida,
        array $participantes,
        int $dia,
        int $hora,
        ?string $lugarId = null
    ): string {
        if ($lugarId !== null && $lugarId !== '' && !ComplejoCatalog::estaAbierto($lugarId, $hora)) {
            $nombre = MisionPlantillas::nombreLugar($lugarId);
            $horario = ComplejoCatalog::horarioUi($lugarId);
            if ($horario !== '') {
                return $nombre . ' abre ' . $horario;
            }
            return 'el sitio está cerrado a esa hora';
        }
        foreach ($participantes as $rid) {
            $disp = AgendaEngine::estaDisponible($partida, (string) $rid, $dia, $hora);
            if (!($disp['disponible'] ?? false)) {
                $nombre = IdentidadPublica::nombre($partida, (string) $rid);
                $motivo = self::motivoAgendaUi($disp);
                return $nombre . ': ' . $motivo;
            }
        }
        if (EncuentroEngine::hayConflictoHorario($partida, $participantes, $dia, $hora)) {
            return 'ya hay otro plan a esa hora';
        }
        return '';
    }

    /** @param array<string, mixed> $disp */
    public static function motivoAgendaUi(array $disp): string
    {
        $tipo = (string) ($disp['tipo'] ?? '');
        switch ($tipo) {
            case 'sueno':
                return 'está durmiendo';
            case 'trabajo':
            case 'trabajo_blando':
            case 'trabajo_generico':
            case 'estudio':
                return 'está trabajando';
            case 'compromiso':
                return 'tiene otro compromiso';
            case 'encuentro':
                return 'ya tiene algo programado';
            default:
                break;
        }
        $motivo = (string) ($disp['motivo'] ?? 'ocupado');
        if ($motivo === 'doble_reserva') {
            return 'ya tiene otro plan';
        }
        return 'no está disponible';
    }

    /**
     * @param array<string, mixed> $slot
     */
    public static function etiquetaSlotUi(array $partida, array $slot): string
    {
        $reloj = $partida['reloj'] ?? [];
        $dia = (int) ($slot['dia'] ?? 0);
        $hora = (int) ($slot['hora'] ?? 0);
        $hh = $slot['etiqueta_hora'] ?? str_pad((string) $hora, 2, '0', STR_PAD_LEFT);
        $diaActual = (int) ($reloj['dia_pueblo'] ?? 1);
        if ($dia === $diaActual) {
            return 'hoy ' . $hh;
        }
        if ($dia === $diaActual + 1) {
            return 'mañana ' . $hh;
        }
        $ds = (string) ($slot['dia_semana_ui'] ?? Reloj::diaSemanaUi($dia, $reloj));
        return strtolower($ds) . ' ' . $hh;
    }
}
