<?php

declare(strict_types=1);

namespace Jogo\Domain\Wave;

use InvalidArgumentException;
use Jogo\Domain\Enemy\EnemyArchetype;
use Jogo\Domain\Enemy\EnemyCatalog;
use Jogo\Domain\Enemy\EnemyFactory;
use Jogo\Domain\Random\RandomIndex;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\SpawnPointPicker;

/**
 * Gera inimigos ao longo da partida seguindo waves.json (RF05, RF28, RF29).
 *
 * A cada intervalo da faixa atual, "deve" perSpawn inimigos. Eles nascem
 * longe do jogador, com tipo sorteado entre os da faixa que o nível do
 * jogador já libera, e mais fortes conforme o nível. Com 300 vivos, o que
 * falta fica devendo e entra quando abrir espaço: nada é perdido e o teto
 * nunca é passado.
 */
final class WaveDirector
{
    private float $accumulator = 0.0;

    /** Inimigos devidos que ainda não couberam no teto. */
    private int $owed = 0;

    private int $bandIndex = 0;

    public function __construct(
        private readonly WaveSettings $settings,
        private readonly EnemyCatalog $catalog,
        private readonly EnemyFactory $factory,
        private readonly GameWorld $world,
        private readonly SpawnPointPicker $spawns,
        private readonly RandomIndex $random,
    ) {}

    /**
     * Os dois multiplicadores são a porta dos eventos aleatórios (C9): a Horda
     * triplica a quantidade e corta o intervalo pela metade.
     *
     * @return int Quantos inimigos nasceram neste passo.
     */
    public function update(
        float $deltaSeconds,
        float $elapsedSeconds,
        int $playerLevel,
        float $countMultiplier = 1.0,
        float $intervalMultiplier = 1.0,
    ): int {
        CombatValidation::nonNegative($deltaSeconds, 'deltaSeconds');
        CombatValidation::nonNegative($elapsedSeconds, 'elapsedSeconds');
        CombatValidation::positive($countMultiplier, 'countMultiplier');
        CombatValidation::positive($intervalMultiplier, 'intervalMultiplier');
        if ($playerLevel < 1) {
            throw new InvalidArgumentException('playerLevel deve ser maior ou igual a um.');
        }
        if ($deltaSeconds === 0.0) {
            return 0;
        }

        $this->bandIndex = $this->settings->bandIndexAt($elapsedSeconds);
        $band = $this->settings->bands[$this->bandIndex];
        $interval = $band->intervalSeconds * $intervalMultiplier;

        $this->accumulator += $deltaSeconds;
        // Tolerância para o acúmulo de 1/60 em float não atrasar o spawn um passo.
        while ($this->accumulator >= $interval - 1e-9) {
            $this->accumulator -= $interval;
            $this->owed += max(1, (int) round($band->perSpawn * $countMultiplier));
        }
        // A dívida nunca passa do teto: com o mundo cheio por muito tempo, não vira uma enxurrada depois.
        $this->owed = min($this->owed, $this->settings->maxAlive);

        $free = $this->settings->maxAlive - $this->world->aliveEnemyCount();
        $types = $this->eligibleTypes($band, $playerLevel);
        // Sem nenhum tipo liberado para o nível, a dívida espera o jogador subir.
        $count = $types === [] ? 0 : max(0, min($this->owed, $free));
        for ($i = 0; $i < $count; $i++) {
            $this->spawnOne($types, $playerLevel);
        }
        $this->owed -= $count;

        return $count;
    }

    /** Índice da faixa de waves.json em vigor, para a Frente D mostrar a onda. */
    public function currentBandIndex(): int
    {
        return $this->bandIndex;
    }

    public function owed(): int
    {
        return $this->owed;
    }

    /** @param non-empty-list<string> $types */
    private function spawnOne(array $types, int $level): void
    {
        $typeId = $types[$this->random->nextIndex(count($types))];
        $position = $this->spawns->pick($this->world->player()->position());

        $this->world->addEnemy($this->factory->create($typeId, $position,
            $this->settings->healthMultiplier($level), $this->settings->damageMultiplier($level)));
    }

    /**
     * Tipos da faixa que o nível já libera. Se nenhum serve (jogador de nível
     * baixo numa faixa de tipos fortes), usa os tipos que o nível libera em
     * qualquer faixa, para a partida não ficar vazia.
     *
     * @return list<string>
     */
    private function eligibleTypes(WaveBand $band, int $level): array
    {
        $types = array_values(array_filter($band->typeIds,
            fn (string $id) => $this->catalog->get($id)->canSpawnAtLevel($level)));
        if ($types === []) {
            $types = array_map(static fn (EnemyArchetype $a) => $a->id, $this->catalog->spawnableAtLevel($level));
        }

        return $types;
    }
}
