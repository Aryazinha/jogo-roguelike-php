<?php

declare(strict_types=1);

namespace Jogo\Tests\Simulation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SimulationCliTest extends TestCase
{
    public function test_text_and_json_cli_run_in_a_process_with_ffi_disabled(): void
    {
        $text = self::execute(['--steps=3', '--enemies=0']);
        self::assertSame(0, $text['exit']);
        self::assertSame('', $text['error']);
        self::assertStringContainsString('Passos: 3', $text['output']);
        self::assertStringContainsString('Após limpeza: 0 ativos, 200 disponíveis', $text['output']);
        $first = self::execute(['--seed=17', '--seconds=2', '--enemies=8', '--json']);
        $second = self::execute(['--seed', '17', '--steps', '120', '--enemies', '8', '--json']);
        self::assertSame(0, $first['exit']);
        self::assertSame(0, $second['exit']);
        $left = json_decode($first['output'], true, 512, JSON_THROW_ON_ERROR);
        $right = json_decode($second['output'], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($left);
        self::assertIsArray($right);
        self::assertSame($left['checksum'], $right['checksum']);
        self::assertSame($left['logical'], $right['logical']);
    }

    /** @return iterable<string, array{list<string>}> */
    public static function invalidCommands(): iterable
    {
        yield 'unknown' => [['--unknown']];
        yield 'negative' => [['--steps=-1']];
        yield 'missing' => [['--seed']];
        yield 'ambiguous duration' => [['--steps=1', '--seconds=1']];
    }

    /** @param list<string> $arguments */
    #[DataProvider('invalidCommands')]
    public function test_invalid_arguments_exit_nonzero_with_a_clear_error(array $arguments): void
    {
        $result = self::execute($arguments);
        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['output']);
        self::assertStringContainsString('Erro na simulação:', $result['error']);
    }

    /**
     * @param  list<string>  $arguments
     * @return array{exit: int, output: string, error: string}
     */
    private static function execute(array $arguments): array
    {
        $process = proc_open([PHP_BINARY, '-d', 'ffi.enable=false', dirname(__DIR__, 2).'/tools/simulate.php', ...$arguments],
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
