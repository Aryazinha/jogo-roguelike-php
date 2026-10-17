<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Menu;

use Closure;
use InvalidArgumentException;

final readonly class MenuScreenContext
{
    /** @var Closure(string): void */
    private Closure $onSelected;

    /** @var Closure(): void */
    private Closure $onExit;

    /**
     * @param  list<CharacterChoice>  $characters
     * @param  callable(string): void  $onSelected
     * @param  callable(): void  $onExit
     */
    public function __construct(
        public MenuInputInterface $input,
        public MenuScreenRendererInterface $renderer,
        public array $characters,
        callable $onSelected,
        callable $onExit,
    ) {
        if (count($characters) < 2) {
            throw new InvalidArgumentException(
                'Informe pelo menos dois personagens.'
            );
        }

        $this->onSelected = Closure::fromCallable($onSelected);
        $this->onExit = Closure::fromCallable($onExit);
    }

    public function selectCharacter(string $id): void
    {
        ($this->onSelected)($id);
    }

    public function requestExit(): void
    {
        ($this->onExit)();
    }

    public static function moveSelection(
        int $current,
        int $direction,
        int $count,
    ): int {
        if ($count <= 0) {
            return 0;
        }

        return ($current + $direction + $count) % $count;
    }

    public static function clickedOption(
        MenuInputEvent $event,
        int $count,
    ): ?int {
        if (
            ! $event->click
            || $event->mouseX === null
            || $event->mouseY === null
            || $event->mouseX < 100
            || $event->mouseX >= 700
        ) {
            return null;
        }

        for ($index = 0; $index < $count; $index++) {
            $y = 135 + $index * 56;

            if (
                $event->mouseY >= $y
                && $event->mouseY < $y + 46
            ) {
                return $index;
            }
        }

        return null;
    }
}
