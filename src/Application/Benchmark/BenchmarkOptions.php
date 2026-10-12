<?php

declare(strict_types=1);

namespace Jogo\Application\Benchmark;

use InvalidArgumentException;

final readonly class BenchmarkOptions
{
    public function __construct(public int $seed = 20261012, public int $warmup = 600,
        public int $steps = 1800, public int $runs = 5, public int $enemies = 300,
        public int $projectiles = 200, public bool $json = false, public bool $freshQueries = false)
    {
        if ($warmup < 0 || $steps < 1 || $runs < 1 || $enemies < 1 || $projectiles < 1 || $projectiles > 200
            || $warmup > PHP_INT_MAX - $steps) {
            throw new InvalidArgumentException('warmup >= 0; steps, runs e enemies > 0; projectiles entre 1 e 200; soma de passos representável.');
        }
    }

    /** @param list<string> $arguments */
    public static function fromArguments(array $arguments): self
    {
        $values = [];
        for ($index = 0; $index < count($arguments); $index++) {
            $parts = explode('=', $arguments[$index], 2);
            $name = $parts[0];
            if (! in_array($name, ['--seed', '--warmup', '--steps', '--runs', '--enemies', '--projectiles', '--json', '--fresh-queries'], true)
                || isset($values[$name])) {
                throw new InvalidArgumentException('Argumento desconhecido ou repetido: '.$name);
            }
            if ($name === '--json' || $name === '--fresh-queries') {
                if (count($parts) !== 1) {
                    throw new InvalidArgumentException($name.' não recebe valor.');
                }
                $values[$name] = 'true';

                continue;
            }
            $value = $parts[1] ?? ($arguments[++$index] ?? null);
            if ($value === null || str_starts_with($value, '--')) {
                throw new InvalidArgumentException('Valor ausente para '.$name);
            }
            $values[$name] = $value;
        }
        $integer = static function (string $name, int $default) use ($values): int {
            if (! isset($values[$name])) {
                return $default;
            }
            $number = filter_var($values[$name], FILTER_VALIDATE_INT);
            if ($number === false) {
                throw new InvalidArgumentException($name.' deve ser inteiro representável.');
            }

            return $number;
        };

        return new self($integer('--seed', 20261012), $integer('--warmup', 600), $integer('--steps', 1800),
            $integer('--runs', 5), $integer('--enemies', 300), $integer('--projectiles', 200),
            isset($values['--json']), isset($values['--fresh-queries']));
    }
}
