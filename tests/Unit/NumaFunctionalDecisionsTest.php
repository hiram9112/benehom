<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class NumaFunctionalDecisionsTest extends TestCase
{
    public function testPromptBaseIncluyeContratosFuncionalesDefinitivos(): void
    {
        $prompt = $this->read(BASE_PATH . '/resources/numa/prompts/base.md');

        self::assertStringContainsString('describe cifras, diferencias y tendencias objetivas', $prompt);
        self::assertStringContainsString('No recomiendes que debe hacer el usuario', $prompt);
        self::assertStringContainsString('ingresos, gastos y movimientos', $prompt);
        self::assertStringContainsString('metas de ahorro, escenarios de inversion, proyecciones de inflacion ni hipotecas', $prompt);
        self::assertStringContainsString('movimientos concretos', $prompt);
        self::assertStringContainsString('listados extensos', $prompt);
        self::assertStringContainsString('Europe/Madrid', $prompt);
        self::assertStringContainsString('promedio mensual', $prompt);
        self::assertStringContainsString('solo meses con datos', $prompt);
        self::assertStringContainsString('Las respuestas financieras deben ser solo texto', $prompt);
        self::assertStringContainsString('Las fuentes documentales son metadatos internos', $prompt);
    }

    public function testBaseDeConocimientoDocumentaTranscriptYFuentesNoVisibles(): void
    {
        $intro = $this->read(BASE_PATH . '/knowledge/numa/introduccion.md');
        $faq = $this->read(BASE_PATH . '/knowledge/numa/preguntas-frecuentes.md');

        self::assertStringContainsString('No existe memoria entre sesiones', $intro);
        self::assertStringContainsString('intercambios completos mas recientes', $intro);
        self::assertStringContainsString('zonas autenticadas', $intro);
        self::assertStringContainsString('no se muestran al usuario final', $intro);
        self::assertStringContainsString('conversacion visible mientras dure la sesion', $faq);
        self::assertStringContainsString('No. Las fuentes recuperadas', $faq);
    }

    public function testBaseDeConocimientoNoAtribuyeElDeficitAMovimientosNoRegistrados(): void
    {
        $ahorro = $this->read(BASE_PATH . '/knowledge/numa/ahorro.md');

        self::assertStringContainsString('BeneHom no determina con que recurso se cubrio esa diferencia.', $ahorro);
        self::assertStringContainsString('Por si sola no identifica la causa de un resultado concreto', $ahorro);
        self::assertStringNotContainsString('tuvo que cubrir la diferencia con ahorros previos u otra fuente', $ahorro);
    }

    public function testPrivacidadDocumentaGeminiYRetencionDeNuma(): void
    {
        $privacy = $this->read(APP_PATH . '/views/legal/privacidad.php');

        self::assertStringContainsString('Gemini API', $privacy);
        self::assertStringContainsString('proveedor técnico de inteligencia artificial', $privacy);
        self::assertStringContainsString('mensaje validado', $privacy);
        self::assertStringContainsString('ingresos, gastos', $privacy);
        self::assertStringContainsString('movimientos', $privacy);
        self::assertStringContainsString('No se envían identificadores', $privacy);
        self::assertStringContainsString('internos de usuario', $privacy);
        self::assertStringContainsString('El transcript de Numa se conserva únicamente en la sesión PHP', $privacy);
        self::assertStringContainsString('no existe memoria de Numa', $privacy);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);

        self::assertIsString($contents, $path);

        return $contents;
    }
}
