<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Contrato narrativo de MENSAJITOS (docs/BUZON_DE_CELESTINE.md).
 *
 * Todo Mensajito del canal buzón lo escribe UN vecino DIRECTAMENTE a
 * Celestine, en primera persona. Nunca es un log, un aviso del sistema
 * ni un cotilleo en tercera persona.
 *
 * Bancos deterministas (CopyVariante) con variedad por remitente y
 * modulación ligera usando SOLO datos públicos de la ficha: voz
 * (registro), tono_coletilla y género. Nunca revela rasgos ocultos,
 * preferencias privadas ni números internos del motor.
 *
 * Paridad RNG: las familias de RESULTADO consumen una tirada canónica
 * (nextInt(0, N-1) + persist) con los mismos tamaños de pool que el
 * sistema anterior, para no desalinear el flujo aleatorio de partidas
 * en curso. El resto de familias usa selección determinista por seed
 * (no consume RNG, igual que el CopyCotilleoFamilias anterior).
 */
final class MensajitoVoz
{
    /** @var array<string, array<string, mixed>> */
    private static array $cacheFichas = [];

    // ------------------------------------------------------------------
    // Bancos de copy (primera persona, hablante → Celestine)
    // ------------------------------------------------------------------

    /** @return list<string> */
    private static function bancoResultadoCumplida(bool $conOtro): array
    {
        if ($conOtro) {
            return [
                '{otro} y yo lo pasamos genial. Buen ojo, Celestine.',
                'Al final {otro} se animó y salió redondo. Tú lo viste venir.',
                'Quedé con {otro} y fue estupendo. Apunta que me debes una.',
                'Lo de {otro} y yo funcionó. Gracias por empujarlo, Celestine.',
                '{otro} y yo la pasamos de lujo. No me lo esperaba.',
            ];
        }
        return [
            'Se pudo, Celestine. Y me vino de perlas.',
            'Hecho. Justo lo que necesitaba.',
            'Gracias por acordarte de mí. Quedó de lujo.',
            'Al final se hizo y me encantó. Tienes buen pulso para esto.',
            'Estupendo. Exacto lo que necesitaba.',
        ];
    }

    /** @return list<string> */
    private static function bancoResultadoCaducada(string $grupo): array
    {
        if ($grupo === 'seca') {
            return [
                'Nada, olvídalo. Ya no me apetece.',
                'El arroz ya se me ha pasado. Tema zanjado.',
                'Se acabó lo que era. Siguiente tema.',
                'Déjalo. Las ganas se me esfumaron.',
            ];
        }
        return [
            'Nada, olvídalo, Celestine. Se me han pasado las ganas.',
            'Déjalo correr. Al final no era para tanto.',
            'Lo de "{texto}" ya lo doy por perdido. Otra vez será.',
            'La idea se me fue enfriando... Lo dejamos ahí, ¿va?',
        ];
    }

    /** @return list<string> */
    private static function bancoResultadoIgnorada(string $grupo): array
    {
        if ($grupo === 'seca') {
            return [
                'Vi que pasabas. Pues nada, lo doy por cerrado.',
                'Sin respuesta, sin plan. Asunto archivado.',
                'Ya está. Quedó en nada.',
            ];
        }
        return [
            'Ya que no hubo respuesta... nada, Celestine. Lo dejo así.',
            'Supongo que no llegó el momento. Sin rencor, eh.',
            'Sin rencor, pero se me fueron pasando las ganas por el camino.',
        ];
    }

    /** @return list<string> */
    private static function bancoRechazoTercero(): array
    {
        return [
            'Mal asunto: {otro} prefiere que sea otro día. Yo sigo con las ganas.',
            'Esta vez no pudo ser: {otro} no estaba por la labor. Lo intentamos luego, ¿vale?',
            'Que {otro} ha dicho que no... Paciencia. Queda pendiente, no cancelado.',
            'No contaba con esto: hoy a {otro} no le apetece. Avisaré cuando reintentemos.',
        ];
    }

