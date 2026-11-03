<?php

declare(strict_types=1);

namespace Jogo\Application\Content;

use Jogo\Core\Config\BossConfigLoader;
use Jogo\Core\Config\ChestConfigLoader;
use Jogo\Core\Config\ContactConfigLoader;
use Jogo\Core\Config\EnemyConfigLoader;
use Jogo\Core\Config\EventConfigLoader;
use Jogo\Core\Config\MapConfigLoader;
use Jogo\Core\Config\SceneryConfigLoader;
use Jogo\Core\Config\SteeringConfigLoader;
use Jogo\Core\Config\WaveConfigLoader;
use Jogo\Domain\Ai\Behavior\KeepDistanceBehavior;
use Jogo\Domain\Ai\Behavior\SeparationBehavior;
use Jogo\Domain\Ai\Behavior\WeightedBehavior;
use Jogo\Domain\Enemy\EnemyFactory;
use Jogo\Domain\Enemy\SplitOnDeath;
use Jogo\Domain\Entity\Damageable;
use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Event\EventBusInterface;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\RandomEvent\EventDirector;
use Jogo\Domain\Scenery\SceneryPlacer;
use Jogo\Domain\System\BossSystem;
use Jogo\Domain\System\BreakableSystem;
use Jogo\Domain\System\ChestSystem;
use Jogo\Domain\System\DamageSystem;
use Jogo\Domain\System\EnemyAttackSystem;
use Jogo\Domain\System\EnemyProjectileSystem;
use Jogo\Domain\System\EnemySteeringSystem;
use Jogo\Domain\System\MovementSystem;
use Jogo\Domain\System\RangedAttackSystem;
use Jogo\Domain\System\SceneryBlocker;
use Jogo\Domain\Wave\WaveDirector;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\SpawnPointPicker;

/**
 * Monta todo o conteúdo da Frente C a partir de config/ e executa um passo
 * da partida na ordem certa (C11).
 *
 * É o ponto de encontro com as outras frentes: recebe o jogador (B) e o
 * barramento de eventos (A), e expõe os sistemas para a Application ligar as
 * armas (A), a progressão (B) e o desenho (D). Não abre janela nem lê teclado.
 *
 * Cada sistema que sorteia ganha o próprio gerador, derivado da seed. Assim,
 * mudar um sistema não muda os sorteios dos outros, e a mesma seed repete a
 * partida inteira.
 */
final class ContentSession
{
    private function __construct(
        public readonly GameWorld $world,
        public readonly DamageSystem $damage,
        public readonly WaveDirector $waves,
        public readonly EventDirector $events,
        public readonly BossSystem $boss,
        public readonly EnemySteeringSystem $steering,
        public readonly EnemyAttackSystem $attacks,
        public readonly RangedAttackSystem $ranged,
        public readonly EnemyProjectileSystem $projectiles,
        public readonly ChestSystem $chests,
        public readonly BreakableSystem $breakables,
        public readonly SceneryBlocker $blocker,
        /** @var list<KeepDistanceBehavior> */
        private readonly array $rangedBehaviors,
    ) {}

