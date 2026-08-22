<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class BuzonEngine
{
    public const ESTADOS = ['pendiente', 'leido', 'resuelto', 'en_espera'];
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
        if (!in_array($entry['estado'] ?? '', self::ESTADOS, true)) {
            $entry['estado'] = 'pendiente';
        }

        $partida['buzon'] ??= [];
        $partida['buzon'][] = $entry;
        return ['ok' => true, 'mensaje' => $entry];
    }

    public static function marcarLeido(array &$partida, string $mensajeId): array
    {
        return self::marcarEstado($partida, $mensajeId, 'leido');
    }

    public static function marcarEstado(array &$partida, string $mensajeId, string $estado): array
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            return ['ok' => false, 'error' => 'estado_invalido'];
        }
        foreach ($partida['buzon'] as &$m) {
            if ($m['id'] === $mensajeId) {
                $m['estado'] = $estado;
                if ($estado === 'leido') {
                    $m['leido'] = true;
                    $m['leido_en'] = date('c');
                } elseif ($estado === 'pendiente') {
                    $m['leido'] = false;
                    unset($m['leido_en']);
                }
                return ['ok' => true, 'mensaje' => $m];
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
        return $items;
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
