<?php

declare(strict_types=1);

namespace Jogo\Domain\System;

use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Ai\WorldView;
use Jogo\Domain\Random\RandomIndex;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Damage;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\Attack;
use Jogo\Domain\Weapon\AttackContextProvider;
use Jogo\Domain\Weapon\AttackGeometry;
use Jogo\Domain\Weapon\CombatInventory;
use Jogo\Domain\Weapon\EnemyTarget;
use Jogo\Domain\Weapon\EnemyTargetResolver;
use Jogo\Domain\Weapon\LogicalProjectile;
use Jogo\Domain\Weapon\ProjectileCollisionView;
use Jogo\Domain\Weapon\Targeting\FacingTargeting;
use Jogo\Domain\Weapon\Targeting\NearestEnemyTargeting;
use Jogo\Domain\Weapon\Targeting\RandomEnemyTargeting;
use Jogo\Domain\Weapon\Targeting\SurroundingTargeting;
use Jogo\Domain\Weapon\Targeting\TargetingStrategy;
use Jogo\Domain\Weapon\TntCharge;
use Jogo\Domain\Weapon\WeaponDefinition;
use Jogo\Domain\Weapon\WeaponMutationGuard;
use LogicException;

/** Coordena A4/A5/A6 por partida, sem entidades de conteúdo ou apresentação. */
final class WeaponAttackSystem implements WeaponMutationGuard
{
    /** @var array<string, TargetingStrategy> */
    private readonly array $strategies;

    /** @var list<LogicalProjectile> */
    private array $projectiles = [];

    /** @var list<TntCharge> */
    private array $charges = [];

    /** @var list<array{Attack, string}> */
    private array $pendingHits = [];

    private int $nextAttackId = 1;

    private float $elapsedSeconds = 0.0;

    private bool $updating = false;

    private bool $collecting = false;

    public function __construct(
        private readonly string $sourceEntityId,
        private readonly AttackContextProvider $contextProvider,
        private readonly WorldView $world,
        private readonly ProjectileCollisionView $collisions,
        private readonly EnemyTargetResolver $targets,
        private readonly DamageSystem $damage,
        RandomIndex $random,
    ) {
        CombatValidation::requiredId($sourceEntityId, 'sourceEntityId');
        $this->strategies = [
            'direcao_do_olhar' => new FacingTargeting,
            'inimigo_mais_proximo' => new NearestEnemyTargeting,
            'inimigo_aleatorio' => new RandomEnemyTargeting($random),
            'area_ao_redor' => new SurroundingTargeting,
        ];
    }

    public function assertSafe(): void
    {
        if ($this->updating) {
            throw new LogicException('Modificações de armas devem ocorrer fora da atualização de combate.');
        }
    }

    /** Porta invocável da A6; deve ser utilizada durante update() deste coordenador. */
    public function __invoke(WeaponDefinition $definition): bool
    {
        if (! $this->collecting) {
            throw new LogicException('A tentativa de arma deve ocorrer na fase de inventário de update().');
        }
        $context = $this->contextProvider->context($definition->range);
        if ($context->range !== $definition->range) {
            throw new LogicException('O provider deve preservar o alcance recebido.');
        }
        $selection = $this->strategies[$definition->targetingId]->select($context, $this->world);
        if ($selection === null || ($selection->entityId !== null && $this->eligibleTarget($selection->entityId) === null)) {
            return false;
        }
        $attack = new Attack($this->nextAttackId++, $this->sourceEntityId, $definition);
        switch ($definition->shapeId) {
            case 'meia_lua':
                $angle = $definition->angleDegrees;
                if ($angle === null) {
                    throw new LogicException('Ângulo ausente na definição validada.');
                }
                foreach ($this->enemies($context->origin, $definition->range) as $view) {
                    if (AttackGeometry::inSector($context, $view->position, $angle)) {
                        $this->pendingHits[] = [$attack, $view->id];
                    }
                }
                break;
            case 'projetil':
                $count = $definition->projectileCount;
                if ($count === null) {
                    throw new LogicException('Contagem ausente na definição validada.');
                }
                for ($index = 0; $index < $count; $index++) {
                    $this->projectiles[] = new LogicalProjectile($attack, $context->origin, $selection);
                }
                break;
            case 'area':
                $charge = new TntCharge($attack, $selection->position);
                if ($charge->isReady()) {
                    $this->explode($charge);
                } else {
                    $this->charges[] = $charge;
                }
                break;
            case 'pulso':
                $this->queueArea($attack, $selection->position, $definition->range);
                break;
        }

        return true;
    }