    /** @return list<string> */
    private static function bancoMarchaIntencion(): array
    {
        return [
            'Celestine, tengo algo que contarte: estoy pensando en irme del pueblo.',
            'Llevo tiempo dándole vueltas... Puede que toque hacer las maletas. Quería decírtelo a ti primero.',
            'Últimamente no me encuentro aquí. Me estoy planteando marcharme, y en serio.',
            'Te lo digo yo antes de que se entere el pueblo: quiero irme.',
        ];
    }

    /** @return list<string> */
    private static function bancoMarchaSeQueda(): array
    {
        return [
            'Está bien, me quedo. Por ti, Celestine.',
            'Me has convencido: un tiempo más en el pueblo.',
            'Vale, me quedo. Pero que no se diga que soy blandengue.',
            'Aquí sigo. Supongo que este pueblo aún tiene cosas que decirme.',
        ];
    }

    /** @return list<string> */
    private static function bancoMarchaIntencionAislamiento(): array
    {
        return [
            'Celestine, llevo días sin hablar con nadie. No sé si aquí es mi sitio.',
            'Últimamente paso desapercibido. Me estoy planteando irme.',
            'Aquí nadie me llama. Empiezo a pensar en marcharme.',
        ];
    }

    /** @return list<string> */
    private static function bancoMarchaIntencionEmocion(): array
    {
        return [
            'Celestine, no me encuentro bien. Llevo un tiempo muy bajo y me planteo irme.',
            'Últimamente me cuesta levantarme con ilusión. Quizá debería marcharme.',
            'No es un día malo: es una racha. Y me da vueltas la idea de irme.',
        ];
    }

    /** @return list<string> */
    private static function bancoMarchaIntencionConflicto(): array
    {
        return [
            'Celestine, hay tensión con gente del pueblo. No sé si puedo seguir aquí.',
            'Últimamente las cosas se han puesto difíciles con otros vecinos. Me planteo irme.',
            'No quiero más mal rollo. Estoy pensando en marcharme.',
        ];
    }

    /** @return list<string> */
    private static function bancoMarchaIntencionCrisis(): array
    {
        return [
            'Celestine, he pasado por un momento muy duro. Necesito pensar si sigo aquí.',
            'No ha sido fácil estos días. Me estoy planteando dejar el pueblo.',
            'Algo se ha roto para mí aquí. Quería contártelo antes de decidir.',
        ];
    }

    /** @return list<string> */
    private static function bancoMarchaDespedida(): array
    {
        return [
            'Gracias por todo, Celestine. Me llevo buenos recuerdos.',
            'No ha sido fácil decidirlo, pero es hora. Cuídate.',
            'Me voy con lo justo. Gracias por haberme acogido.',
        ];
    }

    /** @return list<string> */
    private static function bancoMarchaLegado(): array
    {
        return [
            'No me cabe en la maleta. Creo que aquí tendrá mejor vida.',
            'Para ti, Celestine. No sabía qué regalarte, así que he elegido esto.',
            'Quiero que esto quede contigo. Me hace ilusión que lo uses.',
        ];
    }

    /** @return list<string> */
    private static function bancoCandidatoOferta(): array
    {
        return [
            'Hola, Celestine. Me llamo {nombre} y me encantaría mudarme al pueblo. ¿Me guardas un hueco? Necesito respuesta antes del día {dia}.',
            'Buenas, soy {nombre}. Dicen que aquí se vive bien... ¿Cae un hueco para mí? Tengo hasta el día {dia} para saberlo.',
            'Celestine: {nombre}, de paso por el pueblo. Me gustaría quedarme. ¿Me haces sitio hasta el día {dia}?',
        ];
    }

    /** @return list<string> */
    private static function bancosCandidatoEnCamino(): array
    {
        return [
            '¡Genial, acepto! Voy para allá. Me ves llegar en unos {min} minuto{s}.',
            'Pues me pongo en camino. Cuenta unos {min} minuto{s} y ahí estaré.',
        ];
    }

    /** @return list<string> */
    private static function bancoCandidatoLlegado(): array
    {
        return [
            '¡Ya estoy aquí, Celestine! Vivienda asignada y todo. Nos vemos por el pueblo.',
            'Aquí llega {nombre}. Ya tengo mi rincón en el pueblo. Cuando quieras, nos tomamos algo.',
            'Instalad{oa} y con llaves propias. Gracias por la bienvenida, Celestine.',
        ];
    }

