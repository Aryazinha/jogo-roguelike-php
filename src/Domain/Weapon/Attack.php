<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/** Identidade por execução; projéteis irmãos compartilham o registro de atingidos. */
final class Attack
{
    /** @var array<string, true> */
    private array $hits = [];

    public function __construct(public readonly int $id, public readonly string $sourceEntityId, public readonly WeaponDefinition $definition)
    {
        if ($id < 1) {
            throw new InvalidArgumentException('O ID lógico do ataque deve ser positivo.');
        }
        CombatValidation::requiredId($sourceEntityId, 'sourceEntityId');
    }

    public function claim(string $targetId): bool
    {
        CombatValidation::requiredId($targetId, 'targetId');
        $key = 'id:'.$targetId;
        if (isset($this->hits[$key])) {
            return false;
        }
        $this->hits[$key] = true;

        return true;
    }
}
