<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Audio;

interface AudioInterface
{
    public function playEffect(string $effect): void;

    public function playMusic(string $track): void;

    public function update(): void;

    public function stopMusic(): void;

    public function close(): void;
}
