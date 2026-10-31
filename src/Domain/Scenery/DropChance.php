<?php

declare(strict_types=1);

namespace Jogo\Domain\Scenery;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/** Uma linha da tabela de drops: qual item e com que chance. "nenhum" quer dizer que não cai nada. */
final readonly class DropChance
{
    public const NOTHING = 'nenhum';

    public const STEAK = 'steak';

    public const SMALL_XP = 'xp_pequeno';

    public const SPECIAL_ITEM = 'item_especial';

    public const TEMPORARY_BONUS = 'bonus_temporario';

    public const ITEMS = [self::NOTHING, self::STEAK, self::SMALL_XP, self::SPECIAL_ITEM, self::TEMPORARY_BONUS];

    public function __construct(public string $itemId, public float $probability)
    {
        CombatValidation::requiredId($itemId, 'itemId');
        if (! in_array($itemId, self::ITEMS, true)) {
            throw new InvalidArgumentException("item de drop desconhecido: {$itemId}.");
        }
        if (! is_finite($probability) || $probability <= 0.0 || $probability > 1.0) {
            throw new InvalidArgumentException('probability deve estar entre 0 (sem incluir) e 1.');
        }
    }
}
