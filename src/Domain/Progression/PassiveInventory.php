<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression;

use InvalidArgumentException;

/** Passivos adquiridos e seus níveis (RF21); cada nível concede efeitos com origem própria. */
final class PassiveInventory
{
    /** @var array<string, int> */
    private array $levels = [];

    public function __construct(private readonly PassiveCatalog $catalog, private readonly ProgressionEffects $effects) {}

    public function level(string $passiveId): int
    {
        $this->catalog->get($passiveId);

        return $this->levels[$passiveId] ?? 0;
    }

    public function canAcquire(string $passiveId): bool
    {
        return $this->catalog->has($passiveId) && $this->level($passiveId) < $this->catalog->get($passiveId)->maxLevel;
    }

    /** Adquire o passivo ou sobe um nível; devolve o novo nível. */
    public function acquire(string $passiveId): int
    {
        $definition = $this->catalog->get($passiveId);
        $next = $this->level($passiveId) + 1;
        if ($next > $definition->maxLevel) {
            throw new InvalidArgumentException("{$passiveId} já está no nível máximo {$definition->maxLevel}.");
        }
        $modifiers = [];
        foreach ($definition->modifiersPerLevel as $index => $template) {
            $modifiers[] = $template->toModifier("passivo:{$passiveId}:{$next}:{$index}");
        }
        $this->effects->grant($modifiers);
        $this->levels[$passiveId] = $next;

        return $next;
    }

    /** @return array<string, int> */
    public function owned(): array
    {
        return $this->levels;
    }
}
