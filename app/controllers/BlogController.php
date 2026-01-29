<?php
class BlogController {
    
    //Mostramos el blog
    public function index(){
        require_once APP_PATH."/views/blog.php";
    }     
}