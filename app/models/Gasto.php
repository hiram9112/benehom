<?php
require_once __DIR__.'/Database.php';

//Fucnión para agregar ingresos
class Gasto{
   
    //Función para obtener los gastos de un usuario
    public static function obtenerPorMes($usuario_id,$tipo,$fechaInicio,$fechaFin){
        try{
            
            //Conectamos con la base de datos
            $db=Database::getConnection();

            //Preparamos la consulta usando marcadores para mayor seguridad
            $stmt=$db->prepare("Select * FROM gastos 
                                WHERE usuario_id= :usuario_id 
                                AND tipo= :tipo 
                                AND fecha BETWEEN :inicio AND :fin
                                ORDER BY fecha DESC");
            
            //VInculamos parámetros
            $stmt->bindParam(':usuario_id', $usuario_id,PDO::PARAM_INT);
            $stmt->bindParam(':tipo', $tipo,PDO::PARAM_STR);
            $stmt->bindParam(':inicio', $fechaInicio);
            $stmt->bindParam(':fin', $fechaFin);
            
            //Ejecutamos la consulta
            $stmt->execute();

            //Devolvemos los registros como un array asociativo
            return $stmt->fetchAll(PDO::FETCH_ASSOC);    

        } catch(PDOException $e){
            //SI ocurre un error alamcenamos mensaje en sesión y devolvemos un array vacío
            $_SESSION['mensaje_error']='Error al obtener gastos: '.$e->getMessage();
            return[];
        }
    }

    //Método para agregar un Gasto lo utilizamos para voluntarios y obligatorios, gestionamos el tipo desde el controlador
    public static function agregarGasto($usuario_id,$tipo,$categoria,$cantidad,$fecha){

        try{
            //Establecemos conexión con la base de datos
            $db=Database::getConnection();

            $stmt=$db->prepare(
                "INSERT INTO gastos (usuario_id,tipo,categoria,cantidad,fecha)
                 VALUES (:usuario_id, :tipo, :categoria, :cantidad, :fecha)"
            );

            //Vinculamos los parametros
            $stmt->bindParam(':usuario_id',$usuario_id,PDO::PARAM_INT);
            $stmt->bindParam(':tipo',$tipo,PDO::PARAM_STR);
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

    //Método para eliminar un gasto obligatorio
    public static function eliminarGasto($id){

        try{
            //Establecemos conexión con la base de datos
            $db=Database::getConnection();
            
            //Preparamos la consulta usando marcadores para mayor seguridad
            $stmt=$db->prepare("DELETE FROM gastos WHERE id= :id");

            //Vinculamos los parametros
            $stmt->bindParam(':id',$id,PDO::PARAM_INT);
            
            //ejecutamos consulta
            return $stmt->execute();
            
        }catch(PDOException $e){
            return false;
        }

    }


    //Método para actualizar un gasto obligatorio
    public static function actualizarGasto($id,$cantidad){

        try{
            //Establecemos conexión con la base de datos
            $db=Database::getConnection();

            //Preparamos la consulta usando marcadores para mayor seguridad
            $stmt=$db->prepare("UPDATE gastos SET cantidad= :cantidad WHERE id= :id");

            //Vinculamos los parametros
            $stmt->bindParam(':id',$id,PDO::PARAM_INT);
            $stmt->bindParam(':cantidad',$cantidad);
            
            //ejecutamos consulta
            return $stmt->execute();
            
        }catch(PDOException $e){
            return false;
        }

    }

    //Funcion para obetener gastos totales de 6 meses segçun tipo
    public static function totalPorMes($usuario_id,$mes,$tipo){
        try{

            //Conectamos con la base de datos
            $conexion=Database::getConnection();

            //Establecemos inicio y fin de mes
            $inicioMes=$mes."-01";
            $finMes=date("Y-m-t",strtotime($inicioMes));

            //Preparamos y ejecutamos la consulta
            $sql="SELECT SUM(cantidad) AS total FROM gastos
                  WHERE usuario_id= :usuario_id AND tipo= :tipo
                  AND fecha BETWEEN :inicio AND :fin";
            
            $stmt=$conexion->prepare($sql);

            $stmt->bindParam(':usuario_id',$usuario_id,PDO::PARAM_INT);         
            $stmt->bindParam(':tipo',$tipo,PDO::PARAM_STR);
            $stmt->bindParam(':inicio',$inicioMes);
            $stmt->bindParam(':fin',$finMes);   

            $stmt->execute();

            $resultado=$stmt->fetch(PDO::FETCH_ASSOC);

            return $resultado["total"]??0;        

        }catch(Exception $e){
            error_log("Error en totalPorMes():".$e->getMessage());
            return false;
        }

    } 

    //Método para obtener Totales por Rango
    public static function totalPorRango($usuario_id,$fechaInicio,$fechaFin,$tipo){

        try{

            //Establecemos conexión
            $db=Database::getConnection();

            //Preparamos y relizamos consulta
            $sql="SELECT SUM(cantidad) AS total
                  FROM gastos WHERE usuario_id= :usuario_id AND tipo= :tipo
                  AND fecha BETWEEN :inicio AND :fin";

            $stmt=$db->prepare($sql);

            $stmt->bindParam(':usuario_id',$usuario_id,PDO::PARAM_INT);
            $stmt->bindParam(':tipo',$tipo,PDO::PARAM_STR);
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

    //Función para eliminar todos los gastos de un usuario
    public static function eliminarTodosPorUsuario($id){
        try{

            //Establecemos conexión
            $db=Database::getConnection();

            //Preparamos y relizamos consulta
            $sql="DELETE  FROM gastos WHERE usuario_id= :usuario_id";

            $stmt=$db->prepare($sql);

            $stmt->bindParam(':usuario_id',$id,PDO::PARAM_INT);            

            return $stmt->execute();
            
        }catch (Exception $e){
            
            return false;
        }

    }
}
