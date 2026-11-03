<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/**
 * Nível individual de cada arma adquirida (RF19), indexado pelo ID da arma base.
 * Guarda também o ID equipado atual, que muda quando a arma evolui.
 */
final class WeaponLevels
{
    public const STARTING_LEVEL = 1;

    /** @var array<string, array{level: int, currentId: string}> */
    private array $weapons = [];

    public function register(string $baseId): void
    {
        CombatValidation::requiredId($baseId, 'baseId');
        if ($this->has($baseId)) {
            throw new InvalidArgumentException("A arma {$baseId} já foi adquirida.");
        }
        $this->weapons[$baseId] = ['level' => self::STARTING_LEVEL, 'currentId' => $baseId];
    }

    public function has(string $baseId): bool
    {
        return isset($this->weapons[$baseId]);
    }

    public function level(string $baseId): int
    {
        return $this->entry($baseId)['level'];
    }

    public function currentId(string $baseId): string
    {
        return $this->entry($baseId)['currentId'];
    }

    public function isEvolved(string $baseId): bool
    {
        return $this->currentId($baseId) !== $baseId;
    }

    public function baseIdOf(string $currentId): ?string
    {
        foreach ($this->weapons as $baseId => $entry) {
            if ($entry['currentId'] === $currentId) {
                return (string) $baseId;
            }
        }

        return null;
    }

    public function promote(string $baseId, int $level): void
    {
        $entry = $this->entry($baseId);
        if ($level !== $entry['level'] + 1) {
            throw new InvalidArgumentException("{$baseId} só pode subir para o nível ".($entry['level'] + 1).'.');
        }
        $this->weapons[$baseId]['level'] = $level;
    }

    public function evolve(string $baseId, string $evolvedId): void
    {
        CombatValidation::requiredId($evolvedId, 'evolvedId');
        if ($this->isEvolved($baseId)) {
            throw new InvalidArgumentException("{$baseId} já evoluiu.");
        }
        $this->weapons[$baseId]['currentId'] = $evolvedId;
    }

    public function remove(string $baseId): void
    {
        $this->entry($baseId);
        unset($this->weapons[$baseId]);
    }

    /** @return list<string> */
    public function baseIds(): array
    {
        return array_map('strval', array_keys($this->weapons));
    }

    /** @return array{level: int, currentId: string} */
    private function entry(string $baseId): array
    {
        return $this->weapons[$baseId] ?? throw new InvalidArgumentException("Arma não adquirida: {$baseId}.");
    }
}
