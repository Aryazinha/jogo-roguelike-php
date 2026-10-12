<?php

declare(strict_types=1);

namespace Jogo\Application\Benchmark;

use InvalidArgumentException;
use Jogo\Domain\System\GeometrySnapshot;

final readonly class CollisionSummary
{
    /** @var list<float> */
    public array $perEntitySamples;

    public Distribution $perStep;

    public Distribution $perEntity;

    public int $totalCandidates;

    public int $circles;

    public int $segments;

    public int $sectors;

    public int $rebuilds;

    public int $maximumStep;

    public bool $belowBudget;

    /**
     * @param  list<GeometrySnapshot>  $samples
     * @param  list<int>  $activeEntities  Entidades Collidable ativas do mundo naquele passo.
     */
    public function __construct(array $samples, array $activeEntities, int $firstStep)
    {
        if ($samples === [] || count($samples) !== count($activeEntities) || $firstStep < 1) {
            throw new InvalidArgumentException('Amostras de colisão e denominadores devem corresponder.');
        }
        $checks = $ratios = [];
        $candidates = $circles = $segments = $sectors = $rebuilds = 0;
        $maximum = -1.0;
        $maximumStep = $firstStep;
        $below = true;
        foreach ($samples as $index => $sample) {
            $entities = $activeEntities[$index];
            if ($entities < 1) {
                throw new InvalidArgumentException('O denominador deve contar entidades ativas.');
            }
            $narrow = $sample->narrowTests();
            $ratio = $narrow / $entities;
            $checks[] = $narrow;
            $ratios[] = $ratio;
            $candidates += $sample->candidates;
            $circles += $sample->circles;
            $segments += $sample->segments;
            $sectors += $sample->sectors;
            $rebuilds += $sample->rebuilds;
            if ($ratio > $maximum) {
                $maximum = $ratio;
                $maximumStep = $firstStep + $index;
            }
            // Compara a contagem inteira, sem arredondar uma razão próxima de 40.
            $below = $below && $narrow < 40 * $entities;
        }
        $this->perEntitySamples = $ratios;
        $this->perStep = new Distribution($checks);
        $this->perEntity = new Distribution($ratios);
        $this->totalCandidates = $candidates;
        $this->circles = $circles;
        $this->segments = $segments;
        $this->sectors = $sectors;
        $this->rebuilds = $rebuilds;
        $this->maximumStep = $maximumStep;
        $this->belowBudget = $below;
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return ['totalCandidates' => $this->totalCandidates, 'totalNarrowTests' => $this->circles + $this->segments + $this->sectors,
            'circles' => $this->circles, 'segments' => $this->segments, 'sectors' => $this->sectors, 'indexRebuilds' => $this->rebuilds,
            'perStep' => $this->perStep->values(), 'perActiveEntity' => $this->perEntity->values(),
            'maximumRatioStep' => $this->maximumStep, 'strictlyBelow40' => $this->belowBudget];
    }
}