    /** @return list<string> */
    private static function bancoBienvenidaBucle(): array
    {
        return [
            '¡Celestine! El pueblo ya está en marcha. Echa un vistazo a quién hay por aquí y, si te apetece, propón un plan. Yo me apunto a casi todo.',
            'Mira quién ha aparecido: Celestine en persona. El pueblo ya anda con vida propia; mira qué vecinos hay y, si te apetece, propón un plan.',
        ];
    }

    /** @return list<string> */
    private static function bancoTutorialPrimerosPasos(): array
    {
        return [
            'Oye, Celestine: me apetece ir al cine. Por si te da por meter las narices.',
            'Celestine, tengo unas ganas de cine que no puedo más. Si puedes hacer algo, ya sabes.',
            'Me apetece ir al cine, Celestine. No sé si se puede montar algo.',
            'Celestine, hoy me apetece cine. Si te apetece mover los hilos, perfecto.',
        ];
    }

    /** @return list<string> */
    private static function bancoPeticionSinTexto(): array
    {
        return [
            'Celestine, ¿tienes un rato? Me gustaría comentarte algo.',
            'Oye, cuando puedas: quiero comentarte una cosa.',
        ];
    }

    // ------------------------------------------------------------------
    // Bancos espontaneos: F1, F2, F6, F7, F9, F14, F15
    // ------------------------------------------------------------------

    /** @return list<string> */
    private static function bancoFOpinion(): array
    {
        return [
            '¿Qué me dices de {otro}? No sé si estoy haciéndome ilusiones.',
            'Te voy a contar una cosa de {otro}... ¿tú qué opinas?',
            'Últimamente no sé qué pensar de {otro}. ¿Me ayudas a aclararme?',
            'Necesito que me des tu opinión sobre {otro}. En serio.',
            '{otro} me tiene un poco confundido/a. ¿Tú lo conoces bien?',
            'Contigo hablo de {otro}. {historial}, y no sé qué pensar.',
            '{otro} me ronda la cabeza. {historial}, y eso me deja con dudas.',
        ];
    }

    /** @return list<string> */
    private static function bancoFDilema(): array
    {
    return [
            'No sé qué hacer, Celestine. {nombre_a} y {nombre_b}... no puedo decidirme.',
            'Estoy entre {nombre_a} y {nombre_b}. ¿Tú qué harías?',
            'Dos personas me gustan: {nombre_a} y {nombre_b}. Soy un desastre.',
            '¿{nombre_a} o {nombre_b}? Llevo días dándole vueltas.',
            'Contigo hablo, Celestine. {historial}, y no sé qué hacer.',
        ];
    }

    /** @return list<string> */
    private static function bancoFConfidencia(): array
    {
        return [
            'Te confieso algo, Celestine: últimamente estoy {texto}.',
            'No se lo cuento a nadie, pero tú... últimamente me siento {texto}.',
            'Contigo puedo ser sincero/a: estoy {texto}. No sé qué hacer.',
            'Celestine, necesito desahogarme. Estoy {texto} y no sé cuánto aguanto.',
            'Oye, entre nosotros: estoy {texto}. No quiero que se entere nadie.',
        ];
    }

    /** @return list<string> */
    private static function bancoFAlertaVecinal(): array
    {
        return [
            '¿Has visto a {otro}? Lleva unos días bastante apagado/a.',
            'Me preocupa {otro}. No sé si está bien, lleva un tiempo raro.',
            '{otro} no está nada bien. ¿Podrías echarle un ojo?',
            'Celestine, algo le pasa a {otro}. No es el mismo de siempre.',
            'He visto a {otro} un poco bajoneado/a. ¿Tú sabes algo?',
            'Oye, {otro}. {historial}, y ahora me preocupa.',
            'Me da vueltas lo de {otro}. {historial}, y últimamente no lo veo bien.',
        ];
    }

