<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Causa humana de Organizar. Códigos estables; el copy final es de C2.
 * El motor no inventa reglas: solo explica por qué este par/tipo no encaja.
 */
final class OrganizarMotivo
{
    public const MISMA_PERSONA = 'misma_persona';
    public const FALTAN_PERSONAS = 'faltan_personas';
    public const AUN_NO_SE_CONOCEN = 'aun_no_se_conocen';
    public const YA_SE_CONOCEN = 'ya_se_conocen';
    public const TODAVIA_NO = 'todavia_no';
    public const TIPO_NO_ENCAJA = 'tipo_no_encaja';

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     * @return array{codigo: ?string, tipos: list<string>, tipo_sugerido: ?string}
     */
    public static function de(array $partida, string $a, string $b, string $tipoPedido = '', array $cal = []): array
    {
        $tipoPedido = PropuestaNivel::aliasTipo($tipoPedido);
        if ($a === '' || $b === '') {
            return ['codigo' => self::FALTAN_PERSONAS, 'tipos' => [], 'tipo_sugerido' => null];
        }
        if ($a === $b) {
            return ['codigo' => self::MISMA_PERSONA, 'tipos' => [], 'tipo_sugerido' => null];
        }
        $tipos = PropuestaNivel::tiposPermitidos($partida, $a, $b, $cal);
        $sugerido = $tipos[0] ?? null;
        if ($tipoPedido === '' || in_array($tipoPedido, $tipos, true)) {
            return ['codigo' => null, 'tipos' => $tipos, 'tipo_sugerido' => $sugerido];
        }
        $codigo = self::TIPO_NO_ENCAJA;
        if (!RelacionEngine::seConocen($partida, $a, $b) && $tipoPedido !== PropuestaNivel::PRESENTAR) {
            $codigo = self::AUN_NO_SE_CONOCEN;
        } elseif (RelacionEngine::seConocen($partida, $a, $b) && $tipoPedido === PropuestaNivel::PRESENTAR) {
            $codigo = self::YA_SE_CONOCEN;
        } elseif (PropuestaNivel::esTipoCita($tipoPedido)) {
            $codigo = self::TODAVIA_NO;
        }
        return ['codigo' => $codigo, 'tipos' => $tipos, 'tipo_sugerido' => $sugerido];
    }

    /** Placeholder. C2 puede sustituir por voz de pueblo. */
    public static function mensajeUi(?string $codigo): string
    {
        if ($codigo === self::MISMA_PERSONA) {
            return 'Elige a dos personas distintas.';
        }
        if ($codigo === self::FALTAN_PERSONAS) {
            return 'Falta elegir con quién.';
        }
        if ($codigo === self::AUN_NO_SE_CONOCEN) {
            return 'Todavía no se conocen.';
        }
        if ($codigo === self::YA_SE_CONOCEN) {
            return 'Esas dos ya se conocen.';
        }
        if ($codigo === self::TODAVIA_NO) {
            return 'Todavía no tienen tanta confianza.';
        }
        if ($codigo === self::TIPO_NO_ENCAJA) {
            return 'Ese plan no encaja entre estas dos ahora.';
        }
        return '';
    }

    /**
     * Candidatos a un hueco de Organizar. Nunca incluye a la otra persona.
     *
     * @param array<string, mixed> $partida
     * @return list<array{id: string, nombre: string}>
     */
    public static function candidatos(array $partida, string $excluido = ''): array
    {
        $out = [];
        foreach ($partida['residentes'] ?? [] as $id => $res) {
            if (!is_string($id) || $id === '' || $id === $excluido) {
                continue;
            }
            if (($res['presencia'] ?? 'residente') !== 'residente') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'nombre' => IdentidadPublica::nombre($partida, $id),
            ];
        }
        return $out;
    }
}
