<?php

declare(strict_types=1);

namespace Jogo\Application\Progression;

use Jogo\Core\Config\CharacterConfigLoader;
use Jogo\Core\Config\EnemyXpConfigLoader;
use Jogo\Core\Config\EvolutionConfigLoader;
use Jogo\Core\Config\PassiveConfigLoader;
use Jogo\Core\Config\RewardConfigLoader;
use Jogo\Core\Config\UpgradeConfigLoader;
use Jogo\Core\Config\WeaponConfigLoader;
use Jogo\Domain\Character\Player;
use Jogo\Domain\Character\PlayerFactory;
use Jogo\Domain\Event\EventBusInterface;
use Jogo\Domain\Progression\CharacterPassiveSystem;
use Jogo\Domain\Progression\EvolutionService;
use Jogo\Domain\Progression\Experience;
use Jogo\Domain\Progression\ExperienceSystem;
use Jogo\Domain\Progression\LevelUp\LevelUpOfferGenerator;
use Jogo\Domain\Progression\LevelUp\LevelUpService;
use Jogo\Domain\Progression\PassiveInventory;
use Jogo\Domain\Progression\ProgressionEffects;
use Jogo\Domain\Progression\Reward\ChestRewardService;
use Jogo\Domain\Progression\Reward\HealingItem;
use Jogo\Domain\Progression\Reward\PickupService;
use Jogo\Domain\Progression\Reward\TemporaryBonuses;
use Jogo\Domain\Progression\WeaponLevels;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\Run\RunState;
use Jogo\Domain\Score\PlayerName;
use Jogo\Domain\Score\ScoreCalculator;
use Jogo\Domain\Score\ScoreEntry;
use Jogo\Domain\Score\ScoreRepositoryInterface;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\Upgrade\WeaponProgressionService;
use Jogo\Domain\Weapon\WeaponDefinition;
use Jogo\Domain\Weapon\WeaponMutationGuard;

/**
 * Composição da Frente B para uma partida: lê os config/*.json, cria o jogador e liga todos
 * os assinantes no EventBus da partida. Application chama `update()` a cada passo fixo e,
 * enquanto `levelUps->hasPendingChoice()`, mantém o GameLoop pausado para a tela da D.
 *
 * Cada subsistema sorteia com sua própria seed derivada da seed da partida, então XP, opções
 * de level up e baús são reproduzíveis de forma independente.
 */
final class ProgressionSession
{
    private function __construct(
        public readonly Player $player,
        public readonly ExperienceSystem $experience,
        public readonly LevelUpService $levelUps,
        public readonly PassiveInventory $passives,
        public readonly WeaponLevels $weaponLevels,
        public readonly EvolutionService $evolutions,
        public readonly ProgressionEffects $effects,
        public readonly CharacterPassiveSystem $characterPassive,
        public readonly TemporaryBonuses $bonuses,
        public readonly PickupService $pickups,
        public readonly ChestRewardService $chests,
        public readonly HealingItem $steak,
        public readonly RunState $run,
    ) {}

    /** @param callable(WeaponDefinition): bool $execute Executor de ataques da Frente A. */
    public static function start(
        string $configDir,
        string $characterId,
        string $playerId,
        Vector2 $position,
        float $collisionRadius,
        int $seed,
        EventBusInterface $events,
        callable $execute,
        WeaponMutationGuard $combatGuard,
    ): self {
        $weapons = (new WeaponConfigLoader)->load($configDir.'/weapons.json');
        $characters = (new CharacterConfigLoader($weapons))->load($configDir.'/characters.json');
        $passiveCatalog = (new PassiveConfigLoader)->load($configDir.'/passives.json');
        $upgrades = (new UpgradeConfigLoader($weapons))->load($configDir.'/upgrades.json');
        $evolutionCatalog = (new EvolutionConfigLoader($weapons, $passiveCatalog->ids()))->load($configDir.'/evolutions.json');
        $xpTable = (new EnemyXpConfigLoader)->load($configDir.'/enemies.json');
        $rewards = new RewardConfigLoader;

        $player = (new PlayerFactory($characters, $weapons))->create($characterId, $playerId, $position, $collisionRadius, $execute);
        $inventory = $player->inventory();
        $progression = new WeaponProgressionService($inventory, $evolutionCatalog, $combatGuard);
        $effects = new ProgressionEffects($player, $progression);
        $passives = new PassiveInventory($passiveCatalog, $effects);
        $weaponLevels = new WeaponLevels;
        $evolutions = new EvolutionService($evolutionCatalog, $weaponLevels, $passives, $progression, $effects, $inventory);
        $experienceState = new Experience;
        $experience = new ExperienceSystem($playerId, $experienceState, $xpTable, new SeededRandomIndex($seed), $events);
        $generator = new LevelUpOfferGenerator($weapons, $upgrades, $passiveCatalog, $passives, $weaponLevels, $inventory,
            new SeededRandomIndex($seed + 1), $evolutions);
        $levelUps = new LevelUpService($playerId, $generator, $weapons, $upgrades, $progression, $passives, $effects,
            $weaponLevels, $inventory, $execute, $evolutions);
        $characterPassive = new CharacterPassiveSystem($player, $effects);
        $bonuses = new TemporaryBonuses($rewards->temporaryBonuses($configDir.'/scenery.json'), $effects);
        $pickups = new PickupService($player, $bonuses, $experience, $events);
        $chests = new ChestRewardService($upgrades, $weaponLevels, $progression, $evolutions, $inventory,
            new SeededRandomIndex($seed + 2));
        $run = new RunState($playerId, $characterId, $seed, $experienceState);

        $experience->subscribe();
        $characterPassive->subscribe($events);
        $levelUps->subscribe($events);
        $run->subscribe($events);

        return new self($player, $experience, $levelUps, $passives, $weaponLevels, $evolutions, $effects,
            $characterPassive, $bonuses, $pickups, $chests, $rewards->healingItem($configDir.'/chests.json'), $run);
    }

    /**
     * Passo fixo da Frente B: regeneração, duração dos bônus e tempo de sobrevivência.
     *
     * @return list<string> Bônus que expiraram neste passo.
     */
    public function update(float $deltaSeconds): array
    {
        if ($this->run->isEnded()) {
            return [];
        }
        $this->player->regenerate($deltaSeconds);
        $this->run->advance($deltaSeconds);

        return $this->bonuses->update($deltaSeconds);
    }

    /** Fim da partida: associa o nome (RF33), calcula a pontuação (RF32) e grava no ranking (RF34). */
    public function finish(PlayerName $name, ScoreRepositoryInterface $scores): ScoreEntry
    {
        $entry = ScoreEntry::fromSummary($this->run->summary(), $name, new ScoreCalculator);
        $scores->save($entry);

        return $entry;
    }
}
