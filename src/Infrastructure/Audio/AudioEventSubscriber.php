<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Audio;

use Jogo\Domain\Event\EnemyHit;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\EventBusInterface;
use Jogo\Domain\Event\ItemPicked;
use Jogo\Domain\Event\LevelUp;
use Jogo\Domain\Event\PlayerDamaged;
use Jogo\Domain\Event\PlayerDied;
use Throwable;
use WeakMap;

final class AudioEventSubscriber
{
    /** @var WeakMap<EventBusInterface, bool> */
    private WeakMap $registered;

    public function __construct(
        private readonly AudioInterface $audio,
    ) {
        $this->registered = new WeakMap;
    }

    public function register(EventBusInterface $events): void
    {
        if (isset($this->registered[$events])) {
            return;
        }

        $events->subscribe(
            PlayerDamaged::class,
            function (PlayerDamaged $event): void {
                $this->safePlay('player_damaged');
            }
        );

        $events->subscribe(
            EnemyHit::class,
            function (EnemyHit $event): void {
                $this->safePlay('enemy_hit');
            }
        );

        $events->subscribe(
            EnemyKilled::class,
            function (EnemyKilled $event): void {
                $this->safePlay('enemy_killed');
            }
        );

        $events->subscribe(
            LevelUp::class,
            function (LevelUp $event): void {
                $this->safePlay('level_up');
            }
        );

        $events->subscribe(
            PlayerDied::class,
            function (PlayerDied $event): void {
                $this->safePlay('player_died');
            }
        );

        $events->subscribe(
            ItemPicked::class,
            function (ItemPicked $event): void {
                $this->safePlay('item_picked');
            }
        );

        $this->registered[$events] = true;
    }

    private function safePlay(string $effect): void
    {
        try {
            $this->audio->playEffect($effect);
        } catch (Throwable) {
            // Falha de apresentacao nao deve interromper o jogo.
        }
    }
}
