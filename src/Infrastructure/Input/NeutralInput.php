<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Input;

use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\Vector2;

final readonly class NeutralInput implements InputInterface
{
    private InputState $state;

    public function __construct()
    {
        $this->state = new InputState(Vector2::zero());
    }

    /** @phpstan-pure */
    public function poll(): InputState
    {
        return $this->state;
    }
}
