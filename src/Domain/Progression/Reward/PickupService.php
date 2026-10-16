<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression\Reward;

use Jogo\Domain\Character\Player;
use Jogo\Domain\Event\EventBusInterface;
use Jogo\Domain\Event\ItemPicked;
use Jogo\Domain\Progression\ExperienceSystem;
use Jogo\Domain\Value\CombatValidation;

/**
 * Aplica coletas criadas pela Frente C. Cada pickupId produz efeito uma única vez, e jogador
 * morto não coleta. Depois de aplicar, publica ItemPicked para o feedback da Frente D.
 */
final class PickupService
{
    /** @var array<string, true> */
    private array $collected = [];

    public function __construct(
        private readonly Player $player,
        private readonly TemporaryBonuses $bonuses,
        private readonly ExperienceSystem $experience,
        private readonly EventBusInterface $events,
    ) {}

    /** Devolve o HP restaurado, ou null se a coleta foi recusada. */
    public function collectHealing(string $pickupId, HealingItem $item, float $elapsedSeconds): ?float
    {
        if (! $this->accept($pickupId)) {
            return null;
        }
        $restored = $this->player->health()->heal($item->amount);
        $this->publish($pickupId, $item->id, $elapsedSeconds);

        return $restored;
    }

    public function collectBonus(string $pickupId, string $bonusId, float $elapsedSeconds): bool
    {
        if (! $this->accept($pickupId)) {
            return false;
        }
        $this->bonuses->activate($bonusId);
        $this->publish($pickupId, $bonusId, $elapsedSeconds);

        return true;
    }

    /** @return list<int>|null Níveis alcançados, ou null se a coleta foi recusada. */
    public function collectXp(string $pickupId, string $itemId, int $amount, float $elapsedSeconds): ?array
    {
        if (! $this->accept($pickupId)) {
            return null;
        }
        $levels = $this->experience->grant($amount, $elapsedSeconds);
        $this->publish($pickupId, $itemId, $elapsedSeconds);

        return $levels;
    }

    public function wasCollected(string $pickupId): bool
    {
        return isset($this->collected['id:'.$pickupId]);
    }

    private function accept(string $pickupId): bool
    {
        CombatValidation::requiredId($pickupId, 'pickupId');
        if ($this->wasCollected($pickupId) || $this->player->health()->isDead()) {
            return false;
        }
        $this->collected['id:'.$pickupId] = true;

        return true;
    }

    private function publish(string $pickupId, string $itemId, float $elapsedSeconds): void
    {
        $this->events->publish(new ItemPicked($this->player->id(), $pickupId, $itemId, $elapsedSeconds));
    }
}
