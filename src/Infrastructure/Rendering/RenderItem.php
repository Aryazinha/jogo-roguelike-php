<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Rendering;

use InvalidArgumentException;
use Jogo\Domain\Value\Vector2;

final readonly class RenderItem
{
    public const LAYERS = [
        'map',
        'scenery',
        'pickups',
        'entities',
        'attacks',
        'effects',
    ];

    public function __construct(
        public string $layer,
        public string $kind,
        public Vector2 $previous,
        public Vector2 $current,
        public float $size,
        public int $r = 255,
        public int $g = 255,
        public int $b = 255,
        public ?string $assetPath = null,
    ) {
        if (! in_array($layer, self::LAYERS, true)) {
            throw new InvalidArgumentException('Camada visual invalida.');
        }

        if (! in_array($kind, ['circle', 'rectangle', 'texture'], true)) {
            throw new InvalidArgumentException('Tipo visual invalido.');
        }

        if (! is_finite($size) || $size <= 0) {
            throw new InvalidArgumentException('Tamanho visual invalido.');
        }

        foreach ([$r, $g, $b] as $color) {
            if ($color < 0 || $color > 255) {
                throw new InvalidArgumentException('Cor visual invalida.');
            }
        }

        if ($kind === 'texture' && ($assetPath === null || trim($assetPath) === '')) {
            throw new InvalidArgumentException('Textura sem caminho.');
        }
    }
}
