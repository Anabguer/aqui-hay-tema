<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Regalos F2: fuente orgánica de objetos. Al cumplir una petición del pueblo,
 * el vecino puede agradecer dejando un detalle al inventario de Celestine.
 *
 * Reglas (calibración regalos.recompensa_peticion):
 *   - probabilidad por peso de petición (facil 0 / relevante 0.35 / dificil 1.0).
 *   - determinista: el "sorteo" es crc32(peticion.id), no RNG de partida.
 *   - máximo 1 recompensa/día de pueblo (persistente en peticiones_pueblo).
 *   - idempotente: cada petición queda marcada con recompensa_regalo = estado.
 *   - respeta inventario_cap; sin hueco no hay recompensa (y así se marca).
 *
 * El eco narrativo vive en su propio Mensajito (tipo regalo_recompensa,
 * clasificacion oportunidad), creado una sola vez porque el cumplimiento es
 * un único punto de entrada (PeticionPuebloEngine solo cumple peticiones ABIERTAS).
 */
final class RegaloRecompensaEngine
{
    public const TIPO_MENSAJE = 'regalo_recompensa';
    public const ORIGEN_EVENTO = 'recompensa_regalo';

    /** @return list<string> */
    private static function poolMensaje(): array
    {
        return [
            '{nombre} te deja {objeto} como agradecimiento. Te lo apuntas en el inventario.',
            'Detalle inesperado: {nombre} te manda {objeto}. Guardado para cuando quieras.',
        ];
    }

    /**
     * Punto de enganche único: PeticionFeedback::alCumplir().
     *
     * @param array<string, mixed> $peticion
     * @return array<string, mixed>|null null si no toca recompensa
     */
    public static function porPeticionCumplida(array &$partida, array $peticion, ?GameLogger $logger = null): ?array
    {
        $root = dirname(__DIR__, 2);
        $cal = CalibracionConfig::load($root);
        if (!(bool) CalibracionConfig::get($cal, 'regalos.recompensa_peticion.activa', true)) {
            return null;
        }
        $pid = (string) ($peticion['id'] ?? '');
        if ($pid === '') {
            return null;
        }
        $idx = self::indiceDe($partida, $pid);
        if ($idx === null) {
            return null;
        }

        $marcado = $partida['peticiones'][$idx]['recompensa_regalo'] ?? null;
        if (is_array($marcado) && isset($marcado['estado'])) {
            return null; // ya resuelto antes: nunca duplicar
        }

        $peso = (string) ($peticion['peso'] ?? PeticionEsquemas::PESO_FACIL);
        $prob = (float) CalibracionConfig::get($cal, 'regalos.recompensa_peticion.prob_por_peso.' . $peso, 0.0);
        $toca = ((crc32($pid) % 1000) / 1000) < $prob;

        if (!$toca) {
            $partida['peticiones'][$idx]['recompensa_regalo'] = ['estado' => 'no_toca'];
            return null;
        }

        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $partida['peticiones_pueblo'] ??= [];
        $contador = is_array($partida['peticiones_pueblo']['recompensas_dia'] ?? null)
            ? $partida['peticiones_pueblo']['recompensas_dia']
            : ['n' => 0, 'otorgadas' => 0];
        if ((int) ($contador['n'] ?? 0) !== $dia) {
            $contador = ['n' => $dia, 'otorgadas' => 0];
        }
        $maxDia = (int) CalibracionConfig::get($cal, 'regalos.recompensa_peticion.max_por_dia', 1);
        if ((int) $contador['otorgadas'] >= max(1, $maxDia)) {
            $partida['peticiones'][$idx]['recompensa_regalo'] = ['estado' => 'tope_diario'];
            return null;
        }

        $catalog = new CatalogStore($root);
        $objetoId = self::elegirObjeto($cal, $pid, $catalog);
        if ($objetoId === null) {
            $partida['peticiones'][$idx]['recompensa_regalo'] = ['estado' => 'sin_catalogo'];
            return null;
        }

        $alta = InventarioEngine::anadir($partida, $objetoId, 1, $catalog);
        if (empty($alta['ok'])) {
            $partida['peticiones'][$idx]['recompensa_regalo'] = ['estado' => 'sin_hueco'];
            return null;
        }

        $contador['otorgadas'] = (int) $contador['otorgadas'] + 1;
        $partida['peticiones_pueblo']['recompensas_dia'] = $contador;
        $nombreObjeto = self::nombreObjeto($catalog, $objetoId);
        $partida['peticiones'][$idx]['recompensa_regalo'] = [
            'estado' => 'entregada',
            'objeto_id' => $objetoId,
            'via' => 'peticion_cumplida',
        ];

        self::mensajito($partida, $peticion, $objetoId, $nombreObjeto, $logger);

        return [
            'ok' => true,
            'objeto_id' => $objetoId,
            'objeto_nombre' => $nombreObjeto,
            'cantidad' => (int) ($alta['cantidad'] ?? 1),
        ];
    }

