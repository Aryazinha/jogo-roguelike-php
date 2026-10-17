<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Overlay;

use Closure;
use InvalidArgumentException;

final readonly class LevelUpScreenContext
{
    /** @var Closure(string, ?string): bool */
    private Closure $onConfirm;

    /**
     * @param  list<UpgradeOptionView>  $options
     * @param  list<ReplacementSlotView>  $replacementSlots
     * @param  callable(string, ?string): bool  $onConfirm
     */
    public function __construct(
        public array $options,
        public array $replacementSlots,
        callable $onConfirm,
        public bool $allowCancel = false,
    ) {
        if (count($options) > 3 || count($replacementSlots) > 2) {
            throw new InvalidArgumentException(
                'Quantidade de opcoes ou slots invalida.'
            );
        }

        $ids = [];

        foreach ($options as $option) {
            if (isset($ids[$option->id])) {
                throw new InvalidArgumentException(
                    'Opcao duplicada: '.$option->id
                );
            }

            $ids[$option->id] = true;
        }

        $ids = [];

        foreach ($replacementSlots as $slot) {
            if (isset($ids[$slot->id])) {
                throw new InvalidArgumentException(
                    'Slot duplicado: '.$slot->id
                );
            }

            $ids[$slot->id] = true;
        }

        $this->onConfirm = Closure::fromCallable($onConfirm);
    }

    /** @phpstan-impure */
    public function confirm(string $optionId, ?string $slotId): bool
    {
        return ($this->onConfirm)($optionId, $slotId);
    }
}
