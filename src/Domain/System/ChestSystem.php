<?php

declare(strict_types=1);

namespace Jogo\Domain\System;

use Jogo\Domain\Chest\Chest;
use Jogo\Domain\Chest\ChestReward;
use Jogo\Domain\Chest\ChestSettings;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\EventBusInterface;
use Jogo\Domain\Random\RandomIndex;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;

/**
 * Gera e abre baús (RF26).
 *
 * - O boss sempre deixa baú onde morreu. Os outros inimigos têm uma chance
 *   pequena, que o evento Tesouro multiplica (C9).
 * - A raridade é sorteada no nascimento, com as chances de chests.json.
 * - O baú abre quando o jogador encosta, uma única vez. A recompensa fica
 *   guardada até a Frente B buscar com takeOpened().
 *
 * ChestOpened está na lista de eventos do CONTRATOS.md, mas o payload ainda
 * não foi fechado entre B, C e D. Até lá, a recompensa sai por takeOpened().
 */
final class ChestSystem
{
    /** @var list<array{Vector2, bool}> Baús pedidos durante o combate: posição e se veio do boss. */
    private array $pending = [];

    /** @var list<ChestReward> */
    private array $opened = [];

    private float $chanceMultiplier = 1.0;

    private int $created = 0;

    public function __construct(
        private readonly GameWorld $world,
        private readonly ChestSettings $settings,
        private readonly RandomIndex $random,
        private readonly CollisionSystem $collision = new CollisionSystem,
    ) {}

    public function subscribe(EventBusInterface $events): void
    {
        $events->subscribe(EnemyKilled::class, $this->onEnemyKilled(...));
    }

    /** Morte acontece no meio do combate: o baú só entra no mundo no próximo update(). */
    public function onEnemyKilled(EnemyKilled $event): void
    {
        if ($event->isBoss) {
            for ($i = 0; $i < $this->settings->chestsOnBossDefeat; $i++) {
                $this->pending[] = [$event->position, true];
            }

            return;
        }
        if ($this->settings->rollKillDrop($this->random, $this->chanceMultiplier)) {
            $this->pending[] = [$event->position, false];
        }
    }

    /** Porta do evento Tesouro: 2,5 durante o evento, 1,0 fora dele. */
    public function setChanceMultiplier(float $multiplier): void
    {
        CombatValidation::nonNegative($multiplier, 'chanceMultiplier');
        $this->chanceMultiplier = $multiplier;
    }

    public function update(float $elapsedSeconds): void
    {
        CombatValidation::nonNegative($elapsedSeconds, 'elapsedSeconds');
        $this->placePending();

        $player = $this->world->player();
        if (! $player->isActive()) {
            return;
        }
        foreach ($this->world->chests() as $chest) {
            if (! $chest->isOpened() && $this->collision->intersects($chest, $player)) {
                $reward = $chest->open($elapsedSeconds);
                if ($reward !== null) {
                    $this->opened[] = $reward;
                }
            }
        }
    }

    /**
     * Recompensas abertas desde a última chamada; cada uma sai uma vez só.
     *
     * @return list<ChestReward>
     */
    public function takeOpened(): array
    {
        $opened = $this->opened;
        $this->opened = [];

        return $opened;
    }

    private function placePending(): void
    {
        $pending = $this->pending;
        $this->pending = [];
        $bounds = $this->world->layout()->bounds();
        foreach ($pending as $index => [$position, $fromBoss]) {
            // Vários baús do mesmo boss lado a lado, para não ficarem empilhados.
            $offset = $fromBoss ? new Vector2(($index % 3) * Chest::RADIUS * 2.5, intdiv($index, 3) * Chest::RADIUS * 2.5) : Vector2::zero();
            $this->created++;
            $this->world->addChest(new Chest("chest-{$this->created}", $bounds->clamp($position->add($offset)),
                $this->settings->drawRarity($this->random)));
        }
    }
}
