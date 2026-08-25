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

    /** @var array<string, list<string>> */
    private const VARIANTES = [
        'sueno' => [
            'A esa hora ya estoy durmiendo.',
            'Esa hora me pillas en la cama.',
            'A esa hora ya descanso.',
            'A esas horas ya estoy en la cama.',
            'Me pillas durmiendo a esa hora.',
            'Esa hora ya es hora de dormir para mí.',
        ],
        'trabajo' => [
            'Tengo que trabajar.',
            'Ese día trabajo y no me da la vida.',
            'Ese día tengo jornada y no puedo.',
            'Ese día tengo turno y no puedo.',
            'Trabajo hasta tarde ese día.',
            'Justo ese día me coincide el trabajo.',
        ],
        'otro_compromiso' => [
            'Ya tengo otro plan.',
            'Ese día tengo un compromiso.',
            'Esa hora ya la tengo ocupada con otra cosa.',
            'Ya lo tengo ocupado con otro asunto.',
            'Tengo algo pendiente a esa hora.',
            'Justo tengo un compromiso encima.',
        ],
        'encuentro_programado' => [
            'Ya tengo algo programado a esa hora.',
            'A esa hora ya quedé con alguien.',
            'Esa franja ya la tengo reservada.',
            'Ya tengo un plan reservado a esa hora.',
            'Justo a esa hora tengo algo concertado.',
            'Esa franja ya la tengo comprometida.',
        ],
        'lugar_cerrado' => [
            'A esa hora el sitio ya está cerrado.',
            'Ese local cierra antes de esa hora.',
            'A esa hora el lugar no abre.',
            'El sitio ya está cerrado a esa hora.',
            'A esa hora ese lugar no abre.',
            'Cierra antes de esa hora; no hay manera.',
        ],
        'ocupado' => [
            'A esa hora no puedo.',
            'Esa hora no me viene bien.',
            'No puedo a esa hora.',
            'A esa hora mejor no cuentes conmigo.',
            'Esa franja no me viene nada bien.',
            'Justo entonces no puedo.',
        ],
        'emocional' => [
            'Hoy no me encuentro con ánimo.',
            'No estoy con la cabeza para quedar.',
            'Hoy no me apetece socializar.',
            'Hoy ando sin ánimo para planes.',
            'Hoy tengo el ánimo por los suelos.',
            'Prefiero dejarlo para cuando esté mejor.',
            'Hoy no estoy de humor para nadie.',
        ],
        'relacional' => [
            'Prefiero no quedar ahora.',
            'Ahora mismo no me apetece.',
            'Hoy no me entra quedar.',
            'Prefiero que quedemos otro día.',
            'Prefiero no vernos ahora mismo.',
            'No me apetece quedar contigo ahora mismo.',
            'Prefiero dejarlo pasar por hoy.',
        ],
        'banal' => [
            'Hoy no me da la vida.',
            'Hoy no me apetece.',
            'Hoy paso.',
            'Hoy simplemente no me apetece quedar.',
            'Esta vez no estoy por la labor.',
            'Hoy prefiero quedarme a mi aire.',
            'No hay ningún drama: simplemente digo que no.',
            'Hoy no estoy para planes.',
        ],
        'cooldown' => [
            'Todavía no quiero hablar de eso.',
            'Déjame un poco de margen, ¿vale?',
            'Ahora mismo no quiero plantearlo.',
            'Todavía no es el momento.',
            'Mejor no insistir ahora mismo.',
        ],
    ];

    private const COOLDOWN_GENERICO = 'Necesitan un poco de espacio.';

    /**
     * Variantes para el dato real "el otro habría aceptado" (marcador
     * voluntad_ok_pero_plan_rechazado). Sin números ni datos ocultos.
     *
     * @var list<string>
     */
    private const OTRO_DISPUESTO = [
        '{nombre}, en cambio, sí parecía dispuesta.',
        'A {nombre}, en cambio, sí le apetecía dar ese paso.',
        '{nombre}, eso sí, parecía tener ganas.',
        '{nombre}, en cambio, sí tenía ganas.',
        'A {nombre}, en cambio, sí le apetecía el plan.',
        '{nombre}, eso sí, no ponía pegas.',
        'Lo curioso: {nombre} sí estaba por la labor.',
    ];

    /** @var array<string, list<string>> */
    private const CAUSAS_HUMANAS = [
        'emocional' => [
            '{nombre} hoy no tiene muchas ganas de planes.',
            '{nombre} anda con el ánimo bajo y prefiere dejarlo para otro día.',
            '{nombre} anda hoy con el ánimo bajo.',
            '{nombre} hoy no está con la cabeza para planes.',
            '{nombre} hoy no está de humor para quedar.',
            '{nombre} prefiere esperar a estar de mejor humor.',
            'A {nombre} hoy le cuesta ponerse con gente.',
        ],
        'relacional' => [
            '{nombre} no está muy por la labor de quedar con {otro} ahora mismo.',
            '{nombre} prefiere tomar distancia de {otro} estos días.',
            'A {nombre} ahora mismo no le apetece un plan con {otro}.',
            '{nombre} hoy prefiere mantener distancia con {otro}.',
            '{nombre} no acaba de tener ganas de ver a {otro} ahora mismo.',
            'A {nombre} hoy no le apetece verse con {otro}.',
            '{nombre} hoy prefiere otros planes antes que quedar con {otro}.',
        ],
        'banal' => [
            '{nombre} hoy no tenía ganas de quedar, sin más.',
            'A {nombre} hoy no le apetecía, sin más historia.',
            '{nombre} simplemente hoy no tenía ganas de quedar.',
            'Esta vez {nombre} no estaba por la labor.',
            'Hoy {nombre} prefería quedarse a su aire.',
            'Sin ningún drama detrás: {nombre} dijo que no.',
            'Hoy {nombre} no estaba para hacer planes.',
            'No ocurrió nada especial: a {nombre} simplemente no le apetecía.',
        ],
        'cansancio' => [
            '{nombre} lleva unas cuantas negativas últimamente y necesita un poco de espacio.',
            '{nombre} está algo saturado de planes juntos y prefiere respirar un poco.',
            'Con tantos «no» seguidos, a {nombre} le toca un descanso del tema.',
            '{nombre} necesita aire después de tantas respuestas negativas.',
            'Demasiados planes juntos al final pesan: {nombre} quiere un respiro.',
            '{nombre} ha dicho que no tantas veces que ya toca darle margen.',
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
    public static function linea(array &$partida, array $reac, string $seed = ''): string
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
        return rtrim(
            CopyVariante::elegir($partida, 'rech:' . $familia . ':' . $rid, $pool, $key),
            '.'
        );
    }

    /**
     * Frase humana (tercera persona) para causas de voluntad: emocional,
     * relacional, banal o cansancio por rechazos repetidos. Sin datos ocultos.
     * Devuelve '' si la familia no tiene traducción humana (agenda, cooldown...).
     *
     * @param array<string, mixed> $reac
     */
    public static function fraseCausaHumana(array &$partida, array $reac, string $otroId = ''): string
    {
        $rid = (string) ($reac['residente_id'] ?? '');
        if ($rid === '') {
            return '';
        }
        $nombre = (string) ($reac['nombre'] ?? '');
        if ($nombre === '' || $nombre === $rid) {
            $nombre = IdentidadPublica::nombre($partida, $rid);
        }
        if ($nombre === '' || $nombre === $rid) {
            return '';
        }
        $familia = self::familiaDeReaccion($reac);
        if (!in_array($familia, ['emocional', 'relacional', 'banal'], true)) {
            return '';
        }
        $otroNombre = '';
        if ($otroId !== '') {
            $n = IdentidadPublica::nombre($partida, $otroId);
            if ($n !== $otroId && $n !== '') {
                $otroNombre = $n;
            }
        }
        if ($otroId !== ''
            && RechazoMemoria::countHacia($partida, $rid, $otroId) >= (int) CalibracionConfig::get([], 'rechazos.repetidos_umbral', 3)
        ) {
            $familia = 'cansancio';
        }
        $pool = self::CAUSAS_HUMANAS[$familia] ?? [];
        if ($pool === []) {
            return '';
        }
        $key = $rid . ':' . $familia . ':' . ($partida['rng']['cursor'] ?? 0);
        $frase = str_replace(
            ['{nombre}', '{otro}'],
            [$nombre, $otroNombre !== '' ? $otroNombre : $nombre],
            CopyVariante::elegir($partida, 'causa:' . $familia . ':' . $rid, $pool, $key)
        );
        if ($familia === 'emocional'
            && $otroId !== ''
            && (RechazoMemoria::countHacia($partida, $rid, $otroId) > 0 || RechazoMemoria::countHacia($partida, $otroId, $rid) > 0)
        ) {
            $frase .= ' Le pesa lo reciente con ' . ($otroNombre !== '' ? $otroNombre : $nombre) . '.';
        }
        return $frase;
    }

    /**
     * Mensaje de cooldown a nivel par. Resuelve QUIÉN rechazó a QUIÉN desde
     * RechazoMemoria (fila más reciente entre ambos); nunca por orden del array.
     * Fallback genérico si no hay memoria resoluble (partida antigua).
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
     * Frase humana (tercera persona) para el dato REAL "el otro habría
     * aceptado". Solo procede si existe el marcador canónico del motor.
     * Devuelve '' cuando no hay nada real que contar.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $propuesta
     */
    public static function lineaOtroDispuesto(array &$partida, array $propuesta, string $seedPrefijo = ''): string
    {
        $canon = PropuestaEncuentroEngine::rechazoCanonico($propuesta);
        $otra = $canon['habria_aceptado'];
        if (!is_array($otra)) {
            return '';
        }
        if (count($canon['rechazadores']) > 1) {
            // Con varios rechazos no se puede atribuir un único "sí habría aceptado".
            return '';
        }
        $rid = (string) ($otra['residente_id'] ?? '');
        if ($rid === '') {
            return '';
        }
        $nombre = (string) ($otra['nombre'] ?? '');
        if ($nombre === '' || $nombre === $rid) {
            $nombre = IdentidadPublica::nombre($partida, $rid);
        }
        if ($nombre === '' || $nombre === $rid) {
            return '';
        }
        $key = $seedPrefijo . $rid . ':dispuesto:' . ($propuesta['id'] ?? '') . ':' . ($partida['rng']['cursor'] ?? 0);
        return str_replace(
            '{nombre}',
            $nombre,
            CopyVariante::elegir($partida, 'dispuesto:' . $rid, self::OTRO_DISPUESTO, $key)
        );
    }

    /**
     * Mensaje de rechazo. Causa humana real + (si existe el marcador del
     * motor) la información de que el otro sí habría aceptado. Sin datos
     * ocultos: nunca probabilidades, scores, RNG ni IDs.
     *
     * @param array<string, mixed> $propuesta
     * @param array<string, mixed>|null $contrapropuesta
     */
    public static function mensajeRechazo(array &$partida, array $propuesta, ?array $contrapropuesta = null): string
    {
        $hablante = self::hablanteRechazo($propuesta);
        $nombre = (string) ($hablante['nombre'] ?? 'Alguien');
        $reac = is_array($hablante['reaccion'] ?? null) ? $hablante['reaccion'] : [];
        $msg = '';
        $famVoluntad = in_array(self::familiaDeReaccion($reac), ['emocional', 'relacional', 'banal'], true);
        if ($famVoluntad) {
            $otroId = self::otroParticipanteDePropuesta($propuesta, (string) ($reac['residente_id'] ?? ''));
            $fraseCausa = self::fraseCausaHumana($partida, $reac, $otroId);
            if ($fraseCausa !== '') {
                $msg = $fraseCausa;
            }
        }
        if ($msg === '') {
            $reac['propuesta_ctx'] = [
                'lugar' => $propuesta['lugar'] ?? null,
                'hora' => $propuesta['hora'] ?? null,
            ];
            $seed = (string) ($propuesta['id'] ?? '') . ':' . ($reac['residente_id'] ?? '');
            $msg = $nombre . ' ha rechazado la propuesta: "' . self::linea($partida, $reac, $seed) . '."';
        }
        $dispuesta = self::lineaOtroDispuesto($partida, $propuesta);
        if ($dispuesta !== '') {
            $msg .= ' ' . $dispuesta;
        }
        if ($contrapropuesta !== null && !empty($contrapropuesta['texto'])) {
            $msg .= ' ' . (string) $contrapropuesta['texto'];
        }
        return $msg;
    }

    /** @param array<string, mixed> $propuesta */
    private static function otroParticipanteDePropuesta(array $propuesta, string $rid): string
    {
        foreach ((array) ($propuesta['participantes'] ?? []) as $id) {
            $id = (string) $id;
            if ($id !== '' && $id !== $rid) {
                return $id;
            }
        }
        return '';
    }

    /**
     * Delega en la fuente canónica única (PropuestaEncuentroEngine::rechazoCanonico).
     * Nunca decide por posición A/B.
     *
     * @param array<string, mixed> $propuesta
     * @return array{nombre: string, reaccion: array<string, mixed>|null}
     */
    private static function hablanteRechazo(array $propuesta): array
    {
        $hablante = PropuestaEncuentroEngine::rechazoCanonico($propuesta)['hablante'];
        if (!is_array($hablante)) {
            return ['nombre' => '', 'reaccion' => null];
        }
        return [
            'nombre' => (string) ($hablante['nombre'] ?? ''),
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