<?php

class Database{
    //Creamos una varaible estática para mantener una solo conexión activa
    private static $connection=null;

    //Método estático para obtener la conexión PDO 
    public static function getConnection(){
        //Si no hay conexión la creamos
        if(self::$connection===null){
            //cargamos la configuración de la base de datos desde config/database.php
            $config=require CONFIG_PATH.'/database.php';

            try{
                //creamos una nueva conexión PDO
                self::$connection= new PDO(
                    "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4",
                    $config['user'],
                    $config['password']
                );

                //configruamos modo errores para lanzar excepciones
                self::$connection->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

            }catch(PDOException $e){
                //Si hay un error detenemos la ejecución mostrando el  mensaje correspondiente
                die("Error de conexión: ".$e->getMessage());

            }




        }

        //Devolvemos la conexión activa
        return self::$connection;

    }

}