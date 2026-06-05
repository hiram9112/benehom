<?php
require_once __DIR__ . '/Database.php';

class MetaAhorro{

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
