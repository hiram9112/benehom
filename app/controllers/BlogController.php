<?php
require_once APP_PATH . '/models/ArticuloBlog.php';

class BlogController {

    public function index(){
        $articulos = ArticuloBlog::publicados();
        $articuloDestacado = ArticuloBlog::destacado();
        $categorias = ArticuloBlog::categorias();

        require_once APP_PATH."/views/blog.php";
    }

    public function detalle(){
        $slug = trim((string) ($_GET['slug'] ?? ''));

        if ($slug === '') {
            $_SESSION['mensaje_error'] = 'No se encontró el artículo que quieres leer.';
            header('Location: ' . BASE_URL . 'index.php?r=blog/index');
            exit;
        }

        $articulo = ArticuloBlog::obtenerPorSlug($slug);

        if (!$articulo) {
            $_SESSION['mensaje_error'] = 'El artículo no está disponible.';
            header('Location: ' . BASE_URL . 'index.php?r=blog/index');
            exit;
        }

        $articulosRelacionados = ArticuloBlog::relacionadosPara($articulo);

        require_once APP_PATH . '/views/blog-detalle.php';
    }
}
