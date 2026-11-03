<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

interface GameState
{
    public function name(): string;

    public function enter(StateStack $states): void;

    public function update(float $deltaSeconds, StateStack $states): void;

    public function render(float $alpha, StateStack $states): void;

    public function exit(StateStack $states): void;

    public function rendersBelow(): bool;
}