    /** Segundos lógicos do fim do passo vêm do chamador, nunca do relógio real. */
    public function update(CombatInventory $inventory, float $deltaSeconds, float $elapsedSeconds): int
    {
        CombatValidation::nonNegative($deltaSeconds, 'deltaSeconds');
        CombatValidation::nonNegative($elapsedSeconds, 'elapsedSeconds');
        if ($this->updating || $elapsedSeconds < $this->elapsedSeconds) {
            throw new LogicException('Update recursivo ou tempo lógico retrocedendo.');
        }
        $this->updating = true;
        $this->elapsedSeconds = $elapsedSeconds;
        // Objetos nascidos neste passo começam a avançar somente no próximo.
        $projectiles = $this->projectiles;
        $charges = $this->charges;
        try {
            $this->collecting = true;
            $accepted = $inventory->update($deltaSeconds);
            $this->collecting = false;
            foreach ($projectiles as $projectile) {
                $this->advanceProjectile($projectile, $deltaSeconds);
            }
            foreach ($charges as $charge) {
                $charge->advance($deltaSeconds);
                if ($charge->isReady()) {
                    $this->explode($charge);
                }
            }
            foreach ($this->pendingHits as [$attack, $id]) {
                $target = $this->eligibleTarget($id);
                if ($target !== null && $attack->claim($id)) {
                    $this->damage->damageEnemy($target->entity,
                        new Damage($attack->definition->damage, $attack->sourceEntityId, $attack->definition->id),
                        $target->enemyTypeId, $target->isBoss, $this->elapsedSeconds);
                }
            }

            return $accepted;
        } finally {
            // Erros propagam sem replay de golpes que já produziram eventos.
            $this->pendingHits = [];
            $this->projectiles = array_values(array_filter($this->projectiles, static fn (LogicalProjectile $p): bool => $p->isActive()));
            $this->charges = array_values(array_filter($this->charges, static fn (TntCharge $c): bool => ! $c->isDetonated()));
            $this->collecting = false;
            $this->updating = false;
        }
    }

    /** @return list<LogicalProjectile> Cópia da coleção para consulta do estado lógico. */
    public function projectiles(): array
    {
        return $this->projectiles;
    }

    /** @return list<TntCharge> */
    public function charges(): array
    {
        return $this->charges;
    }

    private function advanceProjectile(LogicalProjectile $projectile, float $dt): void
    {
        if (! $projectile->isActive()) {
            return;
        }
        $start = $projectile->position();
        $projectile->advance($dt);
        $end = $projectile->position();
        $hits = [];
        foreach ($this->collisions->candidates($start, $end) as $view) {
            if ($view->kind !== 'enemy' || $view->isAlive !== true || $this->eligibleTarget($view->id) === null) {
                continue;
            }
            $fraction = CollisionSystem::segmentCircleFirstHit($start, $end, $view->position, $view->collisionRadius);
            if ($fraction !== null) {
                $hits[] = [$fraction, $view->id];
            }
        }
        usort($hits, static fn (array $a, array $b): int => ($a[0] <=> $b[0]) ?: strcmp($a[1], $b[1]));
        if ($hits !== []) {
            $projectile->consume();
            $this->pendingHits[] = [$projectile->attack, $hits[0][1]];
        } elseif ($projectile->hasReachedRange()) {
            $projectile->consume();
        }
    }

    private function explode(TntCharge $charge): void
    {
        $radius = $charge->attack->definition->explosionRadius;
        if ($radius === null) {
            throw new LogicException('Raio ausente na definição validada.');
        }
        $charge->detonate();
        $this->queueArea($charge->attack, $charge->position, $radius);
    }

    private function queueArea(Attack $attack, Vector2 $position, float $radius): void
    {
        foreach ($this->enemies($position, $radius) as $view) {
            $this->pendingHits[] = [$attack, $view->id];
        }
    }

    /** @return list<EntityView> */
    private function enemies(Vector2 $center, float $radius): array
    {
        $enemies = [];
        foreach ($this->world->neighbors($center, $radius) as $view) {
            if ($view->kind === 'enemy' && $view->isAlive === true && $view->id !== $this->sourceEntityId
                && CollisionSystem::circlesIntersect($center, $radius, $view->position, 0.0)) {
                $enemies['id:'.$view->id] = $view;
            }
        }
        ksort($enemies, SORT_STRING);

        return array_values($enemies);
    }

    private function eligibleTarget(string $id): ?EnemyTarget
    {
        if ($id === $this->sourceEntityId) {
            return null;
        }
        $target = $this->targets->resolve($id);
        if ($target !== null && $target->entity->id() !== $id) {
            throw new LogicException('O resolver devolveu uma identidade diferente da solicitada.');
        }

        return $target !== null && $target->entity->isActive() && ! $target->entity->health()->isDead() ? $target : null;
    }
}
