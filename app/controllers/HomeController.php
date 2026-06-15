<?php

class HomeController
{
    public function index()
    {
        if (isset($_SESSION['usuario_id'])) {
            header("Location: " . BASE_URL . "index.php?r=dashboard/index");
            exit;
        }

        require APP_PATH . '/views/home.php';
    }
}
