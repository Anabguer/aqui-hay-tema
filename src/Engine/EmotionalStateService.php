<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Aplica estado interno y resuelve expresión. Sin fórmulas de juego. */
final class EmotionalStateService
{
    public function __construct(
        private VisualPackStore $packs,
        private CatalogStore $catalog,
        private ?GameLogger $logger = null
    ) {
    }

    public static function packIdDe(array $residente, VisualPackStore $packs): ?string
    {
        $explicit = $residente['visual_pack_id'] ?? $residente['runtime']['visual_pack_id'] ?? null;
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }
        $catalogId = (string) ($residente['catalog_id'] ?? '');
        return $catalogId !== '' ? $packs->packIdParaCatalogo($catalogId) : null;
    }

    public function resolverResidente(array $partida, array $residente): array
    {
        EstadoEmocional::ensureResidente($residente, $partida['reloj'] ?? null);
        $packId = self::packIdDe($residente, $this->packs);
        $pack = $packId ? $this->packs->pack($packId) : null;
        $est = $residente['runtime']['estado_emocional'];
        $exp = $residente['runtime']['expresion_visual'];

        return ExpressionResolver::resolver([
            'estado_emocional_id' => $est['id'],
            'intensidad' => $est['intensidad'],
            'personalidad' => $residente['personalidad'] ?? [],
            'contexto' => array_merge(
                is_array($est['contexto'] ?? null) ? $est['contexto'] : [],
                [
                    'origen' => $est['origen'],
                    'pack_id' => $packId,
                ]
            ),
            'override_dev' => $exp['override_dev'] ?? null,
            'pack' => $pack,
            'pack_id' => $packId,
        ], $this->packs, $this->catalog);
    }

    public function aplicar(
        array &$partida,
        string $residenteId,
        string $estadoId,
        string $origen = 'dev_manual',
        int|float|null $intensidad = null,
        ?array $hasta = null,
        array $contexto = [],
        ?int $duracionHoras = null
    ): array {
        if (!isset($partida['residentes'][$residenteId])) {
            return ['ok' => false, 'error' => 'residente_no_encontrado'];
        }
        $res = &$partida['residentes'][$residenteId];
        EstadoEmocional::ensureResidente($res, $partida['reloj'] ?? null);
        $antes = $res['runtime']['estado_emocional'];

        $desde = EstadoEmocional::marcaReloj($partida['reloj'] ?? null);
        if ($hasta === null && $duracionHoras !== null) {
            $hasta = EstadoEmocional::hastaDesdeDuracion($partida['reloj'] ?? $desde, $duracionHoras);
        }
        $res['runtime']['estado_emocional'] = EstadoEmocional::estructura(
            $estadoId,
            $intensidad,
            $origen,
            $desde,
            $hasta,
            $contexto,
            $duracionHoras
        );
        $res['runtime']['animo'] = $estadoId; // alias legacy de estado_emocional.id

        $resolved = $this->resolverResidente($partida, $res);
        $this->escribirExpresion($res, $resolved);

        DomainEventDispatcher::emit($partida, DomainEvents::ESTADO_EMOCIONAL_CAMBIADO, [
            'residente_id' => $residenteId,
            'antes' => $antes,
            'despues' => $res['runtime']['estado_emocional'],
            'expresion_id' => $resolved['expression_id'],
            'actores' => [$residenteId],
        ], $this->logger, 'EmotionalStateService::aplicar');

        DomainEventDispatcher::emit($partida, DomainEvents::EXPRESION_VISUAL_RESUELTA, [
            'residente_id' => $residenteId,
            'expression_id' => $resolved['expression_id'],
            'solicitada' => $resolved['solicitada'] ?? null,
            'motivo' => $resolved['motivo'],
            'actores' => [$residenteId],
        ], $this->logger, 'EmotionalStateService::resolver');

        return ['ok' => true, 'estado_emocional' => $res['runtime']['estado_emocional'], 'expresion' => $resolved];
    }

    public function overrideExpresionDev(array &$partida, string $residenteId, ?string $expressionId): array
    {
        if (!isset($partida['residentes'][$residenteId])) {
            return ['ok' => false, 'error' => 'residente_no_encontrado'];
        }
        if ($expressionId !== null && $expressionId !== '' && !ExpresionVisual::idFormatoValido($expressionId)) {
            return ['ok' => false, 'error' => 'expression_id_formato'];
        }
        $res = &$partida['residentes'][$residenteId];
        EstadoEmocional::ensureResidente($res, $partida['reloj'] ?? null);
        $res['runtime']['expresion_visual']['override_dev'] = ($expressionId === null || $expressionId === '')
            ? null
            : $expressionId;

        $resolved = $this->resolverResidente($partida, $res);
        $this->escribirExpresion($res, $resolved);

        return [
            'ok' => true,
            'estado_emocional' => $res['runtime']['estado_emocional'],
            'expresion' => $resolved,
            'sin_evento_de_juego' => true,
        ];
    }

    public function inventarioResidente(array $partida, string $residenteId): array
    {
        if (!isset($partida['residentes'][$residenteId])) {
            return ['ok' => false, 'error' => 'residente_no_encontrado'];
        }
        $res = $partida['residentes'][$residenteId];
        EstadoEmocional::ensureResidente($res, $partida['reloj'] ?? null);
        $packId = self::packIdDe($res, $this->packs);
        $resumen = $packId ? $this->packs->resumenPack($packId, $this->catalog) : null;
        $resolved = $this->resolverResidente($partida, $res);
        $pack = $packId ? $this->packs->pack($packId) : null;
        $disponibles = is_array($pack) ? $this->packs->idsDeclarados($pack) : [];
        $disponiblesOk = [];
        if (is_array($pack)) {
            foreach ($disponibles as $eid) {
                if ($this->packs->disponible($pack, $eid)) {
                    $disponiblesOk[] = $eid;
                }
            }
        }
        return [
            'ok' => true,
            'residente_id' => $residenteId,
            'pack_id' => $packId,
            'pack' => $resumen,
            'estado_emocional' => $res['runtime']['estado_emocional'],
            'expresion_solicitada' => $resolved['solicitada'] ?? ($res['runtime']['expresion_visual']['solicitada'] ?? null),
            'expresion_resuelta' => $resolved['expression_id'],
            'expresiones_disponibles' => $disponiblesOk,
            'expresiones_declaradas' => $disponibles,
            'fallback' => (bool) ($resolved['fallback'] ?? false),
            'motivo' => $resolved['motivo'] ?? null,
            'expresion' => $resolved,
            'sin_pack' => $packId === null,
        ];
    }

    public function expirarVencidos(array &$partida): int
    {
        $n = 0;
        $reloj = $partida['reloj'] ?? [];
        foreach (array_keys($partida['residentes'] ?? []) as $id) {
            EstadoEmocional::ensureResidente($partida['residentes'][$id], $reloj);
            $hasta = $partida['residentes'][$id]['runtime']['estado_emocional']['hasta'] ?? null;
            if (EstadoEmocional::vencido(is_array($hasta) ? $hasta : null, $reloj)) {
                $this->aplicar($partida, (string) $id, EstadoEmocional::NEUTRO, 'expiracion', null, null);
                $n++;
            }
        }
        return $n;
    }

    private function escribirExpresion(array &$res, array $resolved): void
    {
        $res['runtime']['expresion_visual']['id'] = $resolved['expression_id'];
        $res['runtime']['expresion_visual']['fallback'] = $resolved['fallback'];
        $res['runtime']['expresion_visual']['motivo'] = $resolved['motivo'];
        $res['runtime']['expresion_visual']['solicitada'] = $resolved['solicitada'] ?? null;
    }
}
