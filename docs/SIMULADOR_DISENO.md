# Simulador de diseño

**No se programa en esta fase.**

El Maestro pedía ~1.000 partidas para cazar estados imposibles y combinaciones dominantes. Sigue siendo el plan. Cambia el entorno: ya no se asume un corte a 30 días ni un mapa de 8 sitios siempre abiertos.

## Preguntas (se mantienen y se añaden)

Las 8 de la fase 1, más:

- ¿El pueblo se queda atascado si solo hay 2–3 lugares?
- ¿Empezar con 3 deja el mapa muerto o enseña bien?
- ¿Las llegadas irregulares aburren (pocas) o saturan (muchas)?
- ¿Un 24 único rompe diario / El pueblo / móvil? (dirección: **no** usarlo; A luego B)
- ¿~16 en **un** bloque es el techo jugable de la primera prueba?
- ¿«Quédate» siempre deja A sellado y obliga a B demasiado pronto?
- ¿El catch-up offline dispara demasiados irreversibles o congela el pueblo? (una marcha **nunca** se resuelve AFK)
- ¿Al abrir B, 32 tarjetas / el café saturado rompen igual que un 24 único?
- ¿El goteo económico se dispara con n residentes?
- ¿Los NPC se bastan entre ellos a partir de ~12, o todo espera al jugador?
- ¿Los encuentros espontáneos rompen todas las parejas?
- ¿Alguna ocupación deja a alguien siempre indisponible?
- ¿El grafo social (amigos que no se soportan) hace inviable el romance?
- ¿Con mapa grande bloqueado el jugador «no tiene opciones» al inicio de forma injusta?
- ¿Las preferencias se mueven sin causa (restaurante cerrado → odio a los gamers)?
- ¿Cuántos ejes cambian por personaje en 60 días? ¿Cuántos son temporales vs consolidados?
- ¿Alguien pierde la identidad (demasiados ejes girados)?
- ¿Todas las semillas convergen al mismo perfil medio de gustos?

## Entradas futuras

Catálogos, pesos v1, bancos, **políticas de jugador**, semilla RNG, y además: mapa con flags de desbloqueo, rutinas, grafo social, economía dummy.

El horizonte de simulación es **una o varias temporadas** (duración aún sin cifrar), no un corte a 30 días. Mapa de inicio: 3 lugares.

## Políticas

Se mantienen las de la fase 1 (`aleatorio_legal`, `max_atraccion`, `estabilizar`, `ignorar_crisis`, `monocultivo`, `grafo_maximo`) y se añadirán después: `solo_desbloquear`, `ignorar_diario`, `aprendizaje_on` / `aprendizaje_off` (`APRENDIZAJE_PREFERENCIAS.md`).

## Dependencias (siguen siendo las de diseño, no de código)

No tiene sentido programarlo antes de: 16 fichas, grafo validado, 4 variables, agenda, pesos v1, objetivos de temporada, 3 lugares iniciales, vida NPC mínima.

Todo eso está en `PENDIENTES_DISENO.md`.
