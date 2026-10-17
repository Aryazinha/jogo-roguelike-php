<?php

declare(strict_types=1);

use Jogo\Core\GameLoop;
use Jogo\Infrastructure\State\CharacterSelectState;
use Jogo\Infrastructure\State\GameOverState;
use Jogo\Infrastructure\State\LevelUpState;
use Jogo\Infrastructure\State\MenuState;
use Jogo\Infrastructure\State\PauseState;
use Jogo\Infrastructure\State\PlayState;
use Jogo\Infrastructure\State\StateLoopSynchronizer;
use Jogo\Infrastructure\State\StateStack;
use Jogo\Infrastructure\Time\MonotonicClock;

require dirname(__DIR__).'/vendor/autoload.php';

$states = new StateStack;
$updates = 0;

$clock = new MonotonicClock;

$loop = new GameLoop(
    $clock,
    static function (float $dt) use ($states): void {
        $states->update($dt);
    },
    static function (float $alpha) use ($states): void {
        $states->render($alpha);
    }
);

$sync = new StateLoopSynchronizer($states, $loop);

$show = static function (string $action) use ($states, $sync, $loop): void {
    $sync->synchronize();

    echo $action
        .' | Tela: '.($states->top()?->name() ?? 'nenhuma')
        .' | Pilha: '.$states->count()
        .' | Tempo pausado: '.($loop->isPaused() ? 'sim' : 'nao')
        .PHP_EOL;
};

$states->push(new MenuState);
$show('Iniciar');

$states->replace(new CharacterSelectState);
$show('Selecionar personagem');

$states->replace(
    new PlayState(
        static function (float $dt) use (&$updates): void {
            $updates++;
        }
    )
);

$show('Comecar partida');

$states->push(new PauseState);
$show('Abrir pausa');

$loop->tick();

$states->pop();
$show('Retomar partida');

$states->push(new LevelUpState);
$show('Abrir level up');

$states->pop();
$show('Continuar apos level up');

$states->replace(new GameOverState);
$show('Fim da partida');

echo 'Atualizacoes logicas demonstrativas: '.$updates.PHP_EOL;
echo "D5: smoke test concluido.\n";
