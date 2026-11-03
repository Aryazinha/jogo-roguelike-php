<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Rendering;

use Closure;
use Jogo\Infrastructure\Ffi\RaylibBinding;
use LogicException;

final class RaylibResourceCache
{
    /** @var array<string, string> */
    private array $textures = [];

    /** @var array<string, string> */
    private array $fonts = [];

    private bool $closed = false;

    /**
     * @param  Closure(string): string  $loadTexture
     * @param  Closure(string): void  $unloadTexture
     * @param  Closure(string): string  $loadFont
     * @param  Closure(string): void  $unloadFont
     */
    public function __construct(
        private readonly Closure $loadTexture,
        private readonly Closure $unloadTexture,
        private readonly Closure $loadFont,
        private readonly Closure $unloadFont,
    ) {}

    public static function fromRaylib(RaylibBinding $raylib): self
    {
        return new self(
            static fn (string $path): string => $raylib->loadTexture($path),
            static function (string $id) use ($raylib): void {
                $raylib->unloadTexture($id);
            },
            static fn (string $path): string => $raylib->loadFont($path),
            static function (string $id) use ($raylib): void {
                $raylib->unloadFont($id);
            },
        );
    }

    /** @phpstan-impure */
    public function texture(string $path): string
    {
        $this->requireOpen();

        if (! isset($this->textures[$path])) {
            $loader = $this->loadTexture;
            $this->textures[$path] = $loader($path);
        }

        return $this->textures[$path];
    }

    /** @phpstan-impure */
    public function font(string $path): string
    {
        $this->requireOpen();

        if (! isset($this->fonts[$path])) {
            $loader = $this->loadFont;
            $this->fonts[$path] = $loader($path);
        }

        return $this->fonts[$path];
    }

    public function release(): void
    {
        if ($this->closed) {
            return;
        }

        $unloadFont = $this->unloadFont;
        $unloadTexture = $this->unloadTexture;

        foreach ($this->fonts as $id) {
            $unloadFont($id);
        }

        foreach ($this->textures as $id) {
            $unloadTexture($id);
        }

        $this->fonts = [];
        $this->textures = [];
        $this->closed = true;
    }

    private function requireOpen(): void
    {
        if ($this->closed) {
            throw new LogicException(
                'Cache de recursos ja encerrado.'
            );
        }
    }
}
