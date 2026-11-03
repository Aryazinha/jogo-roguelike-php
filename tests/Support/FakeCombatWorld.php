<?php

declare(strict_types=1);

namespace Jogo\Tests\Support;

use Jogo\Domain\Ai\WorldEntity;
use Jogo\Domain\Ai\WorldEntityProvider;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\AttackContextProvider;
use Jogo\Domain\Weapon\EnemyTarget;
use Jogo\Domain\Weapon\EnemyTargetResolver;
use Jogo\Domain\Weapon\Targeting\TargetingContext;

/** Dados artificiais de integração, sem IA, geração de conteúdo ou atributos do jogador. */
final class FakeCombatWorld implements AttackContextProvider, EnemyTargetResolver, WorldEntityProvider
{
    /** @var array<string, FakeDamageable> */
    public array $targets = [];

    /** @var array<string, string> */
    public array $kinds = [];

    /** @var array<string, true> */
    public array $unresolved = [];

    /** @var array<string, bool> */
    public array $bosses = [];

    /** @var array<string, string> Tipo de conteúdo por ID; sem entrada, vale `fake_type`. */
    public array $types = [];

    public Vector2 $origin;

    public Vector2 $facing;

    public function __construct()
    {
        $this->origin = Vector2::zero();
        $this->facing = new Vector2(1.0, 0.0);
    }

    public function add(string $id, float $x, float $y = 0.0, float $radius = 0.0, float $health = 100.0, string $kind = 'enemy'): FakeDamageable
    {
        $entity = new FakeDamageable($id, new Vector2($x, $y), $radius, new Health($health));
        $this->targets[$id] = $entity;
        $this->kinds[$id] = $kind;

        return $entity;
    }

    public function entities(): iterable
    {
        foreach ($this->targets as $id => $entity) {
            yield new WorldEntity($entity, $this->kinds[$id], ! $entity->health()->isDead());
        }
    }

    public function resolve(string $id): ?EnemyTarget
    {
        $entity = $this->targets[$id] ?? null;

        return $entity === null || isset($this->unresolved[$id]) || $this->kinds[$id] !== 'enemy'
            ? null : new EnemyTarget($entity, $this->types[$id] ?? 'fake_type', $this->bosses[$id] ?? false);
    }

    public function context(float $range): TargetingContext
    {
        return new TargetingContext($this->origin, $this->facing, $range);
    }
}
