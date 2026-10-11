<?php

declare(strict_types=1);

namespace Jogo\Application\Simulation;

final readonly class SimulationReport
{
    public function __construct(public SimulationOptions $options, public SimulationMetrics $logical,
        public UpdateTiming $timing, public string $checksum) {}

    public function toJson(): string
    {
        return json_encode(['parameters' => $this->options, 'logical' => $this->logical,
            'timing' => ['unit' => 'nanoseconds', 'total' => $this->timing->totalNanoseconds,
                'average' => $this->timing->averageNanoseconds(), 'max' => $this->timing->maxNanoseconds],
            'checksum' => $this->checksum], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    public function toText(): string
    {
        $logical = $this->logical;

        return sprintf("Simulação A9 — seed %d\nPassos: %d | tempo lógico: %.6f s\n".
            "Entidades no fim: %d | pico: %d\nProjéteis no fim: %d | pico: %d | capacidade: %d\n".
            "Aquisições: %d | liberações (incluindo limpeza): %d | rajadas recusadas: %d\n".
            "Ataques: %d | impactos com dano: %d | mortes: %d\n".
            "Colisão — candidatos: %d (máximo por passo: %d); testes estreitos: %d (máximo: %d)\n".
            "Atualização — total: %.3f ms | média: %.3f ms | máximo: %.3f ms\n".
            "Após limpeza: %d ativos, %d disponíveis\nChecksum: %s\n",
            $this->options->seed, $logical->steps, $logical->elapsedSeconds, $logical->entitiesAtEnd,
            $logical->peakEntities, $logical->poolAtEnd->active, $logical->poolAtEnd->peakActive,
            $logical->poolAtEnd->capacity, $logical->poolAtEnd->acquisitions, $logical->poolAfterCleanup->releases,
            $logical->poolAtEnd->refusedBatches, $logical->acceptedAttacks, $logical->impacts, $logical->deaths,
            $logical->collisions->totalCandidates, $logical->collisions->maxStepCandidates,
            $logical->collisions->totalNarrowTests, $logical->collisions->maxStepNarrowTests,
            $this->timing->totalNanoseconds / 1e6, $this->timing->averageNanoseconds() / 1e6,
            $this->timing->maxNanoseconds / 1e6, $logical->poolAfterCleanup->active,
            $logical->poolAfterCleanup->available, $this->checksum);
    }
}
