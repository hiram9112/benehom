<?php
require_once APP_PATH . '/models/ArticuloBlog.php';

class BlogController {

    public function index(){
        if (bh_query_route_requested('blog/index')) {
            bh_redirect_permanent(bh_blog_url());
        }

        $articulos = ArticuloBlog::publicados();
        $articuloDestacado = ArticuloBlog::destacado();
        $categorias = ArticuloBlog::categorias();

        require_once APP_PATH."/views/blog.php";
    }

    public function detalle(){
        $slug = trim((string) ($_GET['slug'] ?? ''));

        if (bh_query_route_requested('blog/detalle')) {
            bh_redirect_permanent($slug !== '' ? bh_blog_url($slug) : bh_blog_url());
        }

        if ($slug === '') {
            $_SESSION['mensaje_error'] = 'No se encontró el artículo que quieres leer.';
            header('Location: ' . bh_blog_url());
            exit;
        }

        $articulo = ArticuloBlog::obtenerPorSlug($slug);

        if (!$articulo) {
            $_SESSION['mensaje_error'] = 'El artículo no está disponible.';
            header('Location: ' . bh_blog_url());
            exit;
        }

        $articulosRelacionados = ArticuloBlog::relacionadosPara($articulo);

        require_once APP_PATH . '/views/blog-detalle.php';
    }
}
