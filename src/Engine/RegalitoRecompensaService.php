<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Servicio compartido de concesión de regalitos (recompensas garantizadas).
 *
 * Causas canónicas:
 *   - misiones_diarias:{dia}  → 3/3 misiones completadas
 *   - historia:{clave}        → nuevo recuerdo de Historia del Pueblo
 */
final class RegalitoRecompensaService
{
    public const TIPO_MENSAJE = 'regalito_recompensa';
    public const ORIGEN_EVENTO = 'regalito_recompensa';
    public const ORIGEN_MISIONES_3X3 = 'misiones_3x3';
    public const ORIGEN_HISTORIA = 'historia_pueblo';

    public const ESTADO_ENTREGADO = 'entregado';
    public const ESTADO_PENDIENTE = 'pendiente';

    /**
     * @param array<string, mixed> $partida
     * @return array<string, mixed>|null
     */
    public static function otorgar(array &$partida, string $contexto, ?GameLogger $logger = null): ?array
    {
        if ($contexto === '') {
            return null;
        }

        $root = dirname(__DIR__, 2);
        $cal = CalibracionConfig::load($root);

        if (!(bool) CalibracionConfig::get($cal, 'regalito_recompensa.activa', true)) {
            return null;
        }

        $partida['regalito_recompensas'] ??= [];
        if (isset($partida['regalito_recompensas'][$contexto])) {
            return null;
        }

        $catalog = new CatalogStore($root);
        $objetoId = self::elegirObjeto($cal, $contexto, $catalog);
        if ($objetoId === null) {
            return null;
        }

        $nombreObjeto = self::nombreObjeto($catalog, $objetoId);
        $alta = InventarioEngine::anadir($partida, $objetoId, 1, $catalog);
        if (empty($alta['ok'])) {
            $partida['regalito_recompensas'][$contexto] = [
                'objeto_id' => $objetoId,
                'estado' => self::ESTADO_PENDIENTE,
            ];
            self::mensajito($partida, $contexto, $objetoId, $nombreObjeto, $catalog, $logger, true);
            return [
                'ok' => true,
                'objeto_id' => $objetoId,
                'objeto_nombre' => $nombreObjeto,
                'objeto_imagen' => self::urlImagenObjeto($catalog, $objetoId),
                'cantidad' => 0,
                'pendiente' => true,
                'origen' => self::origenDeContexto($contexto),
            ];
        }

        $partida['regalito_recompensas'][$contexto] = [
            'objeto_id' => $objetoId,
            'estado' => self::ESTADO_ENTREGADO,
        ];

        self::mensajito($partida, $contexto, $objetoId, $nombreObjeto, $catalog, $logger, false);

        return [
            'ok' => true,
            'objeto_id' => $objetoId,
            'objeto_nombre' => $nombreObjeto,
            'objeto_imagen' => self::urlImagenObjeto($catalog, $objetoId),
            'cantidad' => (int) ($alta['cantidad'] ?? 1),
            'pendiente' => false,
            'origen' => self::origenDeContexto($contexto),
        ];
    }

    /**
     * @param array<string, mixed> $partida
     * @return list<array<string, mixed>>
     */
    public static function reclamarPendientes(array &$partida, ?GameLogger $logger = null): array
    {
        $partida['regalito_recompensas'] ??= [];
        $entregados = [];

        $root = dirname(__DIR__, 2);
        $catalog = new CatalogStore($root);

        foreach ($partida['regalito_recompensas'] as $contexto => &$entry) {
            if (!is_array($entry) || ($entry['estado'] ?? '') !== self::ESTADO_PENDIENTE) {
                continue;
            }

            $objetoId = (string) ($entry['objeto_id'] ?? '');
            if ($objetoId === '') {
                continue;
            }

            $alta = InventarioEngine::anadir($partida, $objetoId, 1, $catalog);
            if (empty($alta['ok'])) {
                continue;
            }

            $entry['estado'] = self::ESTADO_ENTREGADO;
            $nombreObjeto = self::nombreObjeto($catalog, $objetoId);
            self::mensajito($partida, (string) $contexto, $objetoId, $nombreObjeto, $catalog, $logger, false);

            $entregados[] = [
                'ok' => true,
                'objeto_id' => $objetoId,
                'objeto_nombre' => $nombreObjeto,
                'objeto_imagen' => self::urlImagenObjeto($catalog, $objetoId),
                'cantidad' => (int) ($alta['cantidad'] ?? 1),
                'contexto' => $contexto,
                'origen' => self::origenDeContexto((string) $contexto),
            ];
        }
        unset($entry);

        return $entregados;
    }

