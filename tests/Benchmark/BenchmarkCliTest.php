<?php

declare(strict_types=1);

namespace Jogo\Tests\Benchmark;

use PHPUnit\Framework\TestCase;

final class BenchmarkCliTest extends TestCase
{
    public function test_json_text_and_exit_codes_without_ffi(): void
    {
        $invalid = self::execute(['--projectiles=201']);
        self::assertSame(1, $invalid['exit']);
        self::assertSame('', $invalid['output']);
        self::assertStringContainsString('Erro', $invalid['error']);
        // Carga reduzida deve reprovar as metas de população, independentemente da máquina.
        $json = self::execute(['--warmup=0', '--steps=6', '--runs=1', '--enemies=3', '--projectiles=2', '--json']);
        self::assertSame(2, $json['exit']);
        self::assertSame('', $json['error']);
        $report = json_decode($json['output'], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        self::assertSame('a10-load-v1', $report['scenario']);
        self::assertIsArray($report['visualFps']);
        self::assertSame('not_evaluated', $report['visualFps']['status']);
        self::assertIsArray($report['acceptance']);
        self::assertFalse($report['acceptance']['300Enemies']);
        self::assertFalse($report['acceptance']['200Projectiles']);
        self::assertIsArray($report['environment']);
        self::assertFalse($report['environment']['opcacheActive']);
        self::assertSame('', $report['environment']['ffiEnable']);
        $text = self::execute(['--warmup=0', '--steps=3', '--runs=1', '--enemies=3', '--projectiles=2']);
        self::assertSame(2, $text['exit']);
        self::assertStringContainsString('p99:', $text['output']);
        self::assertStringContainsString('Meta visual de 50 FPS pendente', $text['output']);
    }

    /** @param list<string> $arguments
     * @return array{exit: int, output: string, error: string}
     */
    private static function execute(array $arguments): array
    {
        $process = proc_open([PHP_BINARY, '-d', 'opcache.enable_cli=0', '-d', 'ffi.enable=false', dirname(__DIR__, 2).'/tools/benchmark.php', ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertIsString($output);
        self::assertIsString($error);

        return ['exit' => proc_close($process), 'output' => $output, 'error' => $error];
    }
}
