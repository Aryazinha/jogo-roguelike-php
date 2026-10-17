<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Audio;

interface AudioDeviceInterface
{
    public function init(): bool;

    public function loadSound(string $path): string;

    public function playSound(string $id): void;

    public function unloadSound(string $id): void;

    public function loadMusic(string $path): string;

    public function playMusic(string $id): void;

    public function updateMusic(string $id): void;

    public function unloadMusic(string $id): void;

    public function shutdown(): void;
}