    public static function hayPendientes(array $partida): bool
    {
        foreach ($partida['regalito_recompensas'] ?? [] as $entry) {
            if (is_array($entry) && ($entry['estado'] ?? '') === self::ESTADO_PENDIENTE) {
                return true;
            }
        }
        return false;
    }

    public static function elegirObjeto(array $cal, string $contexto, CatalogStore $catalog): ?string
    {
        $lista = CalibracionConfig::get($cal, 'regalito_recompensa.objetos', []);
        $lista = is_array($lista) ? array_values(array_filter($lista, 'is_string')) : [];
        if ($lista === []) {
            return null;
        }
        $validas = [];
        foreach ($lista as $id) {
            if ($catalog->item('regalos', $id) !== null) {
                $validas[] = $id;
            }
        }
        if ($validas === []) {
            return null;
        }
        sort($validas);
        return $validas[crc32('regalito_recomp|' . $contexto) % count($validas)];
    }

    /**
     * Vista UI de recompensa para Historia / celebraciones.
     *
     * @param array<string, mixed>|null $regalito
     * @return array<string, mixed>|null
     */
    public static function vistaRecompensa(?array $regalito, string $contexto, ?CatalogStore $catalog = null): ?array
    {
        if ($regalito === null || empty($regalito['objeto_id'])) {
            return null;
        }
        $catalog ??= new CatalogStore(dirname(__DIR__, 2));
        $objetoId = (string) $regalito['objeto_id'];
        return [
            'objeto_id' => $objetoId,
            'objeto_nombre' => (string) ($regalito['objeto_nombre'] ?? self::nombreObjeto($catalog, $objetoId)),
            'objeto_imagen' => (string) ($regalito['objeto_imagen'] ?? self::urlImagenObjeto($catalog, $objetoId)),
            'cantidad' => (int) ($regalito['cantidad'] ?? 1),
            'pendiente' => !empty($regalito['pendiente']),
            'origen' => self::origenDeContexto($contexto),
        ];
    }

    /**
     * Reconstruye recompensa persistida desde mapa regalito_recompensas + entrada historia.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $entrada
     * @return array<string, mixed>|null
     */
    public static function recompensaDeEntradaHistoria(array $partida, array $entrada): ?array
    {
        if (!empty($entrada['recompensa']) && is_array($entrada['recompensa'])) {
            return $entrada['recompensa'];
        }
        $clave = (string) ($entrada['clave'] ?? '');
        if ($clave === '') {
            return null;
        }
        $contexto = 'historia:' . $clave;
        $map = $partida['regalito_recompensas'][$contexto] ?? null;
        if (!is_array($map) || empty($map['objeto_id'])) {
            return null;
        }
        $catalog = new CatalogStore(dirname(__DIR__, 2));
        $objetoId = (string) $map['objeto_id'];
        return [
            'objeto_id' => $objetoId,
            'objeto_nombre' => self::nombreObjeto($catalog, $objetoId),
            'objeto_imagen' => self::urlImagenObjeto($catalog, $objetoId),
            'cantidad' => 1,
            'pendiente' => ($map['estado'] ?? '') === self::ESTADO_PENDIENTE,
            'origen' => self::ORIGEN_HISTORIA,
            'animacion_vista' => !empty($entrada['recompensa_animacion_vista']),
        ];
    }

    public static function urlImagenObjeto(CatalogStore $catalog, string $objetoId): string
    {
        $item = $catalog->item('regalos', $objetoId);
        if (!is_array($item)) {
            return 'assets/play-v3/regalos/' . $objetoId . '.png';
        }
        $asset = (string) ($item['asset'] ?? ('regalos/' . $objetoId . '.png'));
        return 'assets/play-v3/' . ltrim($asset, '/');
    }

