<?php

class LegalController
{
    public function privacidad()
    {
        require APP_PATH . '/views/legal/privacidad.php';
    }

    public function terminos()
    {
        require APP_PATH . '/views/legal/terminos.php';
    }

    public function aviso()
    {
        require APP_PATH . '/views/legal/aviso.php';
    }
}
