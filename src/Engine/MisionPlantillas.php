<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Biblioteca V1 de misiones diarias. Copy de pueblo, no de motor.
 * Hecho = lo que Celestine controla (organizar), no el resultado RNG.
 */
final class MisionPlantillas
{
    public const FAM_CONOCERSE = 'conocerse';
    public const FAM_QUEDAR = 'quedar';
    public const FAM_PRIMERA_CITA = 'primera_cita';
    public const FAM_CITA = 'cita';
    public const FAM_LUGAR = 'lugar';
    public const FAM_AISLAMIENTO = 'aislamiento';
    public const FAM_DISCOVERY = 'discovery';
    public const FAM_RELACION = 'relacion';
    public const FAM_TEMA = 'tema';
    public const FAM_RUTINA = 'rutina';

    /**
     * @return list<array<string, mixed>>
     */
    public static function catalogo(): array
    {
        return [
            [
                'id' => 'conocerse_dos',
                'familia' => self::FAM_CONOCERSE,
                'prioridad' => 80,
                'exigencia' => 65,
                'hecho' => 'Organizar un Conocerse que llegue a celebrarse. Si rechazan, prueba con otro par.',
                'copy' => 'Hay demasiado desconocido suelto. Presenta a alguien.',
            ],
            [
                'id' => 'quedar_dos',
                'familia' => self::FAM_QUEDAR,
                'prioridad' => 70,
                'exigencia' => 60,
                'hecho' => 'Organizar un Quedar que se celebre entre dos que ya se conocen.',
                'copy' => 'Que dos que ya se conocen queden. Sin más misterio.',
            ],
            [
                'id' => 'primera_cita_hoy',
                'familia' => self::FAM_PRIMERA_CITA,
                'prioridad' => 60,
                'exigencia' => 100,
                'hecho' => 'Organizar una Primera cita que se celebre. El resultado lo deciden ellos.',
                'copy' => 'Hay temita. Organiza una primera cita y apártate.',
            ],
            [
                'id' => 'cita_desbloqueada',
                'familia' => self::FAM_CITA,
                'prioridad' => 55,
                'exigencia' => 95,
                'hecho' => 'Organizar una Cita que se celebre.',
                'copy' => 'Alguien ya puede ir a una cita. No lo dejes en visto.',
            ],
            [
                'id' => 'sitio_del_dia',
                'familia' => self::FAM_LUGAR,
                'prioridad' => 50,
                'exigencia' => 70,
                'hecho' => 'Organizar un encuentro que se celebre en el lugar pedido.',
                'copy' => 'Hoy toca {lugar}. Organiza algo allí.',
            ],
            [
                'id' => 'vida_de_mueble',
                'familia' => self::FAM_AISLAMIENTO,
                'prioridad' => 65,
                'exigencia' => 75,
                'hecho' => 'Que esa persona participe en un encuentro que tú organizas y se celebra.',
                'copy' => '{nombre} está haciendo vida de mueble. Sácala un rato.',
            ],
            [
                'id' => 'por_descubrir',
                'familia' => self::FAM_DISCOVERY,
                'prioridad' => 50,
                'exigencia' => 55,
                'hecho' => 'Organizar un encuentro con esa persona. No hace falta que salga el secreto; basta con montarlo.',
                'copy' => 'De {nombre} sabemos poco. Organiza un encuentro y que corra el aire.',
            ],
            [
                'id' => 'relacion_floja',
                'familia' => self::FAM_RELACION,
                'prioridad' => 45,
                'exigencia' => 80,
                'hecho' => 'Organizar un Quedar entre ese par que se celebre.',
                'copy' => '{a} y {b} tiran a flojo. Que se vean, ya.',
            ],
            [
                'id' => 'cambio_de_sitio',
                'familia' => self::FAM_RUTINA,
                'prioridad' => 40,
                'exigencia' => 40,
                'hecho' => 'Organizar un encuentro que se celebre fuera de la cafetería.',
                'copy' => 'Si es otra vez la cafetería, el pueblo bosteza. Cambia de sitio.',
            ],
            [
                'id' => 'tema_del_dia',
                'familia' => self::FAM_TEMA,
                'prioridad' => 10,
                'exigencia' => 10,
                'hecho' => 'Organizar cualquier encuentro que se celebre hoy.',
                'copy' => 'Hoy el pueblo pide un poquito de tema. Organiza un encuentro.',
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

    public static function nombreLugar(string $lugarId): string
    {
        $n = [
            'lug_cafeteria' => 'la cafetería',
            'lug_parque' => 'el parque',
            'lug_biblioteca' => 'la biblioteca',
            'lug_cine' => 'el cine',
            'lug_plaza' => 'la plaza',
            'lug_restaurante' => 'el restaurante',
            'lug_bar' => 'el bar',
            'lug_discoteca' => 'la discoteca',
            'lug_bingo' => 'el bingo',
            'lug_arcade' => 'el arcade',
            'lug_tienda_ropa' => 'la tienda de ropa',
            'lug_gimnasio' => 'el gimnasio',
            'lug_mirador' => 'el mirador',
        ];
        return $n[$lugarId] ?? 'ese sitio';
    }
}
