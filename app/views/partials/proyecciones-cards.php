<?php

/**
 * Funciones de formato y de render de las cards de Proyecciones.
 *
 * Se usan tanto en la vista (proyecciones.php) al pintar las listas como en los
 * endpoints AJAX del controlador para devolver el HTML de una card recién creada,
 * de modo que el markup no se duplica en JavaScript.
 */

if (!function_exists('bh_proy_formatear_cantidad')) {
    function bh_proy_formatear_cantidad($cantidad): string
    {
        return number_format((float) $cantidad, 0, ',', '.');
    }
}

if (!function_exists('bh_proy_formatear_euros')) {
    function bh_proy_formatear_euros($cantidad): string
    {
        return bh_proy_formatear_cantidad($cantidad) . ' €';
    }
}

if (!function_exists('bh_proy_formatear_porcentaje')) {
    function bh_proy_formatear_porcentaje($cantidad): string
    {
        return bh_proy_formatear_cantidad($cantidad) . '%';
    }
}

if (!function_exists('bh_proy_formatear_fecha')) {
    function bh_proy_formatear_fecha($fecha): string
    {
        if (empty($fecha)) {
            return 'Sin fecha estimada';
        }

        return date('d/m/Y', strtotime($fecha));
    }
}

if (!function_exists('bh_proy_formatear_plazo')) {
    function bh_proy_formatear_plazo($meses): string
    {
        if ($meses === null) {
            return 'No calculable';
        }

        $meses = intval($meses);
        $anios = intdiv($meses, 12);
        $restoMeses = $meses % 12;

        if ($anios === 0) {
            return $meses . ($meses === 1 ? ' mes' : ' meses');
        }

        if ($restoMeses === 0) {
            return $anios . ($anios === 1 ? ' año' : ' años');
        }

        return $anios . ($anios === 1 ? ' año' : ' años') . ' y ' . $restoMeses . ($restoMeses === 1 ? ' mes' : ' meses');
    }
}

if (!function_exists('bh_proy_formatear_opcion_con_importe')) {
    function bh_proy_formatear_opcion_con_importe($texto, $importe, $prefijo = ''): string
    {
        $texto = trim((string) $texto);
        $importeTexto = $prefijo . bh_proy_formatear_cantidad($importe) . ' €';

        return $texto . ' --> ' . $importeTexto;
    }
}

