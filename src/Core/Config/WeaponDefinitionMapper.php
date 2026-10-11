<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;
use Jogo\Domain\Weapon\WeaponCatalog;
use Jogo\Domain\Weapon\WeaponDefinition;
use stdClass;

/** Valida dados decodificados e entrega somente definições ao domínio. */
final class WeaponDefinitionMapper
{
    private const JSON_FIELDS = [
        'name' => 'nome', 'damage' => 'dano', 'cooldownSeconds' => 'cooldownSegundos',
        'targetingId' => 'mira', 'shapeId' => 'formato', 'range' => 'alcance',
        'angleDegrees' => 'anguloGraus', 'projectileCount' => 'projeteis',
        'projectileSpeed' => 'velocidadeProjetil', 'explosionRadius' => 'raioExplosao',
        'detonationDelaySeconds' => 'atrasoDetonacaoSegundos',
    ];

    public function map(mixed $decoded, string $source = 'weapons.json'): WeaponCatalog
    {
        if (! $decoded instanceof stdClass) {
            throw new InvalidArgumentException("{$source}: a raiz deve ser um objeto.");
        }
        $root = get_object_vars($decoded);
        foreach (array_keys($root) as $field) {
            if ($field !== 'armas' && ! str_starts_with((string) $field, '_')) {
                throw new InvalidArgumentException("{$source}: {$field} é um campo de gameplay desconhecido.");
            }
        }
        $meta = $root['_meta'] ?? null;
        if (! $meta instanceof stdClass) {
            throw new InvalidArgumentException("{$source}: _meta deve ser um objeto.");
        }
        $metadata = get_object_vars($meta);
        if ($this->string($metadata, 'arquivo', "{$source}: _meta") !== 'weapons.json') {
            throw new InvalidArgumentException("{$source}: _meta.arquivo deve ser weapons.json.");
        }
        $this->string($metadata, 'nota', "{$source}: _meta");
        $requirements = $metadata['requisitos'] ?? null;
        if (! is_array($requirements) || ! array_is_list($requirements)) {
            throw new InvalidArgumentException("{$source}: _meta.requisitos deve ser uma lista de IDs.");
        }
        foreach ($requirements as $index => $requirement) {
            if (! is_string($requirement) || trim($requirement) === '') {
                throw new InvalidArgumentException("{$source}: _meta.requisitos[{$index}] deve ser um ID não vazio.");
            }
        }
        $weapons = $root['armas'] ?? null;
        if (! $weapons instanceof stdClass || get_object_vars($weapons) === []) {
            throw new InvalidArgumentException("{$source}: armas deve ser um objeto não vazio.");
        }
        $definitions = [];
        foreach (get_object_vars($weapons) as $id => $raw) {
            // Chaves numéricas de arrays do PHP voltam ao ID textual do objeto JSON.
            $id = (string) $id;
            if (! $raw instanceof stdClass) {
                throw new InvalidArgumentException("{$source}: {$id} deve ser um objeto de arma.");
            }
            $definitions[] = $this->mapWeapon($id, get_object_vars($raw), $source);
        }

        return new WeaponCatalog($definitions);
    }

    /** @param array<string, mixed> $fields */
    public function mapWeapon(string $id, array $fields, string $source): WeaponDefinition
    {
        $path = "{$source}: {$id}";
        $shape = $this->string($fields, 'formato', $path);
        if (! isset(WeaponDefinition::SPECIFIC_FIELDS_BY_SHAPE[$shape])) {
            throw new InvalidArgumentException("{$path}.formato desconhecido.");
        }
        $allowed = ['nome', 'dano', 'cooldownSegundos', 'mira', 'formato', 'alcance'];
        foreach (WeaponDefinition::SPECIFIC_FIELDS_BY_SHAPE[$shape] as $field) {
            $allowed[] = self::JSON_FIELDS[$field];
        }
        foreach (array_keys($fields) as $field) {
            if (! str_starts_with((string) $field, '_') && ! in_array($field, $allowed, true)) {
                throw new InvalidArgumentException("{$path}.{$field} é desconhecido ou não se aplica ao formato.");
            }
        }
        $origin = $this->string($fields, '_fonte', $path);
        if (! in_array($origin, ['enunciado', 'provisorio'], true)) {
            throw new InvalidArgumentException("{$path}._fonte deve ser enunciado ou provisorio.");
        }
        $this->string($fields, '_nota', $path);

        try {
            return new WeaponDefinition(
                id: $id,
                name: $this->string($fields, 'nome', $path),
                damage: $this->number($fields, 'dano', $path),
                cooldownSeconds: $this->number($fields, 'cooldownSegundos', $path),
                targetingId: $this->string($fields, 'mira', $path),
                shapeId: $shape,
                range: $this->number($fields, 'alcance', $path),
                angleDegrees: $this->optionalNumber($fields, 'anguloGraus', $path),
                projectileCount: $this->optionalInteger($fields, 'projeteis', $path),
                projectileSpeed: $this->optionalNumber($fields, 'velocidadeProjetil', $path),
                explosionRadius: $this->optionalNumber($fields, 'raioExplosao', $path),
                detonationDelaySeconds: $this->optionalNumber($fields, 'atrasoDetonacaoSegundos', $path),
            );
        } catch (InvalidArgumentException $exception) {
            // Erros dos helpers já têm o caminho; erros de domínio recebem nomes do JSON.
            if (str_starts_with($exception->getMessage(), $path.'.')) {
                throw $exception;
            }
            throw new InvalidArgumentException($path.'.'.strtr($exception->getMessage(), self::JSON_FIELDS), 0, $exception);
        }
    }

    /** @param array<string, mixed> $fields */
    private function string(array $fields, string $field, string $path): string
    {
        $value = $this->required($fields, $field, $path);
        if (! is_string($value)) {
            throw new InvalidArgumentException("{$path}.{$field} deve ser uma string.");
        }

        return $value;
    }

    /** @param array<string, mixed> $fields */
    private function number(array $fields, string $field, string $path): float
    {
        $value = $this->required($fields, $field, $path);
        if (! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException("{$path}.{$field} deve ser um número.");
        }

        return (float) $value;
    }

    /** @param array<string, mixed> $fields */
    private function optionalNumber(array $fields, string $field, string $path): ?float
    {
        return array_key_exists($field, $fields) ? $this->number($fields, $field, $path) : null;
    }

    /** @param array<string, mixed> $fields */
    private function optionalInteger(array $fields, string $field, string $path): ?int
    {
        if (! array_key_exists($field, $fields)) {
            return null;
        }
        $value = $fields[$field];
        if (! is_int($value)) {
            throw new InvalidArgumentException("{$path}.{$field} deve ser inteiro.");
        }

        return $value;
    }

    /** @param array<string, mixed> $fields */
    private function required(array $fields, string $field, string $path): mixed
    {
        if (! array_key_exists($field, $fields)) {
            throw new InvalidArgumentException("{$path}.{$field} é obrigatório.");
        }

        return $fields[$field];
    }
}
