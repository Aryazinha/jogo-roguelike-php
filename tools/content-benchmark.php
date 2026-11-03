<?php

declare(strict_types=1);

/*
 * Mede o passo de conteúdo da Frente C (C11) com 300 inimigos reais cercando
 * o jogador, sem janela. Não substitui o benchmark A10: mede só a parte da C.
 *
 * Uso: php -d opcache.enable_cli=0 tools/content-benchmark.php [passos] [seed]
 */

use Jogo\Application\Content\ContentSession;
use Jogo\Core\Config\EnemyConfigLoader;
use Jogo\Core\EventBus;
use Jogo\Domain\Enemy\EnemyFactory;
use Jogo\Domain\Entity\Damageable;
use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;

require dirname(__DIR__).'/vendor/autoload.php';

$steps = max(60, (int) ($argv[1] ?? 1200));
$seed = (int) ($argv[2] ?? 20261031);
$root = dirname(__DIR__);

$player = new class('player-1', Vector2::zero(), 16.0) extends Entity implements Damageable
{
    private readonly Health $health;

    public function __construct(string $id, Vector2 $position, float $radius)
    {
        parent::__construct($id, $position, $radius);
        $this->health = new Health(1_000_000_000.0);
    }

    public function health(): Health
    {
        return $this->health;
    }
};
$session = ContentSession::fromProject($root, $player, $seed, new EventBus);

// 299 inimigos (a vaga 300 é do Warden) num anel de 200 a 600 px, misturando todos os tipos de onda.
$factory = new EnemyFactory((new EnemyConfigLoader)->load($root.'/config/enemies.json'), 'bench');
$types = ['zombie', 'spider', 'husk', 'slime', 'skeleton'];
$center = $player->position();
for ($i = 0; $i < 299; $i++) {
    $angle = 2 * M_PI * $i / 299;
    $distance = 200 + 400 * (($i * 7919) % 299) / 299;
    $session->world->addEnemy($factory->create($types[$i % 5],
        $center->add(new Vector2(cos($angle) * $distance, sin($angle) * $distance))));
}

$times = [];
for ($step = 1; $step <= $steps; $step++) {
    $start = hrtime(true);
    // Nível alto e tempo dentro da última faixa, para as ondas tentarem criar o máximo.
    $session->step(1 / 60, 500.0 + $step / 60, 20);
    $times[] = (hrtime(true) - $start) / 1e6;
}

// O primeiro segundo é aquecimento: inimigos ainda chegando ao cerco.
$measured = array_slice($times, 60);
sort($measured);
$count = count($measured);
printf("Passo de conteúdo da Frente C, %d passos medidos (seed %d)\n", $count, $seed);
printf("inimigos vivos no fim: %d\n", $session->world->aliveEnemyCount());
printf("mediana: %.2f ms | p95: %.2f ms | máximo: %.2f ms\n",
    $measured[intdiv($count, 2)], $measured[(int) ceil(0.95 * $count) - 1], $measured[$count - 1]);
printf("OPcache no CLI: %s\n", ini_get('opcache.enable_cli') === '1' ? 'ligado' : 'desligado');
