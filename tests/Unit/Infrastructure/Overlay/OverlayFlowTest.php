<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Infrastructure\Overlay;

use InvalidArgumentException;
use Jogo\Core\GameLoop;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Menu\MenuInputEvent;
use Jogo\Infrastructure\Menu\ScriptedMenuInput;
use Jogo\Infrastructure\Overlay\LevelUpScreenContext;
use Jogo\Infrastructure\Overlay\OverlayFramePresenter;
use Jogo\Infrastructure\Overlay\OverlayUiController;
use Jogo\Infrastructure\Overlay\ReplacementSlotView;
use Jogo\Infrastructure\Overlay\UpgradeOptionView;
use Jogo\Infrastructure\Play\PlayFrameFactory;
use Jogo\Infrastructure\Play\PlayFramePresenter;
use Jogo\Infrastructure\Play\PlayHudSnapshot;
use Jogo\Infrastructure\Play\PlaySnapshot;
use Jogo\Infrastructure\Play\PlaySnapshotProviderInterface;
use Jogo\Infrastructure\Play\PlayWorldSnapshot;
use Jogo\Infrastructure\Rendering\FrameSnapshot;
use Jogo\Infrastructure\Rendering\RendererInterface;
use Jogo\Infrastructure\State\LevelUpState;
use Jogo\Infrastructure\State\PauseState;
use Jogo\Infrastructure\State\PlayState;
use Jogo\Infrastructure\State\StateLoopSynchronizer;
use Jogo\Infrastructure\State\StateStack;
use Jogo\Tests\Support\ManualClock;
use PHPUnit\Framework\TestCase;

final class OverlayFlowTest extends TestCase
{
    public function test_pause_freezes_simulation_and_resumes(): void
    {
        [$states, $input, $clock, $loop, $controller, $counter] =
            $this->setupGame();

        $clock->advance(GameLoop::STEP_SECONDS);
        $loop->tick();

        self::assertSame(1, $counter->updates);

        $states->push(new PauseState);
        $controller->process();

        self::assertTrue($loop->isPaused());

        $clock->advance(20.0);
        $loop->tick();

        self::assertSame(1, $counter->updates);

        $input->enqueue(new MenuInputEvent(confirm: true));
        $controller->process();

        self::assertFalse($loop->isPaused());
        self::assertInstanceOf(PlayState::class, $states->top());

        $clock->advance(GameLoop::STEP_SECONDS);
        $loop->tick();

        self::assertSame(2, $counter->updates);
    }

    public function test_level_up_confirms_exactly_once(): void
    {
        [$states, $input, $clock, $loop, $controller] =
            $this->setupGame();

        $calls = [];

        $context = $this->context(
            static function (string $id, ?string $slot) use (&$calls): bool {
                $calls[] = [$id, $slot];

                return true;
            }
        );

        $states->push(new LevelUpState($context));
        $controller->process();

        self::assertTrue($loop->isPaused());

        $input->enqueue(new MenuInputEvent(confirm: true));
        $controller->process();

        self::assertSame([['upgrade_sword', null]], $calls);
        self::assertInstanceOf(PlayState::class, $states->top());
        self::assertFalse($loop->isPaused());
    }

    public function test_replacement_requires_selecting_a_slot(): void
    {
        [$states, $input, $clock, $loop, $controller] =
            $this->setupGame();

        $calls = [];

        $context = $this->context(
            static function (string $id, ?string $slot) use (&$calls): bool {
                $calls[] = [$id, $slot];

                return true;
            }
        );

        $levelUp = new LevelUpState($context);

        $states->push($levelUp);
        $controller->process();

        $input->enqueue(new MenuInputEvent(vertical: 1));
        $controller->process();

        $input->enqueue(new MenuInputEvent(confirm: true));
        $controller->process();

        self::assertSame('SUBSTITUIR ARMA', $levelUp->overlay()->title);
        self::assertSame([], $calls);

        $input->enqueue(new MenuInputEvent(vertical: 1));
        $controller->process();

        $input->enqueue(new MenuInputEvent(confirm: true));
        $controller->process();

        self::assertSame([['new_weapon', 'slot-2']], $calls);
        self::assertFalse($loop->isPaused());
    }

    public function test_failed_application_keeps_level_up_open(): void
    {
        [$states, $input, $clock, $loop, $controller] =
            $this->setupGame();

        $calls = 0;

        $context = $this->context(
            static function (string $id, ?string $slot) use (&$calls): bool {
                $calls++;

                return false;
            }
        );

        $states->push(new LevelUpState($context));
        $controller->process();

        $input->enqueue(new MenuInputEvent(confirm: true));
        $controller->process();

        self::assertSame(1, $calls);
        self::assertInstanceOf(LevelUpState::class, $states->top());
        self::assertTrue($loop->isPaused());
    }

