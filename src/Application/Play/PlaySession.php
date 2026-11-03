<?php

declare(strict_types=1);

namespace Jogo\Application\Play;

use Jogo\Application\Content\ContentSession;
use Jogo\Application\Progression\ProgressionSession;
use Jogo\Core\Config\MapConfigLoader;
use Jogo\Domain\Ai\SpatialWorldView;
use Jogo\Domain\Event\EventBusInterface;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\Scenery\DropChance;
use Jogo\Domain\System\MovementSystem;
use Jogo\Domain\System\WeaponAttackSystem;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Facing;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Feedback\ChestOpenedNotice;

/**
 * Uma partida jogável: junta as armas (A), a progressão (B) e o conteúdo (C)
 * num único passo fixo, sem janela. O jogo.php (D) só lê o teclado, chama
 * step() e desenha.
 *
 * Ordem do passo: o jogador anda; a C roda ondas, boss e inimigos; no meio
 * do passo da C as armas atacam (inimigos pela A, cenário pela C); depois os
 * itens pegos e os baús abertos viram efeito na B; por fim a B atualiza
 * regeneração, bônus e tempo.
 *
 * Vitória (RF09): derrotar o Warden. A partida continua até o jogador abrir
 * um baú, para a recompensa do boss (RF26) não se perder, ou até
 * VICTORY_GRACE_SECONDS depois da derrota do boss.
 */
final class PlaySession
{
    public const VICTORY_GRACE_SECONDS = 20.0;

    private const PLAYER_RADIUS = 18.0;

    // Bases de movimento e coleta usadas pelo jogo.php antes da integração (provisórias).
    private const PLAYER_SPEED = 160.0;

    private const PICKUP_RADIUS = 35.0;

    private float $elapsed = 0.0;

    private ?float $wonAt = null;

    private function __construct(
        public readonly ProgressionSession $progression,
        public readonly ContentSession $content,
        public readonly WeaponAttackSystem $combat,
        public readonly Facing $facing,
        private readonly PlayerWeaponPort $weapons,
        private readonly SpatialWorldView $view,
        private readonly MovementSystem $movement,
        private readonly EventBusInterface $events,
    ) {}

    public static function start(string $root, string $characterId, int $seed, EventBusInterface $events): self
    {
        $layout = (new MapConfigLoader)->load($root.'/config/map.json');
        $port = new PlayerWeaponPort;
        $progression = ProgressionSession::start($root.'/config', $characterId, 'player-'.$seed, $layout->playerStart(),
            self::PLAYER_RADIUS, self::PLAYER_SPEED, self::PICKUP_RADIUS, $seed, $events, $port, $port);
        $content = ContentSession::fromProject($root, $progression->player, $seed, $events);
        $facing = new Facing(new Vector2(0.0, -1.0));
        $view = $content->world->createView();
        $combat = new WeaponAttackSystem($progression->player->id(), new PlayerAttackContext($progression->player, $facing),
            $view, $view, $content->world, $content->damage, new SeededRandomIndex($seed * 16 + 8));
        $port->connect($combat);

        return new self($progression, $content, $combat, $facing, $port, $view,
            new MovementSystem($layout->bounds()), $events);
    }

    public function step(float $deltaSeconds, InputState $input): void
    {
        CombatValidation::nonNegative($deltaSeconds, 'deltaSeconds');
        $run = $this->progression->run;
        if ($run->isEnded()) {
            return;
        }
        $this->elapsed += $deltaSeconds;
        $player = $this->progression->player;
        $this->movement->update($player, $this->facing, $input, $player->movementSpeed(), $deltaSeconds, true);

        $this->content->step($deltaSeconds, $this->elapsed, $this->progression->experience->experience()->level(),
            function () use ($deltaSeconds): void {
                // A Chuva de XP vale para as mortes deste passo.
                $this->progression->experience->setXpMultiplier($this->content->events->xpMultiplier());
                $this->view->refresh();
                $this->combat->update($this->progression->player->inventory(), $deltaSeconds, $this->elapsed);
                foreach ($this->weapons->takeSwings() as [$range, $damage]) {
                    $this->content->breakables->damageArea($this->progression->player->position(), $range, $damage);
                }
            });

        $openedChest = $this->applyRewards();
        $this->progression->update($deltaSeconds);
        $this->checkVictory($openedChest);
    }

    public function isOver(): bool
    {
        return $this->progression->run->isEnded();
    }

    /** Segundos de partida, inclusive depois da vitória enquanto o baú não é aberto. */
    public function elapsedSeconds(): float
    {
        return $this->elapsed;
    }

    /** Leva os itens pegos e os baús abertos (C) para os serviços da B. Devolve se algum baú abriu. */
    private function applyRewards(): bool
    {
        $pickups = $this->progression->pickups;
        foreach ($this->content->breakables->takePicked() as $item) {
            if ($item->itemId === DropChance::STEAK) {
                $pickups->collectHealing($item->pickupId, $this->progression->steak, $this->elapsed);
            } elseif ($item->bonus !== null) {
                $pickups->collectBonus($item->pickupId, $item->bonus->id, $this->elapsed);
            } elseif ($item->xp !== null) {
                $pickups->collectXp($item->pickupId, $item->itemId, $item->xp, $this->elapsed);
            } elseif ($item->weaponUpgrades !== null) {
                $this->progression->chests->grantWeaponUpgrades($item->pickupId, $item->weaponUpgrades);
            }
        }

        $opened = false;
        foreach ($this->content->chests->takeOpened() as $reward) {
            $opened = true;
            $this->progression->chests->grantWeaponUpgrades($reward->chestId, $reward->quantity);
            // O baú abre ao encostar, então o jogador está em cima dele.
            $this->events->publish(new ChestOpenedNotice($reward->chestId, $this->progression->player->position(),
                $reward->openedAtSeconds));
        }

        return $opened;
    }

    private function checkVictory(bool $openedChest): void
    {
        $run = $this->progression->run;
        if (! $run->hasWon() || $run->isEnded()) {
            return;
        }
        $this->wonAt ??= $this->elapsed;
        if ($openedChest || $this->elapsed - $this->wonAt >= self::VICTORY_GRACE_SECONDS) {
            $run->end();
        }
    }
}
