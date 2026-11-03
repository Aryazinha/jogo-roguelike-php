<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Play;

final readonly class PlaySnapshot
{
    public function __construct(
        public PlayWorldSnapshot $world,
        public PlayHudSnapshot $hud,
    ) {}
}
