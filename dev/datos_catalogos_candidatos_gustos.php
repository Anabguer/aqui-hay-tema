<?php
declare(strict_types=1);

return [
    aht_g('cine_terror', 'cine', 'Terror', ['cine_sala', 'ver_en_casa'], ['lug_cine'], [
        'El terror le pone de un humor extrañamente bueno. No preguntes.',
        'Si hay sangre de atrezzo, se queda. Si hay comedia romántica, negocia.',
    ], ['noche_de_susto', 'regalo_poster']),
    aht_g('cine_comedia', 'cine', 'Comedia', ['cine_sala', 'ver_en_casa'], ['lug_cine'], [
        'Quiere reírse en la butaca. El resto es opcional.',
        'Una comedia floja le ofende más que un drama serio. Tiene un punto.',
    ], ['session_risa']),
    aht_g('cine_romantico', 'cine', 'Cine romántico', ['cine_sala', 'ver_en_casa'], ['lug_cine'], [
        'Las de amor le llegan. No lo anuncia con un megáfono, pero se le nota.',
        'No es que sea “romántica”: es que esas películas le funcionan. Distinto.',
    ], ['final_previsible']),
    aht_g('cine_accion', 'cine', 'Acción', ['cine_sala', 'ver_en_casa'], ['lug_cine'], [
        'Quiere que pasen cosas. Diálogo de dos horas le parece un atraco.',
        'Explosiones, persecuciones, palomitas. El arte, otro día.',
    ], ['estreno_verano']),
    aht_g('cine_thriller', 'cine', 'Thriller', ['cine_sala', 'ver_en_casa'], ['lug_cine'], [
        'Le gusta que la trama apriete. Si lo ve venir, se enfada con el guion.',
        'Sale del cine comentando agujeros. Es su forma de querer la película.',
    ], ['spoiler_crimen']),
    aht_g('cine_autor', 'cine', 'Cine raro / de autor', ['cine_sala', 'ver_en_casa'], ['lug_cine'], [
        'Le tiran las raras. Subtítulos, silencios, finales que no cierran. Su rollo.',
        'Si dices “no la he entendido”, se le ilumina el día. Por fin alguien honesto. O un aliado.',
    ], ['pase_alternativo']),
    aht_g('lectura_novela', 'lectura', 'Novela', ['leer'], ['lug_biblioteca'], [
        'Historias largas. Personajes. Que le dejen vivir en otra parte un rato.',
        'Acaba un libro y necesita un día de duelo. Es oficial.',
    ], ['regalo_novela']),
    aht_g('lectura_ensayo', 'lectura', 'Ensayo / no ficción', ['leer'], ['lug_biblioteca'], [
        'Prefiere que el libro le explique el mundo, no que le invente uno.',
        'Subraya. Discute con el autor. El autor no se entera; quien lee, sí.',
    ], ['regalo_ensayo']),
    aht_g('pasteleria', 'mesa', 'Dulce', ['merendar', 'cafe_con_gente'], ['lug_cafeteria', 'lug_picnic'], [
        'El dulce no es un postre: es una política.',
        'Si hay tarta, hay trato. Si no hay tarta, hay negociación.',
    ], ['cumple_tarta', 'regalo_dulce']),
    aht_g('cocina_de_casa', 'mesa', 'Comida de casa', ['cocina', 'restaurantes'], ['lug_restaurante', 'lug_picnic'], [
        'Le puede una buena receta de toda la vida más que un menú con espuma.',
        'Si el plato sabe a domingo de pueblo, se rinde.',
    ], ['guiso', 'abuela']),
    aht_g('mesa_formal', 'mesa', 'Mesa puesta / formal', ['restaurantes'], ['lug_restaurante'], [
        'Le gusta que la cosa tenga mantel y copas. De vez en cuando, no siempre.',
        'Un restaurante “en condiciones” le sienta como un traje bien cortado.',
    ], ['reserva_buena']),
    aht_g('cafe_en_serio', 'mesa', 'Café de verdad', ['cafe_con_gente'], ['lug_cafeteria'], [
        'El café le importa. El de máquina de gasolinera es una ofensa personal.',
        'Puede hablar cinco minutos de un grano. Tú decides si te quedas.',
    ], ['barista']),
    aht_g('terrazas', 'ambiente', 'Terrazas', ['cafe_con_gente', 'copas', 'merendar'], ['lug_cafeteria', 'lug_bar', 'lug_picnic'], [
        'Fuera, siempre que se pueda. El interior le sabe a espera del dentista.',
        'Una terraza le arregla el carácter. El aire, no el menú.',
    ], ['mesa_fuera']),
    aht_g('sitios_con_ruido', 'ambiente', 'Ambiente con marcha', ['fiesta', 'copas', 'conciertos'], ['lug_bar', 'lug_discoteca', 'lug_karaoke'], [
        'Le sienta bien el jaleo. Pensar, ya pensará mañana.',
        'Si el sitio está muerto, se apaga un poco también.',
    ], ['volumen_alto']),
    aht_g('sitios_en_silencio', 'ambiente', 'Sitios en silencio', ['leer', 'escribir', 'spa', 'crucigramas'], ['lug_biblioteca', 'lug_spa', 'lug_mirador'], [
        'Necesita sitios donde no le griten el café. No es pedantería: es supervivencia.',
        'El silencio le trabaja a favor. El resto del pueblo, a veces no.',
    ], ['mesa_rincón']),
    aht_g('luces_bajas', 'ambiente', 'Luz baja, barra, de noche', ['copas', 'conciertos'], ['lug_bar', 'lug_discoteca'], [
        'Le van los sitios oscuritos. No es misterio: es que se relaja.',
        'Con luz de oficina se encoge. Con luz de bar, aparece.',
    ], ['barra_fondo']),
    aht_g('competir', 'juegos', 'Competir', ['videojuegos_competitivo', 'deporte_equipo', 'bingo', 'arcade', 'juegos_mesa'], ['lug_arcade', 'lug_bingo', 'lug_parque'], [
        'Le gusta ganar. Y que se note, un poco.',
        'Un marcador le pone en modo serio. Luego dice que era broma.',
    ], ['revancha']),
    aht_g('coleccionar_piezas', 'manos', 'Objetos de colección', ['coleccionismo', 'moda'], ['lug_tienda'], [
        'No compra por comprar: busca “la” cosa. La reconoce al vuelo.',
        'Un hallazgo le da para una semana de conversación. Prepárate.',
    ], ['vitrina']),
    aht_g('ropa_atrevida', 'manos', 'Ropa que se nota', ['moda'], ['lug_tienda', 'lug_discoteca'], [
        'Le gusta que la ropa diga algo. Aunque sea “mira”.',
        'Lo discreto le parece un desperdicio. No siempre, pero a menudo.',
    ], ['probarse_loco']),
    aht_g('ropa_comoda', 'manos', 'Ropa cómoda', ['moda'], ['lug_tienda', 'lug_parque'], [
        'Si no se puede sentar, no se lo pone. Fin del comunicado.',
        'La elegancia empieza por no estar sufriendo. El resto es adorno.',
    ], ['zapatos_vivos']),
    aht_g('agua_caliente', 'cuidado', 'Agua caliente / baños', ['spa', 'yoga'], ['lug_spa'], [
        'El agua caliente le arregla más que un consejo bienintencionado.',
        'Si está hecha polvo, no pide fiesta: pide vapor y que cierren la puerta.',
    ], ['circuito']),
    aht_g('sudar', 'movimiento', 'Sudar de verdad', ['correr', 'gimnasio', 'deporte_equipo', 'baile', 'bici'], ['lug_gimnasio', 'lug_parque', 'lug_discoteca'], [
        'Si no suda, considera que no ha hecho nada. Es una teoría discutible y suya.',
        'El esfuerzo le pone de buen humor. El sofá, a veces al revés.',
    ], ['despues_ducha']),
    aht_g('atardeceres', 'aire_libre', 'Atardeceres', ['mirador', 'pasear', 'fotografia'], ['lug_mirador', 'lug_parque'], [
        'El atardecer le funciona. No hace falta violín. Con el cielo basta.',
        'Se queda mirando la luz como quien mira un recado importante.',
    ], ['luz_última']),
    aht_g('documentales', 'cine', 'Documentales', ['ver_en_casa', 'leer'], [], [
        'Prefiere que le cuenten algo que existe. La ficción, según el día.',
        'Un buen documental le da tema para tres cafés. Aviso a la mesa.',
    ], ['recomendacion_rara']),
    aht_g('chisme_sano', 'pueblo', 'Enterarse (con tacto)', ['tertulia', 'asociacion', 'cafe_con_gente'], ['lug_cafeteria', 'lug_bar', 'lug_bingo'], [
        'Le gusta estar al tanto. No es cotilla de oficio: es que el pueblo es su serie.',
        'Pregunta. Se entera. No siempre reparte lo que sabe. Eso ya es carácter.',
    ], ['se_entero_antes']),
    aht_g('escenario', 'musica', 'Subirse a un escenario', ['karaoke', 'tocar_instrumento', 'conciertos'], ['lug_karaoke', 'lug_bar'], [
        'Le tira subirse un rato. El pánico y el gusto van de la mano.',
        'Un micro le cambia la postura. Luego vuelve a ser quien era. O no.',
    ], ['solo_improvisado']),
    aht_g('naturaleza_sin_gente', 'aire_libre', 'Campo casi vacío', ['pasear', 'senderismo', 'plantas', 'mirador'], ['lug_parque', 'lug_mirador'], [
        'La naturaleza le gusta más si no hay picnic de treinta. El pájaro, sí; el bluetooth, no.',
        'Se aleja un poco y se le ve el alma más fácil. O eso dicen.',
    ], ['sendero_solo']),
    aht_g('grupos_grandes', 'pueblo', 'Grupos grandes', ['fiesta', 'deporte_equipo', 'asociacion', 'bingo'], ['lug_discoteca', 'lug_bingo', 'lug_parque'], [
        'Cuanta más gente, mejor. Se enciende. Es agotador y contagioso.',
        'Un plan de dos le sabe a poco. Un plan de doce, a domingo.',
    ], ['queda_masiva']),
];
