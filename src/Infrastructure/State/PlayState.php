<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

use Closure;
use Jogo\Infrastructure\Play\PlayFramePresenter;

final class PlayState extends ScreenState
{
    /** @var (Closure(float): void)|null */
    private ?Closure $onUpdate;

    /** @var (Closure(float): void)|null */
    private ?Closure $onRender;

    /**
     * @param  (callable(float): void)|null  $onUpdate
     * @param  (callable(float): void)|null  $onRender
     */
    public function __construct(
        ?callable $onUpdate = null,
        ?callable $onRender = null,
        private readonly ?PlayFramePresenter $presenter = null,
    ) {
        $this->onUpdate = $onUpdate === null
            ? null
            : Closure::fromCallable($onUpdate);

        $this->onRender = $onRender === null
            ? null
            : Closure::fromCallable($onRender);
    }

    public function name(): string
    {
        return 'play';
    }

    public function update(float $deltaSeconds, StateStack $states): void
    {
        if ($this->onUpdate !== null) {
            ($this->onUpdate)($deltaSeconds);
        }
    }

    public function render(float $alpha, StateStack $states): void
    {
        $this->presenter?->render($alpha);

        if ($this->onRender !== null) {
            ($this->onRender)($alpha);
        }
    }
}
