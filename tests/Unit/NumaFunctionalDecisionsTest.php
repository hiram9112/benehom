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
        self::assertStringContainsString('texto plano estructurado, sin Markdown', $prompt);
        self::assertStringContainsString('saltos de linea, lineas en blanco', $prompt);
        self::assertStringContainsString('listas breves con •', $prompt);
        self::assertStringContainsString('Nombre: valor', $prompt);
        self::assertStringContainsString('No uses negritas con asteriscos', $prompt);
        self::assertStringContainsString('tablas Markdown, backticks, bloques de codigo', $prompt);
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
        self::assertStringContainsString('Numa usa una cookie técnica', $privacy);
        self::assertStringContainsString('bh_numa_anon', $privacy);
        self::assertStringContainsString('seudonimizada', $privacy);
        self::assertStringContainsString('No se utiliza fingerprinting', $privacy);
        self::assertStringContainsString('condiciones de servicios de pago de Google', $privacy);
        self::assertStringContainsString('contribución voluntaria de datos', $privacy);
        self::assertStringContainsString('registro opcional de prompts y respuestas', $privacy);
        self::assertStringContainsString('almacenamiento de GenerateContent API', $privacy);
        self::assertStringContainsString('almacenamiento de Interactions API', $privacy);
        self::assertStringContainsString('no existen datasets compartidos voluntariamente', $privacy);
        self::assertStringContainsString('55 días', $privacy);
        self::assertStringContainsString('supervisión de abuso', $privacy);
        self::assertStringContainsString('rotación periódica', $privacy);
        self::assertStringContainsString('revocación inmediata', $privacy);
        self::assertStringContainsString('únicamente cuando decides usar', $privacy);
        self::assertStringContainsString('artículo 6.1.b del', $privacy);
        self::assertStringContainsString('No se basa en un consentimiento específico para Gemini', $privacy);
        self::assertStringContainsString('no se requiere una casilla, modal ni aceptación adicional', $privacy);
    }

    public function testRunbookDePrivacidadDocumentaControlesDeGeminiYClaveCompartida(): void
    {
        $runbook = $this->read(BASE_PATH . '/resources/numa/privacidad-operativa.md');

        self::assertStringContainsString('55 días', $runbook);
        self::assertStringContainsString('supervisar y prevenir abuso', $runbook);
        self::assertStringContainsString('contribución voluntaria de datos', $runbook);
        self::assertStringContainsString('logging opcional de prompts y respuestas', $runbook);
        self::assertStringContainsString('NUMA_API_KEY', $runbook);
        self::assertStringContainsString('generación y embeddings', $runbook);
        self::assertStringContainsString('Rotación y revocación de claves', $runbook);
        self::assertStringContainsString('NUMA_ENABLED=false', $runbook);
        self::assertStringContainsString('Base jurídica elegida', $runbook);
        self::assertStringContainsString('artículo 6.1.b del RGPD', $runbook);
        self::assertStringContainsString('de la relación contractual y la prestación', $runbook);
        self::assertStringContainsString('únicamente cuando decide usar Numa', $runbook);
        self::assertStringContainsString('No se basa en un consentimiento específico para Gemini', $runbook);
        self::assertStringContainsString('Numa no incorpora una casilla, modal', $runbook);
        self::assertStringContainsString('Comprobaciones verificadas', $runbook);
        self::assertStringContainsString('Nivel 1 / Prepago', $runbook);
        self::assertStringContainsString('GenerateContent API', $runbook);
        self::assertStringContainsString('Interactions API', $runbook);
        self::assertStringContainsString('No existen datasets compartidos voluntariamente', $runbook);
        self::assertStringContainsString('no bloquea el', $runbook);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);

        self::assertIsString($contents, $path);

        return $contents;
    }
}
