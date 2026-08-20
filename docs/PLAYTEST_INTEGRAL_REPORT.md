# REPORT PLAYTEST INTEGRAL

Generado: 2026-08-20T13:42:37+00:00

## Resumen ejecutivo

- PASS: 9
- FAIL: 1
- NO_IMPLEMENTADO: 3
- PARCIAL: 1
- BLOQUEADO_DECISION: 1

## ¿DEJARÍA YA ENTRAR A NENI AL PLAYTEST?

**NO**

- Tutorial canónico 3→≈8 no está cableado: Neni seguiría chocando con arranque incoherente según docs.
- Sin sistema de llegadas post-tutorial; el playtest longitudinal de población no es el juego real.
- Balance de voluntad: BLOQUEADO_DECISION: score≈70 ⇒ p_persona≈0.682 y tasa observada persona=0.652, pero tasa de PLAN (ambos aceptan)≈0.404. Matemáticamente coherente (≈p²); puede sentirse como demasiados plantones en Organizar.

### Mapa de secciones

- **A. Tutorial 3→8**: FAIL
- **B. Llegadas**: NO_IMPLEMENTADO
- **C. Vida autónoma**: PASS
- **D. Planes/voluntad**: PASS
- **E. Compatibilidad/resultados**: PASS
- **F. Emociones**: PASS
- **G. Descubrimientos**: PASS
- **H. Buzón/Cotilleo**: PASS
- **I. Relaciones**: PARCIAL
- **J. Marchas**: NO_IMPLEMENTADO
- **K. Economía**: NO_IMPLEMENTADO
- **L. Aforos**: PASS
- **M. Save/load/API/integración**: PASS
- **N. Invariantes**: PASS

## Voluntad — tasas por banda

```json
{
    "bajo": {
        "n_participantes": 0,
        "tasa_aceptacion_persona": null,
        "p_media_formula": null
    },
    "medio": {
        "n_participantes": 607,
        "tasa_aceptacion_persona": 0.652,
        "p_media_formula": 0.682
    },
    "alto": {
        "n_participantes": 0,
        "tasa_aceptacion_persona": null,
        "p_media_formula": null
    }
}
```

> BLOQUEADO_DECISION: score≈70 ⇒ p_persona≈0.682 y tasa observada persona=0.652, pero tasa de PLAN (ambos aceptan)≈0.404. Matemáticamente coherente (≈p²); puede sentirse como demasiados plantones en Organizar.

### Ejemplos humanos (voluntad)

- **Carmen → José** @10: RECHAZA (voluntad) — José ha rechazado la propuesta: "Hoy no me da la vida."
- **José → Raúl** @11: ACEPTA (—) — José y Raúl han quedado el Jueves 20/08 a las 18:00 en cafeteria.
- **Marta → Dani** @12: ACEPTA (—) — Marta y Dani han quedado el Jueves 20/08 a las 17:00 en cafeteria.
- **Raúl → Sara** @13: ACEPTA (—) — Raúl y Sara han quedado el Jueves 20/08 a las 14:00 en cafeteria.
- **Lucía → José** @14: ACEPTA (—) — Lucía y José han quedado el Sábado 22/08 a las 09:00 en cafeteria.

## Ejemplos de planes (INPUT → DECISIÓN → RESULTADO)

### conocerse_cafe_valido
- INPUT: Carmen + José | conocerse @ lug_cafeteria h15
- DECISIÓN: rechaza | voluntad | José ha rechazado la propuesta: "Me tengo que pintar las uñas."
- RESULTADO emo: neutro / neutro | social A→B 0 B→A 0

### conocerse_hora_cerrada
- INPUT: Carmen + Marta | conocerse @ lug_cafeteria h3
- DECISIÓN: rechaza | voluntad | Marta ha rechazado la propuesta: "Hoy no me da la vida."
- RESULTADO emo: neutro / neutro | social A→B 0 B→A 0

### misma_persona
- INPUT: Carmen + Carmen | conocerse @ lug_cafeteria h16
- DECISIÓN: error | MISMA_PERSONA | Elige a dos personas distintas.
- RESULTADO emo: neutro / neutro | social A→B 0 B→A 0

### parque_si_operativo
- INPUT: Carmen + Lucía | conocerse @ lug_parque h12
- DECISIÓN: rechaza | voluntad | Lucía ha rechazado la propuesta: "Hoy no me da la vida."
- RESULTADO emo: neutro / neutro | social A→B 0 B→A 0

### bar_noche
- INPUT: Carmen + Dani | conocerse @ lug_bar h22
- DECISIÓN: error | LUGAR_NO_OPERATIVO | Ese lugar no está operativo.
- RESULTADO emo: neutro / neutro | social A→B 0 B→A 0

### pareja_distinta_hora
- INPUT: Marta + Raúl | conocerse @ lug_cafeteria h11
- DECISIÓN: ok/acepta | — | Marta y Raúl han quedado el Jueves 20/08 a las 17:00 en cafeteria.
- RESULTADO emo: neutro / neutro | social A→B 0 B→A 0

### otro_par_tarde
- INPUT: Lucía + Dani | conocerse @ lug_biblioteca h17
- DECISIÓN: ok/acepta | — | Lucía y Dani han quedado el Sábado 22/08 a las 10:00 en biblioteca.
- RESULTADO emo: neutro / neutro | social A→B 0 B→A 0

### ocupado_solape
- INPUT: Carmen + Marta | conocerse @ lug_cafeteria h15
- DECISIÓN: error | TIPO_ENCUENTRO_NO_DISPONIBLE | Esas dos ya se conocen.
- RESULTADO emo: neutro / neutro | social A→B 2 B→A 2

## A. Tutorial 3→8 — detalle JSON — FAIL

```json
{
    "status": "FAIL",
    "inicial_n": 3,
    "ids_iniciales": [
        "per_i03",
        "per_p001",
        "per_p002"
    ],
    "tutorial_activo_inicio": true,
    "tutorial_activo_fin": false,
    "n_tras_tutorial": 3,
    "n_fin_dia_1": 3,
    "crecimiento_a_8": false,
    "playtest_01_sin_tutorial": true,
    "playtest_01_n": 8,
    "notas": [
        "NO_IMPLEMENTADO: tras tutorial + día 1 siguen 3 residentes (canónico ≈8)."
    ],
    "gap": "Falta motor de incorporaciones tutorializadas 3→≈8 en el primer día."
}
```

## B. Llegadas — detalle JSON — NO_IMPLEMENTADO

```json
{
    "status": "NO_IMPLEMENTADO",
    "titulo": "Llegadas post-tutorial \/ candidatos por buzón",
    "detalle": "No hay motor de candidato→buzón→aceptar\/rechazar\/expirar\/vivienda en play."
}
```

## C. Vida autónoma — detalle JSON — PASS

```json
{
    "status": "PASS",
    "seeds": [
        "aut-a",
        "aut-b",
        "aut-c"
    ],
    "dias_por_seed": 7,
    "poblacion_auditada": 8,
    "poblaciones_pedidas_no_disponibles": [
        16,
        32,
        48
    ],
    "flags_persona_dos_sitios": 0,
    "aforo_fallos": [],
    "nota": "Autonomía ON en playtest_01 (8 residentes). Sin motor de llegadas no hay roster 16\/32\/48 real → no se finge."
}
```

## D. Planes/voluntad — detalle JSON — PASS

