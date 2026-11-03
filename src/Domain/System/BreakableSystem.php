<?php

declare(strict_types=1);

namespace Jogo\Domain\System;

use Jogo\Domain\Chest\HealingItem;
use Jogo\Domain\Random\RandomIndex;
use Jogo\Domain\Scenery\Breakable;
use Jogo\Domain\Scenery\DropChance;
use Jogo\Domain\Scenery\PickedItem;
use Jogo\Domain\Scenery\Pickup;
use Jogo\Domain\Scenery\ScenerySettings;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;

/**
 * Dano nos quebráveis, drops e coleta de itens (RF38, RF27).
 *
 * As armas da A só miram em kind = enemy (CONTRATOS.md §7.1), por isso o dano
 * nos quebráveis entra por aqui: damage() para um elemento e damageArea()
 * para um golpe em área. A ligação com o ataque das armas é pendência A/C.
 *
 * Um quebrável quebra uma única vez e deixa no máximo um item. O jogador pega
 * o item ao encostar; o registro fica em takePicked() até a Frente B aplicar
 * (ItemPicked tem payload pendente no CONTRATOS.md).
 */
final class BreakableSystem
{
    /** @var list<PickedItem> */
    private array $picked = [];

    private int $dropped = 0;

    public function __construct(
        private readonly GameWorld $world,
        private readonly ScenerySettings $settings,
        private readonly HealingItem $healing,
        private readonly RandomIndex $random,
        private readonly CollisionSystem $collision = new CollisionSystem,
    ) {}

    /** Devolve o dano aplicado. Elemento já quebrado ou inexistente não recebe nada. */
    public function damage(string $breakableId, float $amount): float
    {
        CombatValidation::nonNegative($amount, 'amount');
        $breakable = $this->world->breakable($breakableId);
        if ($breakable === null || ! $breakable->isActive() || $breakable->health()->isDead()) {
            return 0.0;
        }

        $applied = $breakable->health()->damage($amount);
        if ($breakable->health()->current() <= 0.0) {
            $breakable->deactivate();
            $this->drop($breakable);
        }

        return $applied;
    }

    /** Golpe em área: atinge todo quebrável que o círculo tocar. Devolve quantos foram atingidos. */
    public function damageArea(Vector2 $center, float $radius, float $amount): int
    {
        CombatValidation::nonNegative($radius, 'radius');
        $hits = 0;
        foreach ($this->world->breakables() as $breakable) {
            if ($breakable->isActive() && CollisionSystem::circlesIntersect($center, $radius, $breakable->position(),
                $breakable->collisionRadius()) && $this->damage($breakable->id(), $amount) > 0.0) {
                $hits++;
            }
        }

        return $hits;
    }

    /** Coleta: o jogador pega os itens que está tocando. */
    public function update(float $elapsedSeconds): void
    {
        CombatValidation::nonNegative($elapsedSeconds, 'elapsedSeconds');
        $player = $this->world->player();
        if (! $player->isActive()) {
            return;
        }
        foreach ($this->world->pickups() as $pickup) {
            if ($pickup->isActive() && $this->collision->intersects($pickup, $player)) {
                $pickup->deactivate();
                $this->picked[] = new PickedItem($pickup->id(), $pickup->itemId(), $pickup->healing(), $pickup->bonus(), $elapsedSeconds);
            }
        }
    }

    /**
     * Itens pegos desde a última chamada; cada um sai uma vez só.
     *
     * @return list<PickedItem>
     */
    public function takePicked(): array
    {
        $picked = $this->picked;
        $this->picked = [];

        return $picked;
    }

    private function drop(Breakable $breakable): void
    {
        $item = $breakable->type()->drawDrop($this->random);
        if ($item === DropChance::NOTHING) {
            return;
        }
        $bonuses = $this->settings->bonuses();
        $this->dropped++;
        $this->world->addPickup(new Pickup(
            "pickup-{$this->dropped}",
            $breakable->position(),
            $item,
            $item === DropChance::STEAK ? $this->healing : null,
            $item === DropChance::TEMPORARY_BONUS ? $bonuses[$this->random->nextIndex(count($bonuses))] : null,
        ));
    }
}
