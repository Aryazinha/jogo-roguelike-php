<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Play;

interface PlaySnapshotProviderInterface
{
    /** @phpstan-impure */
    public function snapshot(): PlaySnapshot;
}