```json
{
    "status": "PASS",
    "seeds": 12,
    "planes_por_seed": 40,
    "tasa_aceptacion_plan": 0.404,
    "planes_total": 480,
    "tasas_por_banda_score": {
        "bajo": {
            "n_participantes": 0,
            "tasa_aceptacion_persona": null,
            "p_media_formula": null
        },
        "medio": {
            "n_participantes": 607,
            "tasa_aceptacion_persona": 0.652,
            "p_media_formula": 0.682
        },
        "alto": {
            "n_participantes": 0,
            "tasa_aceptacion_persona": null,
            "p_media_formula": null
        }
    },
    "racha_rechazos_plan_max_media": 5.92,
    "racha_rechazos_plan_max_abs": 9,
    "ejemplos": [
        {
            "seed": "vol-0",
            "a": "Carmen",
            "b": "José",
            "hora": 10,
            "rechazada": true,
            "clase": "voluntad",
            "mensaje_ui": "José ha rechazado la propuesta: \"Hoy no me da la vida.\"",
            "reacciones": [
                {
                    "residente_id": "per_p001",
                    "nombre": "Carmen",
                    "decision": "rechaza",
                    "clase": "voluntad",
                    "motivo_tecnico": "voluntad_rechaza_banal",
                    "motivo_tipo": "banal",
                    "copy_id": "el_gato_tiene_que_comer",
                    "score": 70,
                    "p": 0.6819999999999999,
                    "_bloqueado_decision": false,
                    "factores": {
                        "score": 70,
                        "base": 48,
                        "estado_emocional": "neutro",
                        "mod_estado_emocional_aceptar_planes": 0,
                        "relacion_previa_se_conocen": false,
                        "mod_aun_no_se_conocen": -12,
                        "social": 0,
                        "mod_social": 0,
                        "romance": null,
                        "mod_romance": 0,
                        "conflicto": null,
                        "mod_conflicto": 0,
                        "rechazos_previos": 0,
                        "mod_rechazos": 0,
                        "mod_consejo": 0,
                        "lugar": "lug_cafeteria",
                        "afinidad_aporte": 0,
                        "afinidad_penalizacion": 0,
                        "tipo": "conocerse",
                        "mod_tipo": 34,
                        "mod_iniciativa_romantica": 0,
                        "p": 0.6819999999999999,
                        "tirada_rng": 0.7495117632201982,
                        "umbral_p": 0.6819999999999999,
                        "acepta_si_tirada_menor_que_p": false
                    }
                },
                {
                    "residente_id": "per_p002",
                    "nombre": "José",
                    "decision": "rechaza",
                    "clase": "cooldown",
                    "motivo_tecnico": "cooldown_propuesta",
                    "motivo_tipo": "banal",
                    "copy_id": "hoy_no_me_da_la_vida",
                    "score": null,
                    "p": 0,
                    "_bloqueado_decision": false
                }
            ]
        },
        {
            "seed": "vol-0",
            "a": "José",
            "b": "Raúl",
            "hora": 11,
            "rechazada": false,
            "clase": null,
            "mensaje_ui": "José y Raúl han quedado el Jueves 20\/08 a las 18:00 en cafeteria.",
            "reacciones": [
                {
                    "residente_id": "per_p002",
                    "nombre": "José",
                    "decision": "acepta",
                    "clase": null,
                    "motivo_tecnico": "voluntad_acepta",
                    "motivo_tipo": null,
                    "copy_id": null,
                    "score": 70,
                    "p": 0.6819999999999999,
                    "_bloqueado_decision": false,
                    "factores": {
                        "score": 70,
                        "base": 48,
                        "estado_emocional": "neutro",
                        "mod_estado_emocional_aceptar_planes": 0,
                        "relacion_previa_se_conocen": false,
                        "mod_aun_no_se_conocen": -12,
                        "social": 0,
                        "mod_social": 0,
                        "romance": null,
                        "mod_romance": 0,
                        "conflicto": null,
                        "mod_conflicto": 0,
                        "rechazos_previos": 0,
                        "mod_rechazos": 0,
                        "mod_consejo": 0,
                        "lugar": "lug_cafeteria",
                        "afinidad_aporte": 0,
                        "afinidad_penalizacion": 0,
                        "tipo": "conocerse",
                        "mod_tipo": 34,
                        "mod_iniciativa_romantica": 0,
                        "p": 0.6819999999999999,
                        "tirada_rng": 0.5714314743610392,
                        "umbral_p": 0.6819999999999999,
                        "acepta_si_tirada_menor_que_p": true
                    }
                },
                {
                    "residente_id": "per_p004",
                    "nombre": "Raúl",
                    "decision": "acepta",
                    "clase": null,
                    "motivo_tecnico": "voluntad_acepta",
                    "motivo_tipo": null,
                    "copy_id": null,
                    "score": 70,
                    "p": 0.6819999999999999,
                    "_bloqueado_decision": false,
                    "factores": {
                        "score": 70,
                        "base": 48,
                        "estado_emocional": "neutro",
                        "mod_estado_emocional_aceptar_planes": 0,
                        "relacion_previa_se_conocen": false,
                        "mod_aun_no_se_conocen": -12,
                        "social": 0,
                        "mod_social": 0,
                        "romance": null,
                        "mod_romance": 0,
                        "conflicto": null,
                        "mod_conflicto": 0,
                        "rechazos_previos": 0,
                        "mod_rechazos": 0,
                        "mod_consejo": 0,
                        "lugar": "lug_cafeteria",
                        "afinidad_aporte": 0,
                        "afinidad_penalizacion": 0,
                        "tipo": "conocerse",
                        "mod_tipo": 34,
                        "mod_iniciativa_romantica": 0,
                        "p": 0.6819999999999999,
                        "tirada_rng": 0.5686860373883378,
                        "umbral_p": 0.6819999999999999,
                        "acepta_si_tirada_menor_que_p": true
                    }
                }
            ]
        },
        {
            "seed": "vol-0",
            "a": "Marta",
            "b": "Dani",
            "hora": 12,
            "rechazada": false,
            "clase": null,
            "mensaje_ui": "Marta y Dani han quedado el Jueves 20\/08 a las 17:00 en cafeteria.",
            "reacciones": [
                {
                    "residente_id": "per_p003",
                    "nombre": "Marta",
                    "decision": "acepta",
                    "clase": null,
                    "motivo_tecnico": "voluntad_acepta",
                    "motivo_tipo": null,
                    "copy_id": null,
                    "score": 70,
                    "p": 0.6819999999999999,
                    "_bloqueado_decision": false,
                    "factores": {
                        "score": 70,
                        "base": 48,
                        "estado_emocional": "neutro",
                        "mod_estado_emocional_aceptar_planes": 0,
                        "relacion_previa_se_conocen": false,
                        "mod_aun_no_se_conocen": -12,
                        "social": 0,
                        "mod_social": 0,
                        "romance": null,
                        "mod_romance": 0,
                        "conflicto": null,
                        "mod_conflicto": 0,
                        "rechazos_previos": 0,
                        "mod_rechazos": 0,
                        "mod_consejo": 0,
                        "lugar": "lug_cafeteria",
                        "afinidad_aporte": 0,
                        "afinidad_penalizacion": 0,
                        "tipo": "conocerse",
                        "mod_tipo": 34,
                        "mod_iniciativa_romantica": 0,
                        "p": 0.6819999999999999,
                        "tirada_rng": 0.08092341486450602,
                        "umbral_p": 0.6819999999999999,
                        "acepta_si_tirada_menor_que_p": true
                    }
                },
                {
                    "residente_id": "per_p006",
                    "nombre": "Dani",
                    "decision": "acepta",
                    "clase": null,
                    "motivo_tecnico": "voluntad_acepta",
                    "motivo_tipo": null,
                    "copy_id": null,
                    "score": 70,
                    "p": 0.6819999999999999,
                    "_bloqueado_decision": false,
                    "factores": {
                        "score": 70,
                        "base": 48,
                        "estado_emocional": "neutro",
                        "mod_estado_emocional_aceptar_planes": 0,
                        "relacion_previa_se_conocen": false,
                        "mod_aun_no_se_conocen": -12,
                        "social": 0,
                        "mod_social": 0,
                        "romance": null,
                        "mod_romance": 0,
                        "conflicto": null,
                        "mod_conflicto": 0,
                        "rechazos_previos": 0,
                        "mod_rechazos": 0,
                        "mod_consejo": 0,
                        "lugar": "lug_cafeteria",
                        "afinidad_aporte": 0,
                        "afinidad_penalizacion": 0,
                        "tipo": "conocerse",
                        "mod_tipo": 34,
                        "mod_iniciativa_romantica": 0,
                        "p": 0.6819999999999999,
                        "tirada_rng": 0.25415710569746525,
                        "umbral_p": 0.6819999999999999,
                        "acepta_si_tirada_menor_que_p": true
                    }
                }
            ]
        },
        {
            "seed": "vol-0",
            "a": "Raúl",
            "b": "Sara",
            "hora": 13,
            "rechazada": false,
            "clase": null,
            "mensaje_ui": "Raúl y Sara han quedado el Jueves 20\/08 a las 14:00 en cafeteria.",
            "reacciones": [
                {
                    "residente_id": "per_p004",
                    "nombre": "Raúl",
                    "decision": "acepta",
                    "clase": null,
                    "motivo_tecnico": "voluntad_acepta",
                    "motivo_tipo": null,
                    "copy_id": null,
                    "score": 70,
                    "p": 0.6819999999999999,
                    "_bloqueado_decision": false,
                    "factores": {
                        "score": 70,
                        "base": 48,
                        "estado_emocional": "neutro",
                        "mod_estado_emocional_aceptar_planes": 0,
                        "relacion_previa_se_conocen": false,
                        "mod_aun_no_se_conocen": -12,
                        "social": 0,
                        "mod_social": 0,
                        "romance": null,
                        "mod_romance": 0,
                        "conflicto": null,
                        "mod_conflicto": 0,
                        "rechazos_previos": 0,
                        "mod_rechazos": 0,
                        "mod_consejo": 0,
                        "lugar": "lug_cafeteria",
                        "afinidad_aporte": 0,
                        "afinidad_penalizacion": 0,
                        "tipo": "conocerse",
                        "mod_tipo": 34,
                        "mod_iniciativa_romantica": 0,
                        "p": 0.6819999999999999,
                        "tirada_rng": 0.38543002063914206,
                        "umbral_p": 0.6819999999999999,
                        "acepta_si_tirada_menor_que_p": true
                    }
                },
                {
                    "residente_id": "per_p008",
                    "nombre": "Sara",
                    "decision": "acepta",
                    "clase": null,
                    "motivo_tecnico": "voluntad_acepta",
                    "motivo_tipo": null,
                    "copy_id": null,
                    "score": 70,
                    "p": 0.6819999999999999,
                    "_bloqueado_decision": false,
                    "factores": {
                        "score": 70,
                        "base": 48,
                        "estado_emocional": "neutro",
                        "mod_estado_emocional_aceptar_planes": 0,
                        "relacion_previa_se_conocen": false,
                        "mod_aun_no_se_conocen": -12,
                        "social": 0,
                        "mod_social": 0,
                        "romance": null,
                        "mod_romance": 0,
                        "conflicto": null,
                        "mod_conflicto": 0,
                        "rechazos_previos": 0,
                        "mod_rechazos": 0,
                        "mod_consejo": 0,
                        "lugar": "lug_cafeteria",
                        "afinidad_aporte": 0,
                        "afinidad_penalizacion": 0,
                        "tipo": "conocerse",
                        "mod_tipo": 34,
                        "mod_iniciativa_romantica": 0,
                        "p": 0.6819999999999999,
                        "tirada_rng": 0.09251760839719103,
                        "umbral_p": 0.6819999999999999,
                        "acepta_si_tirada_menor_que_p": true
                    }
                }
            ]
        },
        {
            "seed": "vol-0",
            "a": "Lucía",
            "b": "José",
            "hora": 14,
            "rechazada": false,
            "clase": null,
            "mensaje_ui": "Lucía y José han quedado el Sábado 22\/08 a las 09:00 en cafeteria.",
            "reacciones": [
                {
                    "residente_id": "per_p005",
                    "nombre": "Lucía",
                    "decision": "acepta",
                    "clase": null,
                    "motivo_tecnico": "voluntad_acepta",
                    "motivo_tipo": null,
                    "copy_id": null,
                    "score": 70,
                    "p": 0.6819999999999999,
                    "_bloqueado_decision": false,
                    "factores": {
                        "score": 70,
                        "base": 48,
                        "estado_emocional": "neutro",
                        "mod_estado_emocional_aceptar_planes": 0,
                        "relacion_previa_se_conocen": false,
                        "mod_aun_no_se_conocen": -12,
                        "social": 0,
                        "mod_social": 0,
                        "romance": null,
                        "mod_romance": 0,
                        "conflicto": null,
                        "mod_conflicto": 0,
                        "rechazos_previos": 0,
                        "mod_rechazos": 0,
                        "mod_consejo": 0,
                        "lugar": "lug_cafeteria",
                        "afinidad_aporte": 0,
                        "afinidad_penalizacion": 0,
                        "tipo": "conocerse",
                        "mod_tipo": 34,
                        "mod_iniciativa_romantica": 0,
                        "p": 0.6819999999999999,
                        "tirada_rng": 0.293447442626066,
                        "umbral_p": 0.6819999999999999,
                        "acepta_si_tirada_menor_que_p": true
                    }
                },
                {
                    "residente_id": "per_p002",
                    "nombre": "José",
                    "decision": "acepta",
                    "clase": null,
                    "motivo_tecnico": "voluntad_acepta",
                    "motivo_tipo": null,
                    "copy_id": null,
                    "score": 70,
                    "p": 0.6819999999999999,
                    "_bloqueado_decision": false,
                    "factores": {
                        "score": 70,
                        "base": 48,
                        "estado_emocional": "neutro",
                        "mod_estado_emocional_aceptar_planes": 0,
                        "relacion_previa_se_conocen": false,
                        "mod_aun_no_se_conocen": -12,
                        "social": 0,
                        "mod_social": 0,
                        "romance": null,
                        "mod_romance": 0,
                        "conflicto": null,
                        "mod_conflicto": 0,
                        "rechazos_previos": 0,
                        "mod_rechazos": 0,
                        "mod_consejo": 0,
                        "lugar": "lug_cafeteria",
                        "afinidad_aporte": 0,
                        "afinidad_penalizacion": 0,
                        "tipo": "conocerse",
                        "mod_tipo": 34,
                        "mod_iniciativa_romantica": 0,
                        "p": 0.6819999999999999,
                        "tirada_rng": 0.0014964067391086433,
                        "umbral_p": 0.6819999999999999,
                        "acepta_si_tirada_menor_que_p": true
                    }
                }
            ]
        },
        {
            "seed": "vol-0",
            "a": "Dani",
            "b": "Álex",
            "hora": 15,
            "rechazada": true,
            "clase": "voluntad",
            "mensaje_ui": "Álex ha rechazado la propuesta: \"Hoy no me da la vida.\"",
            "reacciones": [
                {
                    "residente_id": "per_p006",
                    "nombre": "Dani",
                    "decision": "rechaza",
                    "clase": "voluntad",
                    "motivo_tecnico": "voluntad_rechaza_banal",
                    "motivo_tipo": "banal",
                    "copy_id": "hoy_no_me_da_la_vida",
                    "score": 70,
                    "p": 0.6819999999999999,
                    "_bloqueado_decision": false,
                    "factores": {
                        "score": 70,
                        "base": 48,
                        "estado_emocional": "neutro",
                        "mod_estado_emocional_aceptar_planes": 0,
                        "relacion_previa_se_conocen": false,
                        "mod_aun_no_se_conocen": -12,
                        "social": 0,
                        "mod_social": 0,
                        "romance": null,
                        "mod_romance": 0,
                        "conflicto": null,
                        "mod_conflicto": 0,
                        "rechazos_previos": 0,
                        "mod_rechazos": 0,
                        "mod_consejo": 0,
                        "lugar": "lug_cafeteria",
                        "afinidad_aporte": 0,
                        "afinidad_penalizacion": 0,
                        "tipo": "conocerse",
                        "mod_tipo": 34,
                        "mod_iniciativa_romantica": 0,
                        "p": 0.6819999999999999,
                        "tirada_rng": 0.9546013921076445,
                        "umbral_p": 0.6819999999999999,
                        "acepta_si_tirada_menor_que_p": false
                    }
                },
                {
                    "residente_id": "per_p007",
                    "nombre": "Álex",
                    "decision": "rechaza",
                    "clase": "cooldown",
                    "motivo_tecnico": "cooldown_propuesta",
                    "motivo_tipo": "banal",
                    "copy_id": "hoy_no_me_da_la_vida",
                    "score": null,
                    "p": 0,
                    "_bloqueado_decision": false
                }
            ]
        },
        {
            "seed": "vol-0",
            "a": "Álex",
            "b": "Carmen",
            "hora": 16,
            "rechazada": false,
            "clase": null,
            "mensaje_ui": "Álex y Carmen han quedado el Jueves 20\/08 a las 19:00 en cafeteria.",
            "reacciones": [
                {
                    "residente_id": "per_p007",
                    "nombre": "Álex",
                    "decision": "acepta",
                    "clase": null,
                    "motivo_tecnico": "voluntad_acepta",
                    "motivo_tipo": null,
                    "copy_id": null,
                    "score": 70,
                    "p": 0.6819999999999999,
                    "_bloqueado_decision": false,
                    "factores": {
                        "score": 70,
                        "base": 48,
                        "estado_emocional": "neutro",
                        "mod_estado_emocional_aceptar_planes": 0,
                        "relacion_previa_se_conocen": false,
                        "mod_aun_no_se_conocen": -12,
                        "social": 0,
                        "mod_social": 0,
                        "romance": null,
                        "mod_romance": 0,
                        "conflicto": null,
                        "mod_conflicto": 0,
                        "rechazos_previos": 0,
                        "mod_rechazos": 0,
                        "mod_consejo": 0,
                        "lugar": "lug_cafeteria",
                        "afinidad_aporte": 0,
                        "afinidad_penalizacion": 0,
                        "tipo": "conocerse",
                        "mod_tipo": 34,
                        "mod_iniciativa_romantica": 0,
                        "p": 0.6819999999999999,
                        "tirada_rng": 0.0781496600975745,
                        "umbral_p": 0.6819999999999999,
                        "acepta_si_tirada_menor_que_p": true
                    }
                },
                {
                    "residente_id": "per_p001",
                    "nombre": "Carmen",
                    "decision": "acepta",
                    "clase": null,
                    "motivo_tecnico": "voluntad_acepta",
                    "motivo_tipo": null,
                    "copy_id": null,
                    "score": 70,
                    "p": 0.6819999999999999,
                    "_bloqueado_decision": false,
                    "factores": {
                        "score": 70,
                        "base": 48,
                        "estado_emocional": "neutro",
                        "mod_estado_emocional_aceptar_planes": 0,
                        "relacion_previa_se_conocen": false,
                        "mod_aun_no_se_conocen": -12,
                        "social": 0,
                        "mod_social": 0,
                        "romance": null,
                        "mod_romance": 0,
                        "conflicto": null,
                        "mod_conflicto": 0,
                        "rechazos_previos": 0,
                        "mod_rechazos": 0,
                        "mod_consejo": 0,
                        "lugar": "lug_cafeteria",
                        "afinidad_aporte": 0,
                        "afinidad_penalizacion": 0,
                        "tipo": "conocerse",
                        "mod_tipo": 34,
                        "mod_iniciativa_romantica": 0,
                        "p": 0.6819999999999999,
                        "tirada_rng": 0.3622408135442443,
                        "umbral_p": 0.6819999999999999,
                        "acepta_si_tirada_menor_que_p": true
                    }
                }
            ]
        },
        {
            "seed": "vol-0",
            "a": "Sara",
            "b": "Marta",
            "hora": 17,
            "rechazada": true,
            "clase": "voluntad",
            "mensaje_ui": "Marta ha rechazado la propuesta: \"Hoy no me da la vida.\"",
            "reacciones": [
                {
                    "residente_id": "per_p008",
                    "nombre": "Sara",
                    "decision": "rechaza",
                    "clase": "voluntad",
                    "motivo_tecnico": "voluntad_rechaza_banal",
                    "motivo_tipo": "banal",
                    "copy_id": "se_me_hace_tarde",
                    "score": 70,
                    "p": 0.6819999999999999,
                    "_bloqueado_decision": false,
                    "factores": {
                        "score": 70,
                        "base": 48,
                        "estado_emocional": "neutro",
                        "mod_estado_emocional_aceptar_planes": 0,
                        "relacion_previa_se_conocen": false,
                        "mod_aun_no_se_conocen": -12,
                        "social": 0,
                        "mod_social": 0,
                        "romance": null,
                        "mod_romance": 0,
                        "conflicto": null,
                        "mod_conflicto": 0,
                        "rechazos_previos": 0,
                        "mod_rechazos": 0,
                        "mod_consejo": 0,
                        "lugar": "lug_cafeteria",
                        "afinidad_aporte": 0,
                        "afinidad_penalizacion": 0,
                        "tipo": "conocerse",
                        "mod_tipo": 34,
                        "mod_iniciativa_romantica": 0,
                        "p": 0.6819999999999999,
                        "tirada_rng": 0.8936822082788518,
                        "umbral_p": 0.6819999999999999,
                        "acepta_si_tirada_menor_que_p": false
                    }
                },
                {
                    "residente_id": "per_p003",
                    "nombre": "Marta",
                    "decision": "rechaza",
                    "clase": "cooldown",
                    "motivo_tecnico": "cooldown_propuesta",
                    "motivo_tipo": "banal",
                    "copy_id": "hoy_no_me_da_la_vida",
                    "score": null,
                    "p": 0,
                    "_bloqueado_decision": false
                }
            ]
        }
    ],
    "balance": "BLOQUEADO_DECISION: score≈70 ⇒ p_persona≈0.682 y tasa observada persona=0.652, pero tasa de PLAN (ambos aceptan)≈0.404. Matemáticamente coherente (≈p²); puede sentirse como demasiados plantones en Organizar.",
    "nota": "Medir persona (≈p) vs plan (ambos). Neni percibe plantones a nivel plan."
}
```

