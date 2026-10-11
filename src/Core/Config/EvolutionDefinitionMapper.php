<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Weapon\Upgrade\EvolutionCatalog;
use Jogo\Domain\Weapon\Upgrade\EvolutionDefinition;
use Jogo\Domain\Weapon\WeaponCatalog;

final readonly class EvolutionDefinitionMapper
{
    /** @param list<string> $passiveIds Registro externo da B, sem comportamento de passivos. */
    public function __construct(private WeaponCatalog $weapons, private array $passiveIds)
    {
        foreach ($passiveIds as $id) {
            CombatValidation::requiredId($id, 'passiveId');
        }
    }

    public function map(mixed $decoded, string $source = 'evolutions.json'): EvolutionCatalog
    {
        $root = ConfigSchema::root($decoded, 'evolucoes', 'evolutions.json', $source);
        $definitions = [];
        $seen = [];
        $combinations = [];
        foreach (ConfigSchema::list($root->evolucoes ?? null, "{$source}: evolucoes") as $index => $raw) {
            $entry = ConfigSchema::object($raw, "{$source}: evolucoes[{$index}]");
            $id = ConfigSchema::string($entry, 'id', "{$source}: evolucoes[{$index}]");
            $path = "{$source}: {$id}";
            ConfigSchema::fields($entry, ['id', 'nome', 'requer', 'resultado'], $path);
            if (isset($seen['id:'.$id]) || $this->weapons->has($id)) {
                throw new InvalidArgumentException("{$path}.id duplicado ou já utilizado por arma base.");
            }
            $seen['id:'.$id] = true;
            $required = ConfigSchema::object($entry->requer ?? null, "{$path}.requer");
            ConfigSchema::fields($required, ['arma', 'passivo'], "{$path}.requer");
            $weapon = ConfigSchema::object($required->arma ?? null, "{$path}.requer.arma");
            $passive = ConfigSchema::object($required->passivo ?? null, "{$path}.requer.passivo");
            ConfigSchema::fields($weapon, ['id', 'nivelMinimo'], "{$path}.requer.arma");
            ConfigSchema::fields($passive, ['id', 'nivelMinimo'], "{$path}.requer.passivo");
            $weaponId = ConfigSchema::string($weapon, 'id', "{$path}.requer.arma");
            $passiveId = ConfigSchema::string($passive, 'id', "{$path}.requer.passivo");
            if (! $this->weapons->has($weaponId)) {
                throw new InvalidArgumentException("{$path}.requer.arma.id inexistente.");
            }
            if (! in_array($passiveId, $this->passiveIds, true)) {
                throw new InvalidArgumentException("{$path}.requer.passivo.id inexistente no registro externo.");
            }
            $result = ConfigSchema::object($entry->resultado ?? null, "{$path}.resultado");
            if (ConfigSchema::string($result, 'substituiArma', "{$path}.resultado") !== $weaponId) {
                throw new InvalidArgumentException("{$path}.resultado.substituiArma diverge da arma requerida.");
            }
            $fields = get_object_vars($result);
            unset($fields['substituiArma']);
            // Nome e procedência pertencem à entrada da evolução, não ao bloco resultado.
            if (array_key_exists('nome', $fields)) {
                throw new InvalidArgumentException("{$path}.resultado.nome é um campo desconhecido.");
            }
            $fields['nome'] = ConfigSchema::string($entry, 'nome', $path);
            $fields['_fonte'] = ConfigSchema::string($entry, '_fonte', $path);
            $fields['_nota'] = ConfigSchema::string($entry, '_nota', $path);
            $definition = (new WeaponDefinitionMapper)->mapWeapon($id, $fields, $source);
            $base = $this->weapons->get($weaponId);
            if ($definition->shapeId !== $base->shapeId || $definition->targetingId !== $base->targetingId) {
                throw new InvalidArgumentException("{$path}.resultado.formato/mira incompatível com a arma substituída.");
            }
            $weaponLevel = ConfigSchema::level($weapon, 'nivelMinimo', "{$path}.requer.arma");
            $passiveLevel = ConfigSchema::level($passive, 'nivelMinimo', "{$path}.requer.passivo");
            $combination = serialize([$weaponId, $weaponLevel, $passiveId, $passiveLevel]);
            if (isset($combinations[$combination])) {
                throw new InvalidArgumentException("{$path}.requer contém combinação de evolução duplicada.");
            }
            $combinations[$combination] = true;
            $definitions[] = new EvolutionDefinition($weaponId, $weaponLevel, $passiveId, $passiveLevel, $definition);
        }
        if ($definitions === []) {
            throw new InvalidArgumentException("{$source}: evolucoes não pode estar vazio.");
        }

        return new EvolutionCatalog($definitions);
    }
}
