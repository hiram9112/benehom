<?php
require_once __DIR__ . '/Database.php';

class InflacionProyeccion{

    public static function obtenerPorUsuario($usuario_id){
        try{
            $db = Database::getConnection();

            $sql = "SELECT *
                    FROM proyecciones_inflacion
                    WHERE usuario_id = :usuario_id
                    ORDER BY fecha_creacion DESC, id DESC";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }catch(Exception $e){
            if (self::tablaNoExiste($e)) {
                return [];
            }

            return false;
        }
    }

    public static function obtenerPorIdYUsuario($id, $usuario_id){
        try{
            $db = Database::getConnection();

            $sql = "SELECT *
                    FROM proyecciones_inflacion
                    WHERE id = :id
                    AND usuario_id = :usuario_id
                    LIMIT 1";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->execute();

            $proyeccion = $stmt->fetch(PDO::FETCH_ASSOC);

            return $proyeccion ?: null;
        }catch(Exception $e){
            if (self::tablaNoExiste($e)) {
                return null;
            }

            return false;
        }
    }

    public static function crear($usuario_id, $nombre, $cantidad_inicial, $inflacion_anual, $plazo_anios){
        try{
            $db = Database::getConnection();

            $sql = "INSERT INTO proyecciones_inflacion
                    (usuario_id, nombre, cantidad_inicial, inflacion_anual, plazo_anios)
                    VALUES
                    (:usuario_id, :nombre, :cantidad_inicial, :inflacion_anual, :plazo_anios)";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindParam(':cantidad_inicial', $cantidad_inicial);
            $stmt->bindParam(':inflacion_anual', $inflacion_anual);
            $stmt->bindParam(':plazo_anios', $plazo_anios, PDO::PARAM_INT);

            $stmt->execute();

            return $db->lastInsertId();
        }catch(Exception $e){
            return false;
        }
    }

    public static function actualizar($id, $usuario_id, $nombre, $cantidad_inicial, $inflacion_anual, $plazo_anios){
        try{
            $db = Database::getConnection();

            $sql = "UPDATE proyecciones_inflacion
                    SET nombre = :nombre,
                        cantidad_inicial = :cantidad_inicial,
                        inflacion_anual = :inflacion_anual,
                        plazo_anios = :plazo_anios
                    WHERE id = :id
                    AND usuario_id = :usuario_id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindParam(':cantidad_inicial', $cantidad_inicial);
            $stmt->bindParam(':inflacion_anual', $inflacion_anual);
            $stmt->bindParam(':plazo_anios', $plazo_anios, PDO::PARAM_INT);

            return $stmt->execute();
        }catch(Exception $e){
            return false;
        }
    }

    public static function eliminarPorUsuario($id, $usuario_id){
        try{
            $db = Database::getConnection();

            $sql = "DELETE FROM proyecciones_inflacion
                    WHERE id = :id
                    AND usuario_id = :usuario_id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);

            return $stmt->execute();
        }catch(Exception $e){
            return false;
        }
    }

    public static function eliminarTodosPorUsuario($usuario_id){
        try{
            $db = Database::getConnection();

            $sql = "DELETE FROM proyecciones_inflacion WHERE usuario_id = :usuario_id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);

            return $stmt->execute();
        }catch(Exception $e){
            if (self::tablaNoExiste($e)) {
                return true;
            }

            return false;
        }
    }

    private static function tablaNoExiste(Exception $e): bool{
        return $e instanceof PDOException && $e->getCode() === '42S02';
    }
}