## E. Compatibilidad/resultados — detalle JSON — PASS

```json
{
    "status": "PASS",
    "n_casos": 8,
    "casos": [
        {
            "label": "conocerse_cafe_valido",
            "input": {
                "a_id": "per_p001",
                "b_id": "per_p002",
                "a": "Carmen",
                "b": "José",
                "tipo": "conocerse",
                "lugar": "lug_cafeteria",
                "hora": 15,
                "before": {
                    "conocen": false,
                    "social_ab": 0,
                    "social_ba": 0,
                    "emo_a": "neutro",
                    "emo_b": "neutro"
                }
            },
            "decision": {
                "ok": true,
                "rechazada": true,
                "error": "ENCUENTRO_RECHAZADO_VOLUNTAD",
                "clase": "voluntad",
                "mensaje_ui": "José ha rechazado la propuesta: \"Me tengo que pintar las uñas.\"",
                "reacciones": [
                    {
                        "residente_id": "per_p001",
                        "nombre": "Carmen",
                        "decision": "acepta",
                        "clase": null,
                        "motivo_tecnico": "voluntad_acepta",
                        "motivo_tipo": null,
                        "copy_id": null,
                        "score": 70,
                        "p": 0.6819999999999999,
                        "_bloqueado_decision": false,
                        "factores": {
                            "score": 70,
                            "base": 48,
                            "estado_emocional": "neutro",
                            "mod_estado_emocional_aceptar_planes": 0,
                            "relacion_previa_se_conocen": false,
                            "mod_aun_no_se_conocen": -12,
                            "social": 0,
                            "mod_social": 0,
                            "romance": null,
                            "mod_romance": 0,
                            "conflicto": null,
                            "mod_conflicto": 0,
                            "rechazos_previos": 0,
                            "mod_rechazos": 0,
                            "mod_consejo": 0,
                            "lugar": "lug_cafeteria",
                            "afinidad_aporte": 0,
                            "afinidad_penalizacion": 0,
                            "tipo": "conocerse",
                            "mod_tipo": 34,
                            "mod_iniciativa_romantica": 0,
                            "p": 0.6819999999999999,
                            "tirada_rng": 0.2659142480845696,
                            "umbral_p": 0.6819999999999999,
                            "acepta_si_tirada_menor_que_p": true
                        }
                    },
                    {
                        "residente_id": "per_p002",
                        "nombre": "José",
                        "decision": "rechaza",
                        "clase": "voluntad",
                        "motivo_tecnico": "voluntad_rechaza_banal",
                        "motivo_tipo": "banal",
                        "copy_id": "pintar_unias",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "_bloqueado_decision": false,
                        "factores": {
                            "score": 70,
                            "base": 48,
                            "estado_emocional": "neutro",
                            "mod_estado_emocional_aceptar_planes": 0,
                            "relacion_previa_se_conocen": false,
                            "mod_aun_no_se_conocen": -12,
                            "social": 0,
                            "mod_social": 0,
                            "romance": null,
                            "mod_romance": 0,
                            "conflicto": null,
                            "mod_conflicto": 0,
                            "rechazos_previos": 0,
                            "mod_rechazos": 0,
                            "mod_consejo": 0,
                            "lugar": "lug_cafeteria",
                            "afinidad_aporte": 0,
                            "afinidad_penalizacion": 0,
                            "tipo": "conocerse",
                            "mod_tipo": 34,
                            "mod_iniciativa_romantica": 0,
                            "p": 0.6819999999999999,
                            "tirada_rng": 0.946663313495613,
                            "umbral_p": 0.6819999999999999,
                            "acepta_si_tirada_menor_que_p": false
                        }
                    }
                ]
            },
            "resultado": {
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro"
            }
        },
        {
            "label": "conocerse_hora_cerrada",
            "input": {
                "a_id": "per_p001",
                "b_id": "per_p003",
                "a": "Carmen",
                "b": "Marta",
                "tipo": "conocerse",
                "lugar": "lug_cafeteria",
                "hora": 3,
                "before": {
                    "conocen": false,
                    "social_ab": 0,
                    "social_ba": 0,
                    "emo_a": "neutro",
                    "emo_b": "neutro"
                }
            },
            "decision": {
                "ok": true,
                "rechazada": true,
                "error": "ENCUENTRO_RECHAZADO_VOLUNTAD",
                "clase": "voluntad",
                "mensaje_ui": "Marta ha rechazado la propuesta: \"Hoy no me da la vida.\"",
                "reacciones": [
                    {
                        "residente_id": "per_p001",
                        "nombre": "Carmen",
                        "decision": "rechaza",
                        "clase": "voluntad",
                        "motivo_tecnico": "voluntad_rechaza_banal",
                        "motivo_tipo": "banal",
                        "copy_id": "pintar_unias",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "_bloqueado_decision": false,
                        "factores": {
                            "score": 70,
                            "base": 48,
                            "estado_emocional": "neutro",
                            "mod_estado_emocional_aceptar_planes": 0,
                            "relacion_previa_se_conocen": false,
                            "mod_aun_no_se_conocen": -12,
                            "social": 0,
                            "mod_social": 0,
                            "romance": null,
                            "mod_romance": 0,
                            "conflicto": null,
                            "mod_conflicto": 0,
                            "rechazos_previos": 0,
                            "mod_rechazos": 0,
                            "mod_consejo": 0,
                            "lugar": "lug_cafeteria",
                            "afinidad_aporte": 0,
                            "afinidad_penalizacion": 0,
                            "tipo": "conocerse",
                            "mod_tipo": 34,
                            "mod_iniciativa_romantica": 0,
                            "p": 0.6819999999999999,
                            "tirada_rng": 0.9310402268832924,
                            "umbral_p": 0.6819999999999999,
                            "acepta_si_tirada_menor_que_p": false
                        }
                    },
                    {
                        "residente_id": "per_p003",
                        "nombre": "Marta",
                        "decision": "rechaza",
                        "clase": "cooldown",
                        "motivo_tecnico": "cooldown_propuesta",
                        "motivo_tipo": "banal",
                        "copy_id": "hoy_no_me_da_la_vida",
                        "score": null,
                        "p": 0,
                        "_bloqueado_decision": false
                    }
                ]
            },
            "resultado": {
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro"
            }
        },
        {
            "label": "misma_persona",
            "input": {
                "a_id": "per_p001",
                "b_id": "per_p001",
                "a": "Carmen",
                "b": "Carmen",
                "tipo": "conocerse",
                "lugar": "lug_cafeteria",
                "hora": 16,
                "before": {
                    "conocen": false,
                    "social_ab": 0,
                    "social_ba": 0,
                    "emo_a": "neutro",
                    "emo_b": "neutro"
                }
            },
            "decision": {
                "ok": false,
                "rechazada": null,
                "error": "MISMA_PERSONA",
                "clase": null,
                "mensaje_ui": "Elige a dos personas distintas.",
                "reacciones": null
            },
            "resultado": {
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro"
            }
        },
        {
            "label": "parque_si_operativo",
            "input": {
                "a_id": "per_p001",
                "b_id": "per_p005",
                "a": "Carmen",
                "b": "Lucía",
                "tipo": "conocerse",
                "lugar": "lug_parque",
                "hora": 12,
                "before": {
                    "conocen": false,
                    "social_ab": 0,
                    "social_ba": 0,
                    "emo_a": "neutro",
                    "emo_b": "neutro"
                }
            },
            "decision": {
                "ok": true,
                "rechazada": true,
                "error": "ENCUENTRO_RECHAZADO_VOLUNTAD",
                "clase": "voluntad",
                "mensaje_ui": "Lucía ha rechazado la propuesta: \"Hoy no me da la vida.\"",
                "reacciones": [
                    {
                        "residente_id": "per_p001",
                        "nombre": "Carmen",
                        "decision": "rechaza",
                        "clase": "voluntad",
                        "motivo_tecnico": "voluntad_rechaza_banal",
                        "motivo_tipo": "banal",
                        "copy_id": "lavadora",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "_bloqueado_decision": false,
                        "factores": {
                            "score": 70,
                            "base": 48,
                            "estado_emocional": "neutro",
                            "mod_estado_emocional_aceptar_planes": 0,
                            "relacion_previa_se_conocen": false,
                            "mod_aun_no_se_conocen": -12,
                            "social": 0,
                            "mod_social": 0,
                            "romance": null,
                            "mod_romance": 0,
                            "conflicto": null,
                            "mod_conflicto": 0,
                            "rechazos_previos": 0,
                            "mod_rechazos": 0,
                            "mod_consejo": 0,
                            "lugar": "lug_parque",
                            "afinidad_aporte": 0,
                            "afinidad_penalizacion": 0,
                            "tipo": "conocerse",
                            "mod_tipo": 34,
                            "mod_iniciativa_romantica": 0,
                            "p": 0.6819999999999999,
                            "tirada_rng": 0.7967951253958001,
                            "umbral_p": 0.6819999999999999,
                            "acepta_si_tirada_menor_que_p": false
                        }
                    },
                    {
                        "residente_id": "per_p005",
                        "nombre": "Lucía",
                        "decision": "rechaza",
                        "clase": "cooldown",
                        "motivo_tecnico": "cooldown_propuesta",
                        "motivo_tipo": "banal",
                        "copy_id": "hoy_no_me_da_la_vida",
                        "score": null,
                        "p": 0,
                        "_bloqueado_decision": false
                    }
                ]
            },
            "resultado": {
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro"
            }
        },
        {
            "label": "bar_noche",
            "input": {
                "a_id": "per_p001",
                "b_id": "per_p006",
                "a": "Carmen",
                "b": "Dani",
                "tipo": "conocerse",
                "lugar": "lug_bar",
                "hora": 22,
                "before": {
                    "conocen": false,
                    "social_ab": 0,
                    "social_ba": 0,
                    "emo_a": "neutro",
                    "emo_b": "neutro"
                }
            },
            "decision": {
                "ok": false,
                "rechazada": null,
                "error": "LUGAR_NO_OPERATIVO",
                "clase": null,
                "mensaje_ui": "Ese lugar no está operativo.",
                "reacciones": null
            },
            "resultado": {
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro"
            }
        },
        {
            "label": "pareja_distinta_hora",
            "input": {
                "a_id": "per_p003",
                "b_id": "per_p004",
                "a": "Marta",
                "b": "Raúl",
                "tipo": "conocerse",
                "lugar": "lug_cafeteria",
                "hora": 11,
                "before": {
                    "conocen": false,
                    "social_ab": 0,
                    "social_ba": 0,
                    "emo_a": "neutro",
                    "emo_b": "neutro"
                }
            },
            "decision": {
                "ok": true,
                "rechazada": null,
                "error": null,
                "clase": null,
                "mensaje_ui": "Marta y Raúl han quedado el Jueves 20\/08 a las 17:00 en cafeteria.",
                "reacciones": [
                    {
                        "residente_id": "per_p003",
                        "nombre": "Marta",
                        "decision": "acepta",
                        "clase": null,
                        "motivo_tecnico": "voluntad_acepta",
                        "motivo_tipo": null,
                        "copy_id": null,
                        "score": 70,
                        "p": 0.6819999999999999,
                        "_bloqueado_decision": false,
                        "factores": {
                            "score": 70,
                            "base": 48,
                            "estado_emocional": "neutro",
                            "mod_estado_emocional_aceptar_planes": 0,
                            "relacion_previa_se_conocen": false,
                            "mod_aun_no_se_conocen": -12,
                            "social": 0,
                            "mod_social": 0,
                            "romance": null,
                            "mod_romance": 0,
                            "conflicto": null,
                            "mod_conflicto": 0,
                            "rechazos_previos": 0,
                            "mod_rechazos": 0,
                            "mod_consejo": 0,
                            "lugar": "lug_cafeteria",
                            "afinidad_aporte": 0,
                            "afinidad_penalizacion": 0,
                            "tipo": "conocerse",
                            "mod_tipo": 34,
                            "mod_iniciativa_romantica": 0,
                            "p": 0.6819999999999999,
                            "tirada_rng": 0.46047620145648366,
                            "umbral_p": 0.6819999999999999,
                            "acepta_si_tirada_menor_que_p": true
                        }
                    },
                    {
                        "residente_id": "per_p004",
                        "nombre": "Raúl",
                        "decision": "acepta",
                        "clase": null,
                        "motivo_tecnico": "voluntad_acepta",
                        "motivo_tipo": null,
                        "copy_id": null,
                        "score": 70,
                        "p": 0.6819999999999999,
                        "_bloqueado_decision": false,
                        "factores": {
                            "score": 70,
                            "base": 48,
                            "estado_emocional": "neutro",
                            "mod_estado_emocional_aceptar_planes": 0,
                            "relacion_previa_se_conocen": false,
                            "mod_aun_no_se_conocen": -12,
                            "social": 0,
                            "mod_social": 0,
                            "romance": null,
                            "mod_romance": 0,
                            "conflicto": null,
                            "mod_conflicto": 0,
                            "rechazos_previos": 0,
                            "mod_rechazos": 0,
                            "mod_consejo": 0,
                            "lugar": "lug_cafeteria",
                            "afinidad_aporte": 0,
                            "afinidad_penalizacion": 0,
                            "tipo": "conocerse",
                            "mod_tipo": 34,
                            "mod_iniciativa_romantica": 0,
                            "p": 0.6819999999999999,
                            "tirada_rng": 0.6467101556683985,
                            "umbral_p": 0.6819999999999999,
                            "acepta_si_tirada_menor_que_p": true
                        }
                    }
                ]
            },
            "resultado": {
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro"
            }
        },
        {
            "label": "otro_par_tarde",
            "input": {
                "a_id": "per_p005",
                "b_id": "per_p006",
                "a": "Lucía",
                "b": "Dani",
                "tipo": "conocerse",
                "lugar": "lug_biblioteca",
                "hora": 17,
                "before": {
                    "conocen": false,
                    "social_ab": 0,
                    "social_ba": 0,
                    "emo_a": "neutro",
                    "emo_b": "neutro"
                }
            },
            "decision": {
                "ok": true,
                "rechazada": null,
                "error": null,
                "clase": null,
                "mensaje_ui": "Lucía y Dani han quedado el Sábado 22\/08 a las 10:00 en biblioteca.",
                "reacciones": [
                    {
                        "residente_id": "per_p005",
                        "nombre": "Lucía",
                        "decision": "acepta",
                        "clase": null,
                        "motivo_tecnico": "voluntad_acepta",
                        "motivo_tipo": null,
                        "copy_id": null,
                        "score": 70,
                        "p": 0.6819999999999999,
                        "_bloqueado_decision": false,
                        "factores": {
                            "score": 70,
                            "base": 48,
                            "estado_emocional": "neutro",
                            "mod_estado_emocional_aceptar_planes": 0,
                            "relacion_previa_se_conocen": false,
                            "mod_aun_no_se_conocen": -12,
                            "social": 0,
                            "mod_social": 0,
                            "romance": null,
                            "mod_romance": 0,
                            "conflicto": null,
                            "mod_conflicto": 0,
                            "rechazos_previos": 0,
                            "mod_rechazos": 0,
                            "mod_consejo": 0,
                            "lugar": "lug_biblioteca",
                            "afinidad_aporte": 0,
                            "afinidad_penalizacion": 0,
                            "tipo": "conocerse",
                            "mod_tipo": 34,
                            "mod_iniciativa_romantica": 0,
                            "p": 0.6819999999999999,
                            "tirada_rng": 0.47851560868184606,
                            "umbral_p": 0.6819999999999999,
                            "acepta_si_tirada_menor_que_p": true
                        }
                    },
                    {
                        "residente_id": "per_p006",
                        "nombre": "Dani",
                        "decision": "acepta",
                        "clase": null,
                        "motivo_tecnico": "voluntad_acepta",
                        "motivo_tipo": null,
                        "copy_id": null,
                        "score": 70,
                        "p": 0.6819999999999999,
                        "_bloqueado_decision": false,
                        "factores": {
                            "score": 70,
                            "base": 48,
                            "estado_emocional": "neutro",
                            "mod_estado_emocional_aceptar_planes": 0,
                            "relacion_previa_se_conocen": false,
                            "mod_aun_no_se_conocen": -12,
                            "social": 0,
                            "mod_social": 0,
                            "romance": null,
                            "mod_romance": 0,
                            "conflicto": null,
                            "mod_conflicto": 0,
                            "rechazos_previos": 0,
                            "mod_rechazos": 0,
                            "mod_consejo": 0,
                            "lugar": "lug_biblioteca",
                            "afinidad_aporte": 0,
                            "afinidad_penalizacion": 0,
                            "tipo": "conocerse",
                            "mod_tipo": 34,
                            "mod_iniciativa_romantica": 0,
                            "p": 0.6819999999999999,
                            "tirada_rng": 0.42693592554604254,
                            "umbral_p": 0.6819999999999999,
                            "acepta_si_tirada_menor_que_p": true
                        }
                    }
                ]
            },
            "resultado": {
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro"
            }
        },
        {
            "label": "ocupado_solape",
            "input": {
                "a_id": "per_p001",
                "b_id": "per_p003",
                "a": "Carmen",
                "b": "Marta",
                "tipo": "conocerse",
                "lugar": "lug_cafeteria",
                "hora": 15,
                "before": {
                    "conocen": true,
                    "social_ab": 2,
                    "social_ba": 2,
                    "emo_a": "neutro",
                    "emo_b": "neutro"
                }
            },
            "decision": {
                "ok": false,
                "rechazada": null,
                "error": "TIPO_ENCUENTRO_NO_DISPONIBLE",
                "clase": null,
                "mensaje_ui": "Esas dos ya se conocen.",
                "reacciones": null
            },
            "resultado": {
                "conocen": true,
                "social_ab": 2,
                "social_ba": 2,
                "emo_a": "neutro",
                "emo_b": "neutro"
            }
        }
    ]
}
```

