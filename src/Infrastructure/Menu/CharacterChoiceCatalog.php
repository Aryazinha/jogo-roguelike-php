<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Menu;

use RuntimeException;

final readonly class CharacterChoiceCatalog
{
    /**
     * Le somente os dados de apresentacao.
     * Nao cria personagens nem aplica atributos.
     *
     * @return list<CharacterChoice>
     */
    public static function fromFile(string $path): array
    {
        $json = @file_get_contents($path);

        if ($json === false) {
            throw new RuntimeException(
                'Nao foi possivel ler personagens: '.$path
            );
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (
            ! is_array($data)
            || ! isset($data['personagens'])
            || ! is_array($data['personagens'])
        ) {
            throw new RuntimeException(
                'Catalogo de personagens invalido.'
            );
        }

        $choices = [];

        foreach ($data['personagens'] as $id => $entry) {
            if (! is_string($id) || ! is_array($entry)) {
                throw new RuntimeException(
                    'Identificacao de personagem invalida.'
                );
            }

            $name = $entry['nome'] ?? null;
            $health = $entry['vida'] ?? null;
            $weapon = $entry['armaInicial'] ?? null;
            $regeneration = $entry['regeneracao'] ?? null;
            $passive = $entry['passiva'] ?? null;

            if (
                ! is_string($name)
                || ! is_int($health)
                || ! is_string($weapon)
                || ! is_array($regeneration)
                || ! is_array($passive)
            ) {
                throw new RuntimeException(
                    'Dados invalidos do personagem '.$id
                );
            }

            $seconds = $regeneration['intervaloSegundos'] ?? null;
            $description = $passive['descricao'] ?? null;

            if (
                $id === ''
                || trim($name) === ''
                || $health <= 0
                || ! is_int($seconds)
                || $seconds <= 0
                || ! is_string($description)
                || trim($description) === ''
            ) {
                throw new RuntimeException(
                    'Atributos de apresentacao invalidos: '.$id
                );
            }

            $choices[] = new CharacterChoice(
                $id,
                $name,
                $health,
                $seconds,
                $weapon,
                $description
            );
        }

        if (count($choices) < 2) {
            throw new RuntimeException(
                'A selecao exige pelo menos dois personagens.'
            );
        }

        return $choices;
    }
}
