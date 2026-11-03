<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Infrastructure\Results;

use Jogo\Infrastructure\Results\FinalRunView;
use Jogo\Infrastructure\Results\RankingPage;
use Jogo\Infrastructure\Results\RankingRow;
use Jogo\Infrastructure\Results\ResultsGatewayInterface;
use Jogo\Infrastructure\Results\ResultsInputEvent;
use Jogo\Infrastructure\Results\ResultsScreenContext;
use Jogo\Infrastructure\Results\ResultsScreenRendererInterface;
use Jogo\Infrastructure\Results\SaveOutcome;
use Jogo\Infrastructure\Results\ScriptedResultsInput;
use Jogo\Infrastructure\State\GameOverState;
use Jogo\Infrastructure\State\PlayState;
use Jogo\Infrastructure\State\RankingState;
use Jogo\Infrastructure\State\StateStack;
use Jogo\Infrastructure\State\VictoryState;
use Jogo\Tools\DemoResultsSqlite;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname(__DIR__, 4).'/tools/demo_results_sqlite.php';

final class ResultsFlowTest extends TestCase
{
    public function test_name_is_required(): void
    {
        [$states, $input, $gateway, $end] = $this->scene();

        $input->enqueue(new ResultsInputEvent(confirm: true));
        $states->update(0.0);

        self::assertSame(0, $gateway->saves);
        self::assertFalse($end->isSaved());
        self::assertStringContainsString(
            'Informe um nome',
            $end->message()
        );
    }

    public function test_successful_save_is_not_repeated(): void
    {
        [$states, $input, $gateway, $end] = $this->scene();

        $input->enqueue(new ResultsInputEvent(text: 'Ana'));
        $states->update(0.0);

        $input->enqueue(new ResultsInputEvent(confirm: true));
        $states->update(0.0);

        $input->enqueue(new ResultsInputEvent(confirm: true));
        $states->update(0.0);

        self::assertSame(1, $gateway->saves);
        self::assertTrue($end->isSaved());
        self::assertSame('Ana', $gateway->lastName);
    }

    public function test_failed_save_can_be_retried(): void
    {
        [$states, $input, $gateway, $end] = $this->scene();
        $gateway->shouldFail = true;

        $input->enqueue(new ResultsInputEvent(text: 'Ana'));
        $states->update(0.0);

        $input->enqueue(new ResultsInputEvent(confirm: true));
        $states->update(0.0);

        self::assertFalse($end->isSaved());

        $gateway->shouldFail = false;

        $input->enqueue(new ResultsInputEvent(confirm: true));
        $states->update(0.0);

        self::assertSame(2, $gateway->saves);
        self::assertTrue($end->isSaved());
    }

    public function test_exception_does_not_close_screen(): void
    {
        [$states, $input, $gateway, $end] = $this->scene();
        $gateway->throws = true;

        $input->enqueue(new ResultsInputEvent(text: 'Ana'));
        $states->update(0.0);

        $input->enqueue(new ResultsInputEvent(confirm: true));
        $states->update(0.0);

        self::assertInstanceOf(GameOverState::class, $states->top());
        self::assertFalse($end->isSaved());
        self::assertStringContainsString('Falha', $end->message());
    }

    public function test_ranking_opens_and_returns_to_end_screen(): void
    {
        [$states, $input, $gateway, $end] = $this->scene();

        $input->enqueue(new ResultsInputEvent(horizontal: 1));
        $states->update(0.0);

        $input->enqueue(new ResultsInputEvent(confirm: true));
        $states->update(0.0);

        self::assertInstanceOf(RankingState::class, $states->top());

        $input->enqueue(new ResultsInputEvent(back: true));
        $states->update(0.0);

        self::assertSame($end, $states->top());
    }

