<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class BuzonEngine
{
    public const ESTADOS = ['pendiente', 'leido', 'resuelto', 'en_espera'];
    public const DECISION_PENDIENTE = 'pendiente';
    public const DECISION_RESUELTO = 'resuelto';
    public const DECISION_NO_APLICA = 'no_aplica';
    public const ESTADOS_DECISION = [self::DECISION_PENDIENTE, self::DECISION_RESUELTO, self::DECISION_NO_APLICA];
    public const IMPORTANTE = 'importante';
    public const OPORTUNIDAD = 'oportunidad';
    public const PETICION = 'peticion';
    public const COTILLEO = 'cotilleo';
    public const CLASIFICACIONES = [self::IMPORTANTE, self::OPORTUNIDAD, self::PETICION, self::COTILLEO];
    public const CANAL_BUZON = 'buzon';
    public const CANAL_COTILLEO = 'cotilleo';

    public static function canalDe(string $clasificacion): string
    {
        if ($clasificacion === self::COTILLEO) {
            return self::CANAL_COTILLEO;
        }
        return self::CANAL_BUZON;
    }

    public static function crear(array &$partida, array $mensaje): array
    {
        if (!self::tieneContenido($mensaje)) {
            return [
                'ok' => false,
                'error' => 'contenido_invalido',
                'mensaje_ui' => 'No se ha creado el Mensajito porque no tiene contenido.',
            ];
        }
        $id = $mensaje['id'] ?? 'msg_' . bin2hex(random_bytes(4));
        $clas = $mensaje['clasificacion'] ?? self::PETICION;
        if (!in_array($clas, self::CLASIFICACIONES, true)) {
            $clas = self::PETICION;
        }
        $entry = array_merge([
            'id' => $id,
            'de_persona' => null,
            'tipo' => 'peticion',
            'texto' => '',
            'dia' => $partida['reloj']['dia_pueblo'] ?? 1,
            'estado' => 'pendiente',
            'clasificacion' => $clas,
            'origen' => [
                'evento_id' => null,
                'tipo_evento' => null,
                'es_narrativo' => false,
                'informacion_revelada' => [],
                '_placeholder' => true,
            ],
            '_placeholder_contenido' => true,
        ], $mensaje);
        $entry['clasificacion'] = $clas;
        $entry['canal'] = isset($mensaje['canal']) && is_string($mensaje['canal']) && $mensaje['canal'] !== ''
            ? $mensaje['canal']
            : self::canalDe($clas);
        $reloj = $partida['reloj'] ?? [];
        $diaMsg = (int) ($entry['dia'] ?? ($reloj['dia_pueblo'] ?? 1));
        $entry['dia'] = $diaMsg;
        $entry['fecha_corta'] = Reloj::fechaCorta($reloj, $diaMsg);
        $entry['fecha_iso'] = Reloj::fechaIso($reloj, $diaMsg);
        $entry['dia_semana_ui'] = Reloj::diaSemanaUi($diaMsg, $reloj);
        if (!isset($entry['ts_juego']) || !is_array($entry['ts_juego'])) {
            $entry['ts_juego'] = [
                'dia' => $diaMsg,
                'hora' => (int) ($reloj['hora_actual'] ?? 0),
            ];
        }
        if (!in_array($entry['estado'] ?? '', self::ESTADOS, true)) {
            $entry['estado'] = 'pendiente';
        }
        $entry = self::normalizar($entry);

        $partida['buzon'] ??= [];
        $partida['buzon'][] = $entry;
        return ['ok' => true, 'mensaje' => self::enriquecerParaUi($entry)];
    }

    /**
     * Migración mínima compatible con saves: separa lectura y decisión pendiente.
     *
     * @param array<string, mixed> $mensaje
     * @return array<string, mixed>
     */
    public static function normalizar(array $mensaje): array
    {
        $acciones = is_array($mensaje['acciones'] ?? null) ? $mensaje['acciones'] : [];
        if (!isset($mensaje['estado_decision'])) {
            if ($acciones === []) {
                $mensaje['estado_decision'] = self::DECISION_NO_APLICA;
            } elseif (($mensaje['estado'] ?? '') === 'resuelto') {
                $mensaje['estado_decision'] = self::DECISION_RESUELTO;
            } else {
                $mensaje['estado_decision'] = self::DECISION_PENDIENTE;
            }
        }
        if (!in_array((string) ($mensaje['estado_decision'] ?? ''), self::ESTADOS_DECISION, true)) {
            $mensaje['estado_decision'] = $acciones === [] ? self::DECISION_NO_APLICA : self::DECISION_PENDIENTE;
        }
        if (!array_key_exists('leido', $mensaje)) {
            $mensaje['leido'] = ($mensaje['estado'] ?? '') !== 'pendiente';
        }
        return $mensaje;
    }

    /**
     * @param array<string, mixed> $mensaje
     */
    public static function estaLeido(array $mensaje): bool
    {
        $m = self::normalizar($mensaje);
        return !empty($m['leido']);
    }

    /**
     * @param array<string, mixed> $mensaje
     */
    public static function tieneDecisionPendiente(array $mensaje): bool
    {
        $m = self::normalizar($mensaje);
        $acciones = is_array($m['acciones'] ?? null) ? $m['acciones'] : [];
        return $acciones !== []
            && ($m['estado_decision'] ?? '') === self::DECISION_PENDIENTE;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function buscar(array $partida, string $mensajeId): ?array
    {
        foreach ($partida['buzon'] ?? [] as $m) {
            if (is_array($m) && ($m['id'] ?? '') === $mensajeId) {
                return self::enriquecerParaUi($m);
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $mensaje
     * @return array<string, mixed>
     */
    public static function enriquecerParaUi(array $mensaje): array
    {
        $m = self::normalizar($mensaje);
        $acciones = is_array($m['acciones'] ?? null) ? $m['acciones'] : [];
        $pendiente = self::tieneDecisionPendiente($m);
        $m['requiere_decision'] = $pendiente;
        $m['acciones_ui'] = MensajitoAcciones::vistaDe($acciones, $pendiente);
        $m['leido'] = self::estaLeido($m);
        return $m;
    }

    public static function resolverDecision(array &$partida, string $mensajeId): array
    {
        foreach ($partida['buzon'] as &$m) {
            if (!is_array($m) || ($m['id'] ?? '') !== $mensajeId) {
                continue;
            }
            $m = self::normalizar($m);
            $m['estado_decision'] = self::DECISION_RESUELTO;
            $m['estado'] = 'resuelto';
            return ['ok' => true, 'mensaje' => self::enriquecerParaUi($m)];
        }
        return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
    }

    public static function marcarLeido(array &$partida, string $mensajeId): array
    {
        return self::marcarEstado($partida, $mensajeId, 'leido');
    }

    /**
     * Marca como leídos todos los mensajes pendientes del canal (por defecto buzón).
     *
     * @return array{ok: bool, marcados: int, ids: list<string>, no_leidos: int}
     */
    public static function marcarTodosLeidos(array &$partida, ?string $canal = self::CANAL_BUZON): array
    {
        $marcados = [];
        foreach ($partida['buzon'] ?? [] as $m) {
            if (!is_array($m)) {
                continue;
            }
            if (!self::tieneContenido($m)) {
                continue;
            }
            if (($m['estado'] ?? '') !== 'pendiente') {
                continue;
            }
            $c = (string) ($m['canal'] ?? self::canalDe((string) ($m['clasificacion'] ?? self::PETICION)));
            if ($canal !== null && $c !== $canal) {
                continue;
            }
            $mid = (string) ($m['id'] ?? '');
            if ($mid === '') {
                continue;
            }
            $r = self::marcarEstado($partida, $mid, 'leido');
            if ($r['ok'] ?? false) {
                $marcados[] = $mid;
            }
        }

        return [
            'ok' => true,
            'marcados' => count($marcados),
            'ids' => $marcados,
            'no_leidos' => self::contarNoLeidos($partida, $canal),
        ];
    }

    public static function marcarEstado(array &$partida, string $mensajeId, string $estado): array
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            return ['ok' => false, 'error' => 'estado_invalido'];
        }
        foreach ($partida['buzon'] as &$m) {
            if ($m['id'] === $mensajeId) {
                $m = self::normalizar($m);
                if ($estado === 'leido') {
                    $m['leido'] = true;
                    $m['leido_en'] = date('c');
                    if (($m['estado'] ?? '') === 'pendiente') {
                        $m['estado'] = 'leido';
                    }
                } elseif ($estado === 'pendiente') {
                    $m['leido'] = false;
                    $m['estado'] = 'pendiente';
                    unset($m['leido_en']);
                } else {
                    $m['estado'] = $estado;
                }
                return ['ok' => true, 'mensaje' => self::enriquecerParaUi($m)];
            }
        }
        return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
    }

    public static function listar(
        array $partida,
        ?string $estado = null,
        ?string $clasificacion = null,
        ?string $canal = null
    ): array {
        $items = $partida['buzon'] ?? [];
        if ($estado !== null) {
            $items = array_values(array_filter($items, static function ($m) use ($estado) {
                return ($m['estado'] ?? '') === $estado;
            }));
        }
        if ($clasificacion !== null) {
            $items = array_values(array_filter($items, static function ($m) use ($clasificacion) {
                return ($m['clasificacion'] ?? '') === $clasificacion;
            }));
        }
        if ($canal !== null) {
            $items = array_values(array_filter($items, static function ($m) use ($canal) {
                $c = (string) ($m['canal'] ?? self::canalDe((string) ($m['clasificacion'] ?? self::PETICION)));
                return $c === $canal;
            }));
        }
        return array_map(static fn($m) => is_array($m) ? self::enriquecerParaUi($m) : $m, $items);
    }

    /**
     * Un Mensajito jugable necesita texto propio. Los registros legacy sin
     * texto se conservan en el save para auditoría, pero no se proyectan.
     */
    public static function tieneContenido(array $mensaje): bool
    {
        return trim((string) ($mensaje['texto'] ?? '')) !== '';
    }

    /**
     * Mensajes no leídos del canal Mensajitos (solo estado pendiente).
     */
    public static function contarNoLeidos(array $partida, ?string $canal = self::CANAL_BUZON): int
    {
        $n = 0;
        foreach ($partida['buzon'] ?? [] as $m) {
            if (!is_array($m)) {
                continue;
            }
            if (!self::tieneContenido($m)) {
                continue;
            }
            if (($m['estado'] ?? '') !== 'pendiente') {
                continue;
            }
            $c = (string) ($m['canal'] ?? self::canalDe((string) ($m['clasificacion'] ?? self::PETICION)));
            if ($canal !== null && $c !== $canal) {
                continue;
            }
            $n++;
        }
        return $n;
    }
}
