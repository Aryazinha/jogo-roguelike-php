<?php

declare(strict_types=1);

namespace Jogo\Domain\Character;

use InvalidArgumentException;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\CombatInventory;
use Jogo\Domain\Weapon\WeaponCatalog;
use Jogo\Domain\Weapon\WeaponDefinition;

/** Cria o jogador a partir do ID escolhido, validando tudo antes de montar a partida. */
final readonly class PlayerFactory
{
    public function __construct(private CharacterCatalog $characters, private WeaponCatalog $weapons)
    {
        foreach ($characters->all() as $character) {
            if (! $weapons->has($character->startingWeaponId)) {
                throw new InvalidArgumentException("{$character->id}.armaInicial referencia a arma inexistente {$character->startingWeaponId}.");
            }
        }
    }

    /**
     * @param  callable(WeaponDefinition): bool  $execute  Executor de ataques fornecido pela Frente A.
     * @param  array<string, float>  $optionalBaseStats  velocidadeJogador/raioColeta, quando a equipe definir.
     */
    public function create(
        string $characterId,
        string $playerId,
        Vector2 $position,
        float $collisionRadius,
        callable $execute,
        array $optionalBaseStats = [],
    ): Player {
        $character = $this->characters->get($characterId);
        $inventory = new CombatInventory;
        $inventory->add($this->weapons->get($character->startingWeaponId), $execute);

        return new Player($playerId, $position, $collisionRadius, $character, $inventory, $optionalBaseStats);
    }
}
