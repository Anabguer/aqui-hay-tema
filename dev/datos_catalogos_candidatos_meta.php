<?php
declare(strict_types=1);

/**
 * Datos del catálogo candidato. Solo se usa para volcar JSON.
 * Fuente de revisión: data/catalogos/_candidatos_personalidad/*.json
 */
return [
    'meta' => [
        'estado' => 'PROPUESTA_NO_ACTIVA',
        'canon' => false,
        'no_toca' => ['P001-P200', 'data/catalogos/*.json de producción', 'UI', 'assets'],
        'fuente_produccion_sigue' => 'data/catalogos/aficiones.json y hermanos',
        'formato' => 'JSON versionable. Una carpeta candidata. CatalogStore de PLAY no la carga.',
        'taxonomia' => [
            'aficiones' => 'Lo que HACE. Prácticas. Pueden mapear a destinos sin ser el destino.',
            'gustos' => 'Matices dentro de una familia. Pesan más que el estereotipo. cine≠terror.',
            'rechazos' => 'Polaridad negativa sobre afición, gusto, destino, actividad o contexto. No es un segundo catálogo de hobbies.',
            'rasgos' => 'Cómo es. Modulan; no asignan destino.',
            'preferencias_sociales' => 'Ejes (energía, selectividad, ritmo, ruido, tamaño de grupo). El label es opcional y derivado.',
            'afecto_estilo' => 'Cómo se vincula (espacio, ritmo, demostración). NO es Relacional V1 ni orientación ni dealbreaker.',
            'manias' => 'Peculiaridades narrativas. Casi sin mecánica. 0 o 1 por persona. Pool amplio (60–80) para no repetir en población activa.',
        'cardinalidad' => 'Exactamente 3 aficiones + 3 rasgos. Si el muestreo queda corto, se reintenta; no se acepta una ficha con 2.',
        'contradiccion_produccion' => 'Presupuesto: ~70% coherente, ~25% una tensión, ~5% varias. El showcase de lab (10 primeras de la muestra) sí fuerza ejemplos.',
        'copy' => 'Prosa neutra (“le”). Etiquetas de rasgo invariables (Timidez, Orgullo). Variantes por canal: libreta, cotilleo, conversacion, mensaje, plan.',
        'tienda' => 'Id candidato lug_tienda (Tienda de Café & Libros). No lug_tienda_ropa.',
        ],
        'solapes_rechazados' => [
            'No crear catálogo “Gustos” duplicando aficiones genéricas (música, deporte, cine).',
            'No crear “Preferencias sociales” paralelas a estilos_sociales: se AMPLÍAN los ejes ya existentes.',
            'No crear “romántico” como rasgo que empuja al mirador.',
            'ejes_preferencia.json (lo que busco en OTRA persona) se queda en el sistema relacional. Este bloque no lo sustituye.',
            'orientaciones.json y dealbreakers.json no se tocan.',
        ],
        'incompatibilidades_duras' => [
            'No afición y rechazo de la MISMA afición.',
            'No gusto y rechazo del MISMO gusto.',
            'No dos valores del mismo eje social.',
            'Los alias de producción (observadora→observador) no se reescriben: se conservan al migrar.',
        ],
        'incompatibilidades_que_NO_son' => [
            'tímida + karaoke',
            'sociable + odia discotecas',
            'deporte al aire + odia el gimnasio',
            'leer + no quiere vivir en la biblioteca',
            'cine + odia el terror',
            'música + odia bailar',
            'competitiva + lleva fatal perder',
            'lectora + fiestera',
            'juegos + naturaleza',
            'cálida + mucho espacio personal',
        ],
        'descubrimiento' => [
            'al_llegar' => '1 afición + 1 rasgo (ya es el contrato de DiscoveryReveal).',
            'el_resto' => 'planes, conversaciones, eventos, peticiones, coincidencias, regalos, cumpleaños, Cotilleo, buzón, comentarios de terceros.',
            'nada_es_excel' => 'La libreta usa frases de descubrimientos[], nunca el id.',
        ],
    ],
];
