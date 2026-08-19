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
