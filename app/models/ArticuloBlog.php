<?php

class ArticuloBlog
{
    private const CATEGORIAS_RELACIONADAS = [
        'Ahorro' => ['Gastos', 'Metas', 'Hábitos', 'Conceptos básicos', 'Inflación'],
        'Gastos' => ['Ahorro', 'Hábitos', 'Inflación', 'Hipotecas'],
        'Metas' => ['Ahorro', 'Conceptos básicos', 'Proyecciones'],
        'Proyecciones' => ['Conceptos básicos', 'Activos financieros', 'Metas', 'Ahorro'],
        'Hábitos' => ['Gastos', 'Ahorro'],
        'Conceptos básicos' => ['Ahorro', 'Metas', 'Proyecciones', 'Activos financieros'],
        'Inflación' => ['Gastos', 'Ahorro', 'Hipotecas'],
        'Hipotecas' => ['Gastos', 'Inflación', 'Ahorro'],
        'Activos financieros' => ['Proyecciones', 'Conceptos básicos', 'Ahorro'],
    ];

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
        $relacionados = array_filter(self::publicados(), static function (array $item) use ($slug): bool {
            return ($item['slug'] ?? '') !== $slug;
        });

        usort($relacionados, static function (array $a, array $b) use ($articulo): int {
            $puntuacionB = self::puntuacionRelacion($articulo, $b);
            $puntuacionA = self::puntuacionRelacion($articulo, $a);

            if ($puntuacionB !== $puntuacionA) {
                return $puntuacionB <=> $puntuacionA;
            }

            return strcmp((string) ($b['fecha'] ?? ''), (string) ($a['fecha'] ?? ''));
        });

        return array_values($relacionados);
    }

    public static function categoriasOficiales(): array
    {
        $editorial = self::editorial();

        return is_array($editorial['categorias_oficiales'] ?? null) ? $editorial['categorias_oficiales'] : [];
    }

    public static function lineaEditorial(): array
    {
        $editorial = self::editorial();

        return is_array($editorial['linea_editorial'] ?? null) ? $editorial['linea_editorial'] : [];
    }

    public static function estructuraEditorial(): array
    {
        $editorial = self::editorial();

        return is_array($editorial['estructura_articulo'] ?? null) ? $editorial['estructura_articulo'] : [];
    }

    public static function auditoriaEditorial(): array
    {
        $editorial = self::editorial();

        return is_array($editorial['auditoria_articulos_existentes'] ?? null) ? $editorial['auditoria_articulos_existentes'] : [];
    }

    private static function todos(): array
    {
        $articulos = require CONFIG_PATH . '/blog_articulos.php';

        return is_array($articulos) ? $articulos : [];
    }

    private static function editorial(): array
    {
        $editorial = require CONFIG_PATH . '/blog_editorial.php';

        return is_array($editorial) ? $editorial : [];
    }

    private static function puntuacionRelacion(array $actual, array $candidato): int
    {
        $categoriaActual = (string) ($actual['categoria'] ?? '');
        $categoriaCandidata = (string) ($candidato['categoria'] ?? '');
        $puntuacion = 0;

        if ($categoriaActual !== '' && $categoriaActual === $categoriaCandidata) {
            $puntuacion += 30;
        }

        if (in_array($categoriaCandidata, self::CATEGORIAS_RELACIONADAS[$categoriaActual] ?? [], true)) {
            $puntuacion += 20;
        }

        $funcionalidadActual = self::funcionalidadConexion($actual);
        if ($funcionalidadActual !== '' && $funcionalidadActual === self::funcionalidadConexion($candidato)) {
            $puntuacion += 10;
        }

        return $puntuacion;
    }

    private static function funcionalidadConexion(array $articulo): string
    {
        $conexion = strtolower((string) ($articulo['conexion'] ?? ''));

        foreach (['dashboard', 'metas', 'meta', 'proyecciones', 'grafico', 'gráfico'] as $funcionalidad) {
            if (str_contains($conexion, $funcionalidad)) {
                if ($funcionalidad === 'meta') {
                    return 'metas';
                }

                return $funcionalidad === 'grafico' ? 'gráfico' : $funcionalidad;
            }
        }

        return '';
    }
}