    public function test_no_options_does_not_allow_a_selection(): void
    {
        [$states, $input, $clock, $loop, $controller] =
            $this->setupGame();

        $calls = 0;

        $context = new LevelUpScreenContext(
            [],
            [],
            static function (string $id, ?string $slot) use (&$calls): bool {
                $calls++;

                return true;
            },
        );

        $levelUp = new LevelUpState($context);

        $states->push($levelUp);
        $controller->process();

        $input->enqueue(new MenuInputEvent(confirm: true));
        $controller->process();

        self::assertSame(0, $calls);
        self::assertSame([], $levelUp->overlay()->options);
        self::assertTrue($loop->isPaused());
    }

    public function test_cancel_only_when_allowed(): void
    {
        [$states, $input, $clock, $loop, $controller] =
            $this->setupGame();

        $context = new LevelUpScreenContext(
            [],
            [],
            static fn (string $id, ?string $slot): bool => true,
            allowCancel: true,
        );

        $states->push(new LevelUpState($context));
        $controller->process();

        $input->enqueue(new MenuInputEvent(back: true));
        $controller->process();

        self::assertInstanceOf(PlayState::class, $states->top());
        self::assertFalse($loop->isPaused());
    }

    public function test_default_level_up_cannot_be_cancelled(): void
    {
        [$states, $input, $clock, $loop, $controller] =
            $this->setupGame();

        $states->push(new LevelUpState(
            $this->context(
                static fn (string $id, ?string $slot): bool => true
            )
        ));

        $controller->process();

        $input->enqueue(new MenuInputEvent(back: true));
        $controller->process();

        self::assertInstanceOf(LevelUpState::class, $states->top());
        self::assertTrue($loop->isPaused());
    }

    public function test_overlay_uses_one_render_frame(): void
    {
        $provider = new OverlayTestProvider;
        $renderer = new OverlayTestRenderer;

        $playPresenter = new PlayFramePresenter(
            $provider,
            new PlayFrameFactory,
            $renderer,
        );

        $overlayPresenter = new OverlayFramePresenter(
            $provider,
            new PlayFrameFactory,
            $renderer,
        );

        $states = new StateStack;

        $states->push(new PlayState(presenter: $playPresenter));
        $states->push(new PauseState($overlayPresenter));
        $states->flush();

        $states->render(0.5);

        self::assertCount(1, $renderer->frames);
        self::assertNotNull($renderer->frames[0]->overlay);
    }

    public function test_invalid_option_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UpgradeOptionView(
            'upgrade',
            'Upgrade',
            'invalid',
            'Descricao',
            'Efeito',
        );
    }

    /**
     * @param  callable(string, ?string): bool  $callback
     */
    private function context(callable $callback): LevelUpScreenContext
    {
        return new LevelUpScreenContext(
            [
                new UpgradeOptionView(
                    'upgrade_sword',
                    'Sharpness',
                    'weapon_upgrade',
                    'Melhora a espada',
                    'Aumenta o dano',
                ),
                new UpgradeOptionView(
                    'new_weapon',
                    'Nova arma',
                    'new_weapon',
                    'Adicionar uma arma',
                    'Substituir uma das armas equipadas',
                    true,
                ),
                new UpgradeOptionView(
                    'passive',
                    'Feather',
                    'passive',
                    'Melhora a movimentacao',
                    'Bonus passivo',
                ),
            ],
            [
                new ReplacementSlotView('slot-1', 'Stone Sword'),
                new ReplacementSlotView('slot-2', 'Bow'),
            ],
            $callback,
        );
    }

    /**
     * @return array{
     *     StateStack,
     *     ScriptedMenuInput,
     *     ManualClock,
     *     GameLoop,
     *     OverlayUiController,
     *     OverlayUpdateCounter
     * }
     */
    private function setupGame(): array
    {
        $states = new StateStack;
        $input = new ScriptedMenuInput;
        $counter = new OverlayUpdateCounter;
        $clock = new ManualClock;

        $states->push(new PlayState(
            static function (float $dt) use ($counter): void {
                $counter->updates++;
            }
        ));

        $states->flush();

        $loop = new GameLoop(
            $clock,
            static function (float $dt) use ($states): void {
                $states->update($dt);
            },
            static function (float $alpha): void {},
        );

        $controller = new OverlayUiController(
            $states,
            $input,
            new StateLoopSynchronizer($states, $loop),
        );

        return [$states, $input, $clock, $loop, $controller, $counter];
    }
}

final class OverlayUpdateCounter
{
    public int $updates = 0;
}

final class OverlayTestProvider implements PlaySnapshotProviderInterface
{
    public function snapshot(): PlaySnapshot
    {
        $zero = Vector2::zero();

        return new PlaySnapshot(
            new PlayWorldSnapshot($zero, $zero),
            new PlayHudSnapshot(
                20.0,
                20.0,
                0,
                10,
                1,
                0.0,
            ),
        );
    }
}

final class OverlayTestRenderer implements RendererInterface
{
    /** @var list<FrameSnapshot> */
    public array $frames = [];

    public function render(FrameSnapshot $frame, float $alpha): void
    {
        $this->frames[] = $frame;
    }
}
