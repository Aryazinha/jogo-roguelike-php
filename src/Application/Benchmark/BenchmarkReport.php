<?php

declare(strict_types=1);

namespace Jogo\Application\Benchmark;

use InvalidArgumentException;

final readonly class BenchmarkReport
{
    public Distribution $timing;

    public Distribution $collisionRatios;

    /** @param list<BenchmarkRun> $runs
     * @param  array<string, mixed>  $environment
     */
    public function __construct(public BenchmarkOptions $options, public array $runs, public array $environment)
    {
        if ($runs === [] || count($runs) !== $options->runs) {
            throw new InvalidArgumentException('A quantidade de resultados deve corresponder às execuções pedidas.');
        }
        $this->timing = new Distribution(array_merge(...array_map(static fn (BenchmarkRun $run): array => $run->durations, $runs)));
        $this->collisionRatios = new Distribution(array_merge(...array_map(static fn (BenchmarkRun $run): array => $run->collisions->perEntitySamples, $runs)));
    }

    /** @return array<string, bool> */
    public function acceptance(): array
    {
        $goals = ['300Enemies' => $this->options->enemies === 300, '200Projectiles' => $this->options->projectiles === 200,
            'poolReuse' => true, 'cleanup' => true, 'noDeaths' => true, 'p95AtMost16.6ms' => true,
            'collisionMaximumBelow40' => true, 'identicalChecksums' => count(array_unique(array_map(static fn (BenchmarkRun $r): string => $r->checksum, $this->runs))) === 1];
        foreach ($this->runs as $run) {
            $logical = $run->logical;
            $goals['300Enemies'] = $goals['300Enemies'] && $logical->minimumEnemies === 300 && $logical->maximumEnemies === 300;
            $goals['200Projectiles'] = $goals['200Projectiles'] && $logical->measuredProjectilePeak === 200 && $logical->poolAtEnd->peakActive <= 200;
            $goals['poolReuse'] = $goals['poolReuse'] && $logical->poolAtEnd->acquisitions > $logical->poolAtEnd->capacity;
            $goals['cleanup'] = $goals['cleanup'] && $logical->poolAfterCleanup->active === 0
                && $logical->poolAfterCleanup->available === $logical->poolAfterCleanup->capacity;
            $goals['noDeaths'] = $goals['noDeaths'] && $logical->deaths === 0;
            $goals['p95AtMost16.6ms'] = $goals['p95AtMost16.6ms'] && $run->timing->meetsTimeBudget();
            $goals['collisionMaximumBelow40'] = $goals['collisionMaximumBelow40'] && $run->collisions->belowBudget;
        }

        return $goals;
    }

    public function exitCode(): int
    {
        return in_array(false, $this->acceptance(), true) ? 2 : 0;
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return ['scenario' => 'a10-load-v1', 'tool' => 'tools/benchmark.php', 'parameters' => $this->options,
            'environment' => $this->environment, 'measurement' => ['scope' => 'movement, spatial index, contacts, weapons, damage, events and geometry counters',
                'excluded' => 'configuration loading, initialization, warmup, serialization, checksums, reports and cleanup',
                'percentiles' => 'nearest rank: ceil(p * n)', 'timeCriterion' => 'every run: p95 <= 16600000 nanoseconds',
                'collisionDenominator' => 'active Collidable world entities at the end of each fixed step; logical projectiles are excluded conservatively'],
            'timingMilliseconds' => $this->timing->values(1_000_000.0), 'updatesPerSecond' => $this->timing->updatesPerSecond(),
            'collisionRatios' => $this->collisionRatios->values(), 'acceptance' => $this->acceptance(),
            'visualFps' => ['status' => 'not_evaluated', 'reason' => 'Meta visual de 50 FPS pendente de validação conjunta com a Frente D.'],
            'runs' => array_map(static fn (BenchmarkRun $run): array => $run->values(), $this->runs)];
    }

    public function toJson(): string
    {
        return json_encode($this->values(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE)."\n";
    }

    public function toText(): string
    {
        $timing = $this->timing;
        $lines = [sprintf('Benchmark A10 — seed %d | aquecimento %d | passos %d | execuções %d', $this->options->seed,
            $this->options->warmup, $this->options->steps, $this->options->runs),
            sprintf('Amostras: %d | total: %.9f ms | média: %.9f ms | mediana: %.9f ms', $timing->count,
                $timing->total / 1e6, $timing->mean / 1e6, $timing->median / 1e6),
            sprintf('p95: %.9f ms | p99: %.9f ms | máximo: %.9f ms | atualizações/s: %s', $timing->p95 / 1e6,
                $timing->p99 / 1e6, $timing->maximum / 1e6, $timing->updatesPerSecond() === null ? 'não disponível' : sprintf('%.6f', $timing->updatesPerSecond())),
            sprintf('Verificações/entidade: média %.9f | p95 %.9f | máximo %.9f', $this->collisionRatios->mean,
                $this->collisionRatios->p95, $this->collisionRatios->maximum)];
        foreach ($this->runs as $index => $run) {
            $lines[] = sprintf('Execução %d: inimigos %d–%d | pico projéteis %d | aquisições %d | recusas %d | candidatos %d | testes %d | passo máximo %d',
                $index + 1, $run->logical->minimumEnemies, $run->logical->maximumEnemies, $run->logical->measuredProjectilePeak,
                $run->logical->poolAtEnd->acquisitions, $run->logical->poolAtEnd->refusedBatches, $run->collisions->totalCandidates,
                $run->collisions->circles + $run->collisions->segments + $run->collisions->sectors, $run->collisions->maximumStep);
            $lines[] = sprintf('Memória inicial: %d B | pico do processo: %d B | checksum: %s', $run->memoryStartBytes, $run->memoryPeakBytes, $run->checksum);
        }
        foreach ($this->acceptance() as $goal => $passed) {
            $lines[] = $goal.': '.($passed ? 'APROVADO' : 'REPROVADO');
        }
        $lines[] = 'Meta visual de 50 FPS pendente de validação conjunta com a Frente D.';

        return implode("\n", $lines)."\n";
    }
}
