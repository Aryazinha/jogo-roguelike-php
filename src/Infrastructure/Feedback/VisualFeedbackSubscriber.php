<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Feedback;

use Closure;
use Jogo\Domain\Event\EnemyHit;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\EventBusInterface;
use Jogo\Domain\Event\ItemPicked;
use Jogo\Domain\Event\LevelUp;
use Jogo\Domain\Event\PlayerDamaged;
use Jogo\Domain\Event\PlayerDied;
use Jogo\Domain\Value\Vector2;
use Throwable;
use WeakMap;

final class VisualFeedbackSubscriber
{
    /** @var WeakMap<EventBusInterface, bool> */
    private WeakMap $registered;

    /** @var Closure(): Vector2 */
    private Closure $playerPosition;

    /** @param callable(): Vector2 $playerPosition */
    public function __construct(
        private readonly VisualFeedbackPool $pool,
        callable $playerPosition,
    ) {
        $this->registered = new WeakMap;
        $this->playerPosition = Closure::fromCallable($playerPosition);
    }

    public function register(EventBusInterface $events): void
    {
        if (isset($this->registered[$events])) {
            return;
        }

        $events->subscribe(
            PlayerDamaged::class,
            function (PlayerDamaged $event): void {
                $this->safeEmit(
                    'player_damaged',
                    $event->position,
                    $event->appliedDamage
                );
            }
        );

        $events->subscribe(
            EnemyHit::class,
            function (EnemyHit $event): void {
                $this->safeEmit(
                    'enemy_hit',
                    $event->position,
                    $event->appliedDamage
                );
            }
        );

        $events->subscribe(
            EnemyKilled::class,
            function (EnemyKilled $event): void {
                $this->safeEmit('enemy_killed', $event->position);
            }
        );

        $events->subscribe(
            LevelUp::class,
            function (LevelUp $event): void {
                $this->safeEmitAtPlayer('level_up');
            }
        );

        $events->subscribe(
            ChestOpenedNotice::class,
            function (ChestOpenedNotice $event): void {
                $this->safeEmit('chest_opened', $event->position);
            }
        );

        $events->subscribe(
            PlayerDied::class,
            function (PlayerDied $event): void {
                $this->safeEmit('player_died', $event->position);
            }
        );

        $events->subscribe(
            ItemPicked::class,
            function (ItemPicked $event): void {
                $this->safeEmitAtPlayer('item_picked');
            }
        );

        $this->registered[$events] = true;
    }

    private function safeEmitAtPlayer(string $kind): void
    {
        try {
            $position = ($this->playerPosition)();
            $this->pool->emit($kind, $position);
        } catch (Throwable) {
            // Um erro visual nao interrompe o dominio.
        }
    }

    private function safeEmit(
        string $kind,
        Vector2 $position,
        ?float $damage = null,
    ): void {
        try {
            $this->pool->emit($kind, $position, $damage);
        } catch (Throwable) {
            // Um erro visual nao interrompe o dominio.
        }
    }
}
