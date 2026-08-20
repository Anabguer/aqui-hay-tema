<?php
declare(strict_types=1);

return [
    aht_m('nombra_plantas', 'Nombra las plantas', [
        'Les pone nombre a las plantas. Pregunta por Gerardo, que es un ficus.',
        'Si una maceta cambia de sitio, se entera. El resto del mobiliario, según.',
    ], ['gerardo_enfermo'], ['regalo' => 'maceta']),
    aht_m('llega_tarde_teatro', 'Llega tarde con historia', [
        'Llega tarde. Siempre hay una historia. Algunas son incluso verdad.',
        'La entrada es un acto. El reloj, un extra.',
    ], ['espera_en_la_puerta']),
    aht_m('puntual_militar', 'Puntual de reloj', [
        'Si dices las siete, está a las seis cincuenta y ocho.',
        'Esperar le parece un deporte ajeno. Llegar tarde, una ofensa menor.',
    ], ['le_hicieron_esperar']),
    aht_m('cafe_en_jarra', 'El café, en jarra', [
        'Una taza le parece un aperitivo. El café va en jarra o no es café.',
        'Pide la jarra sin pestañear. El resto de la mesa toma nota.',
    ], ['regalo_jarra'], ['regalo' => 'jarra']),
    aht_m('relee_el_mismo', 'Relee el mismo libro', [
        'Tiene un libro que relee como quien visita a un primo.',
        'Ya se lo sabe. Da igual. Lo abre por la página de siempre.',
    ], ['regalo_edicion_nueva'], ['regalo' => 'esa_novela']),
    aht_m('no_cumple_sorpresa', 'Odia los cumpleaños sorpresa', [
        'Una tarta-sorpresa es una emboscada. Pastel anunciado, gracias.',
        'Felicitar en voz baja funciona. Saltar de detrás de un sofá, no.',
    ], ['emboscada_velas']),
    aht_m('apuesta_chicles_bingo', 'En el bingo apuesta chicles', [
        'No se la juega con dinero. Se la juega con chicles. El honor es el mismo.',
        'El bote de chicle es sagrado. El de euros, ya se verá.',
    ], ['bote_de_chicle']),
    aht_m('captura_arcade', 'Foto al récord del arcade', [
        'Si hay récord, hay foto. Si no hay récord, hay foto del marcador, por si acaso.',
        'El teléfono sale antes que la palomita. Prioridades.',
    ], ['marca_batida']),
    aht_m('colecciona_servilletas', 'Colecciona servilletas de bar', [
        'Se lleva la servilleta si tiene dibujo. El camarero ya ni pregunta.',
        'Hay un cajón. No lo abras sin avisar.',
    ], ['servilleta_rara'], ['regalo' => 'papel_bonito']),
    aht_m('canta_en_la_ducha_alto', 'Canta en la ducha, fuerte', [
        'El vecindario confirma que hay repertorio.',
        'La ducha es escenario. El karaoke, según el día, es otra guerra.',
    ], ['vecino_queja']),
    aht_m('lista_de_la_compra_obsesiva', 'Lista de la compra sagrada', [
        'Si no está en la lista, no se compra. Salvo que sí. Entonces hay crisis menor.',
        'La lista viaja. El bolsillo, el móvil, la cabeza. En ese orden.',
    ], ['item_no_listado']),
    aht_m('sillas_espalda_pared', 'Silla de espaldas a la pared', [
        'En la cafetería pide el asiento de espaldas a la pared. Es manual.',
        'Mesa en medio del local: se le ve el disgusto antes que el café.',
    ], ['mesa_ocupada']),
    aht_m('cuenta_escalones', 'Cuenta escalones', [
        'Cuenta los escalones. Si el número no cuadra, lo vuelve a contar.',
        'El mirador tiene un número. Pregunta y te arrepentirás.',
    ], ['escalera_del_mirador']),
    aht_m('fotos_sin_gente', 'Fotos sin gente', [
        'Hace fotos del pueblo vacío. La gente estropea el encuadre. Lo dice.',
        'Espera a que pase el grupo. A veces espera de más.',
    ], ['alguien_se_coló']),
    aht_m('guarda_entradas', 'Guarda las entradas', [
        'Guarda las entradas de cine como si fueran estampas.',
        'Un día será un altar. O un cajón. Hoy es archivo.',
    ], ['entrada_perdida'], ['regalo' => 'marco']),
    aht_m('pide_lo_mismo', 'Pide siempre lo mismo', [
        'En la carta ya ni mira. Lo de siempre.',
        'El día que cambia, el pueblo se entera. Es noticia local.',
    ], ['plato_agotado']),
    aht_m('no_pisa_juntas', 'No pisa las juntas', [
        'En la acera no pisa las juntas. Es un deporte silencioso.',
        'Si le sacas de quicio, pisará una a propósito. No lo hagas.',
    ], ['suelo_del_parque']),
    aht_m('saluda_a_los_perros', 'Saluda primero a los perros', [
        'Saluda al perro. A la persona, si queda tiempo.',
        'El hocico es prioridad. El resto es protocolo.',
    ], ['perro_nuevo']),
    aht_m('anota_en_servilleta', 'Anota en servilletas', [
        'Las ideas van a la servilleta. Luego se pierden. Luego hay enfado con el papel.',
        'Un bolígrafo prestado es un favor serio.',
    ], ['servilleta_olvidada']),
    aht_m('clima_como_personaje', 'El tiempo es un personaje', [
        'Habla del tiempo como de un vecino difícil.',
        'Tiene razón más a menudo de lo que duele.',
    ], ['borrasca']),
    aht_m('playlist_de_la_vida', 'Una canción para cada cosa', [
        'Hay canción para fregar, para enfadarse y para el bus. No es metáfora.',
        'Si suena el tema equivocado, el día se tuerce un milímetro.',
    ], ['tema_equivocado'], ['regalo' => 'vinilo_o_enlace']),
    aht_m('nunca_ve_el_final', 'Se tapa el final', [
        'En el cine se tapa los ojos al final. Luego pregunta. Luego se enfada si se lo cuentas.',
        'Los créditos son zona de peligro. Silencio en la fila.',
    ], ['spoiler_accidental']),
    aht_m('ordena_cubiertos', 'Alinea los cubiertos', [
        'Antes de comer, alinea tenedor y cuchillo. Es un ritual breve y no negociable.',
        'Si el mantel está torcido, el plato espera.',
    ], ['mantel_cruzado']),
    aht_m('prueba_el_borde', 'Prueba por el borde del plato', [
        'Empieza por el borde. El centro es para cuando se fíe.',
        'Un bocado de reconocimiento. Luego, si acaso, el resto.',
    ], ['plato_nuevo']),
    aht_m('guarda_piedras', 'Guarda piedras bonitas', [
        'Vuelve del paseo con una piedra en el bolsillo. Siempre una.',
        'Hay un cuenco. No es basura. Es archivo geológico casero.',
    ], ['piedra_del_rio'], ['regalo' => 'cuenco']),
    aht_m('cuenta_las_uvas_mal', 'Cuenta las uvas mal', [
        'En Nochevieja se pierde. El resto del año también, si hay uvas.',
        'El doce es una teoría. La práctica, otra.',
    ], ['fin_de_ano']),
    aht_m('toca_madera', 'Toca madera', [
        'Si dice algo que puede tentarle a la suerte, toca madera. La mesa, la silla, el marco.',
        'No es broma. O sí, pero toca igual.',
    ], ['mala_pata']),
    aht_m('no_pasa_bajo_escalera', 'No pasa bajo escaleras', [
        'Si hay una escalera abierta, da el rodeo. El pueblo ya lo sabe.',
        'La superstición es pequeña. El rodeo, también.',
    ], ['obras_en_la_plaza']),
    aht_m('sienta_en_la_punta', 'Se sienta en la punta del banco', [
        'En el parque ocupa la punta. El centro del banco es para otras geografías.',
        'Si el banco está lleno, se queda de pie. Tiene su lógica.',
    ], ['banco_lleno']),
    aht_m('camina_por_la_sombra', 'Camina por la sombra', [
        'Cruza la calle si hace falta, con tal de no pisar el sol de las tres.',
        'La sombra es un plan. El sol, un trámite.',
    ], ['siesta_de_luz']),
    aht_m('saluda_con_dos_besos_mal', 'Duda con los besos de saludo', [
        'Nunca tiene claro si es uno, dos, o la mano. Improvisa con dignidad irregular.',
        'El momento del saludo le dura más que a la otra persona.',
    ], ['presentacion']),
    aht_m('pide_hielo_aparte', 'Pide el hielo aparte', [
        'La bebida, sin hielo. El hielo, si acaso, al lado. Hay un sistema.',
        'Diluirle el vaso es un acto hostil. Sin querer, pero hostil.',
    ], ['cubito']),
    aht_m('remueve_el_azucar_mucho', 'Remueve más de la cuenta', [
        'Remueve el café como si hubiera un secreto al fondo.',
        'La cucharilla trabaja. El café espera. La mesa observa.',
    ], ['cucharilla']),
    aht_m('guarda_bolsas_de_tela', 'Acumula bolsas de tela', [
        'Nunca llega a la tienda sin bolsa. A veces llega con cuatro.',
        'Hay un cajón de bolsas. Es un ecosistema.',
    ], ['olvido_bolsa']),
    aht_m('lee_los_prospectos', 'Lee los prospectos', [
        'Antes de un medicamento, lee el papel entero. Luego pregunta. Luego lo lee otra vez.',
        'Los efectos secundarios le parecen literatura. Oscura, pero literatura.',
    ], ['resfriado']),
    aht_m('nombra_los_dias_por_la_comida', 'Nombra los días por lo que se come', [
        'El jueves es el de lentejas. El domingo, el del horno. El calendario, secundario.',
        'Si cambias el menú, cambias el día. Cuidado.',
    ], ['jueves_sin_lentejas']),
    aht_m('cuenta_las_aceitunas', 'Cuenta las aceitunas', [
        'En la tapa, cuenta. Si el número es impar, hay comentario. Si es par, también.',
        'No es hambre: es inventario.',
    ], ['racion']),
    aht_m('deja_el_ultimo_bocado', 'Deja el último bocado', [
        'Nunca termina el plato del todo. El último bocado es de respeto. O de costumbre.',
        'Limpiar el plato le parece una falta de educación con la comida.',
    ], ['segundo_plato']),
    aht_m('repite_la_pregunta', 'Repite la pregunta para estar seguro', [
        '“¿A las siete?”. Ya te lo han dicho. Lo vuelve a preguntar. Es el método.',
        'No es que no escuche. Es que el dato tiene que entrar dos veces.',
    ], ['quedada']),
    aht_m('mira_atras_al_salir', 'Mira atrás al salir de casa', [
        'Cierra, da dos pasos, vuelve a mirar la puerta. Luego se va de verdad.',
        'La llave está. Lo sabe. Mira igual.',
    ], ['llaves']),
    aht_m('colecciona_imanes', 'Colecciona imanes de nevera', [
        'Cada sitio deja un imán. La nevera es un mapa. El mapa, un poco caótico.',
        'Regalarle un imán feo es un riesgo. Uno bonito, un acierto.',
    ], ['viaje_corto'], ['regalo' => 'iman']),
    aht_m('habla_con_los_objetos', 'Habla con los objetos', [
        'Le dice “no te caigas” a la taza. La taza, de momento, obedece.',
        'No espera respuesta. Eso lo hace más serio, no menos.',
    ], ['taza_en_el_borde']),
    aht_m('guarda_piedras_de_hueso', 'Guarda huesos de aceituna', [
        'Hay un platito. Los huesos no van al plato principal. Hay normas.',
        'Mezclar huesos y comida le parece un desorden moral menor.',
    ], ['racion_aceitunas']),
    aht_m('usa_siempre_la_misma_taza', 'Usa siempre la misma taza', [
        'Hay una taza. Las demás son extras. La de siempre, no se presta.',
        'Si se rompe, hay duelo breve y una sucesión complicada.',
    ], ['taza_rota'], ['regalo' => 'taza_parecida']),
    aht_m('dobla_las_servilletas', 'Dobla las servilletas en cuatro', [
        'Antes de usarlas, las dobla. Es inútil y necesario.',
        'Una servilleta abierta de golpe le parece improvisación.',
    ], ['mesa_puesta']),
    aht_m('no_pisa_la_sombra_de_otro', 'Evita la sombra ajena', [
        'Si puede, no pisa la sombra de nadie. Es un tic. No lo discute.',
        'En el paseo estrecho, se complica. El pueblo cabe igual.',
    ], ['paseo_estrecho']),
    aht_m('cuenta_las_campanadas', 'Cuenta las campanadas', [
        'Si suena el reloj, cuenta. Si falla una, lo dice. El pueblo no siempre agradece.',
        'El tiempo, para esta persona, hace ruido. Hay que llevar la cuenta.',
    ], ['iglesia']),
    aht_m('se_cambia_de_lado_de_la_cama', 'Cambia de lado de la cama según el día', [
        'Hay un sistema. No está escrito. Fallar el lado tuerce la mañana.',
        'No preguntes el sistema. Tampoco lo entiende del todo.',
    ], ['insomnio']),
    aht_m('limpia_las_gafas_demasiado', 'Limpia las gafas de más', [
        'Las limpia. Las mira. Las limpia otra vez. El mundo, entonces, sí.',
        'Un vaho es un incidente. Un paño, una herramienta de precisión.',
    ], ['vaho']),
    aht_m('guarda_ticket_del_super', 'Guarda el ticket del súper', [
        'El ticket viaja a un cajón. Por si acaso. El por si acaso dura meses.',
        'Tirarlo el mismo día le parece temerario.',
    ], ['devolucion']),
    aht_m('prueba_el_agua_del_grifo', 'Prueba el agua del grifo como catador', [
        'Un sorbo. Un gesto. Un veredicto. El agua del pueblo tiene opinión.',
        'Si el agua “está rara”, hay comunicado. Si está bien, silencio de experto.',
    ], ['obras_del_agua']),
    aht_m('se_sienta_cerca_de_la_salida', 'Se sienta cerca de la salida', [
        'En el cine, en el bingo, en la reunión: pasillo. Por si acaso.',
        'No es huida. Es geografía personal.',
    ], ['sala_llena']),
    aht_m('repite_el_pedido_en_voz_baja', 'Repite el pedido en voz baja', [
        'Lo dice al camarero. Luego lo repite, más bajo, para que conste.',
        'No es desconfianza: es archivo oral.',
    ], ['barra']),
    aht_m('alineas_los_zapatos', 'Alinea los zapatos en la entrada', [
        'Los zapatos no se tiran. Se colocan. Punta a la pared, o al revés, según dogma.',
        'Un zapato torcido es un recado para el resto de la casa.',
    ], ['visita']),
    aht_m('no_abre_paraguas_dentro', 'No abre el paraguas en casa', [
        'Aunque esté seco. Aunque sea para comprobarlo. Fuera, y punto.',
        'La superstición cabe en el recibidor.',
    ], ['lluvia']),
    aht_m('colecciona_piedras_de_jabon', 'Acaba los jabones hasta el final', [
        'El pastilla se usa hasta que es una lámina. Tirarla antes es desperdicio moral.',
        'Hay un sistema de empalme de jabones. Funciona. Más o menos.',
    ], ['baño']),
    aht_m('cuenta_los_pajaros', 'Cuenta los pájaros del tendido', [
        'En el parque, mira el cable. Cuenta. Si hay uno raro, hay comentario.',
        'No es ornitología: es costumbre.',
    ], ['tendido']),
    aht_m('deja_recado_en_la_nevera', 'Deja recados en la nevera', [
        'El imán sujeta avisos. “Leche”. “No tocar el flan”. Literatura doméstica.',
        'Si no hay recado, la nevera parece muda. Eso también se nota.',
    ], ['flan']),
    aht_m('camina_sin_pisar_charcos_a_proposito', 'Rodea los charcos con ceremonia', [
        'Un charco no se pisa. Se honra con un rodeo. Aunque sea ridículo.',
        'Los días de lluvia el trayecto se alarga. El calzado, se agradece.',
    ], ['lluvia_calle']),
    aht_m('guarda_mecheros_vacios', 'Guarda mecheros vacíos', [
        'Por si recobran vida. No recobran. El cajón, igual, los acoge.',
        'Tirar un mechero “por si acaso” le parece precipitado.',
    ], ['mechero']),
    aht_m('saluda_al_conductor_del_bus', 'Saluda a quien conduce el bus', [
        'Al subir, un gesto. Al bajar, otro. El pueblo es pequeño. El gesto, también.',
        'Si un día no saluda, es que va a otra parte con la cabeza.',
    ], ['parada']),
    aht_m('ordena_los_libros_por_color', 'Ordena los libros por color', [
        'El lomo manda. El abecedario, que se apañe.',
        'Encontrar un título es una cacería. La estantería, una bandera.',
    ], ['estanteria']),
    aht_m('no_empieza_capitulos_de_noche', 'No empieza capítulos de noche', [
        'Un capítulo a las once es una trampa. Lo sabe. Aun así, a veces cae.',
        'La regla existe. El sueño, también. Gana quien gana.',
    ], ['insomnio_libro']),
    aht_m('toca_el_marco_al_entrar', 'Toca el marco al entrar', [
        'Un toque al marco de la puerta. Luego ya está dentro de verdad.',
        'Si se olvida, vuelve dos pasos y lo hace. El recibidor lo ha visto.',
    ], ['visita_ajena']),
    aht_m('pide_la_cuenta_en_cuanto_termina', 'Pide la cuenta en cuanto termina', [
        'El último bocado y la mirada al camarero. Quedarse de más le pica.',
        'No es prisa: es cierre. La sobremesa, en otro sitio.',
    ], ['restaurante']),
    aht_m('guarda_piedras_de_la_suerte', 'Lleva una piedra de la suerte', [
        'En el bolsillo hay una piedra. No se enseña. No se presta.',
        'Si se queda en el otro pantalón, el día empieza con un hueco.',
    ], ['bolsillo_vacio']),
    aht_m('repite_el_camino_de_siempre', 'Vuelve por el mismo camino', [
        'Ida, un trazado. Vuelta, el mismo. Atajar le parece hacer trampas con el pueblo.',
        'Si cortan la calle, hay crisis de itinerario. Se resuelve. Con comentario.',
    ], ['obras']),
    aht_m('se_limpia_las_manos_antes_de_pagar', 'Se limpia las manos antes de pagar', [
        'Un gesto rápido. Luego el dinero o la tarjeta. El orden importa.',
        'No es manía de sucio: es de cierre de acto.',
    ], ['caja']),
    aht_m('nombra_las_calles_mal_a_proposito', 'Nombra las calles a su manera', [
        'Esa no es “la calle Nueva”. Es “la de la panadería vieja”. El mapa oficial, secundario.',
        'Quien llega de fuera se pierde. Quien es del pueblo, entiende.',
    ], ['indicaciones']),
    aht_m('espera_el_numero_par_del_ascensor', 'Espera un número par en el ascensor', [
        'Si puede, deja pasar el impar. Es absurdo. Lo sabe. Espera igual.',
        'En un pueblo con pocas plantas, se nota más.',
    ], ['portal']),
    aht_m('guarda_las_tapas_de_los_botes', 'Guarda tapas de más', [
        'Hay un cajón de tapas. Algún día encajarán. El día, de momento, no llega.',
        'Tirar una tapa buena es un desperdicio. Aunque el bote ya no exista.',
    ], ['cajon']),
    aht_m('silba_una_sola_cancion', 'Silba siempre la misma canción', [
        'Hay un tema. Sale al fregar, al andar, al esperar. El pueblo ya lo tiene memorizado.',
        'Si un día silba otra, alguien pregunta si pasa algo.',
    ], ['fregadero']),
    aht_m('cuenta_los_azulejos', 'Cuenta azulejos en el baño ajeno', [
        'En casas de otras personas, la mirada se va a la pared. Hay un número. Hay paz.',
        'No es juicio. Es costumbre. El anfitrión no tiene por qué saberlo.',
    ], ['visita_aseo']),
];
