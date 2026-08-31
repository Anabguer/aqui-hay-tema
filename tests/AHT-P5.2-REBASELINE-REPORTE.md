# AHT-P5.2 — REBASELINE CON RELOJ REAL

## Auditoria Harnesses P0-P6

| Harness | Funcion tiempo | Modo | Clasificacion |
|---------|---------------|------|---------------|
| p3_densidad_social.php | avanzarReloj($p,24) | BATCH | C-INVALIDA |
| p3_curva_base_20seeds.php | avanzarReloj($p,24) | BATCH | C-INVALIDA |
| p3_contrafactuales.php | avanzarReloj($p,24) | BATCH | C-INVALIDA |
| p4_baseline_20seeds.php | avanzarReloj($p,24) | BATCH | C-INVALIDA |
| p4_control_activo.php | avanzarReloj($p,24) | BATCH | C-INVALIDA |
| p4_red_social_detalle.php | avanzarReloj($p,24) | BATCH | C-INVALIDA |
| p5_baseline_pasiva_30seeds.php | avanzarReloj($p,24) | BATCH | C-INVALIDA |
| p5_baseline_activa_30seeds.php | avanzarReloj($p,24) | BATCH | C-INVALIDA |
| p5_trayectoria_parejas.php | avanzarReloj($p,24) | BATCH | C-INVALIDA |
| p6_diagnostico_trayectoria.php | avanzarReloj($p,24) | BATCH | C-INVALIDA |
| p2_playtest_10seeds.php | avanzarRelojPasoAPaso($p,30) | PASO A PASO | A-VALIDA |
| p5_1_auditoria_declaracion.php | avanzarRelojPasoAPaso($p,744) | PASO A PASO | A-VALIDA |

## Mecanismo del defecto batch

RelojOperations::avanzar() ejecuta MotorVidaDiaria::tickHora UNA SOLA VEZ
al final del salto, sin importar cuantas horas se saltaron.
Los NPC solo generan 1 tick de vida por dia simulado en vez de 24.

## Resultados Paso a Paso (30 seeds D1-D30)

| Metrica | BATCH | PASO A PASO |
|---------|-------|-------------|
| Encuentros/dia | 1-3 | 3.5 |
| Flechazo activacion | 0% | 87% seeds |
| Parejas D30 | 0% | 60% |
| Primera pareja | nunca | D15-D20 |
| Densidad social | baja (falsa) | saludable |

## Conclusion

A) EL RITMO ROMANTICO YA ES CORRECTO. NO CAMBIAR BALANCE.