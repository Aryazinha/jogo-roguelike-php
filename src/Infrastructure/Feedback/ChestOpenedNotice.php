<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Feedback;

use InvalidArgumentException;
use Jogo\Domain\Event\DomainEvent;
use Jogo\Domain\Value\Vector2;

/**
 * Aviso provisório da apresentação.
 * Substituir pelo evento tipado acordado com a Frente C.
 */
final readonly class ChestOpenedNotice implements DomainEvent
{
    public function __construct(
        public string $chestId,
        public Vector2 $position,
        public float $elapsedSeconds,
    ) {
        if (
            trim($chestId) === ''
            || ! is_finite($elapsedSeconds)
            || $elapsedSeconds < 0.0
        ) {
            throw new InvalidArgumentException(
                'Aviso de abertura de bau invalido.'
            );
        }
    }
}
