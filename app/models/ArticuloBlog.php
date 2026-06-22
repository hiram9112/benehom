<?php

class ArticuloBlog
{
    public static function publicados(): array
    {
        $articulos = array_filter(self::todos(), static function (array $articulo): bool {
            return ($articulo['estado'] ?? '') === 'publicado';
        });

        usort($articulos, static function (array $a, array $b): int {
            return strcmp((string) ($b['fecha'] ?? ''), (string) ($a['fecha'] ?? ''));
        });

        return array_values($articulos);
    }

    public static function obtenerPorSlug(string $slug): ?array
    {
        foreach (self::publicados() as $articulo) {
            if (($articulo['slug'] ?? '') === $slug) {
                return $articulo;
            }
        }

        return null;
    }

    public static function destacado(): ?array
    {
        foreach (self::publicados() as $articulo) {
            if (!empty($articulo['destacado'])) {
                return $articulo;
            }
        }

        return self::publicados()[0] ?? null;
    }

    public static function categorias(): array
    {
        $categorias = [];

        foreach (self::publicados() as $articulo) {
            $categoria = trim((string) ($articulo['categoria'] ?? ''));

            if ($categoria !== '') {
                $categorias[$categoria] = true;
            }
        }

        $categorias = array_keys($categorias);
        sort($categorias, SORT_NATURAL | SORT_FLAG_CASE);

        return $categorias;
    }

    public static function relacionadosPara(array $articulo): array
    {
        $slug = (string) ($articulo['slug'] ?? '');
        $articulos = self::publicados();

        foreach ($articulos as $indice => $item) {
            if (($item['slug'] ?? '') !== $slug) {
                continue;
            }

            $relacionados = [];

            if (isset($articulos[$indice - 1])) {
                $relacionados[] = $articulos[$indice - 1];
            }

            if (isset($articulos[$indice + 1])) {
                $relacionados[] = $articulos[$indice + 1];
            }

            return $relacionados;
        }

        return [];
    }

    private static function todos(): array
    {
        $articulos = require CONFIG_PATH . '/blog_articulos.php';

        return is_array($articulos) ? $articulos : [];
    }

}
