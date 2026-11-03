<?php

declare(strict_types=1);

namespace Jogo\Domain\Event;

use Jogo\Domain\Value\CombatValidation;

/** Publicado pela B depois de aplicar uma coleta; payload proposto, a confirmar com C/D. */
final readonly class ItemPicked implements DomainEvent
{
    public function __construct(
        public string $playerId,
        public string $pickupId,
        public string $itemId,
        public float $elapsedSeconds,
    ) {
        CombatValidation::requiredId($playerId, 'playerId');
        CombatValidation::requiredId($pickupId, 'pickupId');
        CombatValidation::requiredId($itemId, 'itemId');
        CombatValidation::nonNegative($elapsedSeconds, 'elapsedSeconds');
    }
}