## F. Emociones — detalle JSON — PASS

```json
{
    "status": "PASS",
    "canon": [
        "alegre",
        "neutro",
        "triste",
        "enfadado"
    ],
    "emo_post_a": "neutro",
    "emo_post_b": "neutro",
    "nota": "Emoción es individual; bridge emocional solo en ENCUENTRO_TERMINADO (cobertura parcial de eventos).",
    "parcial": true
}
```

## G. Descubrimientos — detalle JSON — PASS

```json
{
    "status": "PASS",
    "residente": "Carmen",
    "snapshots": {
        "dia_1": {
            "n": 2,
            "items": [
                {
                    "id": "disc_d43e3090",
                    "residente_id": "per_p001",
                    "campo": "hobby:costura",
                    "valor": "costura",
                    "estado": "descubierto",
                    "origen": "reveal_inicial",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 1,
                        "hora": 15
                    }
                },
                {
                    "id": "disc_342e36f4",
                    "residente_id": "per_p001",
                    "campo": "rasgo:nervioso",
                    "valor": "nervioso",
                    "estado": "descubierto",
                    "origen": "reveal_inicial",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 1,
                        "hora": 15
                    }
                }
            ]
        },
        "dia_10": {
            "n": 2,
            "items": [
                {
                    "id": "disc_d43e3090",
                    "residente_id": "per_p001",
                    "campo": "hobby:costura",
                    "valor": "costura",
                    "estado": "descubierto",
                    "origen": "reveal_inicial",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 1,
                        "hora": 15
                    }
                },
                {
                    "id": "disc_342e36f4",
                    "residente_id": "per_p001",
                    "campo": "rasgo:nervioso",
                    "valor": "nervioso",
                    "estado": "descubierto",
                    "origen": "reveal_inicial",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 1,
                        "hora": 15
                    }
                }
            ],
            "residente": "Carmen"
        },
        "dia_30": {
            "n": 2,
            "items": [
                {
                    "id": "disc_d43e3090",
                    "residente_id": "per_p001",
                    "campo": "hobby:costura",
                    "valor": "costura",
                    "estado": "descubierto",
                    "origen": "reveal_inicial",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 1,
                        "hora": 15
                    }
                },
                {
                    "id": "disc_342e36f4",
                    "residente_id": "per_p001",
                    "campo": "rasgo:nervioso",
                    "valor": "nervioso",
                    "estado": "descubierto",
                    "origen": "reveal_inicial",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 1,
                        "hora": 15
                    }
                }
            ],
            "residente": "Carmen"
        },
        "dia_100": {
            "n": 4,
            "items": [
                {
                    "id": "disc_d43e3090",
                    "residente_id": "per_p001",
                    "campo": "hobby:costura",
                    "valor": "costura",
                    "estado": "descubierto",
                    "origen": "reveal_inicial",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 1,
                        "hora": 15
                    }
                },
                {
                    "id": "disc_342e36f4",
                    "residente_id": "per_p001",
                    "campo": "rasgo:nervioso",
                    "valor": "nervioso",
                    "estado": "descubierto",
                    "origen": "reveal_inicial",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 1,
                        "hora": 15
                    }
                },
                {
                    "id": "disc_54b8c4d6",
                    "residente_id": "per_p001",
                    "campo": "hobby:musica",
                    "valor": "musica",
                    "estado": "descubierto",
                    "origen": "encuentro",
                    "evento_relacionado": null,
                    "correlacion_id": "enc_25e1bb93",
                    "ts_juego": {
                        "dia": 40,
                        "hora": 15
                    }
                },
                {
                    "id": "disc_333d1909",
                    "residente_id": "per_p001",
                    "campo": "rasgo:tranquilo",
                    "valor": "tranquilo",
                    "estado": "descubierto",
                    "origen": "encuentro",
                    "evento_relacionado": null,
                    "correlacion_id": "enc_25e1bb93",
                    "ts_juego": {
                        "dia": 40,
                        "hora": 15
                    }
                }
            ],
            "residente": "Carmen"
        }
    },
    "crecimiento": {
        "dia1": 2,
        "dia100": 4
    },
    "nota": "Discovery ON; se espera revelación gradual, no dump total día 1."
}
```