    public static function origenDeContexto(string $contexto): string
    {
        if (strpos($contexto, 'historia:') === 0) {
            return self::ORIGEN_HISTORIA;
        }
        if (strpos($contexto, 'misiones_diarias:') === 0) {
            return self::ORIGEN_MISIONES_3X3;
        }
        return self::ORIGEN_EVENTO;
    }

    private static function nombreObjeto(CatalogStore $catalog, string $objetoId): string
    {
        $item = $catalog->item('regalos', $objetoId);
        return (string) (is_array($item) ? ($item['nombre'] ?? $objetoId) : $objetoId);
    }

    private static function conArticulo(string $nombre): string
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return 'un regalo';
        }
        $low = mb_strtolower($nombre);
        $femenino = preg_match('/^(a|á|e|é|i|í|o|ó|u|ú|h)/u', $low) === 1
            && preg_match('/\b(taza|planta|bolsa|bufanda|caja|maceta|vela|postal|cantimplora)\b/u', $low);
        if (!$femenino && preg_match('/a$/u', $low) && !preg_match('/(ma|pa|ta)$/u', $low)) {
            $femenino = true;
        }
        return ($femenino ? 'una ' : 'un ') . $low;
    }

    private static function textoMensajito(string $contexto, string $objetoNombre, bool $pendiente): string
    {
        $objeto = self::conArticulo($objetoNombre);
        $origen = self::origenDeContexto($contexto);
        if ($origen === self::ORIGEN_HISTORIA) {
            if ($pendiente) {
                return 'Has desbloqueado un nuevo recuerdo en Historia del Pueblo. Te has ganado '
                    . $objeto . ', pero el inventario está lleno. Cuando libres hueco, será tuyo.';
            }
            return 'Has desbloqueado un nuevo recuerdo en Historia del Pueblo. Te has ganado '
                . $objeto . '. Ya está en tu inventario.';
        }
        if ($pendiente) {
            return 'Has cumplido las tres misiones del día. Te llevas de regalito '
                . $objeto . ', pero el inventario está lleno. Cuando libres hueco, será tuyo.';
        }
        return 'Has cumplido las tres misiones del día. Te llevas de regalito '
            . $objeto . '. ¡Guárdalo para quien quieras!';
    }

    /**
     * @param array<string, mixed> $partida
     */
    private static function mensajito(
        array &$partida,
        string $contexto,
        string $objetoId,
        string $objetoNombre,
        CatalogStore $catalog,
        ?GameLogger $logger,
        bool $pendiente
    ): void {
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return;
        }
        if (!empty($partida['_lab_peticiones_b4']) || !empty($partida['_lab_misiones_b3'])) {
            return;
        }
        $origenTipo = self::origenDeContexto($contexto);
        $texto = self::textoMensajito($contexto, $objetoNombre, $pendiente);
        $r = BuzonEngine::crear($partida, [
            'clasificacion' => BuzonEngine::OPORTUNIDAD,
            'tipo' => self::TIPO_MENSAJE,
            'canal' => BuzonEngine::CANAL_BUZON,
            'de_persona' => null,
            'actores' => [],
            'texto' => $texto,
            'origen' => [
                'evento_id' => self::ORIGEN_EVENTO . ':' . $objetoId,
                'tipo_evento' => self::TIPO_MENSAJE,
                'regalito_contexto' => $contexto,
                'regalito_origen' => $origenTipo,
                'objeto_id' => $objetoId,
                'objeto_nombre' => $objetoNombre,
                'objeto_imagen' => self::urlImagenObjeto($catalog, $objetoId),
                'es_narrativo' => false,
                'informacion_revelada' => [],
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ]);
        DomainEventDispatcher::emit($partida, DomainEvents::BUZON_MENSAJE, [
            'mensaje' => $r['mensaje'] ?? null,
            'origen_evento' => self::TIPO_MENSAJE,
        ], $logger, 'RegalitoRecompensaService');
    }
}
