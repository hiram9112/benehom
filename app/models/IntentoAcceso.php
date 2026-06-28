<?php

require_once __DIR__ . '/Database.php';

class IntentoAcceso
{
    public static function claveHash(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    public static function estaBloqueado(string $accion, string $claveHash): bool
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                "SELECT bloqueado_hasta FROM intentos_acceso
                 WHERE accion = :accion AND clave_hash = :clave_hash
                 LIMIT 1"
            );
            $stmt->bindParam(':accion', $accion, PDO::PARAM_STR);
            $stmt->bindParam(':clave_hash', $claveHash, PDO::PARAM_STR);
            $stmt->execute();

            $bloqueadoHasta = $stmt->fetchColumn();

            return is_string($bloqueadoHasta) && $bloqueadoHasta !== '' && strtotime($bloqueadoHasta) > time();
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function registrarFallo(
        string $accion,
        string $claveHash,
        int $maxIntentos,
        int $ventanaSegundos,
        int $bloqueoSegundos
    ): bool {
        try {
            $db = Database::getConnection();
            $ahora = date('Y-m-d H:i:s');

            $stmt = $db->prepare(
                "SELECT intentos, primer_intento, bloqueado_hasta FROM intentos_acceso
                 WHERE accion = :accion AND clave_hash = :clave_hash
                 LIMIT 1"
            );
            $stmt->bindParam(':accion', $accion, PDO::PARAM_STR);
            $stmt->bindParam(':clave_hash', $claveHash, PDO::PARAM_STR);
            $stmt->execute();

            $registro = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$registro) {
                $bloqueadoHasta = $maxIntentos <= 1 ? date('Y-m-d H:i:s', time() + $bloqueoSegundos) : null;

                $stmt = $db->prepare(
                    "INSERT INTO intentos_acceso (accion, clave_hash, intentos, primer_intento, ultimo_intento, bloqueado_hasta)
                     VALUES (:accion, :clave_hash, 1, :primer_intento, :ultimo_intento, :bloqueado_hasta)"
                );
                $stmt->bindParam(':accion', $accion, PDO::PARAM_STR);
                $stmt->bindParam(':clave_hash', $claveHash, PDO::PARAM_STR);
                $stmt->bindParam(':primer_intento', $ahora, PDO::PARAM_STR);
                $stmt->bindParam(':ultimo_intento', $ahora, PDO::PARAM_STR);
                $stmt->bindValue(':bloqueado_hasta', $bloqueadoHasta, $bloqueadoHasta === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->execute();

                return $bloqueadoHasta !== null;
            }

            if (!empty($registro['bloqueado_hasta']) && strtotime((string) $registro['bloqueado_hasta']) > time()) {
                return true;
            }

            $primerIntento = strtotime((string) $registro['primer_intento']);

            if ($primerIntento === false || $primerIntento < (time() - $ventanaSegundos)) {
                $stmt = $db->prepare(
                    "UPDATE intentos_acceso
                     SET intentos = 1, primer_intento = :primer_intento, ultimo_intento = :ultimo_intento, bloqueado_hasta = NULL
                     WHERE accion = :accion AND clave_hash = :clave_hash"
                );
                $stmt->bindParam(':primer_intento', $ahora, PDO::PARAM_STR);
                $stmt->bindParam(':ultimo_intento', $ahora, PDO::PARAM_STR);
                $stmt->bindParam(':accion', $accion, PDO::PARAM_STR);
                $stmt->bindParam(':clave_hash', $claveHash, PDO::PARAM_STR);
                $stmt->execute();

                return false;
            }

            $intentos = ((int) $registro['intentos']) + 1;
            $bloqueadoHasta = $intentos >= $maxIntentos ? date('Y-m-d H:i:s', time() + $bloqueoSegundos) : null;

            $stmt = $db->prepare(
                "UPDATE intentos_acceso
                 SET intentos = :intentos, ultimo_intento = :ultimo_intento, bloqueado_hasta = :bloqueado_hasta
                 WHERE accion = :accion AND clave_hash = :clave_hash"
            );
            $stmt->bindParam(':intentos', $intentos, PDO::PARAM_INT);
            $stmt->bindParam(':ultimo_intento', $ahora, PDO::PARAM_STR);
            $stmt->bindValue(':bloqueado_hasta', $bloqueadoHasta, $bloqueadoHasta === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':accion', $accion, PDO::PARAM_STR);
            $stmt->bindParam(':clave_hash', $claveHash, PDO::PARAM_STR);
            $stmt->execute();

            return $bloqueadoHasta !== null;
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function limpiar(string $accion, string $claveHash): bool
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                "DELETE FROM intentos_acceso WHERE accion = :accion AND clave_hash = :clave_hash"
            );
            $stmt->bindParam(':accion', $accion, PDO::PARAM_STR);
            $stmt->bindParam(':clave_hash', $claveHash, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }
}
