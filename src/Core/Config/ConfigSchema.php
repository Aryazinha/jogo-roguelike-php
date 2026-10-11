<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;
use stdClass;

/** Auxiliar interno: tipos e diagnósticos iguais nos dois esquemas da A8. */
final class ConfigSchema
{
    public static function root(mixed $decoded, string $collection, string $filename, string $source): stdClass
    {
        $root = self::object($decoded, $source);
        self::fields($root, [$collection], $source);
        $meta = self::object($root->_meta ?? null, "{$source}: _meta");
        if (self::string($meta, 'arquivo', "{$source}: _meta") !== $filename) {
            throw new InvalidArgumentException("{$source}: _meta.arquivo deve ser {$filename}.");
        }
        self::string($meta, 'nota', "{$source}: _meta");
        foreach (self::list($meta->requisitos ?? null, "{$source}: _meta.requisitos") as $index => $id) {
            if (! is_string($id) || trim($id) === '') {
                throw new InvalidArgumentException("{$source}: _meta.requisitos[{$index}] deve ser um ID não vazio.");
            }
        }

        return $root;
    }

    public static function object(mixed $value, string $path): stdClass
    {
        if (! $value instanceof stdClass) {
            throw new InvalidArgumentException("{$path} deve ser um objeto.");
        }

        return $value;
    }

    /** @return list<mixed> */
    public static function list(mixed $value, string $path): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException("{$path} deve ser uma lista.");
        }

        return $value;
    }

    /** @param list<string> $allowed */
    public static function fields(stdClass $object, array $allowed, string $path): void
    {
        foreach (get_object_vars($object) as $field => $value) {
            if (! str_starts_with((string) $field, '_') && ! in_array($field, $allowed, true)) {
                throw new InvalidArgumentException("{$path}.{$field} é um campo de gameplay desconhecido.");
            }
        }
        if (property_exists($object, '_fonte')) {
            $origin = self::string($object, '_fonte', $path);
            if (! in_array($origin, ['enunciado', 'provisorio'], true)) {
                throw new InvalidArgumentException("{$path}._fonte deve ser enunciado ou provisorio.");
            }
        }
        if (property_exists($object, '_nota')) {
            self::string($object, '_nota', $path);
        }
    }

    public static function string(stdClass $object, string $field, string $path): string
    {
        $value = $object->{$field} ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("{$path}.{$field} deve ser uma string não vazia.");
        }

        return $value;
    }

    public static function number(stdClass $object, string $field, string $path): float
    {
        $value = $object->{$field} ?? null;
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
            throw new InvalidArgumentException("{$path}.{$field} deve ser um número finito.");
        }

        return (float) $value;
    }

    public static function level(stdClass $object, string $field, string $path): int
    {
        $value = $object->{$field} ?? null;
        if (! is_int($value) || $value < 1) {
            throw new InvalidArgumentException("{$path}.{$field} deve ser um inteiro >= 1.");
        }

        return $value;
    }
}
