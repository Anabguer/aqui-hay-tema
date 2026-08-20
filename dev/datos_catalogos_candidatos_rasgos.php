<?php
declare(strict_types=1);

return [
    aht_r('directo', 'social', 'Franqueza', [
        'Va al grano. El rodeito le parece una pérdida de tarde.',
        'Si hay que decirlo, lo dice. El resto es decorado.',
    ], ['corte_seco']),
    aht_r('timido', 'social', 'Timidez', [
        'Le cuesta el primer paso. El segundo, a veces, lo da de una vez.',
        'No es que no quiera: es que la entrada le sale cara. Luego puede cantar karaoke, ojo.',
    ], ['calentamiento'], ['nota' => 'No veta karaoke ni fiesta.']),
    aht_r('reservado', 'social', 'Reserva', [
        'Cuenta poco. No es vacío: es selección.',
        'Si te suelta algo personal, anótalo. No lo repite cada martes.',
    ], ['confidencia_rara']),
    aht_r('observador', 'social', 'Observación', [
        'Se entera de más de lo que habla. Incómodo para quien cotillea alto.',
        'Mira. Luego opina, si acaso. El orden importa.',
    ], ['se_dio_cuenta']),
    aht_r('sociable', 'social', 'Sociabilidad', [
        'La gente le sienta bien. No todas las pistas ni todos los volúmenes.',
        'Necesita caras. Luego puede odiar la discoteca igual. Las dos cosas caben.',
    ], ['se_apunta'], ['nota' => 'No implica discoteca.']),
    aht_r('cotilla', 'social', 'Al tanto', [
        'Se entera. A veces incluso de lo que aún no ha pasado.',
        'El pueblo es su serial. No siempre lo cuenta. Eso ya es ética, o estrategia.',
    ], ['se_entero_antes']),
    aht_r('discreto', 'social', 'Discreción', [
        'No reparte lo que sabe. En este pueblo eso casi es un superpoder.',
        'Si le cuentas algo, se queda ahí. Raro. Valioso.',
    ], ['secreto_a_salvo']),
    aht_r('ironico', 'humor', 'Ironía', [
        'El tono va por otro lado. Si lo tomas al pie de la letra, os peleáis sin motivo.',
        'Bromea en serio. O habla en broma. Adivina.',
    ], ['malentendido_tono']),
    aht_r('bromista', 'humor', 'Bromas', [
        'Suelta chistes como quien suelta aire. Algunos salen bien.',
        'Si el ambiente se pone denso, tira de broma. A veces ayuda. A veces no.',
    ], ['broma_a_destiempo']),
    aht_r('socarron', 'humor', 'Socarronería', [
        'Picarda de pueblo. No es crueldad: es deporte local.',
        'Te pincha para ver si aguantas. Si aguantas, sois amigos. Si no, también, pero más lejos.',
    ], ['pulla']),
    aht_r('leal', 'vinculo', 'Lealtad', [
        'Cuando está, está. No es ruido: es sitio.',
        'No cambia de gente como de chaqueta. Quien está dentro, lo nota.',
    ], ['acude']),
    aht_r('empatico', 'vinculo', 'Empatía', [
        'Se le pegan los estados ajenos. Conveniente y cansado.',
        'Se da cuenta de cómo estás antes de que lo cuentes. Inquietante, útil.',
    ], ['consuelo']),
    aht_r('cabezota', 'vinculo', 'Cabezota', [
        'Cuando se planta, se planta. El resto es negociación de fachada.',
        'No es sordo: es que ya decidió. Matiz importante.',
    ], ['disputa_larga']),
    aht_r('protector', 'vinculo', 'Protección', [
        'Se pone delante. A veces hace falta. A veces ahoga. El arte está en el cuándo.',
        'Cuida. Pregunta si el otro quería ser cuidado.',
    ], ['intervencion']),
    aht_r('independiente', 'vinculo', 'Independencia', [
        'Se basta. No es un rechazo: es un sistema operativo.',
        'Pide poco. Cuando pide, es que importa.',
    ], ['no_necesita'], ['nota' => 'Compatible con vínculo cálido.']),
    aht_r('generoso', 'vinculo', 'Generosidad', [
        'Da. Tiempo, favores, postre. Luego hay que vigilar que no se quede a cero.',
        'Invita. A veces antes de saber si te apetecía. El gesto es bueno; el radar, mejorable.',
    ], ['invita']),
    aht_r('vanidoso', 'ego', 'Vanidad', [
        'Le importa cómo queda. No lo niegues: se le ve el esfuerzo.',
        'Un cumplido le dura. Una pega, también. Maneja ambos con tiento.',
    ], ['espejo']),
    aht_r('orgulloso', 'ego', 'Orgullo', [
        'Pedir perdón le sale caro. No imposible: caro.',
        'Prefiere tener razón a tener paz. Trabajo para Celestine, si te pica.',
    ], ['no_cede']),
    aht_r('ansioso', 'afecto', 'Ansiedad', [
        'Adelanta problemas que aún no han aparcado. El cuerpo va por delante.',
        'Si no le llega un mensaje, se inventa tres películas. Ninguna es buena.',
    ], ['espera']),
    aht_r('calido', 'afecto', 'Calidez', [
        'Calienta el cuarto. No es azúcar: es presencia.',
        'Se le está bien al lado. Luego puede pedir espacio igual. Cabe.',
    ], ['abrazo_a_tiempo']),
    aht_r('desconfiado', 'afecto', 'Desconfianza', [
        'No compra la primera versión. Ni la segunda, según el día.',
        'La gente nueva le parece un tráiler. El filme, más adelante.',
    ], ['prueba_de_fuego']),
    aht_r('sensible', 'afecto', 'Sensible', [
        'Le llega todo un poco más alto. No es fragilidad: es volumen.',
        'Una frase de pasada le dura la tarde. Elige las tuyas.',
    ], ['comentario_cruzado']),
    aht_r('practico', 'cognicion', 'Pragmatismo', [
        'Va a lo que funciona. La teoría, si acaso, de postre.',
        'Si el plan no se puede hacer, lo dice. Sin poesía. Gracias.',
    ], ['atajo']),
    aht_r('curioso', 'cognicion', 'Curiosidad', [
        'Pregunta. Se mete. Quiere saber cómo va la cosa por dentro.',
        'Un dato nuevo le alegra el día más que un pastel. Casi.',
    ], ['pregunta_incómoda']),
    aht_r('soñador', 'cognicion', 'Ensoñación', [
        'Se va. Vuelve. A veces trae un plan imposible y un brillo raro.',
        'La realidad le parece un primer borrador. Hay que quererle así.',
    ], ['idea_grande']),
    aht_r('constante', 'ritmo', 'Constancia', [
        'Mantiene. Lo de cada martes, lo de cada planta, lo de cada persona.',
        'No presume de disciplina. Simplemente no deja que se caiga.',
    ], ['racha']),
    aht_r('impaciente', 'ritmo', 'Impaciencia', [
        'Lo quiere ya. Esperar le parece un deporte ajeno.',
        'Si el plan tarda, se le pone cara de bus perdido.',
    ], ['darse_prisa']),
    aht_r('competitivo', 'ritmo', 'Competitividad', [
        'Todo le sabe un poco a partido. Hasta un café, si se descuida.',
        'Ganar le sienta bien. Perder, ya es otro rasgo o una manía. No lo mezcles todavía.',
    ], ['reto']),
    aht_r('perezoso', 'ritmo', 'Pereza', [
        'Economiza movimiento. No es que no quiera: es que el sofá hace campaña.',
        'Se apunta… y luego negocia la hora. Suele ganar el sofá. Salvo karaoke. Pasa.',
    ], ['queda_para_luego']),
    aht_r('tranquilo', 'ritmo', 'Calma', [
        'No se altera a la primera. Ni a la segunda. A la tercera, ya veremos.',
        'Baja el volumen de la mesa sin decir nada. Útil. A veces desesperante.',
    ], ['no_pasa_nada']),
    aht_r('nervioso', 'ritmo', 'Nervios', [
        'Se le ve el motor. La pierna, el vaso, la frase a medias.',
        'El cuerpo avisa antes que la boca. Si aprendes el código, llegas a tiempo.',
    ], ['no_para']),
    aht_r('caotico', 'ritmo', 'Caos cotidiano', [
        'El orden le parece una sugerencia. Llega, pero no por el camino del mapa.',
        'Improvisa. A veces sale bien. El resto es anécdota, o incendio.',
    ], ['cambio_de_plan']),
];
