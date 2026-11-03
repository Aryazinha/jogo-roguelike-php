<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Input;

use Closure;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Ffi\RaylibBinding;

final class RaylibInput implements InputInterface
{
    private const KEY_A = 65;

    private const KEY_D = 68;

    private const KEY_P = 80;

    private const KEY_S = 83;

    private const KEY_W = 87;

    private const KEY_RIGHT = 262;

    private const KEY_LEFT = 263;

    private const KEY_DOWN = 264;

    private const KEY_UP = 265;

    /**
     * @param  Closure(int): bool  $keyDown
     * @param  Closure(int): bool  $keyPressed
     */
    public function __construct(
        private readonly Closure $keyDown,
        private readonly Closure $keyPressed,
    ) {}

    public static function fromRaylib(RaylibBinding $raylib): self
    {
        return new self(
            static fn (int $key): bool => $raylib->isKeyDown($key),
            static fn (int $key): bool => $raylib->isKeyPressed($key),
        );
    }

    /** @phpstan-impure */
    public function poll(): InputState
    {
        $movement = new Vector2(
            $this->axis(self::KEY_A, self::KEY_D),
            $this->axis(self::KEY_W, self::KEY_S),
        );

        $look = new Vector2(
            $this->axis(self::KEY_LEFT, self::KEY_RIGHT),
            $this->axis(self::KEY_UP, self::KEY_DOWN),
        );

        $facing = null;

        if (! $look->equals(Vector2::zero())) {
            $facing = $look->normalized();
        }

        $keyPressed = $this->keyPressed;

        return new InputState(
            movement: $movement,
            facing: $facing,
            pauseRequested: $keyPressed(self::KEY_P),
        );
    }

    private function axis(int $negative, int $positive): float
    {
        $keyDown = $this->keyDown;

        $positivePressed = $keyDown($positive);
        $negativePressed = $keyDown($negative);

        return (float) (
            (int) $positivePressed - (int) $negativePressed
        );
    }
}
