<?php

declare(strict_types=1);

namespace Jogo\Domain\Enemy;

use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\EventBusInterface;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;

/**
 * RF36: quando um inimigo com aoMorrer morre, deixa os filhos no lugar.
 *
 * Escuta EnemyKilled, que o DamageSystem publica no meio do combate. Por
 * isso os filhos não entram direto no mundo: vão para a fila do GameWorld
 * e só aparecem no flush(), o ponto seguro do passo. Os filhos não se
 * dividem de novo, regra que o EnemyCatalog já garante.
 */
final class SplitOnDeath
{
    /** Distância dos filhos até o ponto da morte, em relação ao raio do pai. */
    private const SPREAD = 0.5;

    public function __construct(
        private readonly GameWorld $world,
        private readonly EnemyFactory $factory,
    ) {}

    public function subscribe(EventBusInterface $events): void
    {
        $events->subscribe(EnemyKilled::class, $this->onEnemyKilled(...));
    }

    public function onEnemyKilled(EnemyKilled $event): void
    {
        // O morto ainda está registrado: o flush que o retira só vem no fim do passo.
        $parent = $this->world->enemy($event->enemyId);
        $rule = $parent?->archetype()->split;
        if ($parent === null || $rule === null) {
            return;
        }

        $distance = $parent->collisionRadius() * self::SPREAD;
        $bounds = $this->world->layout()->bounds();
        // O pai já morreu e liberou a vaga dele; com o mundo cheio, nascem só os que cabem (RNF06).
        $count = min($rule->count, $this->world->freeEnemySlots());
        for ($i = 0; $i < $count; $i++) {
            // Filhos espalhados em ângulos iguais, para não nascerem empilhados.
            $angle = 2.0 * M_PI * $i / $count;
            $offset = new Vector2(cos($angle) * $distance, sin($angle) * $distance);
            $position = $bounds->clamp($event->position->add($offset));
            $this->world->queueEnemy($this->factory->create($rule->childTypeId, $position));
        }
    }
}
