<?php

declare(strict_types=1);

namespace Jogo\Tests\Support;

use Jogo\Core\Config\WeaponConfigLoader;
use Jogo\Core\EventBus;
use Jogo\Domain\Ai\SpatialWorldView;
use Jogo\Domain\Event\EnemyHit;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Random\RandomIndex;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\System\DamageSystem;
use Jogo\Domain\System\WeaponAttackSystem;
use Jogo\Domain\Weapon\CombatInventory;
use Jogo\Domain\Weapon\ProjectilePool;
use Jogo\Domain\Weapon\WeaponCatalog;

final class CombatHarness
{
    public readonly FakeCombatWorld $world;

    public readonly SpatialWorldView $view;

    public readonly CombatInventory $inventory;

    public readonly WeaponCatalog $catalog;

    public readonly EventBus $events;

    public readonly WeaponAttackSystem $combat;

    /** @var list<EnemyHit> */
    public array $hits = [];

    /** @var list<EnemyKilled> */
    public array $deaths = [];

    public function __construct(?RandomIndex $random = null, ?ProjectilePool $pool = null)
    {
        $this->world = new FakeCombatWorld;
        $this->view = new SpatialWorldView($this->world, fn () => $this->world->origin);
        $this->inventory = new CombatInventory;
        $this->catalog = (new WeaponConfigLoader)->load(dirname(__DIR__, 2).'/config/weapons.json');
        $this->events = new EventBus;
        $this->events->subscribe(EnemyHit::class, function (EnemyHit $hit): void {
            $this->hits[] = $hit;
        });
        $this->events->subscribe(EnemyKilled::class, function (EnemyKilled $death): void {
            $this->deaths[] = $death;
        });
        $this->combat = new WeaponAttackSystem('player', $this->world, $this->view, $this->view,
            $this->world, new DamageSystem($this->events), $random ?? new SeededRandomIndex(123), $pool ?? new ProjectilePool);
    }

    public function equip(string $id): void
    {
        $this->inventory->add($this->catalog->get($id), $this->combat);
    }

    public function step(float $dt, float $elapsed): int
    {
        return $this->combat->update($this->inventory, $dt, $elapsed);
    }
}
