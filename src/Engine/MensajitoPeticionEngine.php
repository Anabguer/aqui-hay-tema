<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * F3 petición/encargo y F5 presentación/conexión — integración PeticionEngine → Mensajito.
 *
 * El vecino escribe a Celestine en primera persona; la familia etiqueta el canal
 * y cablea acciones jugables (organizar / elegir persona).
 */
final class MensajitoPeticionEngine
{
    public const F3 = 'f_peticion';
    public const F5 = 'f_presentacion';

    /**
     * Etiqueta el mensaje de buzón recién creado para una petición B4.
     *
     * @param array<string, mixed> $peticion
     */
    public static function aplicarFamilia(array &$partida, string $buzonId, array $peticion): void
    {
        if ($buzonId === '' || empty($peticion['schema_b4'])) {
            return;
        }
        $plantillaId = (string) ($peticion['plantilla_id'] ?? '');
        $familia = $plantillaId === 'conocer_a_alguien' ? self::F5 : self::F3;
        $params = is_array($peticion['params'] ?? null) ? $peticion['params'] : [];
        $rid = (string) ($peticion['residente_id'] ?? '');
        $datos = [
            'plantilla_id' => $plantillaId,
            'peticion_id' => (string) ($peticion['id'] ?? ''),
            'clave' => $familia . '|' . $plantillaId . '|' . $rid,
        ];
        if (isset($params['otro']) && is_string($params['otro']) && $params['otro'] !== '') {
            $datos['otro_id'] = $params['otro'];
            $datos['otro_nombre'] = IdentidadPublica::nombre($partida, $params['otro']);
        }
        $acciones = [];
        if ($familia === self::F5 && !empty($params['opciones']) && is_array($params['opciones'])) {
            $acciones = [MensajitoAcciones::ELEGIR_PERSONA];
        } elseif ($familia === self::F5 && !empty($datos['otro_id'])) {
            $acciones = [MensajitoAcciones::ORGANIZAR_ENCARGO];
        } elseif ($familia === self::F3) {
            $acciones = [MensajitoAcciones::ORGANIZAR_ENCARGO];
        }
        foreach ($partida['buzon'] as &$m) {
            if (!is_array($m) || (string) ($m['id'] ?? '') !== $buzonId) {
                continue;
            }
            $m['familia_mensajito'] = $familia;
            $m['datos_familia'] = $datos;
            if ($acciones !== []) {
                $m['acciones'] = $acciones;
                $m['estado_decision'] = BuzonEngine::DECISION_PENDIENTE;
            }
            if ($familia === self::F5 && !empty($datos['otro_nombre'])) {
                $historial = HistorialPar::contextoNarrativo($partida, $rid, (string) $datos['otro_id']);
                $voz = MensajitoVoz::linea(
                    $partida,
                    self::F5,
                    [
                        'otro' => (string) $datos['otro_nombre'],
                        'historial' => $historial,
                    ],
                    self::F5 . '|' . $rid . '|' . ($datos['otro_id'] ?? ''),
                    $rid
                );
                if ($voz !== '') {
                    $m['texto'] = $voz . ' ' . PeticionPuebloEngine::plazoHumano($peticion, $partida);
                }
            } elseif ($familia === self::F3 && trim((string) ($m['texto'] ?? '')) !== '') {
                $extra = MensajitoVoz::linea(
                    $partida,
                    self::F3,
                    ['texto' => trim((string) ($peticion['texto'] ?? 'un favor'))],
                    self::F3 . '|' . $rid . '|' . $plantillaId,
                    $rid
                );
                if ($extra !== '' && !str_contains((string) $m['texto'], $extra)) {
                    // Solo enriquece si el copy de plantilla es muy genérico
                    if (strlen(trim((string) ($peticion['texto'] ?? ''))) < 20) {
                        $m['texto'] = $extra . ' ' . PeticionPuebloEngine::plazoHumano($peticion, $partida);
                    }
                }
            }
            $m['origen']['es_narrativo'] = true;
            $m['origen']['tipo_evento'] = 'peticion_' . $familia;
            break;
        }
        unset($m);
        if ($familia === self::F5) {
            MensajitosCadenciaEngine::registrar($partida, $rid, self::F5, 'peticion', (string) $datos['clave']);
        } else {
            MensajitosCadenciaEngine::registrar($partida, $rid, self::F3, 'peticion', (string) $datos['clave']);
        }
    }

    /**
     * Refuerza metadatos F5 cuando hay selector de presentación.
     *
     * @param list<array{personaje_id: string, nombre: string, pista: ?string}> $opciones
     */
    public static function marcarPresentacionSelector(array &$partida, string $buzonId, array $opciones): void
    {
        foreach ($partida['buzon'] as &$m) {
            if (!is_array($m) || (string) ($m['id'] ?? '') !== $buzonId) {
                continue;
            }
            $m['familia_mensajito'] = self::F5;
            $datos = is_array($m['datos_familia'] ?? null) ? $m['datos_familia'] : [];
            $datos['subtipo'] = 'selector';
            $datos['n_opciones'] = count($opciones);
            $m['datos_familia'] = $datos;
            $rid = (string) ($m['de_persona'] ?? '');
            if ($rid !== '') {
                $voz = MensajitoVoz::linea(
                    $partida,
                    self::F5,
                    ['texto' => (string) count($opciones)],
                    self::F5 . '|selector|' . $rid . '|' . count($opciones),
                    $rid
                );
                if ($voz !== '') {
                    $pid = (string) ($m['peticion_id'] ?? '');
                    $plazo = '';
                    foreach ($partida['peticiones'] ?? [] as $p) {
                        if (is_array($p) && (string) ($p['id'] ?? '') === $pid) {
                            $plazo = PeticionPuebloEngine::plazoHumano($p, $partida);
                            break;
                        }
                    }
                    $m['texto'] = $voz . ($plazo !== '' ? ' ' . $plazo : '');
                }
            }
            break;
        }
        unset($m);
    }

    /**
     * @return array<string, mixed>
     */
    public static function organizarEncargo(array &$partida, string $mensajeId): array
    {
        $mensaje = BuzonEngine::buscar($partida, $mensajeId);
        if ($mensaje === null) {
            return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
        }
        $pid = (string) ($mensaje['peticion_id'] ?? '');
        $pet = null;
        foreach ($partida['peticiones'] ?? [] as $p) {
            if (is_array($p) && (string) ($p['id'] ?? '') === $pid) {
                $pet = $p;
                break;
            }
        }
        if ($pet === null || ($pet['estado'] ?? '') !== PeticionPuebloEngine::EST_ABIERTA) {
            return ['ok' => false, 'error' => 'peticion_no_abierta'];
        }
        $preset = PeticionPuebloEngine::presetOrganizarParaUi($partida, $pet);
        if ($preset === null) {
            return ['ok' => false, 'error' => 'sin_preset'];
        }
        return [
            'ok' => true,
            'preset_organizar' => $preset,
            'mensaje_ui' => 'Vamos a organizarlo.',
        ];
    }
}
