<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Candidatos de recompensa/penalización B4. NO son canon.
 * El lab compara; Neni/ChatGPT eligen antes de calibrar.
 */
final class PeticionEsquemas
{
    public const PESO_FACIL = 'facil';
    public const PESO_RELEVANTE = 'relevante';
    public const PESO_DIFICIL = 'dificil';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function catalogo(): array
    {
        return [
            'E1' => [
                'id' => 'E1',
                'label' => 'Todas +1, ninguna válida; fallo −1',
                'ok' => [
                    self::PESO_FACIL => 1,
                    self::PESO_RELEVANTE => 1,
                    self::PESO_DIFICIL => 1,
                ],
                'valido' => [
                    self::PESO_FACIL => false,
                    self::PESO_RELEVANTE => false,
                    self::PESO_DIFICIL => false,
                ],
                'fail' => [
                    self::PESO_FACIL => -1,
                    self::PESO_RELEVANTE => -1,
                    self::PESO_DIFICIL => -1,
                ],
                'max_validos_dia' => 0,
            ],
            'E2' => [
                'id' => 'E2',
                'label' => 'Fácil +1 no válido; resto +1 válido; fallo −1/−2; máx. 1 válido/día',
                'ok' => [
                    self::PESO_FACIL => 1,
                    self::PESO_RELEVANTE => 1,
                    self::PESO_DIFICIL => 1,
                ],
                'valido' => [
                    self::PESO_FACIL => false,
                    self::PESO_RELEVANTE => true,
                    self::PESO_DIFICIL => true,
                ],
                'fail' => [
                    self::PESO_FACIL => -1,
                    self::PESO_RELEVANTE => -2,
                    self::PESO_DIFICIL => -2,
                ],
                'max_validos_dia' => 1,
            ],
            'E3' => [
                'id' => 'E3',
                'label' => 'Fácil +1 no válido; relevante +1 válido; difícil +2 válido; fallo −1; máx. 1 válido/día',
                'ok' => [
                    self::PESO_FACIL => 1,
                    self::PESO_RELEVANTE => 1,
                    self::PESO_DIFICIL => 2,
                ],
                'valido' => [
                    self::PESO_FACIL => false,
                    self::PESO_RELEVANTE => true,
                    self::PESO_DIFICIL => true,
                ],
                'fail' => [
                    self::PESO_FACIL => -1,
                    self::PESO_RELEVANTE => -1,
                    self::PESO_DIFICIL => -1,
                ],
                'max_validos_dia' => 1,
            ],
            'E4' => [
                'id' => 'E4',
                'label' => 'Todas +1; 1 válido/día (no fácil); fallo −2',
                'ok' => [
                    self::PESO_FACIL => 1,
                    self::PESO_RELEVANTE => 1,
                    self::PESO_DIFICIL => 1,
                ],
                'valido' => [
                    self::PESO_FACIL => false,
                    self::PESO_RELEVANTE => true,
                    self::PESO_DIFICIL => true,
                ],
                'fail' => [
                    self::PESO_FACIL => -2,
                    self::PESO_RELEVANTE => -2,
                    self::PESO_DIFICIL => -2,
                ],
                'max_validos_dia' => 1,
            ],
            'E5' => [
                'id' => 'E5',
                'label' => 'Fácil/relevante +1 no válido; difícil +2 válido; fallo −1; máx. 1 válido/día',
                'ok' => [
                    self::PESO_FACIL => 1,
                    self::PESO_RELEVANTE => 1,
                    self::PESO_DIFICIL => 2,
                ],
                'valido' => [
                    self::PESO_FACIL => false,
                    self::PESO_RELEVANTE => false,
                    self::PESO_DIFICIL => true,
                ],
                'fail' => [
                    self::PESO_FACIL => -1,
                    self::PESO_RELEVANTE => -1,
                    self::PESO_DIFICIL => -1,
                ],
                'max_validos_dia' => 1,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function de(string $id): array
    {
        $all = self::catalogo();
        if (isset($all[$id])) {
            return $all[$id];
        }
        return $all['E2'];
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function activo(array $cal = [], array $partida = []): array
    {
        $id = (string) ($partida['_b4_esquema'] ?? '');
        if ($id === '') {
            $id = (string) CalibracionConfig::get($cal, 'peticiones_pueblo.esquema_activo', 'E2');
        }
        return self::de($id);
    }
}
