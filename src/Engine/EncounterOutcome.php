<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Facade de lectura sobre el resultado canónico de un encuentro.
 *
 * NO crea nueva persistencia. Lee de:
 * - $enc['resultado'] (array canónico existente)
 * - $enc (datos del encuentro)
 * - $enc['intervencion_celeste'] (si existe)
 *
 * Proporciona interfaz estable para consumidores narrativos.
 * Backward-compatible con saves existentes.
 */
final class EncounterOutcome
{
    private array $enc;
    private array $resultado;
    private ?array $intervencion;

    private function __construct(array $enc, array $resultado, ?array $intervencion)
    {
        $this->enc = $enc;
        $this->resultado = $resultado;
        $this->intervencion = $intervencion;
    }

    public static function fromEncounter(array $enc): self
    {
        $resultado = $enc['resultado'] ?? [];
        $intervencion = $enc['intervencion_celeste'] ?? null;
        return new self($enc, $resultado, $intervencion);
    }

    public static function fromRaw(array $resultado, array $enc = [], ?array $intervencion = null): self
    {
        return new self($enc, $resultado, $intervencion);
    }

    public function esReal(): bool
    {
        return !empty($this->resultado['_deltas_reales']);
    }

    public function esPlaceholder(): bool
    {
        return !empty($this->resultado['_placeholder']);
    }

    public function tipo(): string
    {
        return (string) ($this->enc['tipo'] ?? 'desconocido');
    }

    public function lugar(): ?string
    {
        return $this->enc['lugar'] ?? null;
    }

    public function dia(): int
    {
        return (int) ($this->enc['dia'] ?? 0);
    }

    public function hora(): int
    {
        return (int) ($this->enc['hora'] ?? 0);
    }

    public function participantes(): array
    {
        return array_values($this->enc['participantes'] ?? []);
    }

    public function textoResumen(): string
    {
        return (string) ($this->resultado['texto_resumen'] ?? '');
    }

    public function experienciaNarrativa(): ?array
    {
        return $this->resultado['experiencia_narrativa'] ?? null;
    }

    public function historialPar(): ?array
    {
        return $this->resultado['historial_par'] ?? null;
    }

    public function descubrimientos(): array
    {
        return $this->resultado['descubrimientos'] ?? [];
    }

    public function deltaSocial(): ?array
    {
        return $this->resultado['delta_social'] ?? null;
    }

    public function deltaRomance(): ?array
    {
        return $this->resultado['delta_romance'] ?? null;
    }

    public function conflicto(): ?int
    {
        $c = $this->resultado['conflicto'] ?? null;
        return $c !== null ? (int) $c : null;
    }

    public function tonoCompartido(): string
    {
        $social = $this->deltaSocial();
        if ($social === null) {
            return 'neutral';
        }
        $intensidad = (int) ($social['intensidad'] ?? 0);
        if ($intensidad >= 4) {
            return 'positivo';
        }
        if ($intensidad <= -4) {
            return 'negativo';
        }
        return 'mixto';
    }

    public function perspectiva(string $pid): ?Perspective
    {
        $por = $this->resultado['por_participante'] ?? [];
        if (!isset($por[$pid])) {
            return null;
        }
        $datos = $por[$pid];
        $social = $this->deltaSocial();
        $romance = $this->deltaRomance();
        $participantes = $this->participantes();
        $esA = ($participantes[0] ?? '') === $pid;

        return new Perspective(
            resultado: (string) ($datos['resultado'] ?? 'normal'),
            carga: (float) ($datos['carga'] ?? 0.0),
            cargaTema: (float) ($datos['carga_tema'] ?? 0.0),
            cargaAccion: (float) ($datos['carga_accion'] ?? 0.0),
            socialDelta: $esA
                ? (int) ($social['a_hacia_b'] ?? 0)
                : (int) ($social['b_hacia_a'] ?? 0),
            socialCalidad: $esA
                ? (string) ($social['calidad_a'] ?? 'normal')
                : (string) ($social['calidad_b'] ?? 'normal'),
            romanceDelta: $romance !== null
                ? ($esA ? (int) ($romance['a_hacia_b'] ?? 0) : (int) ($romance['b_hacia_a'] ?? 0))
                : 0,
            conflictoDelta: $this->conflicto(),
            copyMentes: $datos['texto'] ?? null,
            compatibilidadHaciaOtro: $datos['compatibilidad_hacia_otro'] ?? null,
        );
    }

    public function intervencion(): ?IntervencionSummary
    {
        if ($this->intervencion === null) {
            return null;
        }
        $int = $this->intervencion;
        return new IntervencionSummary(
            accion: (string) ($int['accion'] ?? ''),
            temaId: $int['tema_id'] ?? null,
            afinidadTema: $int['afinidad_tema'] ?? null,
            rompeHielo: $int['rompe_hielo'] ?? null,
            beneficiario: $int['beneficiario'] ?? null,
            carga: isset($int['carga']) ? (float) $int['carga'] : null,
            temaCargas: $int['tema_cargas'] ?? [],
        );
    }
}

final class Perspective
{
    public function __construct(
        public readonly string $resultado,
        public readonly float $carga,
        public readonly float $cargaTema,
        public readonly float $cargaAccion,
        public readonly int $socialDelta,
        public readonly string $socialCalidad,
        public readonly int $romanceDelta,
        public readonly ?int $conflictoDelta,
        public readonly ?string $copyMentes,
        public readonly ?float $compatibilidadHaciaOtro,
    ) {}

    public function esPositivo(): bool
    {
        return in_array($this->resultado, ['bien', 'muy_bien'], true);
    }

    public function esNegativo(): bool
    {
        return in_array($this->resultado, ['mal', 'muy_mal'], true);
    }

    public function efectoMentes(): string
    {
        if ($this->cargaTema > 0.1) {
            return 'ayudo_notablemente';
        }
        if ($this->cargaTema > 0) {
            return 'ayudo_un_poco';
        }
        if ($this->cargaTema < -0.1) {
            return 'perjudico_notablemente';
        }
        if ($this->cargaTema < 0) {
            return 'perjudico_un_poco';
        }
        return 'sin_efecto';
    }
}

final class IntervencionSummary
{
    public function __construct(
        public readonly string $accion,
        public readonly ?string $temaId,
        public readonly ?string $afinidadTema,
        public readonly ?string $rompeHielo,
        public readonly ?string $beneficiario,
        public readonly ?float $carga,
        public readonly array $temaCargas,
    ) {}

    public function hayTema(): bool
    {
        return $this->temaId !== null && $this->temaId !== '';
    }

    public function esAfin(): bool
    {
        return $this->afinidadTema === 'afin';
    }

    public function esAversion(): bool
    {
        return $this->afinidadTema === 'aversion';
    }

    public function cargaPara(string $pid): float
    {
        return (float) ($this->temaCargas[$pid] ?? 0.0);
    }
}
