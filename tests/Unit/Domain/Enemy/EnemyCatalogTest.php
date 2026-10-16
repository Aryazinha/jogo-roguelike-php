<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Enemy;

use InvalidArgumentException;
use Jogo\Domain\Enemy\EnemyArchetype;
use Jogo\Domain\Enemy\EnemyCatalog;
use Jogo\Domain\Enemy\EnemyCategory;
use Jogo\Domain\Enemy\SplitRule;
use Jogo\Tests\Support\EnemyFixtures;
use PHPUnit\Framework\TestCase;

final class EnemyCatalogTest extends TestCase
{
    public function test_it_indexes_by_id_and_groups_by_category(): void
    {
        $catalog = new EnemyCatalog(EnemyFixtures::fullSet());

        self::assertCount(7, $catalog->all());
        self::assertTrue($catalog->has('zombie'));
        self::assertFalse($catalog->has('creeper'));
        self::assertSame('slime', $catalog->get('slime')->id);
        self::assertCount(3, $catalog->byCategory(EnemyCategory::COMMON));
        self::assertCount(3, $catalog->byCategory(EnemyCategory::SPECIAL));
        self::assertSame('warden', $catalog->boss()->id);
    }

    public function test_spawnable_types_grow_with_the_level(): void
    {
        $catalog = new EnemyCatalog(EnemyFixtures::fullSet());

        self::assertSame(['spider'], self::ids($catalog->spawnableAtLevel(1)));
        self::assertSame(['zombie', 'spider'], self::ids($catalog->spawnableAtLevel(6)));
        self::assertSame(['zombie', 'spider', 'slime'], self::ids($catalog->spawnableAtLevel(8)));
        self::assertSame(['zombie', 'spider', 'husk', 'slime', 'skeleton'], self::ids($catalog->spawnableAtLevel(12)));
    }

    public function test_unknown_id_is_reported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Inimigo desconhecido: creeper.');

        (new EnemyCatalog(EnemyFixtures::fullSet()))->get('creeper');
    }

    public function test_duplicate_id_is_rejected(): void
    {
        $this->expectExceptionMessage('ID de inimigo duplicado: zombie.');

        new EnemyCatalog([...EnemyFixtures::fullSet(), EnemyFixtures::common('zombie')]);
    }

    public function test_split_must_point_to_an_existing_child(): void
    {
        $set = self::without('slime_pequeno');

        $this->expectExceptionMessage('slime se divide em um inimigo inexistente: slime_pequeno.');

        new EnemyCatalog($set);
    }

    public function test_child_must_be_marked_as_split_only(): void
    {
        $set = self::without('slime_pequeno');
        $set[] = new EnemyArchetype('slime_pequeno', 'Slime pequeno', EnemyCategory::SPECIAL, 6.0, 2.0, 1, 2, 80.0,
            11.0, EnemyArchetype::BEHAVIOR_CHASE, minLevel: 8);

        $this->expectExceptionMessage('slime_pequeno deve ser marcado como gerado apenas por divisão.');

        new EnemyCatalog($set);
    }

    public function test_child_cannot_split_again(): void
    {
        $set = self::without('slime_pequeno');
        $set[] = new EnemyArchetype('slime_pequeno', 'Slime pequeno', EnemyCategory::SPECIAL, 6.0, 2.0, 1, 2, 80.0,
            11.0, EnemyArchetype::BEHAVIOR_CHASE, minLevel: 8, split: new SplitRule('slime', 2), onlyFromSplit: true);

        $this->expectExceptionMessage('slime_pequeno não pode se dividir');

        new EnemyCatalog($set);
    }

    public function test_orphan_split_only_type_is_rejected(): void
    {
        $set = self::without('slime');
        $set[] = EnemyFixtures::common('creeper');

        $this->expectExceptionMessage('nenhum inimigo o gera');

        new EnemyCatalog($set);
    }

    public function test_requirement_minimums_are_enforced(): void
    {
        try {
            new EnemyCatalog(self::without('husk'));
            self::fail('Dois comuns não deveriam ser aceitos.');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('RF22 exige pelo menos 3 inimigos comuns', $error->getMessage());
        }

        try {
            new EnemyCatalog(self::without('skeleton'));
            self::fail('Um especial só não deveria ser aceito.');
        } catch (InvalidArgumentException $error) {
            // O Slime pequeno não conta: ele só existe como consequência do Slime.
            self::assertStringContainsString('RF36 exige pelo menos 2 inimigos especiais; há 1', $error->getMessage());
        }

        $this->expectExceptionMessage('exatamente um boss');
        new EnemyCatalog(self::without('warden'));
    }

    /** @return list<EnemyArchetype> */
    private static function without(string $id): array
    {
        return array_values(array_filter(EnemyFixtures::fullSet(), static fn (EnemyArchetype $a) => $a->id !== $id));
    }

    /**
     * @param  list<EnemyArchetype>  $archetypes
     * @return list<string>
     */
    private static function ids(array $archetypes): array
    {
        return array_map(static fn (EnemyArchetype $a) => $a->id, $archetypes);
    }
}