## H. Buzón/Cotilleo — detalle JSON — PASS

```json
{
    "status": "PASS",
    "rechazo_en_buzon_no_cotilleo": true,
    "respuesta_plan_count": 1,
    "cotilleo_max_por_dia_en_14d": 0,
    "notas": []
}
```

## I. Relaciones — detalle JSON — PARCIAL

```json
{
    "status": "PARCIAL",
    "social_romance_contacto": "IMPLEMENTADO",
    "pareja_crisis_ruptura_hitos": "IMPLEMENTADO (manual\/hitos, no umbral automático)",
    "deterioro_diario": "IMPLEMENTADO",
    "nota": "No se simula falsamente crisis automática por umbral si no está cableada."
}
```

## J. Marchas — detalle JSON — NO_IMPLEMENTADO

```json
{
    "status": "NO_IMPLEMENTADO",
    "titulo": "Marchas",
    "detalle": "No existe MarchaEngine \/ salida de roster \/ señales de marcha."
}
```

## K. Economía — detalle JSON — NO_IMPLEMENTADO

```json
{
    "status": "NO_IMPLEMENTADO",
    "economy_enabled": false,
    "dinero_juego_v1": null,
    "misiones_playtest_01": true,
    "peticiones_playtest_01": true,
    "nota": "Economía OFF en play canónico; hay lab SimuladorEconomia separado."
}
```

