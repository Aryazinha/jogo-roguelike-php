<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Audio;

use Jogo\Infrastructure\Ffi\RaylibBinding;

final readonly class RaylibAudioDevice implements AudioDeviceInterface
{
    public function __construct(
        private RaylibBinding $raylib,
    ) {}

    public function init(): bool
    {
        return $this->raylib->initAudio();
    }

    public function loadSound(string $path): string
    {
        return $this->raylib->loadSound($path);
    }

    public function playSound(string $id): void
    {
        $this->raylib->playSound($id);
    }

    public function unloadSound(string $id): void
    {
        $this->raylib->unloadSound($id);
    }

    public function loadMusic(string $path): string
    {
        return $this->raylib->loadMusic($path);
    }

    public function playMusic(string $id): void
    {
        $this->raylib->playMusic($id);
    }

    public function updateMusic(string $id): void
    {
        $this->raylib->updateMusic($id);
    }

    public function unloadMusic(string $id): void
    {
        $this->raylib->unloadMusic($id);
    }

    public function shutdown(): void
    {
        $this->raylib->closeAudio();
    }
}
