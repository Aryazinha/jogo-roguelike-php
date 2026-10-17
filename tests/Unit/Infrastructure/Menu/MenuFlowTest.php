<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Infrastructure\Menu;

use Jogo\Infrastructure\Menu\CharacterChoice;
use Jogo\Infrastructure\Menu\CharacterChoiceCatalog;
use Jogo\Infrastructure\Menu\MenuInputEvent;
use Jogo\Infrastructure\Menu\MenuScreenContext;
use Jogo\Infrastructure\Menu\MenuScreenRendererInterface;
use Jogo\Infrastructure\Menu\ScriptedMenuInput;
use Jogo\Infrastructure\State\CharacterSelectState;
use Jogo\Infrastructure\State\InstructionsState;
use Jogo\Infrastructure\State\MenuState;
use Jogo\Infrastructure\State\RankingState;
use Jogo\Infrastructure\State\StateStack;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MenuFlowTest extends TestCase
{
    public function test_character_catalog_reads_project_config(): void
    {
        $path = dirname(__DIR__, 4).'/config/characters.json';

        $characters = CharacterChoiceCatalog::fromFile($path);

        self::assertSame(['steve', 'alex'], array_map(
            static fn (CharacterChoice $choice): string => $choice->id,
            $characters
        ));

        self::assertSame(20, $characters[0]->health);
        self::assertSame(16, $characters[1]->health);

        self::assertSame('stone_sword', $characters[0]->startingWeapon);
        self::assertSame('bow', $characters[1]->startingWeapon);
    }

    public function test_missing_config_has_clear_error(): void
    {
        $this->expectException(RuntimeException::class);

        CharacterChoiceCatalog::fromFile(
            __DIR__.'/missing-characters.json'
        );
    }

    public function test_enter_opens_character_selection(): void
    {
        [$states, $input] = $this->setupMenu();

        $input->enqueue(new MenuInputEvent(confirm: true));
        $states->update(0.016);

        self::assertInstanceOf(
            CharacterSelectState::class,
            $states->top()
        );
    }

    public function test_navigation_reaches_ranking(): void
    {
        [$states, $input] = $this->setupMenu();

        $input->enqueue(new MenuInputEvent(vertical: 1));
        $states->update(0.016);

        $input->enqueue(new MenuInputEvent(confirm: true));
        $states->update(0.016);

        self::assertInstanceOf(RankingState::class, $states->top());

        $input->enqueue(new MenuInputEvent(back: true));
        $states->update(0.016);

        self::assertInstanceOf(MenuState::class, $states->top());
    }

    public function test_navigation_reaches_instructions(): void
    {
        [$states, $input] = $this->setupMenu();

        $input->enqueue(new MenuInputEvent(vertical: 1));
        $states->update(0.016);

        $input->enqueue(new MenuInputEvent(vertical: 1));
        $states->update(0.016);

        $input->enqueue(new MenuInputEvent(confirm: true));
        $states->update(0.016);

        self::assertInstanceOf(
            InstructionsState::class,
            $states->top()
        );
    }

    public function test_exit_callback_is_called(): void
    {
        $exits = 0;

        [$states, $input] = $this->setupMenu(
            null,
            static function () use (&$exits): void {
                $exits++;
            }
        );

        $input->enqueue(new MenuInputEvent(back: true));
        $states->update(0.016);

        self::assertSame(1, $exits);
    }

    public function test_selection_returns_only_character_id(): void
    {
        $selected = null;

        [$states, $input] = $this->setupMenu(
            static function (string $id) use (&$selected): void {
                $selected = $id;
            }
        );

        $input->enqueue(new MenuInputEvent(confirm: true));
        $states->update(0.016);

        $input->enqueue(new MenuInputEvent(horizontal: 1));
        $states->update(0.016);

        $input->enqueue(new MenuInputEvent(confirm: true));
        $states->update(0.016);

        self::assertSame('alex', $selected);
    }

    public function test_mouse_click_selects_character(): void
    {
        $selected = null;

        [$states, $input] = $this->setupMenu(
            static function (string $id) use (&$selected): void {
                $selected = $id;
            }
        );

        $input->enqueue(
            new MenuInputEvent(
                mouseX: 150,
                mouseY: 155,
                click: true
            )
        );

        $states->update(0.016);

        self::assertInstanceOf(
            CharacterSelectState::class,
            $states->top()
        );

        $input->enqueue(
            new MenuInputEvent(
                mouseX: 150,
                mouseY: 210,
                click: true
            )
        );

        $states->update(0.016);

        self::assertSame('alex', $selected);
    }

    public function test_rendering_uses_selection_and_details(): void
    {
        $renderer = new FakeMenuRenderer;

        [$states, $input] = $this->setupMenu(
            null,
            null,
            $renderer
        );

        $input->enqueue(new MenuInputEvent(confirm: true));
        $states->update(0.016);
        $states->render(0.0);

        self::assertSame(
            'ESCOLHA SEU PERSONAGEM',
            $renderer->lastTitle
        );

        self::assertSame(['Steve', 'Alex'], $renderer->lastOptions);
        self::assertNotEmpty($renderer->lastDetails);
    }

    /**
     * @param  (callable(string): void)|null  $onSelected
     * @param  (callable(): void)|null  $onExit
     * @return array{StateStack, ScriptedMenuInput}
     */
    private function setupMenu(
        ?callable $onSelected = null,
        ?callable $onExit = null,
        ?FakeMenuRenderer $renderer = null,
    ): array {
        $input = new ScriptedMenuInput;
        $renderer ??= new FakeMenuRenderer;

        $characters = [
            new CharacterChoice(
                'steve',
                'Steve',
                20,
                30,
                'stone_sword',
                'Encantamento de dano'
            ),
            new CharacterChoice(
                'alex',
                'Alex',
                16,
                15,
                'bow',
                'Reducao de cooldown'
            ),
        ];

        $ui = new MenuScreenContext(
            $input,
            $renderer,
            $characters,
            $onSelected ?? static function (string $id): void {},
            $onExit ?? static function (): void {},
        );

        $states = new StateStack;
        $states->push(new MenuState($ui));
        $states->flush();

        return [$states, $input];
    }
}

final class FakeMenuRenderer implements MenuScreenRendererInterface
{
    public string $lastTitle = '';

    /** @var list<string> */
    public array $lastOptions = [];

    /** @var list<string> */
    public array $lastDetails = [];

    /**
     * @param  list<string>  $options
     * @param  list<string>  $details
     */
    public function draw(
        string $title,
        array $options,
        int $selected,
        array $details = [],
    ): void {
        $this->lastTitle = $title;
        $this->lastOptions = $options;
        $this->lastDetails = $details;
    }
}
