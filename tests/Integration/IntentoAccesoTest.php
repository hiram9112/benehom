<?php

declare(strict_types=1);

namespace Tests\Integration;

final class IntentoAccesoTest extends IntegrationTestCase
{
    public function testRegistrarFalloBloqueaAlAlcanzarElMaximo(): void
    {
        $claveHash = \IntentoAcceso::claveHash('rate-limit@example.test');

        self::assertFalse(\IntentoAcceso::registrarFallo('login', $claveHash, 3, 900, 900));
        self::assertFalse(\IntentoAcceso::registrarFallo('login', $claveHash, 3, 900, 900));
        self::assertTrue(\IntentoAcceso::registrarFallo('login', $claveHash, 3, 900, 900));
        self::assertTrue(\IntentoAcceso::estaBloqueado('login', $claveHash));
    }

    public function testRegistrarFalloBloqueaResetPasswordAlAlcanzarElMaximo(): void
    {
        $claveHash = \IntentoAcceso::claveHash('rate-limit-reset@example.test');

        self::assertFalse(\IntentoAcceso::registrarFallo('password_reset', $claveHash, 3, 3600, 3600));
        self::assertFalse(\IntentoAcceso::registrarFallo('password_reset', $claveHash, 3, 3600, 3600));
        self::assertTrue(\IntentoAcceso::registrarFallo('password_reset', $claveHash, 3, 3600, 3600));
        self::assertTrue(\IntentoAcceso::estaBloqueado('password_reset', $claveHash));
    }

    public function testRegistrarFalloBloqueaReenvioVerificacionAlAlcanzarElMaximo(): void
    {
        $claveHash = \IntentoAcceso::claveHash('rate-limit-verification@example.test');

        self::assertFalse(\IntentoAcceso::registrarFallo('email_verification', $claveHash, 3, 3600, 3600));
        self::assertFalse(\IntentoAcceso::registrarFallo('email_verification', $claveHash, 3, 3600, 3600));
        self::assertTrue(\IntentoAcceso::registrarFallo('email_verification', $claveHash, 3, 3600, 3600));
        self::assertTrue(\IntentoAcceso::estaBloqueado('email_verification', $claveHash));
    }

    public function testLimpiarEliminaElBloqueo(): void
    {
        $claveHash = \IntentoAcceso::claveHash('limpiar@example.test');

        self::assertTrue(\IntentoAcceso::registrarFallo('login', $claveHash, 1, 900, 900));
        self::assertTrue(\IntentoAcceso::estaBloqueado('login', $claveHash));

        self::assertTrue(\IntentoAcceso::limpiar('login', $claveHash));
        self::assertFalse(\IntentoAcceso::estaBloqueado('login', $claveHash));
    }

    public function testVentanaExpiradaReiniciaElContador(): void
    {
        $claveHash = \IntentoAcceso::claveHash('ventana@example.test');
        $oldDate = date('Y-m-d H:i:s', time() - 3600);

        $stmt = $this->db->prepare(
            "INSERT INTO intentos_acceso (accion, clave_hash, intentos, primer_intento, ultimo_intento)
             VALUES ('login', :clave_hash, 2, :primer_intento, :ultimo_intento)"
        );
        $stmt->bindParam(':clave_hash', $claveHash);
        $stmt->bindParam(':primer_intento', $oldDate);
        $stmt->bindParam(':ultimo_intento', $oldDate);
        $stmt->execute();

        self::assertFalse(\IntentoAcceso::registrarFallo('login', $claveHash, 3, 900, 900));

        $stmt = $this->db->prepare("SELECT intentos, bloqueado_hasta FROM intentos_acceso WHERE clave_hash = :clave_hash");
        $stmt->bindParam(':clave_hash', $claveHash);
        $stmt->execute();
        $registro = $stmt->fetch(\PDO::FETCH_ASSOC);

        self::assertIsArray($registro);
        self::assertSame(1, (int) $registro['intentos']);
        self::assertNull($registro['bloqueado_hasta']);
    }
}
