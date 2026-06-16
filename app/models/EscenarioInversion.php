<?php
require_once __DIR__ . '/Database.php';

class EscenarioInversion{

    public static function obtenerPorUsuario($usuario_id){
        try{
            $db = Database::getConnection();

            $sql = "SELECT *
                    FROM escenarios_inversion
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
                    FROM escenarios_inversion
                    WHERE id = :id
                    AND usuario_id = :usuario_id
                    LIMIT 1";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->execute();

            $escenario = $stmt->fetch(PDO::FETCH_ASSOC);

            return $escenario ?: null;
        }catch(Exception $e){
            if (self::tablaNoExiste($e)) {
                return null;
            }

            return false;
        }
    }

    public static function crear($usuario_id, $nombre, $capital_inicial, $aportacion_mensual, $rentabilidad_anual, $plazo_anios, $frecuencia_reinversion){
        try{
            $db = Database::getConnection();

            $sql = "INSERT INTO escenarios_inversion
                    (usuario_id, nombre, capital_inicial, aportacion_mensual, rentabilidad_anual, plazo_anios, frecuencia_reinversion)
                    VALUES
                    (:usuario_id, :nombre, :capital_inicial, :aportacion_mensual, :rentabilidad_anual, :plazo_anios, :frecuencia_reinversion)";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindParam(':capital_inicial', $capital_inicial);
            $stmt->bindParam(':aportacion_mensual', $aportacion_mensual);
            $stmt->bindParam(':rentabilidad_anual', $rentabilidad_anual);
            $stmt->bindParam(':plazo_anios', $plazo_anios, PDO::PARAM_INT);
            $stmt->bindParam(':frecuencia_reinversion', $frecuencia_reinversion, PDO::PARAM_STR);

            $stmt->execute();

            return $db->lastInsertId();
        }catch(Exception $e){
            return false;
        }
    }

    public static function actualizar($id, $usuario_id, $nombre, $capital_inicial, $aportacion_mensual, $rentabilidad_anual, $plazo_anios, $frecuencia_reinversion){
        try{
            $db = Database::getConnection();

            $sql = "UPDATE escenarios_inversion
                    SET nombre = :nombre,
                        capital_inicial = :capital_inicial,
                        aportacion_mensual = :aportacion_mensual,
                        rentabilidad_anual = :rentabilidad_anual,
                        plazo_anios = :plazo_anios,
                        frecuencia_reinversion = :frecuencia_reinversion
                    WHERE id = :id
                    AND usuario_id = :usuario_id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindParam(':capital_inicial', $capital_inicial);
            $stmt->bindParam(':aportacion_mensual', $aportacion_mensual);
            $stmt->bindParam(':rentabilidad_anual', $rentabilidad_anual);
            $stmt->bindParam(':plazo_anios', $plazo_anios, PDO::PARAM_INT);
            $stmt->bindParam(':frecuencia_reinversion', $frecuencia_reinversion, PDO::PARAM_STR);

            return $stmt->execute();
        }catch(Exception $e){
            return false;
        }
    }

    public static function totalAportaciones($usuario_id){
        try{
            $db = Database::getConnection();

            $sql = "SELECT SUM(aportacion_mensual) AS total
                    FROM escenarios_inversion
                    WHERE usuario_id = :usuario_id";

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

    public static function totalAportacionesExcluyendo($usuario_id, $id){
        try{
            $db = Database::getConnection();

            $sql = "SELECT SUM(aportacion_mensual) AS total
                    FROM escenarios_inversion
                    WHERE usuario_id = :usuario_id
                    AND id <> :id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
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

    public static function eliminarPorUsuario($id, $usuario_id){
        try{
            $db = Database::getConnection();

            $sql = "DELETE FROM escenarios_inversion
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

            $sql = "DELETE FROM escenarios_inversion WHERE usuario_id = :usuario_id";

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
