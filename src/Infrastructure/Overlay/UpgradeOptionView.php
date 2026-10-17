<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Overlay;

use InvalidArgumentException;

final readonly class UpgradeOptionView
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public string $description,
        public string $effect,
        public bool $requiresReplacement = false,
    ) {
        if (
            trim($id) === ''
            || trim($name) === ''
            || trim($description) === ''
            || trim($effect) === ''
            || ! in_array(
                $type,
                ['new_weapon', 'weapon_upgrade', 'passive'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Opcao de level up invalida.'
            );
        }

        if ($requiresReplacement && $type !== 'new_weapon') {
            throw new InvalidArgumentException(
                'Substituicao exige uma nova arma.'
            );
        }
    }

    public function asOverlayOption(): OverlayOption
    {
        $type = match ($this->type) {
            'new_weapon' => 'NOVA ARMA',
            'weapon_upgrade' => 'MELHORIA',
            'passive' => 'PASSIVO',
            default => throw new \LogicException('Tipo de melhoria nao suportado.'),
        };

        return new OverlayOption(
            $this->name.' ['.$type.']',
            $this->description,
            $this->effect,
        );
    }
}
