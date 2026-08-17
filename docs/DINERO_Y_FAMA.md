# Dinero y fama

Dos recursos de **Celestine**. **No es un juego económico complejo.** Tienen que entenderse de un vistazo, como en la referencia visual.

Valores, precios y umbrales: **no fijados.** Los 1.250 € y la fama 38 de la maqueta son decorado.

## Dinero

Recurso principal. Usos previstos (lista abierta, no economía cerrada):

- desbloquear lugares
- mejorar lugares
- desbloquear determinadas opciones
- comprar / desbloquear ropa
- desbloquear / comprar **herramientas sociales** (test, etc.)
- **abrir / rehabilitar un bloque** (candidato: Bloque B; precio no fijado)
- organizar planes especiales (modifican probabilidades, no compran el amor)
- posibles eventos futuros

Cómo se obtiene: **goteo por la actividad laboral de los residentes presentes** (curva cóncava; no `€ × n`). Puede sumar, más adelante, citas/encargos. Cifras: no. `PROPUESTA_VIDA_POOL_Y_MARCHAS.md`.

El oficio manda **horario** más que sueldo. A+B (~32) no deben dejar el dinero irrelevante: hacen falta sumideros (lugares, herramientas, planes especiales, **abrir B**). El goteo cóncavo se calcula sobre **residentes totales presentes**, no por bloque por separado.

No queremos gestión de impuestos, stocks ni minijuegos de tienda. Una cifra, gastos evidentes, feedback inmediato.

## Fama

El jugador se está convirtiendo en la **Celestine** de la comunidad — quien conoce el pulso del barrio y ayuda con vínculos (no solo parejas).

Puede subir con (candidatos, no pesos):

- citas exitosas
- parejas creadas
- relaciones duraderas
- resolver crisis
- bodas
- objetivos especiales
- historias memorables

Puede desbloquear:

- edificios
- habitantes
- actividades
- ropa
- eventos
- posibilidades nuevas

¿La fama puede bajar (escándalos, rupturas en cadena, rumores)? **Pendiente.** No decidir aquí un sistema de reputación punitiva.

## Cómo se combinan en el mapa

Sensación buscada:

> Empiezo con un pueblo medio vacío → consigo dinero y fama → desbloqueo lugares → el pueblo crece → tengo más opciones para citas y vida social.

Una parcela bloqueada puede pedir las dos cosas, solo dinero, o solo fama. Los números de ejemplo del encargo (`500 € / Fama 10`) son **ilustración**, no tabla de precios.

```text
🔒 Cine
500 € / Fama 10     ← ejemplo, no canon
```

## Relación con otros sistemas

- **Agenda:** más lugares = más sitios donde los NPCs viven y se encuentran, no solo más botones de cita.
- **Ropa:** otro sumidero de dinero/fama.
- **Habitantes:** las llegadas tienen ritmo propio. La fama no «compra un vecino» el día 1. El goteo de dinero escala (cóncavo) con residentes *presentes*.
- **Derrota/victoria:** con recursos persistentes, un corte a 30 días ya no se da por bueno. Ver `PROGRESION_Y_DIFICULTAD.md`.

## Modelo (campos)

Ver `MODELO_DATOS_INICIAL.md`: estado de partida del jugador con `dinero` y `fama`; cada lugar con `desbloqueado`, `coste_dinero`, `requisito_fama`.
