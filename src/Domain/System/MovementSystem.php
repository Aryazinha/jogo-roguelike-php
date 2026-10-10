<?php

declare(strict_types=1);

namespace Jogo\Domain\System;

use InvalidArgumentException;
use Jogo\Domain\Entity\Movable;
use Jogo\Domain\Value\Facing;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\MapBounds;

/** Aplica intenções de movimento sem conhecer personagem, janela ou relógio. */
final readonly class MovementSystem
{
    public function __construct(private MapBounds $bounds) {}

    /**
     * Velocidade em px/s e dt em segundos; o chamador informa a atividade da entidade.
     * A velocidade final pode mudar a cada passo sem recriar o sistema.
     */
    public function update(
        Movable $target,
        Facing $facing,
        InputState $input,
        float $speed,
        float $deltaSeconds,
        bool $active,
    ): void {
        if (! is_finite($speed) || $speed < 0.0) {
            throw new InvalidArgumentException('A velocidade deve ser finita e maior ou igual a zero.');
        }

        if (! is_finite($deltaSeconds) || $deltaSeconds < 0.0) {
            throw new InvalidArgumentException('O dt deve ser finito e maior ou igual a zero.');
        }

        if (! $active) {
            return;
        }

        $movement = $input->movement;

        // Limita a magnitude a 1 sem transformar entrada analógica parcial em velocidade máxima.
        if ($movement->length() > 1.0) {
            $movement = $movement->normalized();
        }

        if ($movement->length() === 0.0 || $speed === 0.0 || $deltaSeconds === 0.0) {
            $facing->update($input->facing);

            return;
        }

        // Vector2 rejeita overflow antes de alterar posição ou olhar.
        $nextPosition = $target->position()->add($movement->scale($speed * $deltaSeconds));
        $target->moveTo($this->bounds->clamp($nextPosition));
        $facing->update($input->facing);
    }
}
