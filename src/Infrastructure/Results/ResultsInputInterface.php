<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Results;

interface ResultsInputInterface
{
    /** @phpstan-impure */
    public function poll(): ResultsInputEvent;
}
