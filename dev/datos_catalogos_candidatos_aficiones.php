<?php
declare(strict_types=1);

$U_ALL = ['lugares', 'eventos', 'conversaciones', 'regalos', 'rutinas', 'compatibilidad', 'descubrimientos', 'celestine'];
$U_LUG = ['lugares', 'rutinas', 'descubrimientos', 'celestine'];
$U_NAR = ['conversaciones', 'descubrimientos', 'cotilleo', 'regalos'];

return [
    aht_candidato_item('leer', 'lectura', 'Leer', ['lug_biblioteca', 'lug_cafeteria', 'lug_parque'], $U_ALL, [
        'Como entre en una librería, la hemos perdido.',
        'Un libro y un rincón tranquilo y ya tiene la tarde hecha.',
        'Es bastante rata de biblioteca. Lo dice como un cumplido, creo.',
    ], ['regalo_libro', 'se_pierde_en_la_biblioteca'], ['social' => 'mixto']),
    aht_candidato_item('escribir', 'lectura', 'Escribir', ['lug_biblioteca', 'lug_cafeteria'], $U_ALL, [
        'Si se queda mirando un cuaderno, no le hables. Está en otra parte.',
        'Escribe. No presume. Luego te enteras por una esquina de papel.',
    ], ['cuaderno_olvidado', 'lectura_publica'], ['social' => 'solo']),
    aht_candidato_item('comics', 'lectura', 'Cómics', ['lug_biblioteca', 'lug_tienda', 'lug_cafeteria'], $U_ALL, [
        'No es que no lea: lee con dibujitos y se ofende si lo dices así.',
        'Tiene una pila de cómics que defiende como quien defiende a un primo.',
    ], ['regalo_tomo', 'discutir_canon'], ['social' => 'mixto']),
    aht_candidato_item('cine_sala', 'cine', 'Ir al cine', ['lug_cine'], $U_ALL, [
        'El cine de verdad, con butaca y palomitas. La pantalla del salón no le sirve.',
        'Si hay sesión, se organiza la semana alrededor. Sin drama. Bueno, un poco.',
    ], ['estreno', 'palomitas_sagradas'], ['social' => 'mixto', 'nota' => 'La sala. El género va en gustos.']),
    aht_candidato_item('ver_en_casa', 'cine', 'Ver series en casa', [], ['conversaciones', 'regalos', 'rutinas', 'descubrimientos', 'compatibilidad'], [
        'El sofá es su sala vip. No espera que nadie lo entienda.',
        'Mantiene maratones con una seriedad que otros reservan para el trabajo.',
    ], ['maraton', 'spoiler_guerra'], ['social' => 'solo', 'nota' => 'Sin destino del pueblo; alimenta regalos y planes en casa.']),
    aht_candidato_item('videojuegos_cozy', 'juegos', 'Juegos tranquilitos', ['lug_cafeteria'], $U_ALL, [
        'Juega a cosas bonitas donde nadie te grita. Lo cuenta como quien habla de punto de cruz.',
        'No es “gamer”. Es que le calma plantar tomates digitales. Déjalo.',
    ], ['regalo_juego_suave', 'se_queda_hasta_tarde'], ['social' => 'solo']),
    aht_candidato_item('videojuegos_competitivo', 'juegos', 'Juegos de competir', ['lug_arcade'], $U_ALL, [
        'Si pierde, la casa se entera. Si gana, también.',
        'Le brilla el ojo cuando hay ranking. Luego dice que es solo un juego.',
    ], ['rabieta_sana', 'reto_1v1'], ['social' => 'mixto']),
    aht_candidato_item('arcade', 'juegos', 'Máquinas recreativas', ['lug_arcade'], $U_ALL, [
        'Las máquinas del arcade le devuelven a los quince años y no pide perdón.',
        'Cambia monedas con una solemnidad de misa.',
    ], ['record_local', 'monedas'], ['social' => 'mixto']),
    aht_candidato_item('juegos_mesa', 'juegos', 'Juegos de mesa', ['lug_cafeteria', 'lug_picnic'], $U_ALL, [
        'Si saca una caja, la sobremesa se alarga. Avisa a quien tenga que madrugar.',
        'Explica reglas con paciencia. La primera vez. La segunda, ya veremos.',
    ], ['partida_tensa', 'regalo_caja'], ['social' => 'grupo']),
    aht_candidato_item('bingo', 'juegos', 'Bingo', ['lug_bingo'], $U_ALL, [
        'El bingo no es un chiste. Es una cita con el destino y un rotulador.',
        'Los martes tiene un compromiso. No preguntes si es con una persona.',
    ], ['carton_casi', 'ritual_martes'], ['social' => 'grupo', 'pueblo' => true]),
    aht_candidato_item('escuchar_musica', 'musica', 'Escuchar música', ['lug_bar', 'lug_parque'], $U_ALL, [
        'Anda con banda sonora. Si se la quitas, se queda a medias.',
        'No hace falta que cante: con que suene, ya está en su sitio.',
    ], ['auriculares', 'recomendacion_canción'], ['social' => 'solo', 'nota' => 'No implica bailar ni karaoke.']),
    aht_candidato_item('tocar_instrumento', 'musica', 'Tocar un instrumento', ['lug_bar'], $U_ALL, [
        'Tiene un instrumento y una relación seria con él. El vecindario lo confirma.',
        'Si pide un rato a solas, suele ser para eso. No para rumiar. Bueno, las dos cosas.',
    ], ['ensayo_oido', 'jam_improvisada'], ['social' => 'mixto']),
    aht_candidato_item('conciertos', 'musica', 'Música en directo', ['lug_bar'], $U_ALL, [
        'Prefiere la música con gente sudando al lado y un escenario pequeño.',
        'Un bolo en el bar le arregla una semana entera. O se la fastidia, según el sonido.',
    ], ['bolo_local', 'llegar_tarde_por_la_cola'], ['social' => 'grupo']),
    aht_candidato_item('karaoke', 'musica', 'Karaoke', ['lug_karaoke'], $U_ALL, [
        'En el karaoke se le olvida el resto. Incluido el pudor, a veces.',
        'No canta bien. Canta como si le fuera el alquiler. Es peor y mejor.',
    ], ['tema_fetiche', 'dueto_inesperado'], ['social' => 'grupo']),
    aht_candidato_item('correr', 'movimiento', 'Correr', ['lug_parque', 'lug_gimnasio'], $U_ALL, [
        'Sale a correr como quien paga una deuda con su cabeza.',
        'Si amanece y no ha salido, el día le sale torcido. Aviso a navegantes.',
    ], ['quedar_a_correr', 'lesion_leve'], ['social' => 'mixto', 'tags' => ['deporte']]),
    aht_candidato_item('bici', 'movimiento', 'Bici', ['lug_parque'], $U_ALL, [
        'La bici no es un medio: es una personalidad con ruedas.',
        'Habla de puertos y de viento como otros hablan de series.',
    ], ['quedada_bici', 'pinchazo_dramatico'], ['social' => 'mixto', 'tags' => ['deporte']]),
    aht_candidato_item('deporte_equipo', 'movimiento', 'Deporte de equipo', ['lug_parque'], $U_ALL, [
        'Necesita un balón y otras cuatro personas. El sofá no le sustituye.',
        'Gana, pierde, pero sobre todo discute el fuera de juego hasta la cena.',
    ], ['partido_pueblo', 'lesion_orgullo'], ['social' => 'grupo', 'tags' => ['deporte']]),
    aht_candidato_item('gimnasio', 'movimiento', 'Gimnasio', ['lug_gimnasio'], $U_ALL, [
        'El gimnasio le cuadra. Las pesas no le deben nada y se lo agradece.',
        'Tiene una hora sagrada entre máquinas. Celestine, no la pises sin motivo.',
    ], ['rutina_hierro', 'espejo'], ['social' => 'solo', 'tags' => ['deporte'], 'nota' => 'Se puede ser de deporte y odiar ESTE sitio.']),
    aht_candidato_item('baile', 'movimiento', 'Bailar', ['lug_discoteca', 'lug_gimnasio'], $U_ALL, [
        'Baila. No “le gusta la música”: se le va el cuerpo solo.',
        'En la pista se le entiende mejor que en una sobremesa.',
    ], ['clase_baile', 'sacar_a_bailar'], ['social' => 'grupo']),
    aht_candidato_item('yoga', 'movimiento', 'Yoga / movilidad', ['lug_spa', 'lug_parque', 'lug_gimnasio'], $U_ALL, [
        'Estira, respira y pide que no le conviertan eso en chiste de incienso.',
        'No es que sea zen. Es que si no se mueve despacio, explota.',
    ], ['clase_suave', 'esterilla'], ['social' => 'mixto']),
    aht_candidato_item('pasear', 'aire_libre', 'Pasear', ['lug_parque', 'lug_mirador'], $U_ALL, [
        'Un paseo le arregla más que un consejo. Y sale más barato.',
        'Camina para pensar. Si le acompañas, no hace falta rellenar el silencio.',
    ], ['paseo_largo', 'atajo_secreto'], ['social' => 'mixto']),
    aht_candidato_item('senderismo', 'aire_libre', 'Senderismo', ['lug_parque', 'lug_mirador'], $U_ALL, [
        'Si hay cuesta, se anima. El pueblo llano le sabe a poco.',
        'Habla de botas con el respeto que otros reservan a los zapatos de boda.',
    ], ['ruta_domingo', 'blister'], ['social' => 'mixto', 'tags' => ['deporte']]),
    aht_candidato_item('plantas', 'aire_libre', 'Plantas', ['lug_parque', 'lug_picnic'], $U_ALL, [
        'Habla con las plantas. No es metáfora. O sí, pero da igual: ellas tiran.',
        'Si se le muere un geranio, hay duelo. Bajo, pero hay duelo.',
    ], ['esqueje', 'planta_regalo'], ['social' => 'solo']),
    aht_candidato_item('perros', 'aire_libre', 'Perros', ['lug_parque'], $U_ALL, [
        'Los perros ajenos ya son un poco suyos. Los dueños tardan en enterarse.',
        'Si hay un hocico en el parque, la conversación se resuelve sola.',
    ], ['paseo_canino', 'perro_ajeno'], ['social' => 'mixto']),
    aht_candidato_item('picnic', 'aire_libre', 'Picnic', ['lug_picnic', 'lug_parque'], $U_ALL, [
        'El mantel en el césped es su idea de lujo. Hormigas incluidas.',
        'Organiza comidas al aire con una logística de boda pequeña.',
    ], ['tupper', 'avispas'], ['social' => 'grupo']),
    aht_candidato_item('mirador', 'aire_libre', 'Mirador / vistas', ['lug_mirador'], $U_ALL, [
        'Se sube al mirador a mirar. Punto. No todo atardecer es una declaración.',
        'Le sienta bien el pueblo visto de lejos. A veces también la gente.',
    ], ['atardecer', 'silencio_compartido'], ['social' => 'mixto', 'nota' => 'No es código de romance.']),
    aht_candidato_item('cocina', 'mesa', 'Cocinar', ['lug_restaurante'], $U_ALL, [
        'Cocina de verdad. Si te invita, ve. Si te invita y dices que da igual, se ofende.',
        'Prueba recetas con una fe que otros reservan para las quinielas.',
    ], ['cena_en_casa', 'regalo_cacharro'], ['social' => 'mixto']),
    aht_candidato_item('restaurantes', 'mesa', 'Salir a comer', ['lug_restaurante'], $U_ALL, [
        'Le encanta que le pongan la mesa. Cocinar está bien; que le cocinen, también.',
        'Elige restaurante como quien elige película: con veto incluido.',
    ], ['mesa_reservada', 'plato_fetiche'], ['social' => 'mixto']),
    aht_candidato_item('cafe_con_gente', 'mesa', 'Café y conversación', ['lug_cafeteria'], $U_ALL, [
        'La cafetería es su despacho. El café, una excusa bastante convincente.',
        'Si dice “un café”, cuenta una hora. Mínimo.',
    ], ['mesa_de_siempre', 'cafe_frio_de_tanto_hablar'], ['social' => 'grupo']),
    aht_candidato_item('merendar', 'mesa', 'Merendar', ['lug_cafeteria', 'lug_picnic'], $U_ALL, [
        'Defiende la merienda como institución. Con razón.',
        'Un dulce a media tarde le pone de un humor que conviene aprovechar.',
    ], ['pastel', 'merienda_brigada'], ['social' => 'mixto']),
    aht_candidato_item('manualidades', 'manos', 'Manualidades', ['lug_biblioteca', 'lug_cafeteria'], $U_ALL, [
        'Hace cosas con las manos y luego finge que no es para regalarlas. Lo es.',
        'Si hay taller, aparece. Si no hay taller, se lo inventa en la mesa de la cocina.',
    ], ['taller', 'regalo_hecho'], ['social' => 'mixto']),
    aht_candidato_item('costura', 'manos', 'Costura', ['lug_tienda'], $U_ALL, [
        'Cose. Arregla. Opina de dobladillos con autoridad moral.',
        'Un botón suelto le pica más que un rumor.',
    ], ['arreglo_favor', 'tela_bonita'], ['social' => 'solo']),
    aht_candidato_item('bricolaje', 'manos', 'Bricolaje', [], ['regalos', 'rutinas', 'conversaciones', 'descubrimientos'], [
        'Si algo se mueve y no debería, ya está buscando el destornillador.',
        'Ayuda. A veces sin que se lo pidan. A veces sin que haga falta. El resultado varía.',
    ], ['estanteria', 'dedo_golpeado'], ['social' => 'solo']),
    aht_candidato_item('fotografia', 'manos', 'Fotografía', ['lug_mirador', 'lug_parque', 'lug_picnic'], $U_ALL, [
        'Para el paso para una foto y el pueblo entero espera, resignado.',
        'No hace selfies. Hace “espera, la luz”. Es peor.',
    ], ['foto_robada', 'album'], ['social' => 'mixto']),
    aht_candidato_item('coleccionismo', 'manos', 'Coleccionar', ['lug_tienda', 'lug_bingo'], $U_ALL, [
        'Colecciona algo concreto y puede explicarlo veinte minutos. Tú verás.',
        'Un hallazgo en la tienda le ilumina la semana. No preguntes el precio.',
    ], ['pieza_rara', 'trueque'], ['social' => 'solo']),
    aht_candidato_item('moda', 'manos', 'Moda y escaparates', ['lug_tienda'], $U_ALL, [
        'La tienda no es consumo: es teatro. Mira, toca, opina, a veces compra.',
        'Se le ve el humor en el peinado. Y en si ha pasado o no por los aparadores.',
    ], ['probarse', 'consejo_look'], ['social' => 'mixto']),
    aht_candidato_item('copas', 'noche', 'Copas', ['lug_bar'], $U_ALL, [
        'Una copa le suelta la lengua. Dos, la agenda. Tres, ya es El Cotilleo quien informa.',
        'El bar le sienta bien. No lo confundas con ganas de discoteca.',
    ], ['ronda', 'confesion_barra'], ['social' => 'grupo']),
    aht_candidato_item('fiesta', 'noche', 'Fiesta', ['lug_discoteca', 'lug_bar'], $U_ALL, [
        'Cuando hay fiesta, aparece. Cuando no hay, a veces la monta.',
        'La noche le mejora el carácter. A algunos les pasa lo contrario, aviso.',
    ], ['salir_de_marcha', 'amanecer'], ['social' => 'grupo']),
    aht_candidato_item('spa', 'cuidado', 'Spa', ['lug_spa'], $U_ALL, [
        'El spa no es capricho: es mantenimiento. Como el coche, pero con albornoz.',
        'Si el día ha sido largo, no pide un plan: pide agua caliente y que no le molesten.',
    ], ['bono_spa', 'silencio_toalla'], ['social' => 'solo']),
    aht_candidato_item('asociacion', 'pueblo', 'Asociaciones del pueblo', ['lug_cafeteria', 'lug_bingo'], $U_ALL, [
        'Se apunta a lo del pueblo. Fiestas, listas, lo que se tercie. Alguien tiene que hacerlo.',
        'Sabe quién organiza qué. Es información sensible. Trátala como tal.',
    ], ['comision', 'cartel'], ['social' => 'grupo', 'pueblo' => true]),
    aht_candidato_item('crucigramas', 'lectura', 'Pasatiempos', ['lug_cafeteria', 'lug_biblioteca'], $U_ALL, [
        'Hace crucigramas como quien entrena para no morder a nadie.',
        'Si se atasca en una definición, el café se enfría. Prioridades.',
    ], ['ayuda_7_horizontal', 'periodico'], ['social' => 'solo']),
    aht_candidato_item('tertulia', 'pueblo', 'Tertulia', ['lug_bar', 'lug_cafeteria'], $U_ALL, [
        'Le gusta hablar. De verdad. No el ruido: el hilo.',
        'Si la conversación está buena, se le olvida la hora. Y a veces la mesa de al lado.',
    ], ['mesa_reservada_sin_reserva', 'debate_sano'], ['social' => 'grupo']),
];
