<?php

declare(strict_types=1);

namespace Jogo\Application\Benchmark;

final readonly class BenchmarkRun
{
    public Distribution $timing;

    /**
     * @param  list<int>  $durations  Nanossegundos de todos os passos medidos.
     * @param  array<string, mixed>  $state  Estado anterior à limpeza; não exportado na CLI.
     * @param  list<array<mixed>>  $events  Valores capturados somente nos testes de regressão.
     */
    public function __construct(public array $durations, public CollisionSummary $collisions,
        public BenchmarkLogical $logical, public string $checksum, public string $eventChecksum,
        public array $state, public array $events, public int $memoryStartBytes, public int $memoryPeakBytes)
    {
        $this->timing = new Distribution($durations);
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return ['logical' => $this->logical, 'checksum' => $this->checksum, 'eventChecksum' => $this->eventChecksum,
            'timingMilliseconds' => $this->timing->values(1_000_000.0), 'updatesPerSecond' => $this->timing->updatesPerSecond(),
            'collisions' => $this->collisions->values(), 'memoryStartBytes' => $this->memoryStartBytes,
            'processMemoryPeakBytes' => $this->memoryPeakBytes];
    }
}
