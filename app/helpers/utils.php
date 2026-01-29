<?php

function formatearCategoria($texto){
    //Reemplazamos "_" por espacios 
    $texto=str_replace("_"," ",$texto);

    //Ponemos mayúsucula inicial a cada palabra 
    return ucwords($texto);
}