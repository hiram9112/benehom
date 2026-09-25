<?php

class HomeController
{
    public function index()
    {
        if (isset($_SESSION['usuario_id'])) {
            header('Location: ' . bh_page_url('dashboard/index'));
            exit;
        }

        require APP_PATH . '/views/home.php';
    }
}
