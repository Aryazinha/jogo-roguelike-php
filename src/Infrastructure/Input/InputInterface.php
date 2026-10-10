<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Input;

use Jogo\Domain\Value\InputState;

/** Porta ratificada na A1; a Frente D fornecerá o adaptador de entrada real. */
interface InputInterface
{
    /** @phpstan-impure */
    public function poll(): InputState;
}
