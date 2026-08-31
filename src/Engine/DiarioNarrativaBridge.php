<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Espejo persistente: cotilleo narrativo válido (buzón) → partida['diario'].
 * Sin copy nuevo; idempotente por origen.evento_id.
 */
final class DiarioNarrativaBridge
{
    /**
     * Tras BuzonEngine::crear() con clasificación cotilleo y copy real.
     *
     * @param array<string, mixed> $crearResult
     */
    public static function mirrorCotilleoBuzon(array &$partida, array $crearResult): ?array
    {
        if (!($crearResult['ok'] ?? false)) {
            return null;
        }
        $mensaje = $crearResult['mensaje'] ?? null;
        if (!is_array($mensaje)) {
            return null;
        }
        return self::desdeMensaje($partida, $mensaje);
    }

    /**
     * @param array<string, mixed> $mensaje Mensaje enriquecido del buzón
     * @return array<string, mixed>|null entrada creada o existente (duplicado)
     */
    public static function desdeMensaje(array &$partida, array $mensaje): ?array
    {
        $clas = (string) ($mensaje['clasificacion'] ?? '');
        $canal = (string) ($mensaje['canal'] ?? BuzonEngine::canalDe($clas));
        if ($clas !== BuzonEngine::COTILLEO && $canal !== BuzonEngine::CANAL_COTILLEO) {
            return null;
        }
        $texto = trim((string) ($mensaje['texto'] ?? ''));
        if ($texto === '') {
            return null;
        }
        if (!empty($mensaje['_placeholder_contenido'])) {
            return null;
        }

        $tipoBuzon = (string) ($mensaje['tipo'] ?? 'cotilleo');
        if (in_array($tipoBuzon, ['cotilleo_autonomo', 'cotilleo_patron'], true)) {
            return null;
        }

        $origenMsg = is_array($mensaje['origen'] ?? null) ? $mensaje['origen'] : [];
        $tipoEvento = (string) ($origenMsg['tipo_evento'] ?? '');
        if ($tipoBuzon === 'cotilleo_hito' || $tipoEvento === 'relacion_hito') {
            return null;
        }

        $eventoId = self::claveEventoDeMensaje($mensaje);
        if ($eventoId === '') {
            return null;
        }

        $actores = self::actoresDe($mensaje);
        $msgId = (string) ($mensaje['id'] ?? '');
        $dia = (int) ($mensaje['dia'] ?? ($partida['reloj']['dia_pueblo'] ?? 1));
        $ts = is_array($mensaje['ts_juego'] ?? null) ? $mensaje['ts_juego'] : [];

        $entrada = [
            'id' => $msgId !== '' ? 'diario_' . $msgId : 'dia_' . substr(md5($eventoId), 0, 12),
            'dia' => $dia,
            'tipo' => self::tipoDiario($tipoBuzon, $tipoEvento),
            'texto' => $texto,
            'actores' => $actores,
            'origen' => [
                'evento_id' => $eventoId,
                'tipo_evento' => $tipoEvento !== '' ? $tipoEvento : $tipoBuzon,
                'es_narrativo' => (bool) ($origenMsg['es_narrativo'] ?? false),
                'informacion_revelada' => is_array($origenMsg['informacion_revelada'] ?? null)
                    ? $origenMsg['informacion_revelada']
                    : [],
                'buzon_id' => $msgId !== '' ? $msgId : null,
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ];

        if (isset($mensaje['cotilleo_meta']) && is_array($mensaje['cotilleo_meta'])) {
            $entrada['cotilleo_meta'] = $mensaje['cotilleo_meta'];
        }
        if (isset($mensaje['lugar_id']) && is_string($mensaje['lugar_id']) && $mensaje['lugar_id'] !== '') {
            $entrada['lugar_id'] = $mensaje['lugar_id'];
        }
        if ($ts !== []) {
            $entrada['ts_juego'] = $ts;
        }

        $r = DiarioEngine::crear($partida, $entrada);
        return is_array($r['entrada'] ?? null) ? $r['entrada'] : null;
    }

    /**
     * Clave canónica para idempotencia y deduplicación vista global.
     *
     * @param array<string, mixed> $mensaje
     */
    public static function claveEventoDeMensaje(array $mensaje): string
    {
        $origen = is_array($mensaje['origen'] ?? null) ? $mensaje['origen'] : [];
        $eventoId = (string) ($origen['evento_id'] ?? '');
        if ($eventoId !== '') {
            return $eventoId;
        }
        $patron = (string) ($mensaje['patron_clave'] ?? '');
        if ($patron !== '') {
            return 'cotilleo_patron:' . $patron;
        }
        $hito = (string) ($mensaje['hito_clave'] ?? '');
        if ($hito !== '') {
            return $hito;
        }
        $id = (string) ($mensaje['id'] ?? '');
        if ($id !== '') {
            return 'buzon:' . $id;
        }
        return '';
    }

    /**
     * @param array<string, mixed> $mensaje
     * @return list<string>
     */
    private static function actoresDe(array $mensaje): array
    {
        $raw = $mensaje['actores'] ?? [];
        if (!is_array($raw)) {
            $de = $mensaje['de_persona'] ?? null;
            return is_string($de) && $de !== '' ? [$de] : [];
        }
        $out = [];
        foreach ($raw as $id) {
            if (is_string($id) && $id !== '') {
                $out[] = $id;
            }
        }
        if ($out === [] && is_string($mensaje['de_persona'] ?? null) && ($mensaje['de_persona'] ?? '') !== '') {
            $out[] = (string) $mensaje['de_persona'];
        }
        return array_values(array_unique($out));
    }

    private static function tipoDiario(string $tipoBuzon, string $tipoEvento): string
    {
        $known = [
            'cotilleo',
            'cotilleo_patron',
            'cotilleo_hito',
            'cotilleo_autonomo',
            'cotilleo_casual_descubrimiento',
            'discusion',
            'senal_romantica',
            'acontecimiento_perder_trabajo',
            'acontecimiento_encontrar_trabajo',
            'estado_emocional',
        ];
        if (in_array($tipoBuzon, $known, true)) {
            return $tipoBuzon;
        }
        if ($tipoEvento !== '' && $tipoEvento !== 'mensaje') {
            return $tipoEvento;
        }
        return 'cotilleo';
    }

    /**
     * Genera entrada de diario propia cuando cambia el estado emocional de un
     * residente. NO espeja el cotilleo: usa EmocionalNarrativa para copy propio.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $estadoData runtime.estado_emocional
     */
    public static function desdeEmocion(array &$partida, string $residenteId, array $estadoData): ?array
    {
        if (!FeatureConfig::isEnabled($partida, 'diario_enabled')) {
            return null;
        }
        $estadoId = EstadoEmocional::canonId((string) ($estadoData['id'] ?? ''));
        if ($estadoId === EstadoEmocional::NEUTRO) {
            return null;
        }
        $completa = EmocionalNarrativa::explicacionCompleta($partida, $residenteId, $estadoData);
        if ($completa === null) {
            return null;
        }
        $texto = $completa['explicacion'] ?? '';
        if ($texto === '') {
            return null;
        }

        $dia = (int) ($estadoData['desde']['dia'] ?? ($partida['reloj']['dia_pueblo'] ?? 1));
        $origen = (string) ($estadoData['origen'] ?? '');
        $eventoId = 'emocion:' . $residenteId . ':' . $origen . ':' . $dia;

        $existente = DiarioEngine::entradaPorEvento($partida, $eventoId);
        if ($existente !== null) {
            return $existente;
        }

        $textoHash = md5($texto);
        foreach (($partida['diario'] ?? []) as $e) {
            if (($e['tipo'] ?? '') !== 'estado_emocional') continue;
            if (!in_array($residenteId, $e['actores'] ?? [], true)) continue;
            if (md5($e['texto'] ?? '') === $textoHash) {
                return $e;
            }
        }

        $entrada = [
            'id' => 'dia_emocion_' . $residenteId . '_' . $dia,
            'dia' => $dia,
            'tipo' => 'estado_emocional',
            'texto' => $texto,
            'actores' => [$residenteId],
            'origen' => [
                'evento_id' => $eventoId,
                'tipo_evento' => 'estado_emocional',
                'es_narrativo' => true,
                'informacion_revelada' => [
                    'origen_emocional' => $origen,
                    'estado' => $estadoId,
                ],
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ];

        $r = DiarioEngine::crear($partida, $entrada);
        return is_array($r['entrada'] ?? null) ? $r['entrada'] : null;
    }
}
