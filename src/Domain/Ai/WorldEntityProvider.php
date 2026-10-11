<?php

declare(strict_types=1);

namespace Jogo\Domain\Ai;

interface WorldEntityProvider
{
    /**
     * @return iterable<WorldEntity> Estado atual; atividade vem de Collidable, vida dos metadados.
     *
     * @phpstan-impure
     */
    public function entities(): iterable;
}