    /** @return list<string> */
    private static function bancoFConfidenciaCrush(): array
    {
        return [
            'Te confieso algo, Celestine: creo que me gusta {otro}.',
            'No se lo he dicho a nadie, pero {otro} me pone nervioso/a.',
            'Entre nosotros: {otro} me gusta. No sé qué hacer.',
            'Celestine, necesito desahogarme. {otro} me tiene loco/a.',
            'Te voy a decir una cosa de {otro}. {historial}, y eso me tiene así.',
        ];
    }

    /** @return list<string> */
    private static function bancoFColectivo(): array
    {
        return [
            'Somos unos cuantos que queremos organizar {texto}. ¿Te apuntas, Celestine?',
            'Oye, estamos montando {texto} y nos vendría bien tu ayuda.',
            '¿Te animas a echar una mano con {texto}? Sería genial.',
            'Celestine, ¿nos ayudas a organizar {texto}?',
        ];
    }

    /** @return list<string> */
    private static function bancoFRitualCumpleAviso(): array
    {
        return [
            'Celestine, hoy es el cumpleaños de {otro}. ¿Le decimos algo?',
            'Oye, que hoy cumple años {otro}. ¿Le felicitamos?',
            'Por si no te habías enterado: hoy es el cumple de {otro}.',
            'Hoy toca celebrar a {otro}. ¿Le echamos una mano?',
        ];
    }

    /** @return list<string> */
    private static function bancoFRitualCumpleInvitacion(): array
    {
        return [
            'Celestine, hoy es mi cumpleaños. ¿Te apuntas a celebrarlo conmigo?',
            'Oye, que hoy cumplo años. ¿Montamos algo?',
            'Hoy es mi día. ¿Me acompañas a celebrarlo?',
            'Celestine, hoy cumplo. ¿Te animas?',
        ];
    }

    /** @return list<string> */
    private static function bancoAnuncioEventoPueblo(): array
    {
        return [
            'Celestine, ¡este {dia_semana} hay {nombre_evento}! {asistencia}.',
            'Te aviso: este {dia_semana} toca {nombre_evento}. {asistencia}.',
            'Oye, Celestine, que este {dia_semana} hay {nombre_evento}. {asistencia}.',
            'Por si no te habías enterado: este {dia_semana} hay {nombre_evento}. {asistencia}.',
        ];
    }

    /** @return list<string> */
    private static function bancoCierreEventoPueblo(string $tono): array
    {
        switch ($tono) {
            case 'cancelado':
                return [
                    'Al final lo de {nombre_evento} no pudo ser, Celestine.',
                    'Celestine, al final {nombre_evento} se cayó. Nada que hacer.',
                    'Pues nada, {nombre_evento} no salió al final.',
                ];
            case 'celebrado_fuerte':
                return [
                    'Celestine, al final {nombre_evento} estuvo genial. {asistencia}.',
                    'Te cuento: {nombre_evento} salió de lujo. {asistencia}.',
                    'Qué bien salió {nombre_evento}. {asistencia}, Celestine.',
                ];
            case 'celebrado_normal':
                return [
                    'Al final {nombre_evento} salió bien. {asistencia}.',
                    'Celestine, {nombre_evento} estuvo bien. {asistencia}.',
                    'Nada mal {nombre_evento}. {asistencia}.',
                ];
            case 'celebrado_tenue':
                return [
                    'Bueno… {nombre_evento} tuvo sus momentos.',
                    'Celestine, {nombre_evento} no estuvo mal, pero tampoco fue la gran cosa.',
                    'Pues {nombre_evento}… se salvó, vamos.',
                ];
            case 'ocurrio':
            default:
                return [
                    'Al final se hizo {nombre_evento}. {asistencia}.',
                    'Celestine, ya pasó {nombre_evento}. {asistencia}.',
                    'Te cuento que {nombre_evento} ya se celebró. {asistencia}.',
                ];
        }
    }

