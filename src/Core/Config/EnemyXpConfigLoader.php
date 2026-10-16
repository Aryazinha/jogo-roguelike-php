<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;
use Jogo\Domain\Progression\EnemyXpTable;
use Jogo\Domain\Progression\XpRange;
use stdClass;

/** Lê apenas o campo xp de enemies.json; os demais campos são validados pela Frente C. */
final class EnemyXpConfigLoader
{
    private const GROUPS = ['comuns', 'especiais', 'boss'];

    public function load(string $file): EnemyXpTable
    {
        return $this->map(ConfigJsonReader::load($file), $file);
    }

    public function decode(string $json, string $source = 'enemies.json'): EnemyXpTable
    {
        return $this->map(ConfigJsonReader::decode($json, $source), $source);
    }

    private function map(mixed $decoded, string $source): EnemyXpTable
    {
        $root = ConfigSchema::object($decoded, $source);
        $ranges = [];
        foreach (self::GROUPS as $group) {
            if (! property_exists($root, $group)) {
                continue;
            }
            foreach (get_object_vars(ConfigSchema::object($root->{$group}, "{$source}: {$group}")) as $id => $raw) {
                $id = (string) $id;
                $path = "{$source}: {$id}";
                if (isset($ranges[$id])) {
                    throw new InvalidArgumentException("{$path} aparece em mais de um grupo.");
                }
                $enemy = ConfigSchema::object($raw, $path);
                $xp = ConfigSchema::object($enemy->xp ?? null, "{$path}.xp");
                $min = self::integer($xp, 'min', "{$path}.xp");
                $max = self::integer($xp, 'max', "{$path}.xp");
                if ($max < $min) {
                    throw new InvalidArgumentException("{$path}.xp.max deve ser maior ou igual a min.");
                }
                $ranges[$id] = new XpRange($min, $max);
            }
        }
        if ($ranges === []) {
            throw new InvalidArgumentException("{$source}: nenhum inimigo com XP encontrado.");
        }

        return new EnemyXpTable($ranges);
    }

    private static function integer(stdClass $object, string $field, string $path): int
    {
        $value = $object->{$field} ?? null;
        if (! is_int($value) || $value < 0) {
            throw new InvalidArgumentException("{$path}.{$field} deve ser um inteiro >= 0.");
        }

        return $value;
    }
}
