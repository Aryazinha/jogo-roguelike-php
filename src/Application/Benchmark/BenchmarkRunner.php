<?php

declare(strict_types=1);

namespace Jogo\Application\Benchmark;

use Jogo\Application\Simulation\MeasurementClock;
use Jogo\Application\Simulation\MonotonicMeasurement;
use Jogo\Core\Config\EvolutionConfigLoader;
use Jogo\Core\Config\UpgradeConfigLoader;
use Jogo\Core\Config\WeaponConfigLoader;
use Jogo\Core\GameLoop;

final readonly class BenchmarkRunner
{
    public function __construct(private BenchmarkSession $session) {}

    public static function fromProject(string $root, MeasurementClock $measurement = new MonotonicMeasurement): self
    {
        $weapons = (new WeaponConfigLoader)->load($root.'/config/weapons.json');
        $upgrades = (new UpgradeConfigLoader($weapons))->load($root.'/config/upgrades.json');
        // Somente IDs de referência existentes; não carrega nem concede passivos da B.
        $evolutions = (new EvolutionConfigLoader($weapons, ['sharpness', 'gunpowder']))->load($root.'/config/evolutions.json');

        return new self(new BenchmarkSession($weapons, $upgrades, $evolutions, $measurement));
    }

    /** @param array<string, mixed> $environment
     * @param  list<float>  $frames
     */
    public function run(BenchmarkOptions $options, array $environment = [], array $frames = [GameLoop::STEP_SECONDS], bool $captureEvents = false): BenchmarkReport
    {
        $runs = [];
        for ($index = 0; $index < $options->runs; $index++) {
            $runs[] = $this->session->run($options, $frames, $captureEvents);
        }

        return new BenchmarkReport($options, $runs, $environment);
    }
}
