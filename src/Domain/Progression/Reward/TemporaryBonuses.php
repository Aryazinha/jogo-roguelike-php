<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression\Reward;

use Jogo\Domain\Progression\ProgressionEffects;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Cooldown;

/**
 * Bônus ativos e suas durações pelo tempo lógico. Coletar um bônus já ativo renova a duração
 * sem acumular o efeito. Ao expirar, somente as origens do próprio bônus são removidas.
 */
final class TemporaryBonuses
{
    /** @var array<string, array{timer: Cooldown, sources: list<string>}> */
    private array $active = [];

    private int $activations = 0;

    public function __construct(private readonly TemporaryBonusCatalog $catalog, private readonly ProgressionEffects $effects) {}

    public function activate(string $bonusId): void
    {
        $definition = $this->catalog->get($bonusId);
        if (isset($this->active[$bonusId])) {
            $this->active[$bonusId]['timer']->reset();
            $this->active[$bonusId]['timer']->tryStart();

            return;
        }
        $this->activations++;
        $sources = [];
        $modifiers = [];
        foreach ($definition->modifiers as $index => $template) {
            $source = "bonus:{$bonusId}:{$this->activations}:{$index}";
            $sources[] = $source;
            $modifiers[] = $template->toModifier($source);
        }
        $this->effects->grant($modifiers);
        $timer = new Cooldown($definition->durationSeconds);
        $timer->tryStart();
        $this->active[$bonusId] = ['timer' => $timer, 'sources' => $sources];
    }

    /**
     * Avança as durações; chamado a cada passo fixo, portanto parado durante a pausa.
     *
     * @return list<string> Bônus que expiraram neste passo.
     */
    public function update(float $deltaSeconds): array
    {
        CombatValidation::nonNegative($deltaSeconds, 'deltaSeconds');
        $expired = [];
        foreach ($this->active as $bonusId => $entry) {
            $entry['timer']->advance($deltaSeconds);
            if (! $entry['timer']->isReady()) {
                continue;
            }
            foreach ($entry['sources'] as $source) {
                $this->effects->revoke($source);
            }
            unset($this->active[$bonusId]);
            $expired[] = (string) $bonusId;
        }

        return $expired;
    }

    public function isActive(string $bonusId): bool
    {
        return isset($this->active[$bonusId]);
    }

    public function remainingSeconds(string $bonusId): float
    {
        return isset($this->active[$bonusId]) ? $this->active[$bonusId]['timer']->remainingSeconds() : 0.0;
    }
}
