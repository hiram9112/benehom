<?php
require_once __DIR__.'/Database.php';

//Fucnión para betener ingresos
class Ingreso{
   
    //Función para obtener los ingresos de un usuario
    public static function obtenerPorMes($usuario_id,$fechaInicio,$fechaFin){
        try{
            
            //Conectamos con la base de datos
            $db=Database::getConnection();

            //Preparamos la consulta usando marcadores para mayor seguridad
            $stmt=$db->prepare("Select * FROM ingresos 
                                WHERE usuario_id= :usuario_id 
                                AND fecha BETWEEN :inicio AND :fin ORDER BY fecha DESC");
            
            //VInculamos parámetros
            $stmt->bindParam(':usuario_id', $usuario_id,PDO::PARAM_INT);
            $stmt->bindParam(':inicio', $fechaInicio);
            $stmt->bindParam(':fin', $fechaFin);
            
            //Ejecutamos la consulta
            $stmt->execute();

            //Devolvemos los registros como un array asociativo
            return $stmt->fetchAll(PDO::FETCH_ASSOC);    

        } catch(PDOException $e){
            //SI ocurre un error alamcenamos mensaje en sesión y devolvemos un array vacío
            $_SESSION['mensaje_error']='Error al obtener ingresos: '.$e->getMessage();
            return[];
        }
    }

    //Método para agregar un ingreso
    public static function agregarIngreso($usuario_id,$categoria,$cantidad,$fecha){

        try{
            //Establecemos conexión con la base de datos
            $db=Database::getConnection();

            $stmt=$db->prepare(
                "INSERT INTO ingresos (usuario_id,categoria,cantidad,fecha)
                 VALUES (:usuario_id, :categoria, :cantidad, :fecha)"
            );

            //Vinculamos los parametros
            $stmt->bindParam(':usuario_id',$usuario_id,PDO::PARAM_INT);
            $stmt->bindParam(':categoria',$categoria,PDO::PARAM_STR);
            $stmt->bindParam(':cantidad',$cantidad);
            $stmt->bindParam(':fecha',$fecha);

            //ejecutamos consulta
            $stmt->execute();

            return $db->lastInsertId();

            
        }catch(PDOException $e){
            return false;
        }

    }

    //Método para eliminar un ingreso
    public static function eliminarIngreso($id){

        try{
            //Establecemos conexión con la base de datos
            $db=Database::getConnection();
            
            //Preparamos la consulta usando marcadores para mayor seguridad
            $stmt=$db->prepare("DELETE FROM ingresos WHERE id= :id");

            //Vinculamos los parametros
            $stmt->bindParam(':id',$id,PDO::PARAM_INT);
            
            //ejecutamos consulta
            return $stmt->execute();
            
        }catch(PDOException $e){
            return false;
        }

    }


    //Método para actualizar un ingreso
    public static function actualizarIngreso($id,$cantidad){

        try{
            //Establecemos conexión con la base de datos
            $db=Database::getConnection();

            //Preparamos la consulta usando marcadores para mayor seguridad
            $stmt=$db->prepare("UPDATE ingresos SET cantidad= :cantidad WHERE id= :id");

            //Vinculamos los parametros
            $stmt->bindParam(':id',$id,PDO::PARAM_INT);
            $stmt->bindParam(':cantidad',$cantidad);
            
            //ejecutamos consulta
            return $stmt->execute();
            
        }catch(PDOException $e){
            return false;
        }

    }

    //Método para obtener Totales por Rango
    public static function totalPorRango($usuario_id,$fechaInicio,$fechaFin){

        try{

            //Establecemos conexión
            $db=Database::getConnection();

            //Preparamos y relizamos consulta
            $sql="SELECT SUM(cantidad) AS total
                  FROM ingresos WHERE usuario_id= :usuario_id
                  AND fecha BETWEEN :inicio AND :fin";

            $stmt=$db->prepare($sql);

            $stmt->bindParam(':usuario_id',$usuario_id,PDO::PARAM_INT);
            $stmt->bindParam(':inicio',$fechaInicio);
            $stmt->bindParam(':fin',$fechaFin);

            $stmt->execute();

            //REcogemos el resultado de la consulta
            $resultado=$stmt->fetch(PDO::FETCH_ASSOC);

            return $resultado["total"]!==null ? floatval($resultado["total"]):0;
        }catch (Exception $e){
            
            return false;
        }

    }

    //Función para eliminar todos los ingresos de un usuario
    public static function eliminarTodosPorUsuario($id){
        try{

            //Establecemos conexión
            $db=Database::getConnection();

            //Preparamos y relizamos consulta
            $sql="DELETE  FROM ingresos WHERE usuario_id= :usuario_id";

            $stmt=$db->prepare($sql);

            $stmt->bindParam(':usuario_id',$id,PDO::PARAM_INT);            

            return $stmt->execute();
            
        }catch (Exception $e){
            
            return false;
        }

    }
}