if (!function_exists('bh_render_meta_card')) {
    function bh_render_meta_card(array $meta, array $gastosFlexiblesPorCategoria): string
    {
        $metaId = intval($meta['id']);
        ob_start();
        ?>
        <article
            class="bh-meta-card"
            data-meta-card
            data-importe-objetivo="<?= htmlspecialchars((string) $meta['importe_objetivo'], ENT_QUOTES, 'UTF-8') ?>"
            data-aportacion-original="<?= htmlspecialchars((string) $meta['aportacion_mensual'], ENT_QUOTES, 'UTF-8') ?>"
            data-plazo-original="<?= htmlspecialchars((string) ($meta['plazo_meses_estimado'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <div class="bh-meta-card-main">
                <div>
                    <div class="bh-meta-title-row">
                        <h4><?= htmlspecialchars($meta['nombre'], ENT_QUOTES, 'UTF-8') ?></h4>
                        <span class="bh-meta-projection-badge" data-projection-badge hidden>
                            Proyección
                            <button type="button" class="bh-meta-projection-clear" data-projection-clear aria-label="Limpiar proyección">
                                &times;
                            </button>
                        </span>
                    </div>
                </div>
                <span class="bh-badge bh-badge-saving">Estimación</span>
            </div>

            <div class="bh-meta-metrics">
                <p>
                    <span>Objetivo</span>
                    <strong
                        class="bh-editable-value"
                        data-meta-target-amount
                        data-meta-id="<?= $metaId ?>"
                        data-value="<?= htmlspecialchars((string) $meta['importe_objetivo'], ENT_QUOTES, 'UTF-8') ?>"
                        title="Haz clic para editar"
                        role="button"
                        tabindex="0"
                        aria-label="Editar importe objetivo de la meta">
                        <span data-editable-text><?= bh_proy_formatear_euros($meta['importe_objetivo']) ?></span>
                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                    </strong>
                </p>
                <p>
                    <span>Aportación mensual</span>
                    <strong data-projection-value="aportacion"><?= bh_proy_formatear_euros($meta['aportacion_mensual']) ?></strong>
                </p>
                <p>
                    <span>Plazo estimado</span>
                    <strong>
                        <span data-projection-value="plazo"><?= htmlspecialchars(bh_proy_formatear_plazo($meta['plazo_meses_estimado']), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="bh-meta-projection-improvement" data-projection-value="mejora" hidden></span>
                    </strong>
                </p>
                <p>
                    <span>Finalización estimada</span>
                    <strong data-projection-value="fecha"><?= htmlspecialchars(bh_proy_formatear_fecha($meta['fecha_finalizacion_estimada']), ENT_QUOTES, 'UTF-8') ?></strong>
                </p>
            </div>

            <p class="bh-meta-estimation-copy mb-0">
                Resultado orientativo: no garantiza que la meta se alcance en esa fecha ni modifica tus datos reales.
            </p>

            <div class="bh-meta-flex-projection" aria-label="Proyección de reducción de gastos flexibles">
                <div class="bh-meta-projection-header">
                    <h5 class="bh-meta-projection-title">Proyectar reducción de gastos flexible</h5>
                    <button type="button"
                        class="bh-btn bh-btn-icon bh-btn-ghost info-btn"
                        data-bs-toggle="modal"
                        data-bs-target="#infoProyeccionGastosFlexibles"
                        aria-label="Información sobre proyección de gastos flexibles">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                    </button>
                </div>

                <?php if (empty($gastosFlexiblesPorCategoria)): ?>
                    <p class="bh-field-help mb-0">No hay gastos flexibles registrados en el mes seleccionado para proyectar una reducción.</p>
                <?php else: ?>
                    <div class="bh-category-picker bh-meta-projection-controls" data-projection-picker>
                        <div class="bh-field">
                            <label class="bh-label" for="meta_proyeccion_categoria_<?= $metaId ?>">Categoría flexible</label>
                            <div class="bh-select-shell">
                                <select class="bh-select" id="meta_proyeccion_categoria_<?= $metaId ?>" data-projection-category>
                                    <option value="">Sin proyección</option>
                                    <?php foreach ($gastosFlexiblesPorCategoria as $gastoFlexibleCategoria): ?>
                                        <?php
                                        $categoriaFlexible = (string) $gastoFlexibleCategoria['categoria'];
                                        $totalCategoriaFlexible = floatval($gastoFlexibleCategoria['total']);
                                        ?>
                                        <option
                                            value="<?= htmlspecialchars($categoriaFlexible, ENT_QUOTES, 'UTF-8') ?>"
                                            data-label="<?= htmlspecialchars(formatearCategoria($categoriaFlexible), ENT_QUOTES, 'UTF-8') ?>"
                                            data-total="<?= htmlspecialchars((string) $totalCategoriaFlexible, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(bh_proy_formatear_opcion_con_importe(formatearCategoria($categoriaFlexible), $totalCategoriaFlexible), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="bh-field">
                            <label class="bh-label" for="meta_proyeccion_porcentaje_<?= $metaId ?>">Reducción proyectada</label>
                            <div class="bh-select-shell">
                                <select class="bh-select" id="meta_proyeccion_porcentaje_<?= $metaId ?>" data-projection-percent disabled>
                                    <option value="">Elige primero una categoría</option>
                                    <option value="25" data-percent-label="25%">25%</option>
                                    <option value="50" data-percent-label="50%">50%</option>
                                    <option value="75" data-percent-label="75%">75%</option>
                                    <option value="100" data-percent-label="100%">100%</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <p class="bh-meta-projection-result" data-projection-message hidden></p>
                <?php endif; ?>
            </div>

            <div class="bh-meta-actions">
                <form method="POST" action="index.php?r=proyecciones/eliminarMetaAhorro" class="bh-meta-delete-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $metaId ?>">
                    <button type="submit" class="bh-btn bh-btn-danger" data-confirm="Eliminar esta meta retirará su aportación de la capacidad usada. ¿Quieres continuar?">
                        <i class="bi bi-trash3" aria-hidden="true"></i>
                        Eliminar meta
                    </button>
                </form>
            </div>
        </article>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('bh_render_escenario_inversion_card')) {
    function bh_render_escenario_inversion_card(array $escenario): string
    {
        $escenarioId = intval($escenario['id']);
        ob_start();
        ?>
        <article class="bh-investment-card" data-investment-card data-investment-id="<?= $escenarioId ?>">
            <div class="bh-meta-card-main">
                <div>
                    <h4><?= htmlspecialchars($escenario['nombre'], ENT_QUOTES, 'UTF-8') ?></h4>
                    <p class="bh-investment-card-copy mb-0">
                        Reinversión <?= htmlspecialchars(strtolower($escenario['frecuencia_reinversion_label']), ENT_QUOTES, 'UTF-8') ?>:
                        <?= intval($escenario['periodos_por_anio']) ?> <?= intval($escenario['periodos_por_anio']) === 1 ? 'pago' : 'pagos' ?> al año.
                    </p>
                </div>
                <span class="bh-badge bh-badge-saving">Estimación</span>
            </div>

            <div class="bh-meta-metrics bh-investment-metrics">
                <p>
                    <span>Capital inicial</span>
                    <strong
                        class="bh-editable-value"
                        data-investment-field="capital_inicial"
                        data-investment-value="capital_inicial"
                        data-value="<?= htmlspecialchars((string) $escenario['capital_inicial'], ENT_QUOTES, 'UTF-8') ?>"
                        title="Haz clic para editar"
                        role="button"
                        tabindex="0"
                        aria-label="Editar capital inicial">
                        <span data-editable-text><?= bh_proy_formatear_euros($escenario['capital_inicial']) ?></span>
                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                    </strong>
                </p>
                <p>
                    <span>Aportación mensual</span>
                    <strong
                        class="bh-editable-value"
                        data-investment-field="aportacion_mensual"
                        data-investment-value="aportacion_mensual"
                        data-value="<?= htmlspecialchars((string) $escenario['aportacion_mensual'], ENT_QUOTES, 'UTF-8') ?>"
                        title="Haz clic para editar"
                        role="button"
                        tabindex="0"
                        aria-label="Editar aportación mensual">
                        <span data-editable-text><?= bh_proy_formatear_euros($escenario['aportacion_mensual']) ?></span>
                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                    </strong>
                </p>
                <p>
                    <span>Capital total aportado</span>
                    <strong data-investment-value="capital_total_aportado"><?= bh_proy_formatear_euros($escenario['capital_total_aportado']) ?></strong>
                </p>
                <p>
                    <span>Valor final estimado</span>
                    <strong data-investment-value="valor_final_estimado"><?= bh_proy_formatear_euros($escenario['valor_final_estimado']) ?></strong>
                </p>
                <p>
                    <span>Rendimiento estimado</span>
                    <strong data-investment-value="rendimiento_estimado"><?= bh_proy_formatear_euros($escenario['rendimiento_estimado']) ?></strong>
                </p>
                <p>
                    <span>Rendimiento anual</span>
                    <strong
                        class="bh-editable-value"
                        data-investment-field="rentabilidad_anual"
                        data-investment-value="rentabilidad_anual"
                        data-value="<?= htmlspecialchars((string) $escenario['rentabilidad_anual'], ENT_QUOTES, 'UTF-8') ?>"
                        data-suffix="%"
                        title="Haz clic para editar"
                        role="button"
                        tabindex="0"
                        aria-label="Editar rendimiento anual">
                        <span data-editable-text><?= bh_proy_formatear_porcentaje($escenario['rentabilidad_anual']) ?></span>
                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                    </strong>
                </p>
            </div>

            <p class="bh-meta-estimation-copy mb-0">
                Cuanto antes se reinvierten los beneficios, antes forman parte del capital y mayor puede ser el efecto compuesto estimado.
            </p>

            <form method="POST" action="index.php?r=proyecciones/eliminarEscenarioInversion" class="bh-meta-delete-form bh-investment-delete-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $escenarioId ?>">
                <button type="submit" class="bh-btn bh-btn-danger" data-confirm="Eliminar este escenario de inversión no modificará ningún dato real. ¿Quieres continuar?">
                    <i class="bi bi-trash3" aria-hidden="true"></i>
                    Eliminar escenario
                </button>
            </form>
        </article>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('bh_render_inflacion_card')) {
    function bh_render_inflacion_card(array $proyeccion): string
    {
        $proyeccionId = intval($proyeccion['id']);
        ob_start();
        ?>
        <article class="bh-inflation-card" data-inflacion-card data-inflacion-id="<?= $proyeccionId ?>">
            <div class="bh-meta-card-main">
                <div>
                    <h4><?= htmlspecialchars($proyeccion['nombre'], ENT_QUOTES, 'UTF-8') ?></h4>
                </div>
                <span class="bh-badge bh-badge-saving">Estimación</span>
            </div>

            <div class="bh-meta-metrics bh-inflation-metrics">
                <p>
                    <span>Cantidad inicial</span>
                    <strong
                        class="bh-editable-value"
                        data-inflacion-field="cantidad_inicial"
                        data-inflacion-value="cantidad_inicial"
                        data-value="<?= htmlspecialchars((string) $proyeccion['cantidad_inicial'], ENT_QUOTES, 'UTF-8') ?>"
                        title="Haz clic para editar"
                        role="button"
                        tabindex="0"
                        aria-label="Editar cantidad inicial">
                        <span data-editable-text><?= bh_proy_formatear_euros($proyeccion['cantidad_inicial']) ?></span>
                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                    </strong>
                </p>
                <p>
                    <span>Inflación anual</span>
                    <strong
                        class="bh-editable-value"
                        data-inflacion-field="inflacion_anual"
                        data-inflacion-value="inflacion_anual"
                        data-value="<?= htmlspecialchars((string) $proyeccion['inflacion_anual'], ENT_QUOTES, 'UTF-8') ?>"
                        data-suffix="%"
                        title="Haz clic para editar"
                        role="button"
                        tabindex="0"
                        aria-label="Editar inflación anual">
                        <span data-editable-text><?= bh_proy_formatear_porcentaje($proyeccion['inflacion_anual']) ?></span>
                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                    </strong>
                </p>
                <p>
                    <span>Plazo en años</span>
                    <strong
                        class="bh-editable-value"
                        data-inflacion-field="plazo_anios"
                        data-inflacion-value="plazo_anios"
                        data-value="<?= htmlspecialchars((string) $proyeccion['plazo_anios'], ENT_QUOTES, 'UTF-8') ?>"
                        title="Haz clic para editar"
                        role="button"
                        tabindex="0"
                        aria-label="Editar plazo en años">
                        <span data-editable-text><?= intval($proyeccion['plazo_anios']) ?> <?= intval($proyeccion['plazo_anios']) === 1 ? 'año' : 'años' ?></span>
                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                    </strong>
                </p>
                <p>
                    <span>Poder adquisitivo final</span>
                    <strong data-inflacion-value="poder_adquisitivo_final"><?= bh_proy_formatear_euros($proyeccion['poder_adquisitivo_final']) ?></strong>
                </p>
                <p>
                    <span>Pérdida estimada</span>
                    <strong data-inflacion-value="perdida_estimada"><?= bh_proy_formatear_euros($proyeccion['perdida_estimada']) ?></strong>
                </p>
                <p>
                    <span>Cantidad futura necesaria</span>
                    <strong data-inflacion-value="cantidad_futura_necesaria"><?= bh_proy_formatear_euros($proyeccion['cantidad_futura_necesaria']) ?></strong>
                </p>
                <p>
                    <span>Diferencia necesaria</span>
                    <strong data-inflacion-value="diferencia_necesaria"><?= bh_proy_formatear_euros($proyeccion['diferencia_necesaria']) ?></strong>
                </p>
            </div>

            <p class="bh-meta-estimation-copy mb-0">
                La inflación no reduce el número de euros, sino lo que esos euros pueden comprar. Este cálculo es una estimación educativa.
            </p>

            <form method="POST" action="index.php?r=proyecciones/eliminarInflacionProyeccion" class="bh-meta-delete-form bh-inflation-delete-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $proyeccionId ?>">
                <button type="submit" class="bh-btn bh-btn-danger" data-confirm="Eliminar esta proyección de inflación no modificará ningún dato real. ¿Quieres continuar?">
                    <i class="bi bi-trash3" aria-hidden="true"></i>
                    Eliminar proyección
                </button>
            </form>
        </article>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('bh_render_calculadora_hipoteca_card')) {
    function bh_render_calculadora_hipoteca_card(array $calculadora): string
    {
        $calculadoraId = intval($calculadora['id']);
        ob_start();
        ?>
        <article class="bh-mortgage-card" data-hipoteca-card data-hipoteca-id="<?= $calculadoraId ?>">
            <div class="bh-meta-card-main">
                <div>
                    <h4><?= htmlspecialchars($calculadora['nombre'], ENT_QUOTES, 'UTF-8') ?></h4>
                </div>
                <span class="bh-badge bh-badge-saving">Estimación</span>
            </div>

            <div class="bh-meta-metrics bh-mortgage-metrics">
                <p>
                    <span>Importe del préstamo</span>
                    <strong
                        class="bh-editable-value"
                        data-hipoteca-field="importe_prestamo"
                        data-hipoteca-value="importe_prestamo"
                        data-value="<?= htmlspecialchars((string) $calculadora['importe_prestamo'], ENT_QUOTES, 'UTF-8') ?>"
                        title="Haz clic para editar"
                        role="button"
                        tabindex="0"
                        aria-label="Editar importe del préstamo">
                        <span data-editable-text><?= bh_proy_formatear_euros($calculadora['importe_prestamo']) ?></span>
                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                    </strong>
                </p>
                <p>
                    <span>Interés anual</span>
                    <strong
                        class="bh-editable-value"
                        data-hipoteca-field="interes_anual"
                        data-hipoteca-value="interes_anual"
                        data-value="<?= htmlspecialchars((string) $calculadora['interes_anual'], ENT_QUOTES, 'UTF-8') ?>"
                        data-suffix="%"
                        title="Haz clic para editar"
                        role="button"
                        tabindex="0"
                        aria-label="Editar interés anual">
                        <span data-editable-text><?= bh_proy_formatear_porcentaje($calculadora['interes_anual']) ?></span>
                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                    </strong>
                </p>
                <p>
                    <span>Plazo en años</span>
                    <strong
                        class="bh-editable-value"
                        data-hipoteca-field="plazo_anios"
                        data-hipoteca-value="plazo_anios"
                        data-value="<?= htmlspecialchars((string) $calculadora['plazo_anios'], ENT_QUOTES, 'UTF-8') ?>"
                        title="Haz clic para editar"
                        role="button"
                        tabindex="0"
                        aria-label="Editar plazo en años">
                        <span data-editable-text><?= intval($calculadora['plazo_anios']) ?> <?= intval($calculadora['plazo_anios']) === 1 ? 'año' : 'años' ?></span>
                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                    </strong>
                </p>
                <p>
                    <span>Cuota mensual</span>
                    <strong data-hipoteca-value="cuota_mensual"><?= bh_proy_formatear_euros($calculadora['cuota_mensual']) ?></strong>
                </p>
                <p>
                    <span>Total intereses</span>
                    <strong data-hipoteca-value="total_intereses"><?= bh_proy_formatear_euros($calculadora['total_intereses']) ?></strong>
                </p>
                <p>
                    <span>Total pagado</span>
                    <strong data-hipoteca-value="total_pagado"><?= bh_proy_formatear_euros($calculadora['total_pagado']) ?></strong>
                </p>
            </div>

            <p class="bh-meta-estimation-copy mb-0">
                Este cálculo es una estimación educativa. No representa una oferta vinculante ni una recomendación financiera. Consulta siempre condiciones reales con tu entidad.
            </p>

            <form method="POST" action="index.php?r=proyecciones/eliminarCalculadoraHipoteca" class="bh-meta-delete-form bh-mortgage-delete-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $calculadoraId ?>">
                <button type="submit" class="bh-btn bh-btn-danger" data-confirm="Eliminar esta calculadora de hipoteca no modificará ningún dato real. ¿Quieres continuar?">
                    <i class="bi bi-trash3" aria-hidden="true"></i>
                    Eliminar calculadora
                </button>
            </form>
        </article>
        <?php
        return (string) ob_get_clean();
    }
}
