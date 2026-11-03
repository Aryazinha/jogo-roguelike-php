<?php

declare(strict_types=1);

use Jogo\Domain\Value\MapBounds;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Assets\AssetCatalog;
use Jogo\Infrastructure\Ffi\RaylibBinding;
use Jogo\Infrastructure\Play\PlayEntityView;
use Jogo\Infrastructure\Play\PlayFrameFactory;
use Jogo\Infrastructure\Play\PlayHudSnapshot;
use Jogo\Infrastructure\Play\PlaySnapshot;
use Jogo\Infrastructure\Play\PlayWorldSnapshot;
use Jogo\Infrastructure\Rendering\Camera;
use Jogo\Infrastructure\Rendering\RaylibRenderer;
use Jogo\Infrastructure\Rendering\RaylibResourceCache;

require dirname(__DIR__).'/vendor/autoload.php';

// Mede exclusivamente a etapa grafica da D: 300 inimigos + 200 projeteis.
// O benchmark LOGICO, separado, continua sendo tools/benchmark.php da A10.
$root = dirname(__DIR__);
$catalog = AssetCatalog::fromProject($root);
$catalog->assertReady();
$seed = 20261017;
$generator = static fn (int $index, int $salt): float => (float) ((($index * 131 + $salt * 41 + $seed) % 900) + 340);
$entities = [];

for ($i = 0; $i < 300; $i++) {
    $point = new Vector2($generator($i, 1), (float) ((($i * 83) % 680) + 140));
    $entities[] = new PlayEntityView('enemy', $point, $point, 18.0);
}
for ($i = 0; $i < 200; $i++) {
    $point = new Vector2($generator($i, 3), (float) ((($i * 103) % 680) + 140));
    $entities[] = new PlayEntityView('projectile', $point, $point, 7.0);
}

$center = new Vector2(800.0, 500.0);
$snapshot = new PlaySnapshot(
    new PlayWorldSnapshot($center, $center, $entities),
    new PlayHudSnapshot(20.0, 20.0, 5, 20, 3, 90.0, wave: 'D12 - 300 INIMIGOS / 200 PROJETEIS'),
);
$frame = (new PlayFrameFactory(null, $catalog))->create($snapshot);
$raylib = new RaylibBinding;
$raylib->openWindow(800, 450, 'D12 - Benchmark grafico: 300 + 200');
$raylib->setTargetFps(60);
$cache = RaylibResourceCache::fromRaylib($raylib);
$renderer = new RaylibRenderer(
    $raylib,
    new Camera(800, 450, new MapBounds(0, 0, 1600, 1000)),
    $cache,
    null,
    $catalog,
);
$frames = [];
$warmup = 120;
$samples = 360;

try {
    for ($index = 0; $index < $warmup + $samples; $index++) {
        if ($raylib->shouldClose()) {
            break;
        }
        $begin = hrtime(true);
        $renderer->render($frame, 0.0);
        $seconds = (hrtime(true) - $begin) / 1_000_000_000;
        if ($index >= $warmup && $seconds > 0.0) {
            $frames[] = 1.0 / $seconds;
        }
    }
} finally {
    try {
        $cache->release();
    } finally {
        $raylib->closeWindow();
    }
}

if ($frames === []) {
    throw new RuntimeException('Benchmark encerrado antes de colher quadros.');
}

sort($frames, SORT_NUMERIC);
$average = array_sum($frames) / count($frames);
$lowPercentile = $frames[(int) floor((count($frames) - 1) * 0.05)];
$meetsTarget = $average >= 50.0 && $lowPercentile >= 50.0;
$result = [
    'scenario' => 'd12-visual-only',
    'seed' => $seed,
    'resolution' => '800x450',
    'enemies' => 300,
    'projectiles' => 200,
    'warmupFrames' => $warmup,
    'measuredFrames' => count($frames),
    'averageFps' => round($average, 2),
    'p05Fps' => round($lowPercentile, 2),
    'minFps' => round($frames[0], 2),
    'target50Fps' => $meetsTarget,
    'machine' => php_uname('a'),
    'php' => PHP_VERSION,
    'scope' => 'visual renderer only; not an integrated combat benchmark',
];

$output = $root.'/tools/output';
if (! is_dir($output) && ! mkdir($output, 0775, true) && ! is_dir($output)) {
    throw new RuntimeException('Falha ao criar pasta de resultados.');
}
file_put_contents(
    $output.'/d12_visual.json',
    json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n"
);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
echo "Salvo em tools/output/d12_visual.json\n";
