<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;
use Jogo\Domain\Enemy\EnemyArchetype;
use Jogo\Domain\Enemy\EnemyCatalog;
use Jogo\Domain\Enemy\EnemyCategory;
use Jogo\Domain\Enemy\RangedSettings;
use Jogo\Domain\Enemy\SplitRule;
use stdClass;

/**
 * Converte o JSON já decodificado de enemies.json em EnemyCatalog.
 *
 * Todo erro sai com arquivo, ID e campo do JSON, para quem editar o
 * balanceamento achar o problema sem abrir o código.
 */
final class EnemyDefinitionMapper
{
    private const BASE_FIELDS = ['nome', 'vida', 'dano', 'xp', 'velocidade', 'raioColisao', 'comportamento'];

    private const RANGED_FIELDS = ['distanciaIdeal', 'margemHisterese', 'cooldownTiroSegundos'];

    /** Nomes usados no domínio trocados pelos do JSON nas mensagens de erro. */
    private const JSON_NAMES = [
        'maxHealth' => 'vida', 'damage' => 'dano', 'speed' => 'velocidade', 'collisionRadius' => 'raioColisao',
        'behaviorId' => 'comportamento', 'minLevel' => 'nivelMinimoAparicao', 'spawnAtSeconds' => 'surgeAosSegundos',
        'idealDistance' => 'distanciaIdeal', 'hysteresis' => 'margemHisterese',
        'shotCooldownSeconds' => 'cooldownTiroSegundos', 'childTypeId' => 'aoMorrer.gera', 'count' => 'aoMorrer.quantidade',
    ];

    public function map(mixed $decoded, string $source = 'enemies.json'): EnemyCatalog
    {
        $root = ConfigSchema::object($decoded, $source);
        $groups = array_map(static fn (EnemyCategory $c) => $c->value, EnemyCategory::cases());
        // separacao e contato são regras gerais, lidas pelo SteeringConfigLoader e pelo ContactConfigLoader.
        ConfigSchema::fields($root, [...$groups, 'separacao', 'contato'], $source);
        $this->assertMeta($root, $source);

        $archetypes = [];
        foreach (EnemyCategory::cases() as $category) {
            $group = ConfigSchema::object($root->{$category->value} ?? null, "{$source}: {$category->value}");
            foreach (get_object_vars($group) as $id => $raw) {
                $id = (string) $id;
                $path = "{$source}: {$id}";
                $archetypes[] = $this->mapEnemy($id, $category, ConfigSchema::object($raw, $path), $path);
            }
        }

        try {
            return new EnemyCatalog($archetypes);
        } catch (InvalidArgumentException $error) {
            throw new InvalidArgumentException("{$source}: {$error->getMessage()}", 0, $error);
        }
    }

    private function mapEnemy(string $id, EnemyCategory $category, stdClass $entry, string $path): EnemyArchetype
    {
        $allowed = self::BASE_FIELDS;
        if ($category === EnemyCategory::BOSS) {
            // As fases do Warden são lidas em detalhe pela entrega do boss (C6).
            $allowed = [...$allowed, 'surgeAosSegundos', 'fases'];
        } else {
            $allowed = [...$allowed, 'nivelMinimoAparicao', ...self::RANGED_FIELDS];
        }
        if ($category === EnemyCategory::SPECIAL) {
            $allowed = [...$allowed, 'aoMorrer', 'geradoApenasPorDivisao'];
        }
        ConfigSchema::fields($entry, $allowed, $path);
        // A procedência é obrigatória: valores do enunciado não podem ser trocados no balanceamento.
        ConfigSchema::string($entry, '_fonte', $path);

        $xp = ConfigSchema::object($entry->xp ?? null, "{$path}.xp");
        ConfigSchema::fields($xp, ['min', 'max'], "{$path}.xp");

        try {
            return new EnemyArchetype(
                id: $id,
                name: ConfigSchema::string($entry, 'nome', $path),
                category: $category,
                maxHealth: ConfigSchema::number($entry, 'vida', $path),
                damage: ConfigSchema::number($entry, 'dano', $path),
                xpMin: $this->integer($xp, 'min', "{$path}.xp"),
                xpMax: $this->integer($xp, 'max', "{$path}.xp"),
                speed: ConfigSchema::number($entry, 'velocidade', $path),
                collisionRadius: ConfigSchema::number($entry, 'raioColisao', $path),
                behaviorId: ConfigSchema::string($entry, 'comportamento', $path),
                minLevel: property_exists($entry, 'nivelMinimoAparicao')
                    ? ConfigSchema::level($entry, 'nivelMinimoAparicao', $path) : null,
                spawnAtSeconds: $this->bossSpawn($entry, $category, $path),
                split: $this->split($entry, $path),
                onlyFromSplit: $this->flag($entry, 'geradoApenasPorDivisao', $path),
                ranged: $this->ranged($entry, $path),
            );
        } catch (InvalidArgumentException $error) {
            // Erros dos helpers já trazem o caminho; os do domínio recebem os nomes do JSON.
            if (str_starts_with($error->getMessage(), $path)) {
                throw $error;
            }
            throw new InvalidArgumentException("{$path}: ".strtr($error->getMessage(), self::JSON_NAMES), 0, $error);
        }
    }