    /**
     * Objeto determinista por petición dentro de la lista calibrada
     * (regalos.recompensa_peticion.objetos; fallback: lista canónica corta).
     */
    public static function elegirObjeto(array $cal, string $peticionId, CatalogStore $catalog): ?string
    {
        $lista = CalibracionConfig::get($cal, 'regalos.recompensa_peticion.objetos', []);
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
        return $validas[crc32('obj|' . $peticionId) % count($validas)];
    }

    private static function nombreObjeto(CatalogStore $catalog, string $objetoId): string
    {
        $item = $catalog->item('regalos', $objetoId);
        return (string) (is_array($item) ? ($item['nombre'] ?? $objetoId) : $objetoId);
    }

    /**
     * @param array<string, mixed> $partida
     */
    private static function indiceDe(array $partida, string $peticionId): ?int
    {
        foreach ($partida['peticiones'] ?? [] as $i => $p) {
            if (is_array($p) && (string) ($p['id'] ?? '') === $peticionId) {
                return (int) $i;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $peticion
     */
    private static function mensajito(array &$partida, array $peticion, string $objetoId, string $objetoNombre, ?GameLogger $logger): void
    {
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return;
        }
        // Labs: sin buzón, sin eco (mismo patrón que PeticionFeedback::emitir).
        if (!empty($partida['_lab_peticiones_b4']) || !empty($partida['_lab_misiones_b3'])) {
            return;
        }
        $pid = (string) ($peticion['id'] ?? '');
        $rid = (string) ($peticion['residente_id'] ?? '');
        if ($pid === '' || $rid === '') {
            return;
        }
        $pool = self::poolMensaje();
        $plantilla = $pool[crc32($pid . '|' . $objetoId) % count($pool)];
        $texto = strtr($plantilla, [
            '{nombre}' => IdentidadPublica::nombre($partida, $rid),
            '{objeto}' => $objetoNombre,
        ]);
        $r = BuzonEngine::crear($partida, [
            'clasificacion' => BuzonEngine::OPORTUNIDAD,
            'tipo' => self::TIPO_MENSAJE,
            'canal' => BuzonEngine::CANAL_BUZON,
            'de_persona' => $rid,
            'actores' => [$rid],
            'texto' => $texto,
            'origen' => [
                'evento_id' => self::ORIGEN_EVENTO . ':' . $pid,
                'tipo_evento' => self::TIPO_MENSAJE,
                'es_narrativo' => false,
                'informacion_revelada' => [],
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ]);
        DomainEventDispatcher::emit($partida, DomainEvents::BUZON_MENSAJE, [
            'mensaje' => $r['mensaje'] ?? null,
            'origen_evento' => self::TIPO_MENSAJE,
        ], $logger, 'RegaloRecompensaEngine');
    }
}
