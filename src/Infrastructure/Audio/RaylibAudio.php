<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Audio;

use Jogo\Infrastructure\Ffi\RaylibBinding;
use Throwable;

final class RaylibAudio implements AudioInterface
{
    /** @var array<string, string> */
    private array $soundIds = [];

    private ?string $activeMusicId = null;

    private ?string $activeMusicKey = null;

    private bool $initialized = false;

    private bool $available = false;

    private bool $closed = false;

    /** @var list<string> */
    private array $failures = [];

    /**
     * @param  array<string, string>  $effects
     * @param  array<string, string>  $tracks
     */
    public function __construct(
        private readonly AudioDeviceInterface $device,
        private readonly array $effects = [],
        private readonly array $tracks = [],
    ) {}

    /**
     * @param  array<string, string>  $effects
     * @param  array<string, string>  $tracks
     */
    public static function fromRaylib(
        RaylibBinding $raylib,
        array $effects = [],
        array $tracks = [],
    ): self {
        return new self(
            new RaylibAudioDevice($raylib),
            $effects,
            $tracks,
        );
    }

    /** @phpstan-impure */
    public function playEffect(string $effect): void
    {
        $path = $this->effects[$effect] ?? null;

        if ($path === null || ! $this->ready()) {
            return;
        }

        if (! is_file($path)) {
            $this->failures[] = 'Efeito ausente: '.$path;

            return;
        }

        try {
            if (! isset($this->soundIds[$effect])) {
                $this->soundIds[$effect] = $this->device->loadSound($path);
            }

            $this->device->playSound($this->soundIds[$effect]);
        } catch (Throwable $error) {
            $this->failures[] = 'Falha no efeito '.$effect.': '.$error->getMessage();
        }
    }

    /** @phpstan-impure */
    public function playMusic(string $track): void
    {
        $path = $this->tracks[$track] ?? null;

        if ($path === null || ! $this->ready()) {
            return;
        }

        if ($this->activeMusicKey === $track) {
            return;
        }

        if (! is_file($path)) {
            $this->failures[] = 'Musica ausente: '.$path;

            return;
        }

        $newId = null;

        try {
            $newId = $this->device->loadMusic($path);
            $this->device->playMusic($newId);

            $oldId = $this->activeMusicId;

            $this->activeMusicId = $newId;
            $this->activeMusicKey = $track;

            if ($oldId !== null) {
                $this->device->unloadMusic($oldId);
            }
        } catch (Throwable $error) {
            if ($this->activeMusicId !== $newId && $newId !== null) {
                try {
                    $this->device->unloadMusic($newId);
                } catch (Throwable $cleanupError) {
                    $this->failures[] = 'Falha ao liberar musica: '.$cleanupError->getMessage();
                }
            }

            $this->failures[] = 'Falha na musica '.$track.': '.$error->getMessage();
        }
    }

    /** @phpstan-impure */
    public function update(): void
    {
        if (
            $this->closed
            || ! $this->available
            || $this->activeMusicId === null
        ) {
            return;
        }

        try {
            $this->device->updateMusic($this->activeMusicId);
        } catch (Throwable $error) {
            $this->failures[] = 'Falha ao atualizar musica: '.$error->getMessage();
        }
    }

    /** @phpstan-impure */
    public function stopMusic(): void
    {
        if ($this->activeMusicId === null) {
            return;
        }

        $id = $this->activeMusicId;

        $this->activeMusicId = null;
        $this->activeMusicKey = null;

        try {
            $this->device->unloadMusic($id);
        } catch (Throwable $error) {
            $this->failures[] = 'Falha ao parar musica: '.$error->getMessage();
        }
    }

    /** @phpstan-impure */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->stopMusic();

        foreach ($this->soundIds as $id) {
            try {
                $this->device->unloadSound($id);
            } catch (Throwable $error) {
                $this->failures[] = 'Falha ao liberar som: '.$error->getMessage();
            }
        }

        $this->soundIds = [];

        if ($this->initialized && $this->available) {
            try {
                $this->device->shutdown();
            } catch (Throwable $error) {
                $this->failures[] = 'Falha ao fechar audio: '.$error->getMessage();
            }
        }

        $this->available = false;
        $this->closed = true;
    }

    public function isAvailable(): bool
    {
        return $this->available && ! $this->closed;
    }

    /** @return list<string> */
    public function failures(): array
    {
        return $this->failures;
    }

    /** @phpstan-impure */
    private function ready(): bool
    {
        if ($this->closed) {
            return false;
        }

        if (! $this->initialized) {
            $this->initialized = true;

            try {
                $this->available = $this->device->init();

                if (! $this->available) {
                    $this->failures[] = 'Dispositivo de audio indisponivel.';
                }
            } catch (Throwable $error) {
                $this->available = false;
                $this->failures[] = 'Falha ao iniciar audio: '.$error->getMessage();
            }
        }

        return $this->available;
    }
}