    /** @return list<string> */
    private static function bancoF9Seguimiento(string $resultado = ''): array
    {
        switch ($resultado) {
            case 'bien':
                return [
                    '¿Te acuerdas de lo que me dijiste? Pues mira: {texto}. Tenías razón.',
                    'Lo que me dijiste me vino bien. {texto} y todo ha ido genial.',
                ];
            case 'mal':
            case 'rechazo':
                return [
                    'Te cuento: {texto}. No salió como esperaba.',
                    'Bueno, {texto}. Menos mal que te lo comenté antes.',
                ];
            case 'nada':
                return [
                    'Al final {texto}. Supongo que no era el momento.',
                    'Nada, {texto}. Lo dejo ahí.',
                ];
            case 'pendiente':
                return [
                    'Por cierto: {texto}. Te aviso cuando pase.',
                ];
            case 'calma':
                return [
                    'Tienes razón en lo de ir con calma. {texto}.',
                ];
            default:
                break;
        }
        return [
            '¡{texto}! Al final sí que pasó. Gracias por lo de antes.',
            '¿Te acuerdas de lo que me dijiste? Pues mira: {texto}.',
            'Bueno, te cuento: {texto}. No estaba tan mal como pensaba.',
            'Contigo se habla bien. {texto} y me ha quedado clarísimo.',
        ];
    }

    /** @return list<string> */
    private static function bancoF14Promesa(): array
    {
        return [
            'Oye, que te acuerdes: si vuelvo a quejarme de esto, recuérdamelo.',
            '¿Me haces el favor de recordármelo si vuelvo a lo mismo?',
            'Prométeme que si vuelvo a hacer esto, me lo dices.',
            'Si vuelvo a lo de siempre, avísame. En serio.',
        ];
    }

    /** @return list<string> */
    private static function bancoFCuriosidadCelestine(): array
    {
        return [
            '¿Tú alguna vez te has enamorado, Celestine?',
            'Dime una cosa: ¿con quién te llevarías bien de aquí?',
            '¿No te da envidia ver a la gente tan a su rollo?',
            '¿Tú qué haces cuando no estás con nosotros?',
            '¿Alguna vez has pensado en dejar el pueblo?',
        ];
    }

    /** @return list<string> */
    private static function bancoFPeticion(): array
    {
        return [
            'Celestine, ¿me echas una mano con {texto}?',
            'Te escribo porque necesito que me ayudes con {texto}.',
            'Oye, cuando puedas: {texto}. ¿Me ayudas?',
        ];
    }

    /** @return list<string> */
    private static function bancoFPresentacion(): array
    {
        return [
            'Me gustaría conocer a {otro}. ¿Me lo presentas?',
            'He oído hablar de {otro} y me cae bien de lejos. ¿Puedes presentarnos?',
            'Celestine, ¿me conectas con {otro}? {historial}',
            'No conozco a mucha gente… ¿me presentas a alguien? Tengo {texto} opciones en mente.',
        ];
    }

    /** @return list<string> */
    private static function bancoFDudaPermanencia(): array
    {
        return [
            'Celestine… últimamente me siento {texto} por aquí. ¿De verdad encajo?',
            'No sé si este pueblo es lo mío. Llevo días con poco trato y me lo planteo.',
            'Te lo digo en confianza: me siento {texto}. ¿Qué harías tú?',
            'A veces pienso que nadie me echa de menos. ¿Tú crees que debería quedarme?',
        ];
    }

    /** @return list<string> */
    private static function bancoFMediacion(): array
    {
        return [
            'Con {otro} hemos tenido un mal rollo. ¿Me ayudas a arreglarlo?',
            'No sé cómo hablar con {otro} después de lo que pasó. {historial}',
            'He visto el lío entre gente del pueblo. Con {otro} me gustaría recomponer.',
            'Celestine, ¿puedes echarnos una mano a {otro} y a mí? Esto no puede seguir así.',
        ];
    }

    // ------------------------------------------------------------------
    // API principal
    // ------------------------------------------------------------------

