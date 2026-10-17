<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Menu;

final readonly class CharacterChoice
{
    public function __construct(
        public string $id,
        public string $name,
        public int $health,
        public int $regenerationSeconds,
        public string $startingWeapon,
        public string $passiveDescription,
    ) {}
}