    public static function fromProject(string $root, Entity&Damageable $player, int $seed, EventBusInterface $bus): self
    {
        $config = $root.'/config';
        $catalog = (new EnemyConfigLoader)->load("{$config}/enemies.json");
        $layout = (new MapConfigLoader)->load("{$config}/map.json");
        $steeringSettings = (new SteeringConfigLoader)->load("{$config}/enemies.json");
        $chestSettings = (new ChestConfigLoader)->load("{$config}/chests.json");
        $scenery = (new SceneryConfigLoader)->load("{$config}/scenery.json");
        $random = static fn (int $stream) => new SeededRandomIndex($seed * 16 + $stream);

        $waveSettings = (new WaveConfigLoader($catalog))->load("{$config}/waves.json");
        $world = new GameWorld($player, $layout);
        // Uma vaga fica reservada para o Warden, que nasce mesmo com o mundo cheio: assim o total nunca passa de 300.
        $world->setEnemyCap($waveSettings->maxAlive - 1);
        (new SceneryPlacer($scenery, $random(1)))->place($world);
        $factory = new EnemyFactory($catalog);
        $movement = new MovementSystem($layout->bounds());
        $damage = new DamageSystem($bus);

        $behaviors = ['perseguir' => $steeringSettings->chase()];
        $rangedBehaviors = [];
        foreach ($catalog->all() as $archetype) {
            if ($archetype->ranged !== null) {
                $keep = new KeepDistanceBehavior($archetype->ranged);
                $rangedBehaviors[] = $keep;
                $behaviors[$archetype->id] = new WeightedBehavior([[$keep, $steeringSettings->seekWeight],
                    [new SeparationBehavior($steeringSettings->separationRadius), $steeringSettings->separationWeight]]);
            }
        }

        $boss = $catalog->boss();
        $attacks = new EnemyAttackSystem($damage, (new ContactConfigLoader)->load("{$config}/enemies.json"));
        $session = new self(
            world: $world,
            damage: $damage,
            waves: new WaveDirector($waveSettings, $catalog, $factory,
                $world, new SpawnPointPicker($layout, $random(2)), $random(3)),
            events: new EventDirector((new EventConfigLoader)->load("{$config}/events.json"), $random(4)),
            boss: new BossSystem($world, $factory, (new BossConfigLoader($catalog))->load("{$config}/enemies.json"),
                new SpawnPointPicker($layout, $random(5)), $movement, $boss->id, $boss->spawnAtSeconds ?? 600.0),
            steering: new EnemySteeringSystem($movement, $behaviors, $steeringSettings->separationRadius),
            attacks: $attacks,
            ranged: new RangedAttackSystem,
            projectiles: new EnemyProjectileSystem($attacks),
            chests: new ChestSystem($world, $chestSettings, $random(6)),
            breakables: new BreakableSystem($world, $scenery, $chestSettings->healing, $random(7)),
            blocker: new SceneryBlocker,
            rangedBehaviors: $rangedBehaviors,
        );
        (new SplitOnDeath($world, $factory))->subscribe($bus);
        $session->boss->subscribe($bus);
        $session->chests->subscribe($bus);

        return $session;
    }

    /**
     * Um passo fixo da parte da C. As armas do jogador (A) devem rodar entre o
     * movimento e o flush, no mesmo passo, usando $this->world como resolver.
     */
    public function step(float $deltaSeconds, float $elapsedSeconds, int $playerLevel): void
    {
        $this->events->update($elapsedSeconds);
        $this->chests->setChanceMultiplier($this->events->chestChanceMultiplier());
        $this->waves->update($deltaSeconds, $elapsedSeconds, $playerLevel,
            $this->events->spawnCountMultiplier(), $this->events->spawnIntervalMultiplier());
        $this->boss->update($deltaSeconds, $elapsedSeconds);
        $this->steering->update($this->world, $deltaSeconds);
        $this->blocker->update($this->world);
        $this->ranged->update($this->world, $deltaSeconds);
        $this->projectiles->update($this->world, $deltaSeconds, $elapsedSeconds);
        $this->attacks->update($this->world, $elapsedSeconds);
        $this->chests->update($elapsedSeconds);
        $this->breakables->update($elapsedSeconds);
        $this->settle();
    }

    /** Ponto seguro: aplica a fila do mundo e esquece quem saiu. */
    public function settle(): void
    {
        foreach ($this->world->flush() as $removed) {
            $this->ranged->forget($removed->id());
            foreach ($this->rangedBehaviors as $behavior) {
                $behavior->forget($removed->id());
            }
        }
    }

    /** Resumo do estado lógico, para conferir que a mesma seed repete a partida. */
    public function checksum(): string
    {
        $state = [];
        foreach ($this->world->snapshots() as $view) {
            $state[] = sprintf('%s:%s:%.6f:%.6f', $view->id, $view->kind, $view->position->x(), $view->position->y());
        }

        return hash('sha256', implode('|', $state));
    }
}
