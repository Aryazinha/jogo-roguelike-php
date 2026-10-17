<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

use Closure;
use InvalidArgumentException;
use LogicException;

final class StateStack
{
    /** @var list<GameState> */
    private array $states = [];

    /**
     * @var list<array{type: 'push'|'pop'|'replace', state: ?GameState}>
     */
    private array $pending = [];

    private bool $processing = false;

    private int $callbackDepth = 0;

    public function push(GameState $state): void
    {
        $this->pending[] = [
            'type' => 'push',
            'state' => $state,
        ];
    }

    public function pop(): void
    {
        $this->pending[] = [
            'type' => 'pop',
            'state' => null,
        ];
    }

    public function replace(GameState $state): void
    {
        $this->pending[] = [
            'type' => 'replace',
            'state' => $state,
        ];
    }

    public function top(): ?GameState
    {
        return $this->states[count($this->states) - 1] ?? null;
    }

    public function count(): int
    {
        return count($this->states);
    }

    public function isEmpty(): bool
    {
        return $this->states === [];
    }

    /**
     * Aplica transicoes apenas fora dos callbacks de estados.
     *
     * @phpstan-impure
     */
    public function flush(): void
    {
        if ($this->processing || $this->callbackDepth > 0) {
            return;
        }

        $this->processing = true;
        $processed = 0;

        try {
            while ($this->pending !== []) {
                $processed++;

                if ($processed > 128) {
                    throw new LogicException(
                        'Excesso de transicoes de estados. Verifique transicoes recursivas.'
                    );
                }

                $command = array_shift($this->pending);

                $type = $command['type'];
                $state = $command['state'];

                if ($type === 'pop') {
                    $this->applyPop();

                    continue;
                }

                if ($state === null) {
                    throw new LogicException('Estado obrigatorio ausente.');
                }

                if ($type === 'replace' && ! $this->isEmpty()) {
                    $this->applyPop();
                }

                $this->applyPush($state);
            }
        } finally {
            $this->processing = false;
        }
    }

    /** @phpstan-impure */
    public function update(float $deltaSeconds): void
    {
        if (! is_finite($deltaSeconds) || $deltaSeconds < 0.0) {
            throw new InvalidArgumentException(
                'Delta de atualizacao invalido.'
            );
        }

        $this->flush();

        $state = $this->top();

        if ($state !== null) {
            $this->invoke(
                function () use ($state, $deltaSeconds): void {
                    $state->update($deltaSeconds, $this);
                }
            );
        }

        $this->flush();
    }

    /** @phpstan-impure */
    public function render(float $alpha): void
    {
        if (! is_finite($alpha) || $alpha < 0.0 || $alpha >= 1.0) {
            throw new InvalidArgumentException(
                'Alpha deve ficar no intervalo [0, 1).'
            );
        }

        $this->flush();

        if ($this->isEmpty()) {
            return;
        }

        $firstVisible = count($this->states) - 1;

        while (
            $firstVisible > 0
            && $this->states[$firstVisible]->rendersBelow()
        ) {
            $firstVisible--;
        }

        // A lista visivel e fixada antes de iniciar os callbacks.
        $visible = array_slice($this->states, $firstVisible);

        foreach ($visible as $state) {
            $this->invoke(
                function () use ($state, $alpha): void {
                    $state->render($alpha, $this);
                }
            );
        }

        $this->flush();
    }

    public function shouldPauseGameplay(): bool
    {
        $hasPlay = false;

        foreach ($this->states as $state) {
            if ($state instanceof PlayState) {
                $hasPlay = true;

                continue;
            }

            if (
                $hasPlay
                && (
                    $state instanceof PauseState
                    || $state instanceof LevelUpState
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function applyPush(GameState $state): void
    {
        $this->states[] = $state;

        $this->invoke(
            function () use ($state): void {
                $state->enter($this);
            }
        );
    }

    private function applyPop(): void
    {
        $state = array_pop($this->states);

        if ($state === null) {
            throw new LogicException(
                'Nao e possivel remover um estado de uma pilha vazia.'
            );
        }

        $this->invoke(
            function () use ($state): void {
                $state->exit($this);
            }
        );
    }

    /** @param Closure(): void $callback */
    private function invoke(Closure $callback): void
    {
        $this->callbackDepth++;

        try {
            $callback();
        } finally {
            $this->callbackDepth--;
        }
    }
}
