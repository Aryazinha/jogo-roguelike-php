<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression\Reward;

use InvalidArgumentException;
use Jogo\Domain\Character\Player;
use Jogo\Domain\Event\EventBusInterface;
use Jogo\Domain\Event\ItemPicked;
use Jogo\Domain\Progression\ExperienceSystem;
use Jogo\Domain\Value\CombatValidation;

/**
 * Aplica coletas criadas pela Frente C. Cada pickupId produz efeito uma única vez, e jogador
 * morto não coleta. O pickup só é marcado como coletado depois que o efeito foi aplicado: se a
 * aplicação falhar, ele continua disponível. Depois de marcar, publica ItemPicked para a D.
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
        if (! $this->canCollect($pickupId, $elapsedSeconds)) {
            return null;
        }
        $restored = $this->player->health()->heal($item->amount);
        $this->complete($pickupId, $item->id, $elapsedSeconds);

        return $restored;
    }

    public function collectBonus(string $pickupId, string $bonusId, float $elapsedSeconds): bool
    {
        if (! $this->canCollect($pickupId, $elapsedSeconds)) {
            return false;
        }
        $this->bonuses->activate($bonusId);
        $this->complete($pickupId, $bonusId, $elapsedSeconds);

        return true;
    }

    /** @return list<int>|null Níveis alcançados, ou null se a coleta foi recusada. */
    public function collectXp(string $pickupId, string $itemId, int $amount, float $elapsedSeconds): ?array
    {
        if (! $this->canCollect($pickupId, $elapsedSeconds)) {
            return null;
        }
        CombatValidation::requiredId($itemId, 'itemId');
        if ($amount < 0) {
            throw new InvalidArgumentException('O XP coletado não pode ser negativo.');
        }
        $levels = $this->experience->grant($amount, $elapsedSeconds);
        $this->complete($pickupId, $itemId, $elapsedSeconds);

        return $levels;
    }

    public function wasCollected(string $pickupId): bool
    {
        return isset($this->collected['id:'.$pickupId]);
    }

    /** Valida tudo o que não depende do efeito; não marca nada. */
    private function canCollect(string $pickupId, float $elapsedSeconds): bool
    {
        CombatValidation::requiredId($pickupId, 'pickupId');
        CombatValidation::nonNegative($elapsedSeconds, 'elapsedSeconds');

        return ! $this->wasCollected($pickupId) && ! $this->player->health()->isDead();
    }

    /** Chamado somente depois do efeito aplicado com sucesso. */
    private function complete(string $pickupId, string $itemId, float $elapsedSeconds): void
    {
        $this->collected['id:'.$pickupId] = true;
        $this->events->publish(new ItemPicked($this->player->id(), $pickupId, $itemId, $elapsedSeconds));
    }
}
