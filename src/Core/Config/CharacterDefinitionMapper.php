<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;
use Jogo\Domain\Character\CharacterCatalog;
use Jogo\Domain\Character\CharacterDefinition;
use Jogo\Domain\Character\CharacterPassive;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Weapon\WeaponCatalog;
use stdClass;

/** Valida characters.json e entrega somente definições ao domínio. */
final readonly class CharacterDefinitionMapper
{
    public function __construct(private WeaponCatalog $weapons) {}

    public function map(mixed $decoded, string $source = 'characters.json'): CharacterCatalog
    {
        $root = ConfigSchema::root($decoded, 'personagens', 'characters.json', $source);
        $characters = ConfigSchema::object($root->personagens ?? null, "{$source}: personagens");
        $definitions = [];
        foreach (get_object_vars($characters) as $id => $raw) {
            $id = (string) $id;
            $definitions[] = $this->character($id, ConfigSchema::object($raw, "{$source}: {$id}"), "{$source}: {$id}");
        }
        if ($definitions === []) {
            throw new InvalidArgumentException("{$source}: personagens não pode estar vazio.");
        }

        return new CharacterCatalog($definitions);
    }

    private function character(string $id, stdClass $entry, string $path): CharacterDefinition
    {
        ConfigSchema::fields($entry, ['nome', 'vida', 'regeneracao', 'armaInicial', 'passiva'], $path);
        $name = ConfigSchema::string($entry, 'nome', $path);
        $health = $this->positive($entry, 'vida', $path);
        $regeneration = ConfigSchema::object($entry->regeneracao ?? null, "{$path}.regeneracao");
        ConfigSchema::fields($regeneration, ['hp', 'intervaloSegundos'], "{$path}.regeneracao");
        $regenerationHp = $this->positive($regeneration, 'hp', "{$path}.regeneracao");
        $interval = $this->positive($regeneration, 'intervaloSegundos', "{$path}.regeneracao");
        $weaponId = ConfigSchema::string($entry, 'armaInicial', $path);
        if (! $this->weapons->has($weaponId)) {
            throw new InvalidArgumentException("{$path}.armaInicial referencia a arma inexistente {$weaponId}.");
        }

        return new CharacterDefinition($id, $name, $health, $regenerationHp, $interval, $weaponId,
            $this->passive(ConfigSchema::object($entry->passiva ?? null, "{$path}.passiva"), "{$path}.passiva"));
    }

    private function passive(stdClass $entry, string $path): CharacterPassive
    {
        ConfigSchema::fields($entry, ['id', 'descricao', 'aCadaNiveis', 'modificador'], $path);
        $modifier = ConfigSchema::object($entry->modificador ?? null, "{$path}.modificador");
        ConfigSchema::fields($modifier, ['atributo', 'tipo', 'valor'], "{$path}.modificador");
        $type = ModifierType::tryFrom(ConfigSchema::string($modifier, 'tipo', "{$path}.modificador"))
            ?? throw new InvalidArgumentException("{$path}.modificador.tipo deve ser FLAT ou PERCENT.");
        try {
            return new CharacterPassive(
                ConfigSchema::string($entry, 'id', $path),
                ConfigSchema::string($entry, 'descricao', $path),
                ConfigSchema::level($entry, 'aCadaNiveis', $path),
                ConfigSchema::string($modifier, 'atributo', "{$path}.modificador"),
                $type,
                ConfigSchema::number($modifier, 'valor', "{$path}.modificador"),
            );
        } catch (InvalidArgumentException $error) {
            if (str_starts_with($error->getMessage(), $path)) {
                throw $error;
            }
            throw new InvalidArgumentException("{$path}.modificador: {$error->getMessage()}", 0, $error);
        }
    }

    private function positive(stdClass $object, string $field, string $path): float
    {
        $value = ConfigSchema::number($object, $field, $path);
        if ($value <= 0.0) {
            throw new InvalidArgumentException("{$path}.{$field} deve ser maior que zero.");
        }

        return $value;
    }
}
