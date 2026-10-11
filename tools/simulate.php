<?php

declare(strict_types=1);

use Jogo\Application\Simulation\HeadlessSimulation;
use Jogo\Application\Simulation\MonotonicMeasurement;
use Jogo\Application\Simulation\SimulationOptions;
use Jogo\Core\Config\WeaponConfigLoader;
use Jogo\Infrastructure\Input\NeutralInput;

require dirname(__DIR__).'/vendor/autoload.php';

try {
    if (PHP_SAPI !== 'cli' || ! isset($argv)) {
        throw new RuntimeException('Use PHP CLI com register_argc_argv habilitado.');
    }
    $options = SimulationOptions::fromArguments(array_slice($argv, 1));
    $weapons = (new WeaponConfigLoader)->load(dirname(__DIR__).'/config/weapons.json');
    $report = (new HeadlessSimulation($weapons, new NeutralInput, new MonotonicMeasurement))->run($options);
    echo $options->json ? $report->toJson().PHP_EOL : $report->toText();
} catch (Throwable $error) {
    fwrite(STDERR, 'Erro na simulação: '.$error->getMessage().PHP_EOL);
    exit(1);
}