    /**
     * Línea en primera persona para una familia narrativa del canal buzón.
     * Selección determinista (CopyVariante), sin consumo de RNG.
     *
     * @param array<string, mixed> $partida
     * @param array<string, string|int|null> $vars tokens: texto, otro, nombre, dia, min, s, oa
     */
    public static function linea(array &$partida, string $familia, array $vars = [], string $seed = '', ?string $rid = null): string
    {
        $grupo = self::grupoVoz($partida, $rid);
        $pool = self::pool($familia, $vars, $grupo);
        // Variantes que dependen de un token vacío no entran (evitan huecos raros).
        foreach ($vars as $k => $v) {
            if ((string) $v === '') {
                $pool = array_values(array_filter($pool, static function ($tpl) use ($k) {
                    return is_string($tpl) && strpos($tpl, '{' . $k . '}') === false;
                }));
            }
        }
        if ($pool === []) {
            return '';
        }
        $claveSeed = $seed !== '' ? $seed : $familia . '|' . implode('|', array_map('strval', $vars));
        $plantilla = CopyVariante::elegir($partida, 'mensajito_voz|' . $familia, $pool, $claveSeed);
        $out = strtr((string) $plantilla, self::tokens($vars));
        // Sin token disponible, la variante muere limpia (nunca llega un "{otro}" al jugador).
        $out = trim((string) preg_replace('/\{[a-z_]+\}/u', '', $out));
        $out = trim((string) preg_replace('/\s{2,}/u', ' ', $out));
        if ($out === '') {
            return '';
        }
        $coletilla = self::coletilla($partida, $rid);
        if ($coletilla !== '' && self::aplicaColetilla($familia, $claveSeed)) {
            $out = rtrim($out, '.') . '. ' . $coletilla;
        }
        return Utf8Text::paraJson($out);
    }

    /**
     * Tamaño canónico de pool por familia de RESULTADO. Fijado para consumir
     * EXACTAMENTE la misma tirada RNG que el sistema anterior (paridad de
     * flujo: cero deriva mecánica en simulaciones largas).
     *
     * @return array<string, int>
     */
    public static function tamanoCanonico(): array
    {
        return [
            'resultado_cumplida' => 5,
            'resultado_caducada' => 4,
            'resultado_ignorada' => 3,
            'resultado_rechazo_tercero' => 4,
        ];
    }

    /**
     * Línea para familias de resultado usando el RNG DE LA PARTIDA (misma
     * tirada que el sistema anterior: un nextInt(0, N-1) + persist).
     *
     * @param array<string, mixed> $partida
     * @param array<string, string|int|null> $vars
     */
    public static function lineaRng(array &$partida, string $familia, array $vars, ?string $rid, RngService $rng): string
    {
        $canon = self::tamanoCanonico();
        if (!isset($canon[$familia])) {
            return self::linea($partida, $familia, $vars, '', $rid);
        }
        $grupo = self::grupoVoz($partida, $rid);
        $pool = self::pool($familia, $vars, $grupo);
        foreach ($vars as $k => $v) {
            if ((string) $v === '') {
                $pool = array_values(array_filter($pool, static function ($tpl) use ($k) {
                    return is_string($tpl) && strpos($tpl, '{' . $k . '}') === false;
                }));
            }
        }
        $n = $canon[$familia];
        if (count($pool) !== $n || $n === 0) {
            // Contrato roto en bancos: mejor determinista que desalinear el stream.
            return self::linea($partida, $familia, $vars, $familia . '|' . ($vars['texto'] ?? ''), $rid);
        }
        $idx = $rng->nextInt(0, $n - 1);
        $rng->persistToPartida($partida);
        $out = strtr((string) $pool[$idx], self::tokens($vars));
        $out = trim((string) preg_replace('/\{[a-z_]+\}/u', '', $out));
        $out = trim((string) preg_replace('/\s{2,}/u', ' ', $out));
        if ($out === '') {
            return '';
        }
        $coletilla = self::coletilla($partida, $rid);
        if ($coletilla !== '' && abs(crc32('coletilla|' . $idx . '|' . ($vars['texto'] ?? ''))) % 3 === 0) {
            $out = rtrim($out, '.') . '. ' . $coletilla;
        }
        return Utf8Text::paraJson($out);
    }

    /** Familias soportadas (para gates/tests). @return list<string> */
    public static function familias(): array
    {
        return [
            'resultado_cumplida',
            'resultado_caducada',
            'resultado_ignorada',
            'resultado_rechazo_tercero',
            'marcha_intencion',
            'marcha_se_queda',
            'candidato_oferta',
            'candidato_en_camino',
            'candidato_llegado',
            'bienvenida_bucle',
            'tutorial_primeros_pasos',
            'peticion_sin_texto',
            'f_opinion',
            'f_dilema',
            'f_confidencia',
            'f_confidencia_crush',
            'f_alerta_vecinal',
            'f_colectivo',
            'seguimiento_consejo',
            'f_promesa',
            'f_curiosidad_celestine',
            'f_peticion',
            'f_presentacion',
            'f_duda_permanencia',
            'f_mediacion',
            'anuncio_evento_pueblo',
            'cierre_evento_pueblo',
            'f_ritual_cumple_aviso',
            'f_ritual_cumple_invitacion',
        ];
    }