    public function test_ranking_pages_use_gateway_order(): void
    {
        [$states, $input, $gateway, $end] = $this->scene();

        $gateway->hasNext = true;

        $input->enqueue(new ResultsInputEvent(horizontal: 1));
        $states->update(0.0);

        $input->enqueue(new ResultsInputEvent(confirm: true));
        $states->update(0.0);

        $input->enqueue(new ResultsInputEvent(horizontal: 1));
        $states->update(0.0);

        self::assertSame([0, 6], $gateway->offsets);
    }

    public function test_game_over_and_victory_have_distinct_names(): void
    {
        self::assertSame('game_over', (new GameOverState)->name());
        self::assertSame('victory', (new VictoryState)->name());
    }

    public function test_final_screen_blocks_gameplay(): void
    {
        $states = new StateStack;
        $states->push(new PlayState);
        $states->push(new GameOverState);
        $states->flush();

        self::assertTrue($states->shouldPauseGameplay());

        $states->pop();
        $states->flush();

        self::assertFalse($states->shouldPauseGameplay());
    }

    public function test_demo_sqlite_persists_between_connections(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'd10-test-');

        self::assertIsString($path);

        try {
            $first = new DemoResultsSqlite($path);
            $run = $this->demoRun();

            self::assertTrue($first->save($run, 'Ana')->success);
            self::assertTrue($first->save($run, 'Ana')->success);

            $second = new DemoResultsSqlite($path);
            $page = $second->page(0, 6);

            self::assertCount(1, $page->rows);
            self::assertSame('Ana', $page->rows[0]->name);
            self::assertSame(1250, $page->rows[0]->score);
            self::assertFalse($page->hasNext);
        } finally {
            @unlink($path);
        }
    }

    public function test_demo_sqlite_rejects_invalid_name(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'd10-test-');

        self::assertIsString($path);

        try {
            $gateway = new DemoResultsSqlite($path);

            self::assertFalse(
                $gateway->save($this->demoRun(), '   ')->success
            );
        } finally {
            @unlink($path);
        }
    }

    private function demoRun(): FinalRunView
    {
        return new FinalRunView(
            'run-1',
            'Steve',
            4,
            20,
            120.0,
            1250,
            false
        );
    }

    /**
     * @return array{
     *     StateStack,
     *     ScriptedResultsInput,
     *     FakeResultsGateway,
     *     GameOverState
     * }
     */
    private function scene(): array
    {
        $input = new ScriptedResultsInput;
        $gateway = new FakeResultsGateway;
        $renderer = new FakeResultsRenderer;

        $context = new ResultsScreenContext(
            $input,
            $renderer,
            $gateway,
            static function (): void {},
            static function (): void {}
        );

        $state = new GameOverState($context, $this->demoRun());
        $states = new StateStack;

        $states->push($state);
        $states->flush();

        return [$states, $input, $gateway, $state];
    }
}

final class FakeResultsGateway implements ResultsGatewayInterface
{
    public int $saves = 0;

    public string $lastName = '';

    public bool $shouldFail = false;

    public bool $throws = false;

    public bool $hasNext = false;

    /** @var list<int> */
    public array $offsets = [];

    public function save(
        FinalRunView $run,
        string $name
    ): SaveOutcome {
        $this->saves++;
        $this->lastName = $name;

        if ($this->throws) {
            throw new RuntimeException('Teste de erro.');
        }

        return new SaveOutcome(
            ! $this->shouldFail,
            $this->shouldFail ? 'Falhou' : 'Salvo'
        );
    }

    public function page(int $offset, int $limit): RankingPage
    {
        $this->offsets[] = $offset;

        return new RankingPage(
            [
                new RankingRow('Ana', 'Steve', 1250, 4, 20, 120),
            ],
            $this->hasNext && $offset === 0
        );
    }
}

final class FakeResultsRenderer implements ResultsScreenRendererInterface
{
    public function drawFinish(
        FinalRunView $run,
        string $name,
        string $message,
        bool $saved,
        int $selected,
    ): void {}

    public function drawRanking(
        RankingPage $page,
        int $offset,
        string $message,
    ): void {}
}
