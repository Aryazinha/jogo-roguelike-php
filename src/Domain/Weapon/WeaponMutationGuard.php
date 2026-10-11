<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon;

/** Composição informa se o combate está fora de uma atualização. */
interface WeaponMutationGuard
{
    public function assertSafe(): void;
}
