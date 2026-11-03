<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;
use Jogo\Domain\Enemy\Boss\BossPhaseSettings;
use Jogo\Domain\Enemy\EnemyCatalog;
use Jogo\Domain\Enemy\EnemyCategory;
use stdClass;

/**
 * Lê boss.<id>.fases de enemies.json. Recebe o catálogo já carregado para
 * conferir que o tipo invocado existe e é um inimigo comum.
 */
final readonly class BossConfigLoader
{
    public function __construct(private EnemyCatalog $catalog) {}

    public function load(string $file): BossPhaseSettings
    {
        return $this->map(ConfigJsonReader::load($file), $file);
    }

    public function decode(string $json, string $source = 'enemies.json'): BossPhaseSettings
    {
        return $this->map(ConfigJsonReader::decode($json, $source), $source);
    }

    private function map(mixed $decoded, string $source): BossPhaseSettings
    {
        $boss = $this->catalog->boss();
        $root = ConfigSchema::object($decoded, $source);
        $group = ConfigSchema::object($root->boss ?? null, "{$source}: boss");
        $entry = ConfigSchema::object($group->{$boss->id} ?? null, "{$source}: {$boss->id}");
        $path = "{$source}: {$boss->id}.fases";
        $phases = ConfigSchema::object($entry->fases ?? null, $path);
        ConfigSchema::fields($phases, ['telegrafando', 'investindo', 'recuperando', 'cooldownInvestidaSegundos',
            'invocando', 'enfurecido'], $path);

        $telegraph = $this->section($phases, 'telegrafando', ['duracaoSegundos'], $path);
        $charge = $this->section($phases, 'investindo', ['duracaoSegundos', 'multiplicadorVelocidade'], $path);
        $recover = $this->section($phases, 'recuperando', ['duracaoSegundos'], $path);
        $summon = $this->section($phases, 'invocando', ['emVidaPercentual', 'quantidade', 'tipo', 'duracaoSegundos'], $path);
        $enrage = $this->section($phases, 'enfurecido', ['emVidaPercentual', 'modificadores'], $path);

        $summonType = ConfigSchema::string($summon, 'tipo', "{$path}.invocando");
        if (! $this->catalog->has($summonType) || $this->catalog->get($summonType)->category !== EnemyCategory::COMMON) {
            throw new InvalidArgumentException("{$path}.invocando.tipo deve ser um inimigo comum existente: {$summonType}.");
        }
        $thresholds = [];
        foreach (ConfigSchema::list($summon->emVidaPercentual ?? null, "{$path}.invocando.emVidaPercentual") as $index => $value) {
            if (! is_int($value) && ! is_float($value)) {
                throw new InvalidArgumentException("{$path}.invocando.emVidaPercentual[{$index}] deve ser um número.");
            }
            $thresholds[] = (float) $value;
        }
        $count = $summon->quantidade ?? null;
        if (! is_int($count)) {
            throw new InvalidArgumentException("{$path}.invocando.quantidade deve ser inteiro.");
        }
        [$speedBonus, $cooldownBonus] = $this->enrageModifiers($enrage, "{$path}.enfurecido");

        try {
            return new BossPhaseSettings(
                telegraphSeconds: ConfigSchema::number($telegraph, 'duracaoSegundos', "{$path}.telegrafando"),
                chargeSeconds: ConfigSchema::number($charge, 'duracaoSegundos', "{$path}.investindo"),
                chargeSpeedMultiplier: ConfigSchema::number($charge, 'multiplicadorVelocidade', "{$path}.investindo"),
                recoverSeconds: ConfigSchema::number($recover, 'duracaoSegundos', "{$path}.recuperando"),
                chargeCooldownSeconds: ConfigSchema::number($phases, 'cooldownInvestidaSegundos', $path),
                summonThresholds: $thresholds,
                summonCount: $count,
                summonTypeId: $summonType,
                summonSeconds: ConfigSchema::number($summon, 'duracaoSegundos', "{$path}.invocando"),
                enrageThreshold: ConfigSchema::number($enrage, 'emVidaPercentual', "{$path}.enfurecido"),
                enragedSpeedBonus: $speedBonus,
                enragedCooldownBonus: $cooldownBonus,
            );
        } catch (InvalidArgumentException $error) {
            if (str_starts_with($error->getMessage(), $path)) {
                throw $error;
            }
            throw new InvalidArgumentException("{$path}: {$error->getMessage()}", 0, $error);
        }
    }

    /** @param list<string> $fields */
    private function section(stdClass $phases, string $name, array $fields, string $path): stdClass
    {
        $section = ConfigSchema::object($phases->{$name} ?? null, "{$path}.{$name}");
        ConfigSchema::fields($section, $fields, "{$path}.{$name}");

        return $section;
    }

    /** @return array{float, float} Bônus de velocidade e de cooldown, como frações (PERCENT). */
    private function enrageModifiers(stdClass $enrage, string $path): array
    {
        $bonus = ['velocidade' => null, 'cooldown' => null];
        foreach (ConfigSchema::list($enrage->modificadores ?? null, "{$path}.modificadores") as $index => $raw) {
            $itemPath = "{$path}.modificadores[{$index}]";
            $modifier = ConfigSchema::object($raw, $itemPath);
            ConfigSchema::fields($modifier, ['atributo', 'tipo', 'valor'], $itemPath);
            $attribute = ConfigSchema::string($modifier, 'atributo', $itemPath);
            if (! array_key_exists($attribute, $bonus) || $bonus[$attribute] !== null) {
                throw new InvalidArgumentException("{$itemPath}.atributo deve ser velocidade ou cooldown, sem repetir.");
            }
            if (ConfigSchema::string($modifier, 'tipo', $itemPath) !== 'PERCENT') {
                throw new InvalidArgumentException("{$itemPath}.tipo deve ser PERCENT.");
            }
            $bonus[$attribute] = ConfigSchema::number($modifier, 'valor', $itemPath);
        }

        return [$bonus['velocidade'] ?? 0.0, $bonus['cooldown'] ?? 0.0];
    }
}
