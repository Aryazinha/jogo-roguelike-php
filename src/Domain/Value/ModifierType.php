<?php

declare(strict_types=1);

namespace Jogo\Domain\Value;

enum ModifierType: string
{
    case FLAT = 'FLAT';
    case PERCENT = 'PERCENT';
}
