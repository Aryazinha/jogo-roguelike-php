<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Results;

final readonly class SaveOutcome
{
    public function __construct(
        public bool $success,
        public string $message,
    ) {}
}
