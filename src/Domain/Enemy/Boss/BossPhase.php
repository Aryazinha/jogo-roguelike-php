<?php

declare(strict_types=1);

namespace Jogo\Domain\Enemy\Boss;

/**
 * Fases de movimento do Warden. "Enfurecido" não está aqui porque não é uma
 * fase a mais: é um estado que vale por cima de todas, até o fim da luta.
 */
enum BossPhase: string
{
    case CHASING = 'perseguindo';
    case TELEGRAPHING = 'telegrafando';
    case CHARGING = 'investindo';
    case RECOVERING = 'recuperando';
    case SUMMONING = 'invocando';
}
