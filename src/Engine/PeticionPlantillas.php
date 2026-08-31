<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Encargos personales V1. Nacen del estado real; el hecho lo controla Celestine.
 */
final class PeticionPlantillas
{
    public const FAM_AISLAMIENTO = 'aislamiento';
    public const FAM_LUGAR = 'lugar';
    public const FAM_CONOCER = 'conocer';
    public const FAM_REVER = 'reencuentro';
    public const FAM_QUEDAR = 'quedar';
    public const FAM_RUTINA = 'rutina';
    public const FAM_CITA = 'primera_cita';

    /**
     * @return list<array<string, mixed>>
     */
    public static function catalogo(): array
    {
        return [
            [
                'id' => 'salir_de_casa',
                'familia' => self::FAM_AISLAMIENTO,
                'tipo_legado' => 'tiempo',
                'peso' => PeticionEsquemas::PESO_FACIL,
                'plazo_horas' => 24,
                'prioridad' => 70,
                'exigencia' => 50,
                'hecho' => 'Organizar un encuentro que se celebre con esa persona.',
                'copy' => 'Llevo días sin salir. Sácame de casa.',
                'copy_pool' => [
                    'Llevo días sin salir. Sácame de casa.',
                    'Necesito aire. ¿Me organizas algo?',
                    'Tengo las paredes encima. Sácame un rato.',
                    'Hoy no quiero estar solo en casa. ¿Me ayudas?',
                ],
            ],
            [
                'id' => 'ir_al_lugar',
                'familia' => self::FAM_LUGAR,
                'tipo_legado' => 'lugar',
                'peso' => PeticionEsquemas::PESO_FACIL,
                'plazo_horas' => 24,
                'prioridad' => 55,
                'exigencia' => 40,
                'hecho' => 'Organizar un encuentro que se celebre en el lugar pedido, con quien pide.',
                'copy' => 'Me apetece ir a {lugar}.',
                'copy_pool' => [
                    'Me apetece ir a {lugar}.',
                    'Tengo las ganas de ir a {lugar}. ¿Algo se puede montar?',
                    'Oye, que me apetece ir a {lugar}.',
                    'Me apetece ir a {lugar}. No sé si se puede organizar algo.',
                ],
            ],
            [
                'id' => 'conocer_a_alguien',
                'familia' => self::FAM_CONOCER,
                'tipo_legado' => 'conocer',
                'peso' => PeticionEsquemas::PESO_RELEVANTE,
                'plazo_horas' => 48,
                'prioridad' => 65,
                'exigencia' => 70,
                'hecho' => 'Organizar un Conocerse que se celebre con quien pide.',
                'copy' => 'Me gustaría conocer a alguien.',
                'copy_pool' => [
                    'Me gustaría conocer a alguien.',
                    'Quiero conocer gente nueva. ¿Me presentas a alguien?',
                    'No conozco a casi nadie aquí. ¿Me echas una mano?',
                    'Me apetece conocer a alguien. ¿Tú a quién me presentarías?',
                ],
            ],
            [
                'id' => 'volver_a_ver',
                'familia' => self::FAM_REVER,
                'tipo_legado' => 'relacion',
                'peso' => PeticionEsquemas::PESO_RELEVANTE,
                'plazo_horas' => 48,
                'prioridad' => 60,
                'exigencia' => 75,
                'hecho' => 'Organizar un Quedar que se celebre con la persona pedida.',
                'copy' => 'Quiero volver a ver a {otro}.',
                'copy_pool' => [
                    'Quiero volver a ver a {otro}.',
                    'Me apetece quedar con {otro} otra vez.',
                    'No sé si será posible, pero me gustaría ver a {otro} de nuevo.',
                    'Me apetece retomar con {otro}.',
                ],
            ],
            [
                'id' => 'quedar_con_x',
                'familia' => self::FAM_QUEDAR,
                'tipo_legado' => 'tiempo',
                'peso' => PeticionEsquemas::PESO_RELEVANTE,
                'plazo_horas' => 24,
                'prioridad' => 58,
                'exigencia' => 65,
                'hecho' => 'Organizar un Quedar que se celebre con la persona pedida.',
                'copy' => 'Quiero quedar con {otro}.',
                'copy_pool' => [
                    'Quiero quedar con {otro}.',
                    'Me apetece quedar con {otro}. ¿Se puede organizar?',
                    'Tengo ganas de quedar con {otro}.',
                    'Necesito quedar con {otro}. ¿Me ayudas?',
                ],
            ],
            [
                'id' => 'algo_distinto',
                'familia' => self::FAM_RUTINA,
                'tipo_legado' => 'actividad',
                'peso' => PeticionEsquemas::PESO_FACIL,
                'plazo_horas' => 12,
                'prioridad' => 40,
                'exigencia' => 35,
                'hecho' => 'Organizar un encuentro que se celebre fuera de la cafetería, con quien pide.',
                'copy' => 'Necesito hacer algo distinto.',
                'copy_pool' => [
                    'Necesito hacer algo distinto.',
                    'Hoy toca romper la rutina. ¿Me propones algo?',
                    'Estoy harto de lo mismo. Necesito un plan diferente.',
                    'Algo distinto. Por favor.',
                ],
            ],
            [
                'id' => 'primera_cita_pet',
                'familia' => self::FAM_CITA,
                'tipo_legado' => 'relacion',
                'peso' => PeticionEsquemas::PESO_DIFICIL,
                'plazo_horas' => 72,
                'prioridad' => 25,
                'exigencia' => 100,
                'hecho' => 'Organizar una Primera cita que se celebre con esa persona.',
                'copy' => 'Quiero una primera cita con {otro}.',
                'copy_pool' => [
                    'Quiero una primera cita con {otro}.',
                    'Me gustaría quedar con {otro}, algo tranquilo.',
                    'No sé cómo plantearlo, pero me apetece quedar con {otro}.',
                    'Tengo que quedar con {otro}.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function porId(string $id): ?array
    {
        foreach (self::catalogo() as $p) {
            if (($p['id'] ?? '') === $id) {
                return $p;
            }
        }
        return null;
    }
}