    private function bossSpawn(stdClass $entry, EnemyCategory $category, string $path): ?float
    {
        if ($category !== EnemyCategory::BOSS) {
            return null;
        }
        ConfigSchema::object($entry->fases ?? null, "{$path}.fases");

        return ConfigSchema::number($entry, 'surgeAosSegundos', $path);
    }

    private function split(stdClass $entry, string $path): ?SplitRule
    {
        if (! property_exists($entry, 'aoMorrer')) {
            return null;
        }
        $rule = ConfigSchema::object($entry->aoMorrer, "{$path}.aoMorrer");
        ConfigSchema::fields($rule, ['gera', 'quantidade'], "{$path}.aoMorrer");

        return new SplitRule(ConfigSchema::string($rule, 'gera', "{$path}.aoMorrer"),
            $this->integer($rule, 'quantidade', "{$path}.aoMorrer"));
    }

    private function ranged(stdClass $entry, string $path): ?RangedSettings
    {
        $present = array_filter(self::RANGED_FIELDS, static fn (string $field) => property_exists($entry, $field));
        if ($present === []) {
            return null;
        }

        return new RangedSettings(
            ConfigSchema::number($entry, 'distanciaIdeal', $path),
            ConfigSchema::number($entry, 'margemHisterese', $path),
            ConfigSchema::number($entry, 'cooldownTiroSegundos', $path),
        );
    }

    private function flag(stdClass $entry, string $field, string $path): bool
    {
        if (! property_exists($entry, $field)) {
            return false;
        }
        if (! is_bool($entry->{$field})) {
            throw new InvalidArgumentException("{$path}.{$field} deve ser true ou false.");
        }

        return $entry->{$field};
    }

    private function integer(stdClass $object, string $field, string $path): int
    {
        $value = $object->{$field} ?? null;
        if (! is_int($value) || $value < 0) {
            throw new InvalidArgumentException("{$path}.{$field} deve ser um inteiro >= 0.");
        }

        return $value;
    }

    private function assertMeta(stdClass $root, string $source): void
    {
        $meta = ConfigSchema::object($root->_meta ?? null, "{$source}: _meta");
        if (ConfigSchema::string($meta, 'arquivo', "{$source}: _meta") !== 'enemies.json') {
            throw new InvalidArgumentException("{$source}: _meta.arquivo deve ser enemies.json.");
        }
        ConfigSchema::string($meta, 'nota', "{$source}: _meta");
        foreach (ConfigSchema::list($meta->requisitos ?? null, "{$source}: _meta.requisitos") as $index => $id) {
            if (! is_string($id) || trim($id) === '') {
                throw new InvalidArgumentException("{$source}: _meta.requisitos[{$index}] deve ser um ID não vazio.");
            }
        }
    }
}