    /**
     * Perfil público del remitente: nombre real para UI (remitente_nombre),
     * género para concordancias y grupo de voz. Solo datos ya visibles.
     *
     * @param array<string, mixed> $partida
     * @return array{nombre: string, genero: string, voz: ?string}
     */
    public static function perfilPublico(array $partida, string $rid): array
    {
        $nombre = IdentidadPublica::nombre($partida, $rid);
        $ficha = self::ficha($partida, $rid);
        $genero = '';
        $voz = null;
        if (isset($partida['residentes'][$rid]['narrativa']['voz'])) {
            $vozRaw = $partida['residentes'][$rid]['narrativa']['voz'];
            $voz = is_string($vozRaw) && $vozRaw !== '' ? $vozRaw : null;
        }
        if (isset($partida['residentes'][$rid]['identidad_publica']['genero'])
            && is_string($partida['residentes'][$rid]['identidad_publica']['genero'])) {
            $genero = (string) $partida['residentes'][$rid]['identidad_publica']['genero'];
        }
        if ($ficha !== null) {
            if ($voz === null) {
                $n = $ficha['narrativa']['voz'] ?? null;
                $voz = is_string($n) && $n !== '' ? $n : null;
            }
            if ($genero === '') {
                $g = $ficha['identidad']['genero'] ?? '';
                $genero = is_string($g) ? $g : '';
            }
        }
        return ['nombre' => $nombre, 'genero' => $genero, 'voz' => $voz];
    }

