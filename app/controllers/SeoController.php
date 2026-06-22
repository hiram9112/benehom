<?php

require_once APP_PATH . '/models/ArticuloBlog.php';

class SeoController
{
    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=UTF-8');

        $urls = [
            [
                'loc' => bh_url(),
                'lastmod' => $this->fechaArchivo(BASE_PATH . '/app/views/home.php'),
                'changefreq' => 'weekly',
                'priority' => '1.0',
            ],
            [
                'loc' => bh_blog_url(),
                'lastmod' => $this->ultimaFechaBlog(),
                'changefreq' => 'weekly',
                'priority' => '0.9',
            ],
        ];

        foreach (ArticuloBlog::publicados() as $articulo) {
            $slug = trim((string) ($articulo['slug'] ?? ''));

            if ($slug === '') {
                continue;
            }

            $urls[] = [
                'loc' => bh_blog_url($slug),
                'lastmod' => $this->fechaValida((string) ($articulo['fecha'] ?? '')),
                'changefreq' => 'monthly',
                'priority' => !empty($articulo['destacado']) ? '0.8' : '0.7',
            ];
        }

        foreach ($this->paginasLegales() as $pagina => $archivo) {
            $urls[] = [
                'loc' => bh_public_page_url($pagina),
                'lastmod' => $this->fechaArchivo($archivo),
                'changefreq' => 'yearly',
                'priority' => '0.4',
            ];
        }

        echo $this->renderXml($urls);
    }

    private function renderXml(array $urls): string
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($urls as $url) {
            $xml->startElement('url');
            $xml->writeElement('loc', (string) $url['loc']);
            $xml->writeElement('lastmod', (string) $url['lastmod']);
            $xml->writeElement('changefreq', (string) $url['changefreq']);
            $xml->writeElement('priority', (string) $url['priority']);
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function ultimaFechaBlog(): string
    {
        $fechas = array_map(static function (array $articulo): string {
            return (string) ($articulo['fecha'] ?? '');
        }, ArticuloBlog::publicados());

        rsort($fechas, SORT_STRING);

        return $this->fechaValida($fechas[0] ?? '');
    }

    private function paginasLegales(): array
    {
        return [
            'privacidad' => BASE_PATH . '/app/views/legal/privacidad.php',
            'terminos' => BASE_PATH . '/app/views/legal/terminos.php',
            'aviso' => BASE_PATH . '/app/views/legal/aviso.php',
        ];
    }

    private function fechaArchivo(string $archivo): string
    {
        if (is_file($archivo)) {
            return date('Y-m-d', (int) filemtime($archivo));
        }

        return date('Y-m-d');
    }

    private function fechaValida(string $fecha): string
    {
        $fecha = trim($fecha);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1) {
            return $fecha;
        }

        return date('Y-m-d');
    }
}
