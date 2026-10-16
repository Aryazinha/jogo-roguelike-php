<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;
use Jogo\Domain\Enemy\EnemyCatalog;
use Jogo\Domain\Wave\WaveBand;
use Jogo\Domain\Wave\WaveSettings;
use stdClass;

/**
 * Lê waves.json. Recebe o catálogo para conferir que cada tipo citado existe
 * e pode surgir por onda: o boss tem hora própria e o Slime pequeno só nasce
 * de divisão.
 */
final readonly class WaveConfigLoader
{
    public function __construct(private EnemyCatalog $catalog) {}

    public function load(string $file): WaveSettings
    {
        return $this->map(ConfigJsonReader::load($file), $file);
    }

    public function decode(string $json, string $source = 'waves.json'): WaveSettings
    {
        return $this->map(ConfigJsonReader::decode($json, $source), $source);
    }

    private function map(mixed $decoded, string $source): WaveSettings
    {
        $root = ConfigSchema::object($decoded, $source);
        ConfigSchema::fields($root, ['diretorAdaptativo', 'limites', 'faixas', 'escalonamentoPorNivel'], $source);
        $this->assertMeta($root, $source);
        // O diretor adaptativo está fora do escopo firme (PLANO.md 4.4): ligado, não há o que executar.
        if (($root->diretorAdaptativo ?? null) !== false) {
            throw new InvalidArgumentException("{$source}: diretorAdaptativo deve ser false até a equipe decidir implementá-lo.");
        }

        $limits = ConfigSchema::object($root->limites ?? null, "{$source}: limites");
        ConfigSchema::fields($limits, ['inimigosSimultaneosMaximo'], "{$source}: limites");
        $maxAlive = $limits->inimigosSimultaneosMaximo ?? null;
        if (! is_int($maxAlive)) {
            throw new InvalidArgumentException("{$source}: limites.inimigosSimultaneosMaximo deve ser inteiro.");
        }

        $bands = [];
        foreach (ConfigSchema::list($root->faixas ?? null, "{$source}: faixas") as $index => $raw) {
            $bands[] = $this->band(ConfigSchema::object($raw, "{$source}: faixas[{$index}]"), "{$source}: faixas[{$index}]");
        }

        $scaling = ConfigSchema::object($root->escalonamentoPorNivel ?? null, "{$source}: escalonamentoPorNivel");
        ConfigSchema::fields($scaling, ['vidaPorNivel', 'danoPorNivel'], "{$source}: escalonamentoPorNivel");

        try {
            return new WaveSettings($bands, $maxAlive, $this->percent($scaling, 'vidaPorNivel', $source),
                $this->percent($scaling, 'danoPorNivel', $source));
        } catch (InvalidArgumentException $error) {
            throw new InvalidArgumentException("{$source}: {$error->getMessage()}", 0, $error);
        }
    }

    private function band(stdClass $entry, string $path): WaveBand
    {
        ConfigSchema::fields($entry, ['deSegundos', 'ateSegundos', 'intervaloSpawnSegundos', 'inimigosPorSpawn', 'tipos'], $path);
        if (! property_exists($entry, 'ateSegundos')) {
            throw new InvalidArgumentException("{$path}.ateSegundos é obrigatório; use null para até o fim.");
        }
        $count = $entry->inimigosPorSpawn ?? null;
        if (! is_int($count)) {
            throw new InvalidArgumentException("{$path}.inimigosPorSpawn deve ser inteiro.");
        }
        $types = [];
        foreach (ConfigSchema::list($entry->tipos ?? null, "{$path}.tipos") as $offset => $type) {
            if (! is_string($type) || ! $this->catalog->has($type)) {
                throw new InvalidArgumentException("{$path}.tipos[{$offset}] não é um inimigo conhecido.");
            }
            $archetype = $this->catalog->get($type);
            if ($archetype->isBoss() || $archetype->onlyFromSplit) {
                throw new InvalidArgumentException("{$path}.tipos[{$offset}] ({$type}) não surge por onda.");
            }
            $types[] = $type;
        }

        try {
            return new WaveBand(
                ConfigSchema::number($entry, 'deSegundos', $path),
                $entry->ateSegundos === null ? null : ConfigSchema::number($entry, 'ateSegundos', $path),
                ConfigSchema::number($entry, 'intervaloSpawnSegundos', $path),
                $count,
                $types,
            );
        } catch (InvalidArgumentException $error) {
            if (str_starts_with($error->getMessage(), $path)) {
                throw $error;
            }
            throw new InvalidArgumentException("{$path}: {$error->getMessage()}", 0, $error);
        }
    }

    private function percent(stdClass $scaling, string $field, string $source): float
    {
        $path = "{$source}: escalonamentoPorNivel.{$field}";
        $modifier = ConfigSchema::object($scaling->{$field} ?? null, $path);
        ConfigSchema::fields($modifier, ['tipo', 'valor'], $path);
        if (ConfigSchema::string($modifier, 'tipo', $path) !== 'PERCENT') {
            throw new InvalidArgumentException("{$path}.tipo deve ser PERCENT.");
        }

        return ConfigSchema::number($modifier, 'valor', $path);
    }

    private function assertMeta(stdClass $root, string $source): void
    {
        $meta = ConfigSchema::object($root->_meta ?? null, "{$source}: _meta");
        if (ConfigSchema::string($meta, 'arquivo', "{$source}: _meta") !== 'waves.json') {
            throw new InvalidArgumentException("{$source}: _meta.arquivo deve ser waves.json.");
        }
    }
}