    /** Concordancia o/a según género público (fallback neutro 'o'). */
    public static function oa(string $genero): string
    {
        return $genero === 'mujer' ? 'a' : 'o';
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    /** @param array<string, string|int|null> $vars @return list<string> */
    private static function pool(string $familia, array $vars, string $grupo): array
    {
        switch ($familia) {
            case 'resultado_cumplida':
                return self::bancoResultadoCumplida(trim((string) ($vars['otro'] ?? '')) !== '');
            case 'resultado_caducada':
                return self::bancoResultadoCaducada($grupo);
            case 'resultado_ignorada':
                return self::bancoResultadoIgnorada($grupo);
            case 'resultado_rechazo_tercero':
                return self::bancoRechazoTercero();
            case 'marcha_intencion':
                return self::bancoMarchaIntencion();
            case 'marcha_intencion_aislamiento':
                return self::bancoMarchaIntencionAislamiento();
            case 'marcha_intencion_emocion_negativa':
                return self::bancoMarchaIntencionEmocion();
            case 'marcha_intencion_conflicto':
                return self::bancoMarchaIntencionConflicto();
            case 'marcha_intencion_crisis':
                return self::bancoMarchaIntencionCrisis();
            case 'marcha_se_queda':
                return self::bancoMarchaSeQueda();
            case 'marcha_despedida':
                return self::bancoMarchaDespedida();
            case 'marcha_legado':
                return self::bancoMarchaLegado();
            case 'candidato_oferta':
                return self::bancoCandidatoOferta();
            case 'candidato_en_camino':
                return self::bancosCandidatoEnCamino();
            case 'candidato_llegado':
                return self::bancoCandidatoLlegado();
            case 'bienvenida_bucle':
                return self::bancoBienvenidaBucle();
            case 'tutorial_primeros_pasos':
                return self::bancoTutorialPrimerosPasos();
            case 'peticion_sin_texto':
                return self::bancoPeticionSinTexto();
            case 'f_opinion':
                return self::bancoFOpinion();
            case 'f_dilema':
                return self::bancoFDilema();
            case 'f_confidencia':
                return self::bancoFConfidencia();
            case 'f_confidencia_crush':
                return self::bancoFConfidenciaCrush();
            case 'f_alerta_vecinal':
                return self::bancoFAlertaVecinal();
            case 'f_colectivo':
                return self::bancoFColectivo();
            case 'seguimiento_consejo':
                return self::bancoF9Seguimiento((string) ($vars['resultado'] ?? ''));
            case 'f_promesa':
                return self::bancoF14Promesa();
            case 'f_curiosidad_celestine':
                return self::bancoFCuriosidadCelestine();
            case 'f_peticion':
                return self::bancoFPeticion();
            case 'f_presentacion':
                return self::bancoFPresentacion();
            case 'f_duda_permanencia':
                return self::bancoFDudaPermanencia();
            case 'f_mediacion':
                return self::bancoFMediacion();
            case 'anuncio_evento_pueblo':
                return self::bancoAnuncioEventoPueblo();
            case 'cierre_evento_pueblo':
                return self::bancoCierreEventoPueblo((string) ($vars['tono'] ?? 'ocurrio'));
            case 'f_ritual_cumple_aviso':
                return self::bancoFRitualCumpleAviso();
            case 'f_ritual_cumple_invitacion':
                return self::bancoFRitualCumpleInvitacion();
            default:
                return [];
        }
    }

    /** @param array<string, string|int|null> $vars @return array<string, string> */
    private static function tokens(array $vars): array
    {
        $t = [];
        foreach (['texto', 'otro', 'nombre', 'nombre_a', 'nombre_b', 'dia', 'min', 's', 'oa', 'dia_semana', 'nombre_evento', 'asistencia', 'historial'] as $k) {
            $v = $vars[$k] ?? null;
            if ($v !== null && $v !== '') {
                $t['{' . $k . '}'] = (string) $v;
            }
        }
        return $t;
    }

    /** Grupo de voz derivado SOLO del registro público (VozPerfil). */
    private static function grupoVoz(array $partida, ?string $rid): string
    {
        if ($rid === null || $rid === '') {
            return 'neutral';
        }
        $p = self::perfilPublico($partida, $rid);
        $voz = VozPerfil::normalizar($p['voz']);
        $reg = (string) ($voz['registro'] ?? '');
        if (in_array($reg, ['seca', 'borde'], true)) {
            return 'seca';
        }
        if (in_array($reg, ['tranquila', 'calida', 'candida'], true)) {
            return 'calida';
        }
        return 'neutral';
    }

    private static function coletilla(array $partida, ?string $rid): string
    {
        if ($rid === null || $rid === '') {
            return '';
        }
        if (isset($partida['residentes'][$rid]['narrativa']['tono_coletilla'])) {
            $c = $partida['residentes'][$rid]['narrativa']['tono_coletilla'];
            return is_string($c) ? trim($c) : '';
        }
        $ficha = self::ficha($partida, $rid);
        if ($ficha === null) {
            return '';
        }
        $c = $ficha['narrativa']['tono_coletilla'] ?? null;
        return is_string($c) ? trim($c) : '';
    }

    /** La coletilla aparece solo en algunos resultados/marchas y de forma determinista. */
    private static function aplicaColetilla(string $familia, string $seed): bool
    {
        if (!in_array($familia, ['resultado_caducada', 'resultado_ignorada', 'marcha_se_queda'], true)) {
            return false;
        }
        return abs(crc32('coletilla|' . $seed)) % 3 === 0;
    }

    /**
     * Ficha de catálogo del residente (lectura pública, caché por request).
     *
     * @param array<string, mixed> $partida
     * @return array<string, mixed>|null
     */
    private static function ficha(array $partida, string $rid): ?array
    {
        $cid = (string) ($partida['residentes'][$rid]['catalog_id'] ?? $rid);
        if ($cid === '') {
            return null;
        }
        if (isset(self::$cacheFichas[$cid])) {
            $hit = self::$cacheFichas[$cid];
            return is_array($hit) ? $hit : null;
        }
        try {
            $catalog = new Catalog(dirname(__DIR__, 2));
            $ficha = $catalog->loadPersonaje($cid);
        } catch (\Throwable $e) {
            $ficha = null;
        }
        self::$cacheFichas[$cid] = $ficha ?? false;
        return $ficha;
    }
}
