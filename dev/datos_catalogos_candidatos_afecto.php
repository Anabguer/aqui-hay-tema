<?php
declare(strict_types=1);

return [
    'nota' => 'Cómo se vincula. NO sustituye Relacional V1 (fases, señales, romance, orientación, dealbreakers, ejes_preferencia sobre la otra persona). Celestine lo descubre con frases, no como columnas.',
    'ejes' => [
        [
            'id' => 'espacio_personal',
            'etiqueta' => 'Espacio',
            'valores' => [
                [
                    'id' => 'amplio',
                    'etiqueta' => 'Necesita sitio',
                    'descubrimientos' => [
                        'Quiere cerca, pero no encima. El “todo el rato” le ahoga aunque le gustes.',
                        'Un mensaje al día le vale. Diez, le piden aire. No es desinterés: es pulmón.',
                        'Después de un rato bueno, se retira. Quien lo lee como corte, se equivoca.',
                        'El hueco entre quedadas no es vacío. Es cómo aguanta el vínculo.',
                    ],
                    'hooks' => ['quedar_cada_dia_mal'],
                ],
                [
                    'id' => 'normal',
                    'etiqueta' => 'Según',
                    'descubrimientos' => [
                        'Ni se esfuma ni se pega. Raro en este pueblo, casi un lujo.',
                        'Aguanta cercanía y también un paréntesis. El extremo, en cualquiera, le cansa.',
                        'Un “te escribo mañana” no ofende. Un silencio de una semana, ya se nota.',
                        'Pide sitio cuando lo necesita y se acerca cuando apetece. Sin manual.',
                    ],
                    'hooks' => [],
                ],
                [
                    'id' => 'cercano',
                    'etiqueta' => 'Cerca',
                    'descubrimientos' => [
                        'Si le gustas, se nota en la agenda. Pide rato. A veces más rato del que hay.',
                        'El hueco le pica. No siempre lo dice. Se le ve en el mensaje de más.',
                        'Una semana sin verse le parece mucho. A quien necesita aire, le parece poco.',
                        'Aparece. Escribe. Queda. El calor es el modo por defecto.',
                    ],
                    'hooks' => ['mensaje_seguido'],
                ],
            ],
        ],
        [
            'id' => 'ritmo_vinculo',
            'etiqueta' => 'Ritmo de vínculo',
            'valores' => [
                [
                    'id' => 'lento',
                    'etiqueta' => 'Despacio',
                    'descubrimientos' => [
                        'No acelera. Si aprietas, se cierra. No es desinterés: es tempo.',
                        'Las prisas le saben a examen. El café repetido, a conocimiento.',
                        'Un “ya somos algo” pronto le cierra la boca. Un rato más, la abre.',
                        'Hay que dejar que el vínculo ande. Empujarlo, lo tuerce.',
                    ],
                    'hooks' => ['primera_cita_pronta'],
                ],
                [
                    'id' => 'normal',
                    'etiqueta' => 'Al paso',
                    'descubrimientos' => [
                        'Ni prisa ni eternidad. Se deja llevar si el otro no empuja con pala.',
                        'Un paso más, si hay motivo. Un freno, si hay empujón. El medio, aquí, es carácter.',
                        'No dramatiza el calendario del querer. Tampoco lo ignora.',
                        'Encaja con quien no corre y con quien no se eterniza. Los extremos piden conversación.',
                    ],
                    'hooks' => [],
                ],
                [
                    'id' => 'deprisa',
                    'etiqueta' => 'Deprisa',
                    'descubrimientos' => [
                        'Cuando se enciende, quiere ya. El “vamos a conocernos” le sabe a sala de espera.',
                        'El siguiente paso le parece natural. A la otra parte, a veces, un salto.',
                        'Espera señales pronto. El silencio largo le parece un no, aunque no lo sea.',
                        'Hay que poner palabras al ritmo. Si no, se adelanta y luego hay que recoger.',
                    ],
                    'hooks' => ['todo_a_la_vez'],
                ],
            ],
        ],
        [
            'id' => 'demostracion',
            'etiqueta' => 'Cómo se nota',
            'valores' => [
                ['id' => 'palabras', 'etiqueta' => 'Diciéndolo', 'descubrimientos' => [
                    'Si le importas, lo dice. O lo escribe. El silencio no es su idioma.',
                    'Un mensaje claro le sale. Un detalle mudo, a veces se le olvida.',
                    'Las cartas, los recados, la frase a tiempo: ahí está el cariño.',
                    'Quien espera solo gestos puede pasar por alto que ya lo ha dicho.',
                ], 'hooks' => ['carta', 'mensaje']],
                ['id' => 'gestos', 'etiqueta' => 'Con detalles', 'descubrimientos' => [
                    'No suelta discursos. Aparece con un café, un favor, un arreglo. Traduce eso.',
                    'El “te quiero” le cuesta. El recado hecho, no.',
                    'Hay que mirar lo que hace, no lo que no dice. El manual está ahí.',
                    'Un detalle pequeño es el volumen alto. Un discurso, a veces, le sale torcido.',
                ], 'hooks' => ['detalle']],
                ['id' => 'tiempo', 'etiqueta' => 'Estando', 'descubrimientos' => [
                    'Regala horas. En este pueblo eso es más íntimo que un ramo.',
                    'Quedarse es el mensaje. Irse pronto, a veces, se lee mal y hay que explicarlo.',
                    'Una tarde entera dice más que una frase. El calendario, aquí, es cariño.',
                    'Pide rato, no espectáculo. El banco del parque le vale más que un anuncio.',
                ], 'hooks' => ['tarde_entera']],
                ['id' => 'regalos', 'etiqueta' => 'Con cosas', 'descubrimientos' => [
                    'Un objeto bien elegido le sale del alma. Uno genérico, al revés.',
                    'El envoltorio es un idioma. Quien no lo habla, se pierde el recado.',
                    'Cumpleaños, un capricho, una pieza rara: ahí se le ve el cuidado.',
                    'No es consumo: es que la cosa concreta le parece más honrada que un discurso.',
                ], 'hooks' => ['cumple', 'capricho']],
                ['id' => 'presencia', 'etiqueta' => 'Aparcando cerca', 'descubrimientos' => [
                    'Se presenta. En el bingo, en la puerta, en el recado. Estar es el mensaje.',
                    'No hace falta un detalle. Hace falta que aparezca. Suele aparecer.',
                    'La silla de al lado es el gesto. El ramo, según el día, es extra.',
                    'Si no puede estar, se nota el hueco. El mensaje, a veces, llega tarde.',
                ], 'hooks' => ['aparece']],
            ],
        ],
    ],
];
