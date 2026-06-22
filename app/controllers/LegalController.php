<?php

class LegalController
{
    public function privacidad()
    {
        if (bh_query_route_requested('legal/privacidad')) {
            bh_redirect_permanent(bh_public_page_url('privacidad'));
        }

        require APP_PATH . '/views/legal/privacidad.php';
    }

    public function terminos()
    {
        if (bh_query_route_requested('legal/terminos')) {
            bh_redirect_permanent(bh_public_page_url('terminos'));
        }

        require APP_PATH . '/views/legal/terminos.php';
    }

    public function aviso()
    {
        if (bh_query_route_requested('legal/aviso')) {
            bh_redirect_permanent(bh_public_page_url('aviso'));
        }

        require APP_PATH . '/views/legal/aviso.php';
    }
}