## L. Aforos — detalle JSON — PASS

```json
{
    "status": "PASS",
    "techos": {
        "cafe_libros": 10,
        "rincon_lola": 10,
        "cine_game": 12,
        "mala_idea": 12,
        "parque": 16,
        "gimnasio_spa": 10
    },
    "mala_idea_suma_destinos": 20,
    "mala_idea_techo": 12,
    "techo_impide_20": true,
    "cabe_4_en_disco_con_bar_8": true,
    "cabe_5_en_disco_con_bar_8": false
}
```

## M. Save/load/API/integración — detalle JSON — PASS

```json
{
    "status": "PASS",
    "checks": {
        "save_load_mismo_dia": true,
        "rechaza_misma_persona": true,
        "segunda_propuesta_sin_fatal": true,
        "organizar_misma_persona_causa": true
    },
    "r1_estado": "programada"
}
```

## N. Invariantes — detalle JSON — PASS

```json
{
    "status": "PASS",
    "horizonte": 7,
    "perfiles": [
        "activa",
        "normal",
        "torpe",
        "inactiva"
    ],
    "fallos": [],
    "n_fallos": 0
}
```

## Horizontes

```json
{
    "activa:d30": {
        "n": 2,
        "planes": 94,
        "aceptados": 33,
        "rechazados": 31,
        "cotilleo": 33,
        "fallos_inv": 0,
        "tasa_acept": 0.351
    },
    "normal:d30": {
        "n": 2,
        "planes": 37,
        "aceptados": 13,
        "rechazados": 21,
        "cotilleo": 11,
        "fallos_inv": 0,
        "tasa_acept": 0.351
    },
    "torpe:d30": {
        "n": 2,
        "planes": 57,
        "aceptados": 18,
        "rechazados": 25,
        "cotilleo": 18,
        "fallos_inv": 0,
        "tasa_acept": 0.316
    },
    "inactiva:d30": {
        "n": 2,
        "planes": 6,
        "aceptados": 2,
        "rechazados": 4,
        "cotilleo": 2,
        "fallos_inv": 0,
        "tasa_acept": 0.333
    },
    "activa:d100": {
        "n": 2,
        "planes": 300,
        "aceptados": 48,
        "rechazados": 58,
        "cotilleo": 48,
        "fallos_inv": 0,
        "tasa_acept": 0.16
    },
    "normal:d100": {
        "n": 2,
        "planes": 144,
        "aceptados": 33,
        "rechazados": 50,
        "cotilleo": 33,
        "fallos_inv": 0,
        "tasa_acept": 0.229
    },
    "torpe:d100": {
        "n": 2,
        "planes": 209,
        "aceptados": 47,
        "rechazados": 56,
        "cotilleo": 47,
        "fallos_inv": 0,
        "tasa_acept": 0.225
    },
    "inactiva:d100": {
        "n": 2,
        "planes": 29,
        "aceptados": 13,
        "rechazados": 13,
        "cotilleo": 13,
        "fallos_inv": 0,
        "tasa_acept": 0.448
    },
    "activa:d365": {
        "n": 1,
        "planes": 547,
        "aceptados": 28,
        "rechazados": 32,
        "cotilleo": 28,
        "fallos_inv": 0,
        "tasa_acept": 0.051
    },
    "normal:d365": {
        "n": 1,
        "planes": 325,
        "aceptados": 28,
        "rechazados": 40,
        "cotilleo": 28,
        "fallos_inv": 0,
        "tasa_acept": 0.086
    },
    "torpe:d365": {
        "n": 1,
        "planes": 363,
        "aceptados": 28,
        "rechazados": 39,
        "cotilleo": 28,
        "fallos_inv": 0,
        "tasa_acept": 0.077
    },
    "inactiva:d365": {
        "n": 1,
        "planes": 38,
        "aceptados": 11,
        "rechazados": 18,
        "cotilleo": 11,
        "fallos_inv": 0,
        "tasa_acept": 0.289
    }
}
```

