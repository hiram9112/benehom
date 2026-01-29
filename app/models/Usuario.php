<?php
require_once __DIR__.'/Database.php';

class Usuario{
    //Método estático para registrar nuevo usuario
    public static function registrar($usuario,$email,$password){
        try{
            //Obentemos la conexión
            $db=Database::getConnection();

            //Ciframos la contraseña antes de guardarla
            $passwordHash=password_hash($password,PASSWORD_BCRYPT);

            //Preparamos la consulta SQL usando marcadores para mayor seguridad
            $stmt=$db->prepare("INSERT INTO usuarios(usuario,email,password) VALUES(:usuario,:email,:password)");

            //Asociamos los valores alos marcadores
            $stmt->bindParam(':usuario',$usuario);
            $stmt->bindParam(':email',$email);
            $stmt->bindParam(':password',$passwordHash);

            //Ejecutamos la consulta
            $stmt->execute();

            //Si todo fue bien devolvemos true, el registro fue exitoso

            return true;
        }
        catch(PDOException $e){
            //Obtenemos el array con información detallada del error               
            $codigoError=$e->errorInfo; 

            //Obetenemos el código específico del error
            if($codigoError[1]==1062){
                 //Si el error es porque ya existe el ususario devolvemos false
                 return false;
            }
            else{
                //Si hay un error detenemos la ejecución mostrando el mensaje correspondiente.
                die("Error al registrarse el usuario: ".$e->getMessage());
            }
           

            

        }

    }

    //Método par obtener los datos de un usuario
    public static function obtenerUsuario($email){
        try{
            //Establecesmo la conexión
            $db= Database::getConnection();

            //Preparamos la consulta SQL con marcador para evitar inyección SQL
            $stmt=$db->prepare("SELECT * FROM usuarios WHERE email =:email");

            //Asociamos el parámetro
            $stmt->bindParam(':email', $email,PDO::PARAM_STR);

            //Ejecutamos la consulta
            $stmt->execute();

            // obetenemos el resultado
            $resultado=$stmt->fetch(PDO::FETCH_ASSOC); 

            //Si no hay resultados devolvemos false
            if(!$resultado){
                return false;                
            }

            //Si el usuario existe lo devolvemos incluyendo el hash de la contraseña
            return $resultado;
        }
        catch(PDOException $e){
            die("Error al obtener el usuario: ".$e->getMessage());

        }

    }
    

    //Método para obtener hash de  contraseña
    public static function obtenerHashPassword($id){
        try{
            $db=Database::getConnection();
            
            $sql="SELECT password FROM usuarios WHERE id= :id";
            $stmt=$db->prepare($sql);
            $stmt->bindParam(':id' , $id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado=$stmt->fetch(PDO::FETCH_ASSOC);

            return $resultado ? $resultado['password']: false;
        }catch(PDOException $e){
            return false;
        }
    }

    //Método para actualizar la contraseña
    public static function actualizarPassword($id,$nuevoHash){
        try{
            $db=Database::getConnection();
            
            $sql="UPDATE usuarios SET password= :password WHERE id= :id";
            $stmt=$db->prepare($sql);
            $stmt->bindParam(':password', $nuevoHash, PDO::PARAM_STR);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);            
            
            return $stmt->execute();
        }catch(PDOException $e){
            return false;
        }
    }

    //Función para eliminar  un usuario
    public static function eliminar($id){
        try{

            //Establecemos conexión
            $db=Database::getConnection();

            //Preparamos y relizamos consulta
            $sql="DELETE  FROM usuarios WHERE id= :id";

            $stmt=$db->prepare($sql);

            $stmt->bindParam(':id',$id,PDO::PARAM_INT);            

            return $stmt->execute();
            
        }catch (Exception $e){
            
            return false;
        }

    }
    
}


