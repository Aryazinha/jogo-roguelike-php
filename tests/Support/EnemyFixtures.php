<?php

declare(strict_types=1);

namespace Jogo\Tests\Support;

use Jogo\Domain\Enemy\EnemyArchetype;
use Jogo\Domain\Enemy\EnemyCategory;
use Jogo\Domain\Enemy\RangedSettings;
use Jogo\Domain\Enemy\SplitRule;

/** Arquétipos pequenos para testes, independentes de config/enemies.json. */
final class EnemyFixtures
{
    public static function common(string $id = 'zombie', int $minLevel = 1): EnemyArchetype
    {
        return new EnemyArchetype($id, ucfirst($id), EnemyCategory::COMMON, 13.0, 3.0, 3, 4, 55.0, 14.0,
            EnemyArchetype::BEHAVIOR_CHASE, minLevel: $minLevel);
    }

    public static function slime(): EnemyArchetype
    {
        return new EnemyArchetype('slime', 'Slime', EnemyCategory::SPECIAL, 20.0, 4.0, 4, 6, 60.0, 20.0,
            EnemyArchetype::BEHAVIOR_CHASE, minLevel: 8, split: new SplitRule('slime_pequeno', 2));
    }

    public static function smallSlime(): EnemyArchetype
    {
        return new EnemyArchetype('slime_pequeno', 'Slime pequeno', EnemyCategory::SPECIAL, 6.0, 2.0, 1, 2, 80.0,
            11.0, EnemyArchetype::BEHAVIOR_CHASE, minLevel: 8, onlyFromSplit: true);
    }

    public static function skeleton(): EnemyArchetype
    {
        return new EnemyArchetype('skeleton', 'Skeleton', EnemyCategory::SPECIAL, 16.0, 3.0, 5, 7, 50.0, 14.0,
            EnemyArchetype::BEHAVIOR_KEEP_DISTANCE, minLevel: 10, ranged: new RangedSettings(200.0, 40.0, 2.2));
    }

    public static function boss(): EnemyArchetype
    {
        return new EnemyArchetype('warden', 'Warden', EnemyCategory::BOSS, 900.0, 18.0, 150, 150, 45.0, 46.0,
            EnemyArchetype::BEHAVIOR_BOSS, spawnAtSeconds: 600.0);
    }

    /** @return list<EnemyArchetype> Conjunto mínimo que atende RF22-a, RF22-b e RF36. */
    public static function fullSet(): array
    {
        return [self::common('zombie', 6), self::common('spider'), self::common('husk', 12),
            self::slime(), self::smallSlime(), self::skeleton(), self::boss()];
    }
}
