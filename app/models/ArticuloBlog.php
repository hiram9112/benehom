<?php

class ArticuloBlog
{
    public static function publicados(): array
    {
        return self::publicadosDesdeCatalogo(self::todos());
    }

    /**
     * @param array<int, array<string, mixed>> $catalogo
     * @return array<int, array<string, mixed>>
     */
    private static function publicadosDesdeCatalogo(array $catalogo): array
    {
        $articulos = array_filter($catalogo, static function (array $articulo): bool {
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
        return self::seleccionarParaRag(self::todos());
    }

    /**
     * @param array<int, array<string, mixed>> $catalogo
     * @return array<int, array{slug:string, titulo:string, resumen:string, intencion_busqueda:string, contenido:array<int, array{titulo:string, parrafos:array<int, string>}>, conexion:string}>
     */
    public static function seleccionarParaRag(array $catalogo): array
    {
        $publicados = self::publicadosDesdeCatalogo($catalogo);
        self::validarArticulosPublicadosParaRag($publicados);
        $articulos = array_filter($publicados, [self::class, 'esElegibleParaRag']);
        self::validarArticulosParaRag(array_values($articulos));

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

    /**
     * @param array<int, array<string, mixed>> $articulos
     */
    private static function validarArticulosPublicadosParaRag(array $articulos): void
    {
        $slugs = [];

        foreach ($articulos as $articulo) {
            $slug = trim((string) ($articulo['slug'] ?? ''));
            $contenido = $articulo['contenido'] ?? null;

            if (
                $slug === ''
                || isset($slugs[$slug])
                || trim((string) ($articulo['titulo'] ?? '')) === ''
                || trim((string) ($articulo['resumen'] ?? '')) === ''
                || trim((string) ($articulo['intencion_busqueda'] ?? '')) === ''
                || trim((string) ($articulo['conexion'] ?? '')) === ''
                || !is_array($contenido)
                || $contenido === []
                || !array_key_exists('rag_pertinente', $articulo)
                || !is_bool($articulo['rag_pertinente'])
                || !array_key_exists('rag_aprobado', $articulo)
                || !is_bool($articulo['rag_aprobado'])
            ) {
                throw new RuntimeException('Articulo publicado del blog para RAG invalido.');
            }

            $slugs[$slug] = true;

            foreach ($contenido as $seccion) {
                if (!is_array($seccion)) {
                    throw new RuntimeException('Articulo publicado del blog para RAG invalido.');
                }

                $parrafos = $seccion['parrafos'] ?? null;
                if (
                    trim((string) ($seccion['titulo'] ?? '')) === ''
                    || !is_array($parrafos)
                    || $parrafos === []
                ) {
                    throw new RuntimeException('Articulo publicado del blog para RAG invalido.');
                }

                foreach ($parrafos as $parrafo) {
                    if (trim((string) $parrafo) === '') {
                        throw new RuntimeException('Articulo publicado del blog para RAG invalido.');
                    }
                }
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $articulos
     */
    private static function validarArticulosParaRag(array $articulos): void
    {
        $slugs = [];

        foreach ($articulos as $articulo) {
            $slug = trim((string) ($articulo['slug'] ?? ''));
            $contenido = $articulo['contenido'] ?? null;

            if (
                ($articulo['estado'] ?? '') !== 'publicado'
                || $slug === ''
                || isset($slugs[$slug])
                || trim((string) ($articulo['titulo'] ?? '')) === ''
                || trim((string) ($articulo['resumen'] ?? '')) === ''
                || trim((string) ($articulo['intencion_busqueda'] ?? '')) === ''
                || trim((string) ($articulo['conexion'] ?? '')) === ''
                || !is_array($contenido)
                || $contenido === []
            ) {
                throw new RuntimeException('Articulo de blog para RAG invalido.');
            }

            $slugs[$slug] = true;

            foreach ($contenido as $seccion) {
                if (!is_array($seccion)) {
                    throw new RuntimeException('Articulo de blog para RAG invalido.');
                }

                $parrafos = $seccion['parrafos'] ?? null;
                if (
                    trim((string) ($seccion['titulo'] ?? '')) === ''
                    || !is_array($parrafos)
                    || $parrafos === []
                ) {
                    throw new RuntimeException('Articulo de blog para RAG invalido.');
                }

                foreach ($parrafos as $parrafo) {
                    if (trim((string) $parrafo) === '') {
                        throw new RuntimeException('Articulo de blog para RAG invalido.');
                    }
                }
            }
        }
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
        $catalogPath = CONFIG_PATH . '/blog_articulos.php';
        if (!is_file($catalogPath) || !is_readable($catalogPath)) {
            throw new RuntimeException('Catalogo de articulos del blog no legible.');
        }

        $articulos = require $catalogPath;

        if (!is_array($articulos)) {
            throw new RuntimeException('Catalogo de articulos del blog invalido.');
        }

        return $articulos;
    }

}
