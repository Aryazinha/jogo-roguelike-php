<?php

declare(strict_types=1);

use Jogo\Application\Benchmark\BenchmarkEnvironment;
use Jogo\Application\Benchmark\BenchmarkOptions;
use Jogo\Application\Benchmark\BenchmarkRunner;

require dirname(__DIR__).'/vendor/autoload.php';

try {
    if (PHP_SAPI !== 'cli' || ! isset($argv)) {
        throw new RuntimeException('Execute no PHP CLI com register_argc_argv habilitado.');
    }
    $root = dirname(__DIR__);
    $options = BenchmarkOptions::fromArguments(array_slice($argv, 1));
    $environment = BenchmarkEnvironment::capture($root);
    $report = BenchmarkRunner::fromProject($root)->run($options, $environment);
    echo $options->json ? $report->toJson() : $report->toText();
    exit($report->exitCode());
} catch (Throwable $error) {
    fwrite(STDERR, 'Erro no benchmark: '.$error->getMessage().PHP_EOL);
    exit(1);
}
