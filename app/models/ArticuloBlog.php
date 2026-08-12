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

    public static function esElegibleParaRag(array $articulo): bool
    {
        return ($articulo['estado'] ?? '') === 'publicado'
            && ($articulo['rag_pertinente'] ?? false) === true
            && ($articulo['rag_aprobado'] ?? false) === true;
    }

    /**
     * @return array<int, array{slug:string, titulo:string, resumen:string, intencion_busqueda:string, contenido:array<int, array{titulo:string, parrafos:array<int, string>}>, conexion:string}>
     */
    public static function publicadosParaRag(): array
    {
        $articulos = array_filter(self::publicados(), [self::class, 'esElegibleParaRag']);

        return array_values(array_map(static function (array $articulo): array {
            $secciones = array_map(static function (array $seccion): array {
                return [
                    'titulo' => (string) ($seccion['titulo'] ?? ''),
                    'parrafos' => array_values(array_map('strval', $seccion['parrafos'] ?? [])),
                ];
            }, $articulo['contenido'] ?? []);

            return [
                'slug' => (string) ($articulo['slug'] ?? ''),
                'titulo' => (string) ($articulo['titulo'] ?? ''),
                'resumen' => (string) ($articulo['resumen'] ?? ''),
                'intencion_busqueda' => (string) ($articulo['intencion_busqueda'] ?? ''),
                'contenido' => $secciones,
                'conexion' => (string) ($articulo['conexion'] ?? ''),
            ];
        }, $articulos));
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