### Sims individuales (resumen)

- seed `s1-activa-d30` perfil **activa** 30d | pop 8 | planes 47 (acept 17/rech 15) | cotilleo 17 | inv_fallos 0
- seed `s2-activa-d30` perfil **activa** 30d | pop 8 | planes 47 (acept 16/rech 16) | cotilleo 16 | inv_fallos 0
- seed `s1-normal-d30` perfil **normal** 30d | pop 8 | planes 15 (acept 5/rech 9) | cotilleo 4 | inv_fallos 0
- seed `s2-normal-d30` perfil **normal** 30d | pop 8 | planes 22 (acept 8/rech 12) | cotilleo 7 | inv_fallos 0
- seed `s1-torpe-d30` perfil **torpe** 30d | pop 8 | planes 25 (acept 8/rech 14) | cotilleo 8 | inv_fallos 0
- seed `s2-torpe-d30` perfil **torpe** 30d | pop 8 | planes 32 (acept 10/rech 11) | cotilleo 10 | inv_fallos 0
- seed `s1-inactiva-d30` perfil **inactiva** 30d | pop 8 | planes 4 (acept 2/rech 2) | cotilleo 2 | inv_fallos 0
- seed `s2-inactiva-d30` perfil **inactiva** 30d | pop 8 | planes 2 (acept 0/rech 2) | cotilleo 0 | inv_fallos 0
- seed `s1-activa-d100` perfil **activa** 100d | pop 8 | planes 142 (acept 24/rech 27) | cotilleo 24 | inv_fallos 0
- seed `s2-activa-d100` perfil **activa** 100d | pop 8 | planes 158 (acept 24/rech 31) | cotilleo 24 | inv_fallos 0
- seed `s1-normal-d100` perfil **normal** 100d | pop 8 | planes 73 (acept 15/rech 23) | cotilleo 15 | inv_fallos 0
- seed `s2-normal-d100` perfil **normal** 100d | pop 8 | planes 71 (acept 18/rech 27) | cotilleo 18 | inv_fallos 0
- seed `s1-torpe-d100` perfil **torpe** 100d | pop 8 | planes 101 (acept 24/rech 24) | cotilleo 24 | inv_fallos 0
- seed `s2-torpe-d100` perfil **torpe** 100d | pop 8 | planes 108 (acept 23/rech 32) | cotilleo 23 | inv_fallos 0
- seed `s1-inactiva-d100` perfil **inactiva** 100d | pop 8 | planes 18 (acept 8/rech 8) | cotilleo 8 | inv_fallos 0
- seed `s2-inactiva-d100` perfil **inactiva** 100d | pop 8 | planes 11 (acept 5/rech 5) | cotilleo 5 | inv_fallos 0
- seed `y1-activa-d365` perfil **activa** 365d | pop 8 | planes 547 (acept 28/rech 32) | cotilleo 28 | inv_fallos 0
- seed `y1-normal-d365` perfil **normal** 365d | pop 8 | planes 325 (acept 28/rech 40) | cotilleo 28 | inv_fallos 0
- seed `y1-torpe-d365` perfil **torpe** 365d | pop 8 | planes 363 (acept 28/rech 39) | cotilleo 28 | inv_fallos 0
- seed `y1-inactiva-d365` perfil **inactiva** 365d | pop 8 | planes 38 (acept 11/rech 18) | cotilleo 11 | inv_fallos 0

