<?php

declare(strict_types=1);

namespace Jogo\Application\Simulation;

use InvalidArgumentException;

final readonly class SimulationOptions
{
    public function __construct(public int $seed = 123, public int $steps = 600, public int $enemies = 24, public bool $json = false)
    {
        if ($steps < 1 || $enemies < 0) {
            throw new InvalidArgumentException('steps deve ser positivo e enemies não negativo.');
        }
    }

    /** @param list<string> $arguments */
    public static function fromArguments(array $arguments): self
    {
        $values = [];
        for ($index = 0; $index < count($arguments); $index++) {
            $parts = explode('=', $arguments[$index], 2);
            $name = $parts[0];
            if (! in_array($name, ['--seed', '--steps', '--seconds', '--enemies', '--json'], true) || isset($values[$name])) {
                throw new InvalidArgumentException('Argumento desconhecido ou repetido: '.$name);
            }
            if ($name === '--json') {
                if (count($parts) !== 1) {
                    throw new InvalidArgumentException('--json não recebe valor.');
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
        if (isset($values['--steps'], $values['--seconds'])) {
            throw new InvalidArgumentException('Use --steps ou --seconds, não ambos.');
        }
        $steps = isset($values['--steps']) ? self::integer($values['--steps'], '--steps') : 600;
        if (isset($values['--seconds'])) {
            $raw = $values['--seconds'];
            $seconds = (float) $raw;
            $count = ceil($seconds * 60.0);
            if (! is_numeric($raw) || ! is_finite($count) || $seconds <= 0.0 || $count >= (float) PHP_INT_MAX) {
                throw new InvalidArgumentException('--seconds deve ser positivo, finito e representável em passos.');
            }
            $steps = (int) $count;
        }

        return new self(isset($values['--seed']) ? self::integer($values['--seed'], '--seed') : 123, $steps,
            isset($values['--enemies']) ? self::integer($values['--enemies'], '--enemies') : 24, isset($values['--json']));
    }

    private static function integer(string $value, string $name): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false) {
            throw new InvalidArgumentException($name.' deve ser um inteiro representável.');
        }

        return $number;
    }
}
