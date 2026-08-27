<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Generador espontaneo de Mensajitos (familias F1, F2, F4, F6, F7, F14, F15).
 *
 * Los NPCs se dirigen a Celestine cuando tienen algo que decir, disparado
 * por su estado real (emociones, relaciones, eventos recientes, personalidad).
 * NO es feed general: solo publica cuando hay valor jugable para Celestine.
 *
 * Provisional: cifras y probabilidades son tecnicas, no canon.
 */
final class MensajitoGeneradorEspontaneo
{
    public static function evaluar(array &$partida, string $residenteId, array $cal, RngService $rng): ?array
    {
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return null;
        }
        if (MensajitosCadenciaEngine::enCooldownVecino($partida, $residenteId, $cal)) {
            return null;
        }
        if (!MensajitosCadenciaEngine::hayPresupuesto($partida, $cal)) {
            return null;
        }
        $familias = self::familiasCandidatas($partida, $residenteId, $cal);
        if ($familias === []) {
            return null;
        }
        usort($familias, static fn(array $a, array $b) => $a['peso'] <=> $b['peso']);
        $elegida = $familias[0];
        return self::generar($partida, $residenteId, $elegida, $cal, $rng);
    }

    /**
     * @return list<array{familia: string, peso: int, datos: array<string, mixed>}>
     */
    private static function familiasCandidatas(array $partida, string $residenteId, array $cal): array
    {
        $c = [];
        $catalog = new Catalog(dirname(__DIR__, 2));
        $f4 = MensajitoColectivoEngine::candidato($partida, $residenteId, $cal, $catalog);
        if ($f4 !== null) {
            $c[] = $f4;
        }
        $f1 = self::candidatoF1($partida, $residenteId, $cal);
        if ($f1 !== null) {
            $c[] = $f1;
        }
        $f2 = self::candidatoF2($partida, $residenteId);
        if ($f2 !== null) {
            $c[] = $f2;
        }
        $f6 = self::candidatoF6($partida, $residenteId);
        if ($f6 !== null) {
            $c[] = $f6;
        }
        $f7 = self::candidatoF7($partida, $residenteId);
        if ($f7 !== null) {
            $c[] = $f7;
        }
        $f15 = self::candidatoF15($partida, $residenteId, $cal);
        if ($f15 !== null) {
            $c[] = $f15;
        }
        return $c;
    }

    private static function candidatoF1(array $partida, string $rid, array $cal): ?array
    {
        $rels = self::relacionesSignificativas($partida, $rid);
        if ($rels === []) { return null; }
        $prob = (float) CalibracionConfig::get($cal, 'mensajitos.f1_prob_base', 0.15);
        if (mt_rand(1, 10000) > $prob * 10000) { return null; }
        $rel = $rels[array_rand($rels)];
        $clave = 'f_opinion|' . ($rel['otro_id'] ?? '');
        if (MensajitoConsejoEngine::yaExisteHiloReciente($partida, $rid, 'f_opinion', $clave)) {
            return null;
        }
        return ['familia' => 'f_opinion', 'peso' => 2, 'datos' => ['otro_id' => $rel['otro_id'], 'otro_nombre' => $rel['otro_nombre'], 'tipo_relacion' => $rel['tipo'], 'clave' => $clave]];
    }

    private static function candidatoF2(array $partida, string $rid): ?array
    {
        $rom = self::senalesRomanticas($partida, $rid);
        if (count($rom) < 2) { return null; }
        shuffle($rom);
        $pair = array_slice($rom, 0, 2);
        $clave = 'f_dilema|' . ($pair[0]['otro_id'] ?? '') . '|' . ($pair[1]['otro_id'] ?? '');
        if (MensajitoConsejoEngine::yaExisteHiloReciente($partida, $rid, 'f_dilema', $clave)) {
            return null;
        }
        return ['familia' => 'f_dilema', 'peso' => 2, 'datos' => ['opcion_a_id' => $pair[0]['otro_id'], 'opcion_a_nombre' => $pair[0]['otro_nombre'], 'opcion_b_id' => $pair[1]['otro_id'], 'opcion_b_nombre' => $pair[1]['otro_nombre'], 'clave' => $clave]];
    }

    private static function candidatoF6(array $partida, string $rid): ?array
    {
        $crush = self::crushLatente($partida, $rid);
        if ($crush !== null) {
            $clave = 'confidencia|crush|' . ($crush['otro_id'] ?? '');
            $rec = MensajitoPromesaEngine::datosRecuerdoSiAplica($partida, $rid, [
                'subtipo' => 'crush',
                'otro_id' => $crush['otro_id'],
                'clave' => $clave,
            ]);
            if ($rec !== null) {
                return ['familia' => 'f_promesa', 'peso' => 1, 'datos' => $rec];
            }
            if (MensajitoConsejoEngine::yaExisteHiloReciente($partida, $rid, 'f_confidencia', $clave)) {
                return null;
            }
            return [
                'familia' => 'f_confidencia',
                'peso' => 3,
                'datos' => [
                    'subtipo' => 'crush',
                    'otro_id' => $crush['otro_id'],
                    'otro_nombre' => $crush['otro_nombre'],
                    'emocion' => 'nervioso/a',
                    'clave' => $clave,
                ],
            ];
        }
        $ems = self::emocionesFuertes($partida, $rid);
        if ($ems === []) {
            return null;
        }
        $em = $ems[array_rand($ems)];
        $clave = 'confidencia|' . ($em['tipo'] ?? 'general') . '|' . $rid;
        $rec = MensajitoPromesaEngine::datosRecuerdoSiAplica($partida, $rid, [
            'emocion' => $em['tipo'],
            'subtipo' => $em['tipo'],
            'clave' => $clave,
        ]);
        if ($rec !== null) {
            return ['familia' => 'f_promesa', 'peso' => 1, 'datos' => $rec];
        }
        if (MensajitoConsejoEngine::yaExisteHiloReciente($partida, $rid, 'f_confidencia', $clave)) {
            return null;
        }
        return [
            'familia' => 'f_confidencia',
            'peso' => 3,
            'datos' => [
                'emocion' => $em['tipo'],
                'subtipo' => $em['tipo'],
                'intensidad' => $em['intensidad'],
                'clave' => $clave,
            ],
        ];
    }

    /**
     * @return array{otro_id: string, otro_nombre: string}|null
     */
    private static function crushLatente(array $partida, string $rid): ?array
    {
        foreach (self::senalesRomanticas($partida, $rid) as $r) {
            $otro = (string) ($r['otro_id'] ?? '');
            if ($otro === '') {
                continue;
            }
            $rom = 0.0;
            if (isset($partida['relaciones'][$rid][$otro]['romance'])) {
                $rom = (float) $partida['relaciones'][$rid][$otro]['romance'];
            } elseif (isset($partida['relaciones'][$otro][$rid]['romance'])) {
                $rom = (float) $partida['relaciones'][$otro][$rid]['romance'];
            }
            if ($rom >= 12 && $rom <= 28) {
                return $r;
            }
        }
        return null;
    }

    private static function candidatoF7(array $partida, string $rid): ?array
    {
        $ap = self::vecinosApagados($partida, $rid);
        if ($ap === []) { return null; }
        $t = $ap[array_rand($ap)];
        $clave = 'f_alerta|' . ($t['residente_id'] ?? '');
        if (MensajitoConsejoEngine::yaExisteHiloReciente($partida, $rid, 'f_alerta_vecinal', $clave)) {
            return null;
        }
        return ['familia' => 'f_alerta_vecinal', 'peso' => 2, 'datos' => ['observado_id' => $t['residente_id'], 'observado_nombre' => $t['nombre'], 'clave' => $clave]];
    }

    private static function candidatoF15(array $partida, string $rid, array $cal): ?array
    {
        $cen = self::cercaniaNarrativa($partida, $rid);
        $umbral = (float) CalibracionConfig::get($cal, 'mensajitos.f15_cercania_umbral', 0.6);
        if ($cen < $umbral) { return null; }
        $prob = (float) CalibracionConfig::get($cal, 'mensajitos.f15_prob_base', 0.08);
        if (mt_rand(1, 10000) > $prob * 10000) { return null; }
        return ['familia' => 'f_curiosidad_celestine', 'peso' => 3, 'datos' => []];
    }

    private static function generar(array &$partida, string $rid, array $fam, array $cal, RngService $rng): ?array
    {
        $texto = self::textoFamilia($partida, $rid, $fam['familia'], $fam['datos']);
        if ($texto === '') { return null; }
        switch ($fam['familia']) {
            case 'f_alerta_vecinal':
                $clas = BuzonEngine::IMPORTANTE;
                break;
            case 'f_colectivo':
                $clas = BuzonEngine::OPORTUNIDAD;
                break;
            default:
                $clas = BuzonEngine::OPORTUNIDAD;
        }
        switch ($fam['familia']) {
            case 'f_opinion':
            case 'f_dilema':
                $acciones = ['responder_consejo'];
                break;
            case 'f_confidencia':
            case 'f_promesa':
                $acciones = ['responder_escuchar'];
                break;
            case 'f_colectivo':
                $acciones = ['aceptar_evento', 'declinar_evento'];
                break;
            case 'f_alerta_vecinal':
                $acciones = ['investigar', 'organizar_algo', 'no_meterse'];
                break;
            case 'f_curiosidad_celestine':
                $acciones = ['responder_celestine'];
                break;
            default:
                $acciones = [];
        }
        $msgId = 'msg_' . bin2hex(random_bytes(4));
        $r = CanalDeduplicador::crearSiAplica($partida, [
            'id' => $msgId,
            'clasificacion' => $clas,
            'tipo' => 'espontaneo_' . $fam['familia'],
            'canal' => BuzonEngine::CANAL_BUZON,
            'de_persona' => $rid,
            'actores' => [$rid],
            'texto' => $texto,
            'acciones' => $acciones,
            'familia_mensajito' => $fam['familia'],
            'datos_familia' => $fam['datos'],
            'hilo_id' => $msgId,
            'hilo_estado' => 'abierto',
            'seguimiento_pendiente' => in_array($fam['familia'], ['f_opinion', 'f_dilema', 'f_confidencia'], true),
            'origen' => ['evento_id' => $msgId, 'tipo_evento' => 'espontaneo_' . $fam['familia'], 'es_narrativo' => true, 'informacion_revelada' => [], '_placeholder' => false],
            '_placeholder_contenido' => false,
        ]);
        if ($r === null || !($r['ok'] ?? false)) {
            return null;
        }
        MensajitosCadenciaEngine::registrar($partida, $rid, $fam['familia'], 'espontaneo', (string) ($fam['datos']['clave'] ?? ''));
        DomainEventDispatcher::emit($partida, DomainEvents::BUZON_MENSAJE, [
            'mensaje' => $r['mensaje'] ?? null,
            'origen_evento' => 'espontaneo_' . $fam['familia'],
        ], null, 'MensajitoGeneradorEspontaneo');
        return $r['mensaje'] ?? null;
    }

    private static function textoFamilia(array &$partida, string $rid, string $familia, array $datos): string
    {
        switch ($familia) {
            case 'f_opinion':
                return MensajitoVoz::linea($partida, 'f_opinion', ['otro' => $datos['otro_nombre'] ?? '', 'texto' => $datos['tipo_relacion'] ?? 'amigo'], 'f_opinion|' . $rid . '|' . ($datos['otro_id'] ?? ''), $rid);
            case 'f_dilema':
                return MensajitoVoz::linea($partida, 'f_dilema', ['nombre_a' => $datos['opcion_a_nombre'] ?? '', 'nombre_b' => $datos['opcion_b_nombre'] ?? '', 'texto' => 'dos personas'], 'f_dilema|' . $rid, $rid);
            case 'f_confidencia':
                if (($datos['subtipo'] ?? '') === 'crush') {
                    return MensajitoVoz::linea(
                        $partida,
                        'f_confidencia_crush',
                        ['otro' => $datos['otro_nombre'] ?? ''],
                        'f_confidencia_crush|' . $rid . '|' . ($datos['otro_id'] ?? ''),
                        $rid
                    );
                }
                return MensajitoVoz::linea(
                    $partida,
                    'f_confidencia',
                    ['texto' => $datos['emocion'] ?? 'algo personal'],
                    'f_confidencia|' . $rid . '|' . ($datos['subtipo'] ?? ''),
                    $rid
                );
            case 'f_promesa':
                return MensajitoVoz::linea(
                    $partida,
                    'f_promesa',
                    ['texto' => $datos['tema'] ?? 'lo de siempre'],
                    'f_promesa|' . $rid . '|' . ($datos['clave'] ?? ''),
                    $rid
                );
            case 'f_colectivo':
                return MensajitoVoz::linea(
                    $partida,
                    'f_colectivo',
                    ['texto' => $datos['evento_nombre'] ?? 'algo en el pueblo'],
                    'f_colectivo|' . $rid . '|' . ($datos['evento_catalogo_id'] ?? ''),
                    $rid
                );
            case 'f_alerta_vecinal':
                return MensajitoVoz::linea($partida, 'f_alerta_vecinal', ['otro' => $datos['observado_nombre'] ?? '', 'texto' => 'apagado'], 'f_alerta|' . $rid . '|' . ($datos['observado_id'] ?? ''), $rid);
            case 'f_curiosidad_celestine':
                return MensajitoVoz::linea($partida, 'f_curiosidad_celestine', [], 'f_curiosidad|' . $rid, $rid);
        }
        return '';
    }

    private static function relacionesSignificativas(array $partida, string $rid): array
    {
        $out = [];
        $seen = [];
        foreach ($partida['relaciones'] ?? [] as $rA => $rels) {
            if (!is_array($rels)) { continue; }
            foreach ($rels as $rB => $val) {
                if (!is_array($val) || !is_string($rB)) { continue; }
                if ($rA !== $rid && $rB !== $rid) { continue; }
                $otro = $rA === $rid ? $rB : $rA;
                if (isset($seen[$otro])) { continue; }
                $social = (float) ($val['social'] ?? 0);
                $romance = (float) ($val['romance'] ?? 0);
                $tipo = $romance > 10 ? 'romance' : ($social > 30 ? 'muy_cercano' : ($social > 20 ? 'cercano' : 'amigo'));
                $nombre = IdentidadPublica::nombre($partida, $otro);
                if ($social > 20 || $romance > 10) {
                    $out[] = ['otro_id' => $otro, 'otro_nombre' => $nombre, 'tipo' => $tipo];
                    $seen[$otro] = true;
                }
            }
        }
        return $out;
    }

    private static function senalesRomanticas(array $partida, string $rid): array
    {
        $out = [];
        $seen = [];
        foreach ($partida['relaciones'] ?? [] as $rA => $rels) {
            if (!is_array($rels)) { continue; }
            foreach ($rels as $rB => $val) {
                if (!is_array($val) || !is_string($rB)) { continue; }
                if ($rA !== $rid && $rB !== $rid) { continue; }
                $otro = $rA === $rid ? $rB : $rA;
                if (isset($seen[$otro])) { continue; }
                if ((float) ($val['romance'] ?? 0) > 15) {
                    $out[] = ['otro_id' => $otro, 'otro_nombre' => IdentidadPublica::nombre($partida, $otro)];
                    $seen[$otro] = true;
                }
            }
        }
        return $out;
    }

    private static function emocionesFuertes(array $partida, string $rid): array
    {
        $out = [];
        foreach ($partida['residentes'][$rid]['emociones'] ?? [] as $em) {
            if (is_array($em) && (int) ($em['intensidad'] ?? 0) >= 3) {
                $out[] = ['tipo' => (string) ($em['tipo'] ?? 'general'), 'intensidad' => (int) $em['intensidad']];
            }
        }
        return $out;
    }

    private static function vecinosApagados(array $partida, string $rid): array
    {
        $out = [];
        $bajos = ['triste', 'solo', 'aislado', 'preocupado', 'apagado', 'neutro'];
        foreach ($partida['residentes'] ?? [] as $otro => $rData) {
            if ($otro === $rid || !is_array($rData)) { continue; }
            if (!self::seConocen($partida, $rid, $otro)) { continue; }
            $motivo = null;
            $ems = $rData['emociones'] ?? [];
            if (is_array($ems)) {
                foreach ($ems as $em) {
                    if (!is_array($em)) { continue; }
                    $est = (string) ($em['estado'] ?? $em['tipo'] ?? '');
                    if (in_array($est, $bajos, true) && (int) ($em['intensidad'] ?? 1) >= 2) {
                        $motivo = $est;
                        break;
                    }
                }
            }
            if ($motivo === null) {
                $emoRt = (string) ($rData['runtime']['estado_emocional']['id'] ?? '');
                if (in_array($emoRt, ['triste', 'apagado'], true)) {
                    $motivo = $emoRt;
                }
            }
            if ($motivo !== null) {
                $out[] = ['residente_id' => $otro, 'nombre' => IdentidadPublica::nombre($partida, $otro), 'motivo' => $motivo];
            }
        }
        return $out;
    }

    private static function seConocen(array $partida, string $a, string $b): bool
    {
        $rels = $partida['relaciones'][$a] ?? [];
        if (!is_array($rels)) { return false; }
        return isset($rels[$b]) && is_array($rels[$b]);
    }

    private static function cercaniaNarrativa(array $partida, string $rid): float
    {
        $hist = $partida['mensajitos_historial'] ?? [];
        $n = 0;
        foreach ($hist as $h) {
            if (is_array($h) && ($h['residente_id'] ?? '') === $rid) {
                $n++;
            }
        }
        return min(1.0, $n / 10.0);
    }
}