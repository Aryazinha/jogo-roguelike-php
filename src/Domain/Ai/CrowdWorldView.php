<?php

declare(strict_types=1);

namespace Jogo\Domain\Ai;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;

/**
 * Retrato do mundo tirado uma vez por passo, só para a IA dos inimigos (RNF06).
 *
 * Cumpre o mesmo contrato da SpatialWorldView, mas é feito para centenas de
 * consultas pequenas no mesmo passo: cada entidade entra como um EntityView
 * já pronto, as posições ficam em arrays de float e a grade usa células do
 * tamanho do raio de separação, com chave inteira. A SpatialWorldView da A
 * continua sendo a visão das armas e projéteis.
 */
final class CrowdWorldView implements WorldView
{
    /** Multiplicador da chave de célula: x * KEY + y, sem colisão para mapas de até 1 milhão de células por eixo. */
    private const KEY = 1_000_003;

    /** @var list<EntityView> Em ordem de ID, como o contrato pede. */
    private array $views = [];

    /** @var array<string, int> Posição em por "id:" + ID. */
    private array $indexById = [];

    /** @var list<float> */
    private array $xs = [];

    /** @var list<float> */
    private array $ys = [];

    /** @var list<bool> Se a entidade conta para a separação: inimigo vivo. */
    private array $pushes = [];

    /** @var array<int, list<int>> Índices em por célula. */
    private array $cells = [];

    private function __construct(private readonly Vector2 $player, private readonly float $cellSize) {}

    /** @param iterable<EntityView> $entities Entidades ativas, já como retrato. */
    public static function fromViews(iterable $entities, Vector2 $player, float $cellSize): self
    {
        CombatValidation::positive($cellSize, 'cellSize');
        $crowd = new self(new Vector2($player->x(), $player->y()), $cellSize);

        $views = [];
        foreach ($entities as $entity) {
            $views[] = $entity;
        }
        usort($views, static fn (EntityView $a, EntityView $b) => strcmp($a->id, $b->id));

        foreach ($views as $index => $entity) {
            $key = 'id:'.$entity->id;
            if (isset($crowd->indexById[$key])) {
                throw new InvalidArgumentException("ID duplicado no retrato: {$entity->id}");
            }
            $x = $entity->position->x();
            $y = $entity->position->y();
            $crowd->views[] = $entity;
            $crowd->indexById[$key] = $index;
            $crowd->xs[] = $x;
            $crowd->ys[] = $y;
            $crowd->pushes[] = $entity->kind === 'enemy' && $entity->isAlive !== false;
            $crowd->cells[$crowd->cell($x) * self::KEY + $crowd->cell($y)][] = $index;
        }

        return $crowd;
    }

    public function playerPosition(): Vector2
    {
        return $this->player;
    }

    public function neighbors(Vector2 $center, float $radius): array
    {
        CombatValidation::nonNegative($radius, 'radius');
        $x = $center->x();
        $y = $center->y();
        $limit = $radius * $radius;
        $found = [];

        foreach ($this->cellsAround($x, $y, $radius) as $cell) {
            foreach ($cell as $index) {
                $dx = $this->xs[$index] - $x;
                $dy = $this->ys[$index] - $y;
                if ($dx * $dx + $dy * $dy <= $limit) {
                    $found[] = $index;
                }
            }
        }
        // Índices crescentes = IDs em ordem, porque $views já foi ordenado.
        sort($found);
        $result = [];
        foreach ($found as $index) {
            $result[] = $this->views[$index];
        }

        return $result;
    }

    /**
     * Soma da separação feita direto nos arrays, sem montar a lista de vizinhos.
     *
     * Cada inimigo vivo a distância d <= raio empurra com força 1 - d/raio na
     * direção oposta a ele. Quem está exatamente no mesmo ponto não tem
     * direção e volta em $stacked, para o comportamento decidir o desempate.
     *
     * @return array{float, float, list<string>} Empurrão em x, em y e IDs empilhados.
     */
    public function separation(string $selfId, Vector2 $center, float $radius): array
    {
        CombatValidation::positive($radius, 'radius');
        $x = $center->x();
        $y = $center->y();
        $limit = $radius * $radius;
        $pushX = 0.0;
        $pushY = 0.0;
        $stacked = [];

        foreach ($this->cellsAround($x, $y, $radius) as $cell) {
            foreach ($cell as $index) {
                if (! $this->pushes[$index]) {
                    continue;
                }
                $awayX = $x - $this->xs[$index];
                $awayY = $y - $this->ys[$index];
                $squared = $awayX * $awayX + $awayY * $awayY;
                if ($squared > $limit) {
                    continue;
                }
                if ($squared > 0.0) {
                    $distance = sqrt($squared);
                    $strength = (1.0 - $distance / $radius) / $distance;
                    $pushX += $awayX * $strength;
                    $pushY += $awayY * $strength;
                } elseif ($this->views[$index]->id !== $selfId) {
                    $stacked[] = $this->views[$index]->id;
                }
            }
        }
        sort($stacked);

        return [$pushX, $pushY, $stacked];
    }

    /** Retrato de uma entidade pelo ID, sem criar outro objeto. */
    public function find(string $id): ?EntityView
    {
        $index = $this->indexById['id:'.$id] ?? null;

        return $index === null ? null : $this->views[$index];
    }

    /** @return list<list<int>> Conteúdo das células tocadas pelo quadrado em volta do círculo. */
    private function cellsAround(float $x, float $y, float $radius): array
    {
        $cells = [];
        $maxX = $this->cell($x + $radius);
        $minY = $this->cell($y - $radius);
        $maxY = $this->cell($y + $radius);
        for ($cx = $this->cell($x - $radius); $cx <= $maxX; $cx++) {
            for ($cy = $minY; $cy <= $maxY; $cy++) {
                $cell = $this->cells[$cx * self::KEY + $cy] ?? null;
                if ($cell !== null) {
                    $cells[] = $cell;
                }
            }
        }

        return $cells;
    }

    private function cell(float $coordinate): int
    {
        return (int) floor($coordinate / $this->cellSize);
    }
}
