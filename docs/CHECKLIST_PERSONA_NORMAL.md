# Checklist — Prueba “persona normal” (play.php)

Partida de prueba sin abrir `dev.php`.

## Pasos

1. [ ] Abrir `play.php` en el navegador.
2. [ ] Entender quién vive allí: lista de residentes + Bloque A con viviendas.
3. [ ] Seleccionar un residente (lista o vivienda).
4. [ ] Ver qué se sabe de esa persona: bloque Discovery + hobbies visibles + agenda.
5. [ ] Ir a “Proponer encuentro” y elegir Residente A y B distintos.
6. [ ] Comprobar que aparecen horas compatibles (sin probar al azar).
7. [ ] Elegir lugar operativo y pulsar “Programar encuentro”.
8. [ ] Ver feedback de éxito, el mapa centrado en el lugar con el detalle abierto, y el encuentro en “Programados / en curso”.
9. [ ] Usar “Ir al próximo encuentro” (o +8h / +1h) hasta la hora del encuentro.
10. [ ] Comprobar que el encuentro pasa a “En curso” y luego “Terminado”.
11. [ ] Al terminar, leer el resultado (social / romance / conflicto) en el resumen o en “Ver resultado”, sin abrir `dev.php`.
12. [ ] Comprobar que la ficha muestra el último encuentro y permite abrir el detalle.
13. [ ] Opcional: programar otro y cancelarlo desde la lista (confirmación simple).
14. [ ] Ver el próximo encuentro en el resumen del día (nombres, hora, lugar).
15. [ ] Tras +8h o “Ir al próximo”, leer “Durante este avance” (sin listar cada hora).

## Responsive (checklist manual)

- [ ] Móvil: el header no empuja el resumen; Landing/Dev/Guardar/Nueva caben en dos columnas.
- [ ] Móvil: +1h, +8h e “Ir al próximo encuentro” están agrupados bajo el resumen, botones grandes, sin scroll horizontal.
- [ ] Móvil: el mapa muestra badge Ahora/Próximo sin hover; al tocar el lugar se ve quién y cuándo.
- [ ] Móvil: al programar, el lugar no queda oculto bajo el header; sin scroll horizontal; “Ver en mapa” usable al toque.
- [ ] Móvil: “Ver resultado” abre el detalle sin hover; botones no diminutos.
- [ ] Tablet/escritorio: mismos controles, toolbar en fila.

## Criterios de fallo

- Necesitar `dev.php` para cualquier paso anterior.
- No poder distinguir residentes o seleccionarlos.
- No aparecer horas compatibles cuando deberían existir.
- Mensaje de error incomprensible al rechazar un encuentro.
- El encuentro no cambia de estado al avanzar el reloj.
- UI rota en móvil (controles inutilizables o texto ilegible).

## Notas

- Buzón y diario pueden estar vacíos; eso es correcto.
- Retratos pueden mostrar placeholder; eso es correcto.
- Copy de rechazos es funcional/provisional, no narrativo.
