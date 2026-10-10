<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Input;

use InvalidArgumentException;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\Vector2;

/** Sequência finita de entradas para testes; cada poll() consome um estado. */
final class ScriptedInput implements InputInterface
{
    private int $index = 0;

    private readonly InputState $neutral;

    /** @var list<InputState> */
    private readonly array $states;

    /** @param array<array-key, mixed> $states */
    public function __construct(array $states)
    {
        if (! array_is_list($states)) {
            throw new InvalidArgumentException('A entrada programada deve ser uma lista sequencial.');
        }

        $validatedStates = [];

        foreach ($states as $state) {
            if (! $state instanceof InputState) {
                throw new InvalidArgumentException('Cada entrada programada deve ser um InputState.');
            }

            $validatedStates[] = $state;
        }

        $this->states = $validatedStates;
        $this->neutral = new InputState(Vector2::zero());
    }

    /**
     * Após o fim, devolve repouso, sem repetir pedidos de pausa ou apagar o olhar.
     *
     * @phpstan-impure
     */
    public function poll(): InputState
    {
        if ($this->index >= count($this->states)) {
            return $this->neutral;
        }

        return $this->states[$this->index++];
    }
}
