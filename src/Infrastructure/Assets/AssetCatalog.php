<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Assets;

use RuntimeException;

final readonly class AssetCatalog
{
    private const REQUIRED_SPRITES = [
        'map',
        'scenery',
        'player',
        'enemy',
        'special_enemy',
        'boss',
        'projectile',
        'sword',
        'tnt',
        'pulse',
        'chest',
        'pickup',
        'breakable',
        'effect',
        'hud_heart',
        'hud_xp',
        'menu_emblem',
        'menu_panel',
        'victory',
        'defeat',
    ];

    private const REQUIRED_EFFECTS = [
        'player_damaged',
        'enemy_hit',
        'enemy_killed',
        'level_up',
        'chest_opened',
        'player_died',
        'item_picked',
    ];

    private const REQUIRED_TRACKS = ['menu', 'game'];

    /** @param array<string, mixed> $manifest */
    private function __construct(
        private string $root,
        private array $manifest,
    ) {}

    public static function fromProject(string $root): self
    {
        $root = rtrim($root, '/\\');

        $file = $root.'/assets/manifest.json';

        if (! is_file($file)) {
            throw new RuntimeException(
                'Manifesto D11 ausente: '.$file
            );
        }

        $json = file_get_contents($file);

        if ($json === false) {
            throw new RuntimeException(
                'Nao foi possivel ler o manifesto de assets.'
            );
        }

        $data = json_decode(
            $json,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (! is_array($data)) {
            throw new RuntimeException(
                'Formato de manifesto invalido.'
            );
        }

        /** @var array<string, mixed> $data */
        return new self($root, $data);
    }

    public function sprite(string $id): string
    {
        return $this->sprites()[$id]
            ?? throw new RuntimeException(
                'Sprite nao registrado: '.$id
            );
    }

    /** @return array<string, string> */
    public function sprites(): array
    {
        return $this->resolve('sprites');
    }

    /** @return array<string, string> */
    public function effects(): array
    {
        return $this->resolve('effects');
    }

    /** @return array<string, string> */
    public function tracks(): array
    {
        return $this->resolve('tracks');
    }

    public function assertReady(): void
    {
        if (
            ($this->manifest['version'] ?? null) !== 1
            || ($this->manifest['font'] ?? null) !== 'raylib_builtin'
        ) {
            throw new RuntimeException(
                'Versao ou fonte do manifesto invalida.'
            );
        }

        $creditsPath = $this->root.'/assets/CREDITOS.md';
        $credits = @file_get_contents($creditsPath);

        if ($credits === false) {
            throw new RuntimeException(
                'Arquivo de creditos ausente.'
            );
        }

        foreach ([
            'sprites' => self::REQUIRED_SPRITES,
            'effects' => self::REQUIRED_EFFECTS,
            'tracks' => self::REQUIRED_TRACKS,
        ] as $section => $required) {
            $files = $this->resolve($section);

            foreach ($required as $id) {
                if (! isset($files[$id])) {
                    throw new RuntimeException(
                        'Asset obrigatorio ausente: '.$id
                    );
                }
            }

            foreach ($files as $id => $path) {
                if (! is_file($path)) {
                    throw new RuntimeException(
                        'Arquivo nao encontrado: '.$id
                    );
                }

                $relative = substr(
                    $path,
                    strlen($this->root) + 1
                );

                if (! str_contains($credits, '`'.$relative.'`')) {
                    throw new RuntimeException(
                        'Asset sem credito: '.$relative
                    );
                }
            }
        }
    }

    /** @return array<string, string> */
    private function resolve(string $section): array
    {
        $entries = $this->manifest[$section] ?? null;

        if (! is_array($entries)) {
            throw new RuntimeException(
                'Secao de assets invalida: '.$section
            );
        }

        $result = [];

        foreach ($entries as $id => $relative) {
            if (
                ! is_string($id)
                || $id === ''
                || ! is_string($relative)
                || ! str_starts_with($relative, 'assets/')
                || str_contains($relative, '..')
                || str_contains($relative, '\\')
            ) {
                throw new RuntimeException(
                    'Caminho de asset invalido.'
                );
            }

            $result[$id] = $this->root.'/'.$relative;
        }

        return $result;
    }
}
