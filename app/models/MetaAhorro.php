<?php
require_once __DIR__ . '/Database.php';

class MetaAhorro{

    public static function obtenerActivasPorUsuario($usuario_id){
        try{
            $db = Database::getConnection();

            $sql = "SELECT *
                    FROM metas_ahorro
                    WHERE usuario_id = :usuario_id
                    AND estado = 'activa'
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
                    FROM metas_ahorro
                    WHERE id = :id
                    AND usuario_id = :usuario_id
                    AND estado = 'activa'
                    LIMIT 1";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->execute();

            $meta = $stmt->fetch(PDO::FETCH_ASSOC);

            return $meta ?: null;
        }catch(Exception $e){
            if (self::tablaNoExiste($e)) {
                return null;
            }

            return false;
        }
    }

    public static function totalAportacionesActivas($usuario_id){
        try{
            $db = Database::getConnection();

            $sql = "SELECT SUM(aportacion_mensual) AS total
                    FROM metas_ahorro
                    WHERE usuario_id = :usuario_id
                    AND estado = 'activa'";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return $resultado['total'] !== null ? floatval($resultado['total']) : 0;
        }catch(Exception $e){
            if (self::tablaNoExiste($e)) {
                return 0;
            }

            return false;
        }
    }

    public static function totalAportacionesActivasExcluyendoMeta($usuario_id, $meta_id){
        try{
            $db = Database::getConnection();

            $sql = "SELECT SUM(aportacion_mensual) AS total
                    FROM metas_ahorro
                    WHERE usuario_id = :usuario_id
                    AND estado = 'activa'
                    AND id <> :meta_id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->bindParam(':meta_id', $meta_id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return $resultado['total'] !== null ? floatval($resultado['total']) : 0;
        }catch(Exception $e){
            if (self::tablaNoExiste($e)) {
                return 0;
            }

            return false;
        }
    }

    public static function crear($usuario_id, $nombre, $categoria, $importe_objetivo, $aportacion_mensual, $fecha_objetivo){
        try{
            $db = Database::getConnection();

            $sql = "INSERT INTO metas_ahorro
                    (usuario_id, nombre, categoria, importe_objetivo, aportacion_mensual, fecha_objetivo)
                    VALUES
                    (:usuario_id, :nombre, :categoria, :importe_objetivo, :aportacion_mensual, :fecha_objetivo)";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindParam(':categoria', $categoria, PDO::PARAM_STR);
            $stmt->bindParam(':importe_objetivo', $importe_objetivo);
            $stmt->bindParam(':aportacion_mensual', $aportacion_mensual);
            $stmt->bindValue(':fecha_objetivo', $fecha_objetivo, $fecha_objetivo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

            $stmt->execute();

            return $db->lastInsertId();
        }catch(Exception $e){
            return false;
        }
    }

    public static function actualizar($id, $usuario_id, $nombre, $categoria, $importe_objetivo, $aportacion_mensual, $fecha_objetivo){
        try{
            $db = Database::getConnection();

            $sql = "UPDATE metas_ahorro
                    SET nombre = :nombre,
                        categoria = :categoria,
                        importe_objetivo = :importe_objetivo,
                        aportacion_mensual = :aportacion_mensual,
                        fecha_objetivo = :fecha_objetivo
                    WHERE id = :id
                    AND usuario_id = :usuario_id
                    AND estado = 'activa'";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindParam(':categoria', $categoria, PDO::PARAM_STR);
            $stmt->bindParam(':importe_objetivo', $importe_objetivo);
            $stmt->bindParam(':aportacion_mensual', $aportacion_mensual);
            $stmt->bindValue(':fecha_objetivo', $fecha_objetivo, $fecha_objetivo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

            return $stmt->execute();
        }catch(Exception $e){
            return false;
        }
    }

    public static function eliminarPorUsuario($id, $usuario_id){
        try{
            $db = Database::getConnection();

            $sql = "DELETE FROM metas_ahorro
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

            $sql = "DELETE FROM metas_ahorro WHERE usuario_id = :usuario_id";

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
