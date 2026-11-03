<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Overlay;

use InvalidArgumentException;

final readonly class ReplacementSlotView
{
    public function __construct(
        public string $id,
        public string $weaponName,
    ) {
        if (trim($id) === '' || trim($weaponName) === '') {
            throw new InvalidArgumentException(
                'Slot de arma invalido.'
            );
        }
    }
}
