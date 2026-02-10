<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Gestor de Economía Familiar --Dashboard</title>
    <!--Bootstrap CSS-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!--Bootstrap Iconos-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!--Bootstrap JS(componentes interactivos)-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!--Conectamos con archivo CSS propio-->
    <link rel="stylesheet" href="<?= BASE_URL ?>css/custom.css">

    <!-- Flatpickr: selector de fecha -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- Flatpickr plugin: selección por mes/año -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">


</head>

<body>
    <!--Mensajes para cuando somos redirigidos después de agregar ingresos o gastos -->
    <?php
    //Mensaje de éxito
    if (isset($_SESSION['mensaje_exitoso'])) {
        echo "<p class='alert alert-success text-center'>";
        echo $_SESSION['mensaje_exitoso'];
        echo "</p>";

        //eliminamos mensaje para no mostrarlo otra vez
        unset($_SESSION['mensaje_exitoso']);
    }
    //Mensjae de error
    if (isset($_SESSION['mensaje_error'])) {
        echo "<p class='alert alert-danger text-center'>";
        echo $_SESSION['mensaje_error'];
        echo "</p>";

        //eliminamos mensaje para no mostrarlo ota vez;
        unset($_SESSION['mensaje_error']);
    }
    ?>


    <!--Contenedor Principal-->
    <div class="container-fluid ">
        <div class="row">


            <!-- Panel Lateral izquierdo-->
            <aside class="col-12 col-md-3 col-lg-1  bg-side-menu text-white full-height py-4">

                <!--Saludo, escapamos caracteres especiales para evitar que se interprete como código html o scripts 
                <h4>Bienvenido, <?= htmlspecialchars($_SESSION['usuario']) ?></h4>-->

                <!-- Logo Benehom-->
                <div class="logo-container text-center mb-4">
                    <a href="index.php?r=dashboard/index">
                        <img src="<?= BASE_URL ?>img/logo-benehom.png" alt="Logo Benehom" class="logo-benehom">
                    </a>
                </div>

                <hr class="sidebar-separator">

                <!-- Panel Lateral izquierdo-->
                <nav>
                    <ul class="nav flex-column">
                        <li class="nav-item mb-2"><a class="nav-link p-0" href="index.php?r=dashboard/index">Inicio</a></li>
                        <li class="nav-item mb-2"><a class="nav-link p-0" href="index.php?r=metas/index">Metas</a></li>
                        <li class="nav-item mb-2"><a class="nav-link p-0" href="index.php?r=blog/index">Blog</a></li>
                        <li class="nav-item mb-2"><a class="nav-link p-0" href="index.php?r=cuenta/index">Cuenta</a></li>
                    </ul>
                </nav>

                <!--Enlace para cerrar cesión-->
                <div>
                    <a class="nav-link p-0" href="?r=auth/logout">Cerrar sesión</a>
                </div>
            </aside>

            <!-- Contenedor principal -->
            <main class="col-12 col-md-6 col-lg-8 mb-4 bg-main-content">



                <!--Panel central-->
                <section>
                    <!--Selector de mes-->
                    <div id="selector_mes" class="mb-4">
                        <form method="GET" action="index.php">
                            <input type="hidden" name="r" value="dashboard/index">

                            <input
                                type="text"
                                id="mes"
                                name="mes"
                                value="<?= isset($_GET['mes']) ? $_GET['mes'] : date('Y-m') ?>">
                        </form>
                    </div>



                    <!-- Ingresos-->
                    <div class="card mb-3">
                        <div class="card-header ">
                            <h3 class="titulo">
                                Ingresos
                                <button type="button"
                                    class="btn btn-link p-0 info-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#infoIngresos"
                                    aria-label="Información sobre ingresos">
                                    <i class="bi bi-info-circle"></i>
                                </button>

                            </h3>
                        </div>
                        <div class="card-body">
                            <form id="formIngresos" class="formulario-bh">
                                <?= csrf_field() ?>

                                <label for="categoria_ingreso">Categoría:</label>
                                <select name="categoria_ingreso" id="categoria_ingreso" required>
                                    <option value="" selected disabled>Selecciona un tipo de ingreso</option>
                                    <option value="salario">Salario</option>
                                    <option value="inversiones">Inversiones</option>
                                    <option value="otros">Otros</option>
                                </select>

                                <label for="cantidad_ingreso">Cantidad(€): </label>
                                <input type="number" name="cantidad_ingreso" id="cantidad_ingreso" step="0.01" required>

                                <!--Enviamos el valor del mes seleccionado , esto será especialmente útil cuando el usuario queira insertar valores en meses pasados-->
                                <input type="hidden" name="mes_seleccionado" value="<?= $mesSeleccionado ?>">

                                <button type="submit">Añadir ingreso</button>
                            </form>

                            <!--Contenedor para manejar de manera dinámica los ingresos utilizando AJAX y PHP-->
                            <div id="lista_ingresos" class="lista-ingresos">
                                <?php if (!empty($ingresos)): ?>
                                    <ul>
                                        <?php foreach ($ingresos as $ingreso): ?>
                                            <!--Agregamos manejo de id de forma dinámica para usarlo en ajax-->
                                            <li id="ingreso-<?= $ingreso['id'] ?>">
                                                <span
                                                    class="categoria_ingreso_individual"><?= htmlspecialchars(formatearCategoria($ingreso['categoria'])) ?></span>:
                                                <span class="cantidad_ingreso"
                                                    data-id="<?= $ingreso['id'] ?>"><?= formatearCantidadPHP($ingreso['cantidad']) ?></span>€
                                                <button class="eliminar_ingreso" data-id="<?= $ingreso['id'] ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p>No tienes ingresos registrados todavía.</p>
                                <?php endif; ?>
                            </div>

                            <!--Usaremos este elemento para mostrar de manera dinámica el total de ingresos -->
                            <p id="total_ingresos_texto" class="mt-2 fw-bold total-texto total-ingreso"></p>
                        </div>

                    </div>



                    <!--Gastos obligatorios-->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="titulo ">Gastos Obligatorios
                                <button type="button"
                                    class="btn btn-link p-0 info-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#infoGastosObligatorios"
                                    aria-label="Información sobre gastos obligatorios">
                                    <i class="bi bi-info-circle"></i>
                                </button>
                            </h3>
                        </div>
                        <div class="card-body">
                            <form id="formGastosObligatorios" class="formulario-bh">
                                <?= csrf_field() ?>

                                <label for="gastos_obligatorio">Tipo de gasto: </label>
                                <select name="categoria_gasto_obligatorio" id="categoria_gasto_obligatorio" required>
                                    <option value="" selected disabled>Selecciona un tipo de gastos</option>

                                    <!-- Vivienda -->
                                    <option value="vivienda">Vivienda (alquiler / hipoteca)</option>
                                    <option value="comunidad">Gastos de comunidad</option>
                                    <option value="mantenimiento_hogar">Mantenimiento y reparaciones del hogar</option>
                                    <option value="mobiliario_hogar">Mobiliario y equipamiento del hogar</option>

                                    <!-- Suministros -->
                                    <option value="agua">Agua</option>
                                    <option value="electricidad">Electricidad</option>
                                    <option value="gas">Gas</option>
                                    <option value="internet_telefonia">Internet y telefonía</option>

                                    <!-- Alimentación -->
                                    <option value="supermercado">Compra del supermercado</option>

                                    <!-- Hijos -->
                                    <option value="alimentacion_bebe">Alimentación bebé</option>
                                    <option value="higiene_bebe">Higiene bebé</option>
                                    <option value="ropa_bebe">Ropa bebé</option>
                                    <option value="cuidado_infantil">Cuidado infantil (guardería / cuidador)</option>

                                    <!-- Transporte -->
                                    <option value="combustible_trabajo">Combustible por trabajo / estudio</option>
                                    <option value="transporte_publico">Transporte público</option>
                                    <option value="reparaciones_coche">Reparaciones y mantenimiento del coche</option>

                                    <!-- Salud y educación -->
                                    <option value="salud">Salud y medicación</option>
                                    <option value="educacion">Educación / material escolar</option>

                                    <!-- Trabajo e impuestos -->
                                    <option value="trabajo">Gastos de trabajo</option>
                                    <option value="impuestos">Impuestos y tasas</option>
                                    <option value="seguros">Seguros</option>

                                    <!-- Otros -->
                                    <option value="imprevistos">Imprevistos</option>
                                    <option value="otros_obligatorios">Otros gastos obligatorios</option>
                                </select>

                                <label for="cantidad_gasto_obligatorio">Cantidad(€):</label>
                                <input type="number" name="cantidad_gasto_obligatorio" id="cantidad_gasto_obligatorio"
                                    step="0.01" required>

                                <!--Enviamos el valor del mes seleccionado , esto será especialmente útil cuando el usuario queira insertar valores en meses pasados-->
                                <input type="hidden" name="mes_seleccionado" value="<?= $mesSeleccionado ?>">

                                <button type="submit">Añadir gasto</button>
                            </form>


                            <!--Contenedor para manejar de manera dinámica los gastos obligatorios utilizando AJAX y PHP-->
                            <div id="lista_gastos_obligatorios" class="lista-gastos">
                                <?php if (!empty($gastosObligatorios)): ?>
                                    <ul>
                                        <?php foreach ($gastosObligatorios as $gastoObligatorio): ?>
                                            <!--Agregamos manejo de id de forma dinámica para usarlo en ajax-->
                                            <li id="gasto-<?= $gastoObligatorio['id'] ?>"
                                                data-tipo="<?= $gastoObligatorio['tipo'] ?>">
                                                <span
                                                    class="categoria_gasto_obli"><?= htmlspecialchars(formatearCategoria($gastoObligatorio['categoria'])) ?></span>:
                                                <span class="cantidad_gasto_obli cantidad_gasto"
                                                    data-id="<?= $gastoObligatorio['id'] ?>"><?= formatearCantidadPHP($gastoObligatorio['cantidad']) ?></span>€
                                                <button class="eliminar_gasto" data-id="<?= $gastoObligatorio['id'] ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p>No tienes gastos obligatorios registrados todavía.</p>
                                <?php endif; ?>
                            </div>

                            <!--Usaremos este elemento para mostrar de manera dinámica el total de gastos obligatorios -->
                            <p id="total_gastos_obligatorios_texto" class="mt-2 fw-bold total-texto total-gasto"></p><br>

                            <!--Usaremos este elemento para mostrar de manera dinámica la capacidad de ahorro  -->
                            <p id="capacidad_ahorro_texto" class="mt-1 fw-bold texto-resumen total-texto total-ahorro"></p>


                        </div>

                    </div>



                    <!--Gastos voluntarios-->
                    <div class="card ">
                        <div class="card-header">
                            <h3 class="titulo">
                                Gastos voluntarios
                                <button type="button"
                                    class="btn btn-link p-0 info-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#infoGastosVoluntarios"
                                    aria-label="Información sobre gastos voluntarios">
                                    <i class="bi bi-info-circle"></i>
                                </button>
                            </h3>
                        </div>
                        <div class="card-body">
                            <form id="formGastosVoluntarios" class="formulario-bh">
                                <?= csrf_field() ?>

                                <label for="gastos_voluntarios">Tipo de gasto:</label>
                                <select name="categoria_gasto_voluntario" id="categoria_gasto_voluntario" required>
                                    <!-- Ocio y consumo -->
                                    <option value="ocio">Ocio y entretenimiento</option>
                                    <option value="gimnasio">Gimnasio / actividad deportiva</option>

                                    <!-- Restauración -->
                                    <option value="comidas_fuera">Comidas fuera</option>
                                    <option value="pedir_comida">Pedir comida a domicilio</option>

                                    <!-- Suscripciones -->
                                    <option value="suscripciones">Suscripciones</option>
                                    <option value="movil_financiado">Móvil financiado</option>

                                    <!-- Transporte personal -->
                                    <option value="combustible_personal">Combustible (uso personal)</option>

                                    <!-- Finanzas personales -->
                                    <option value="compras_financiadas">Compras financiadas</option>
                                    <option value="prestamo_personal">Préstamo personal</option>
                                    <option value="prestamo_coche">Préstamo o renting de coche</option>

                                    <!-- Viajes -->
                                    <option value="viajes">Viajes y vacaciones</option>

                                    <!-- Solidaridad -->
                                    <option value="donaciones">Donaciones</option>

                                    <!-- Otros -->
                                    <option value="otros_voluntarios">Otros gastos voluntarios</option>
                                </select>

                                <label for="cantidad_gasto_voluntario">Cantidad(€):</label>
                                <input type="number" name="cantidad_gasto_voluntario" id="cantidad_gasto_voluntario"
                                    step="0.01" required>

                                <!--Enviamos el valor del mes seleccionado , esto será especialmente útil cuando el usuario queira insertar valores en meses pasados-->
                                <input type="hidden" name="mes_seleccionado" value="<?= $mesSeleccionado ?>">

                                <button type="submit">Añadir gasto</button>
                            </form>

                            <!--Contenedor para manejar de manera dinámica los gastos obligatorios utilizando AJAX y PHP-->
                            <div id="lista_gastos_voluntarios" class="lista-gastos">
                                <?php if (!empty($gastosVoluntarios)): ?>
                                    <ul>
                                        <?php foreach ($gastosVoluntarios as $gastoVoluntario): ?>
                                            <!--Agregamos manejo de id de forma dinámica para usarlo en ajax-->
                                            <li id="gasto-<?= $gastoVoluntario['id'] ?>"
                                                data-tipo="<?= $gastoVoluntario['tipo'] ?>">
                                                <span
                                                    class="categoria_gasto_volun"><?= htmlspecialchars(formatearCategoria($gastoVoluntario['categoria'])) ?></span>:
                                                <span class="cantidad_gasto_volun cantidad_gasto"
                                                    data-id="<?= $gastoVoluntario['id'] ?>"><?= formatearCantidadPHP($gastoVoluntario['cantidad']) ?></span>€
                                                <button class="eliminar_gasto" data-id="<?= $gastoVoluntario['id'] ?>"> <i
                                                        class="bi bi-trash"></i></button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p>No tienes gastos voluntarios registrados todavía.</p>
                                <?php endif; ?>
                            </div>

                            <!--Usaremos este elemento para mostrar de manera dinámica el total de gastos voluntarios -->
                            <p id="total_gastos_voluntarios_texto" class="mt-2 fw-bold total-texto total-gasto"></p><br>
                            <!--Usaremos este elemento para mostrar de manera dinámica el ahorro real -->
                            <p id="ahorro_real_texto" class="mt-1 fw-bold texto-resumen total-texto total-ahorro"></p>

                        </div>
                    </div>

                </section>

            </main>

            <!--Panel lateral derecho-->
            <aside class="col-12 col-md-3 col-lg-3 bg-main-content mt-5">

                <!--Gráfico presupuesto mensual-->
                <div class="card p-3 mb-3 contenedor-grafico">
                    <h5 class="mb-3">
                        Presupuesto mensual
                        <button type="button"
                            class="btn btn-link p-0 info-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#infoPresupuestoMensual"
                            aria-label="Información sobre presupuesto mensual">
                            <i class="bi bi-info-circle"></i>
                        </button>
                    </h5>
                    <div class="resumen-flex">
                        <p id="totalIngresosTexto" class="fw-bold mb-1 ingreso-resumenMensual"></p>
                        <p id="ahorro_mensual" class="mb-1 fw-bold ahorro-resumenMensual"></p>
                    </div>
                    <canvas id="graficoPresupuestoMensual"></canvas>
                </div>

                <!--Gráfico Ahorros 6m-->
                <div class="card p-3 mb-3 contenedor-grafico">
                    <h5 class="mb-3">
                        Evolución del Ahorro
                        <button type="button"
                            class="btn btn-link p-0 info-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#infoEvolucionAhorro"
                            aria-label="Información sobre evolución del ahorro">
                            <i class="bi bi-info-circle"></i>
                        </button>
                    </h5>
                    <canvas id="graficoAhorros6m"></canvas>
                </div>


                <!--Gráfico evolución gastos obligatorios-->
                <div class="card p-3 mb-3 contenedor-grafico">
                    <h5 class="mb-3">
                        Evolución Gastos Obligatorios
                        <button type="button"
                            class="btn btn-link p-0 info-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#infoEvolucionObligatorios"
                            aria-label="Información sobre la evolución de los gastos obligatorios">
                            <i class="bi bi-info-circle"></i>
                        </button>
                    </h5>
                    <canvas id="graficoObligatorios6m"></canvas>
                </div>


                <!--Gráfico evolución gastos voluntarios-->
                <div class="card p-3 mb-3 contenedor-grafico">
                    <h5 class="mb-3">
                        Evolución Gastos Voluntarios
                        <button type="button"
                            class="btn btn-link p-0 info-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#infoEvolucionVoluntarios"
                            aria-label="Información sobre gastos voluntarios">
                            <i class="bi bi-info-circle"></i>
                        </button>
                    </h5>
                    <canvas id="graficoVoluntarios6m"></canvas>
                </div>
            </aside>

        </div>
    </div>

    <!--Modal de ingresos-->
    <div class="modal fade" id="infoIngresos" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">¿Qué son los ingresos?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p>Los ingresos representan el dinero que entra en tu hogar durante el mes.</p>

                    <ul>
                        <li>Salarios o nóminas</li>
                        <li>Ingresos extra o puntuales</li>
                        <li>Ingresos por inversiones</li>
                    </ul>

                    <p class="mt-2">Registrar correctamente los ingresos es clave para entender tu capacidad real de ahorro
                        y analizar si tus gastos están equilibrados.</p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!--Modal de gastos obligatorios-->
    <div class="modal fade" id="infoGastosObligatorios" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">¿Qué son los gastos obligatorios?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p> Los gastos obligatorios son aquellos necesarios para mantener el hogar
                        y cubrir necesidades básicas. </p>

                    <p>No dependen de decisiones de ocio o consumo,son obligaciones que no podemos
                        ignorar, suelen repetirse cada mes.</p>

                    <p class="mt-2"> Separarlos del resto de gastos te ayuda a entender
                        cuánto dinero necesitas realmente para vivir.</p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>

            </div>
        </div>
    </div>

    <!--Modal de gastos voluntarios-->

    <div class="modal fade" id="infoGastosVoluntarios" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">¿Qué son los gastos voluntarios?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p>Los gastos voluntarios son aquellos que <strong>no son imprescindibles para vivir</strong>
                        y dependen principalmente de nuestros hábitos de consumo y decisiones personales.
                    </p>

                    <p>Identificar este tipo de gastos permite entender con claridad
                        <strong>qué parte de nuestro presupuesto es realmente flexible</strong>.
                        Son gastos que pueden reducirse o eliminarse sin afectar a las
                        necesidades básicas del hogar.
                    </p>

                    <p class="mt-2">
                        Identificarlos ayuda a medir el impacto real que pequeños cambios en nuestros hábitos
                        pueden tener sobre la <strong>capacidad de ahorro</strong> y el
                        <strong>bienestar financiero</strong>.
                        Al no ser obligaciones fijas, muchos de estos gastos pueden eliminarse
                        de un mes para otro, generando un efecto positivo
                        <strong>rápido y significativo</strong> en la economía familiar.
                    </p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>

            </div>
        </div>
    </div>

    <!--Modal Gráfico de presupuesto mensual-->
    <div class="modal fade" id="infoPresupuestoMensual" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">¿Cómo interpretar el presupuesto mensual?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p>Este gráfico muestra un resumen claro de tu economía en el mes actual.</p>

                    <p><strong>Compara tus ingresos con el total de gastos</strong> y te indica cuánto dinero estás
                        ahorrando o perdiendo al final del mes.
                    </p>


                    <p>Los porcentajes te ayudan a entender qué parte de tus ingresos
                        se destina a gastos y qué parte se convierte en ahorro.
                    </p>

                    <p>Si el <strong>ahorro es positivo</strong>, significa que <strong>gastas menos de lo que ingresas</strong>.
                        Si es <strong>negativo</strong>, estás en déficit: estás <strong>gastando más dinero del que entra</strong>.
                    </p>

                    <p class="mt-2">Este gráfico te permite detectar desequilibrios rápidamente
                        y tomar decisiones para mejorar tu situación financiera.
                    </p>
                    <p class="mt-2">Puedes pasar el cursor sobre cada barra para ver el valor exacto.
                    </p>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>

            </div>
        </div>
    </div>

    <!--Modal Gráfico de evolución del ahorro-->
    <div class="modal fade" id="infoEvolucionAhorro" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">¿Cómo interpretar la evolución del ahorro?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p>Este gráfico es uno de los más importantes de la aplicación.
                        Muestra cómo ha evolucionado tu ahorro mes a mes y te ayuda
                        a entender si tu economía es sostenible en el tiempo.
                    </p>

                    <p>La <strong>capacidad de ahorro</strong> representa cuánto dinero
                        podrías haber ahorrado en un mes según tus ingresos y gastos obligatorios.
                    </p>

                    <p>El <strong>ahorro real</strong> muestra lo que finalmente ocurrió
                        después de incluir los gastos voluntarios: lo que realmente se ahorró
                        o se perdió.
                    </p>

                    <p>Cuando el <strong>ahorro real es negativo</strong>, significa que ese mes se ha <strong>gastado
                            más dinero del que se ingresó</strong>, utilizando ahorros anteriores.
                    </p>

                    <p class="mt-2">Si esta situación se repite durante varios meses, es una señal de alerta:
                        el nivel de gasto actual <strong>no es sostenible</strong> , a largo plazo los ahorros
                        pueden agotarse.
                    </p>

                    <p class="mt-2">Puedes pasar el cursor sobre cada barra para ver el valor exacto
                        correspondiente a cada mes.
                    </p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>

            </div>
        </div>
    </div>

    <!--Modal Gráfico de evolución de gastos obligatorios-->
    <div class="modal fade" id="infoEvolucionObligatorios" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">¿Qué muestra este gráfico?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p>
                        Este gráfico muestra la <strong>evolución de tus gastos obligatorios</strong>
                        durante los últimos 6 meses.
                    </p>

                    <p>
                        Los gastos obligatorios suelen ser <strong>estables en el tiempo</strong>,
                        ya que corresponden a pagos necesarios como vivienda, suministros o seguros.
                    </p>

                    <p>
                        Si las <strong>cantidades se mantienen constantes</strong>,
                        significa que tus gastos básicos están bajo control.
                    </p>

                    <p>
                        Si los valores <strong>empiezan a subir o a bajar de forma notable</strong>,
                        es una señal de que <strong>algo está cambiando</strong> y conviene revisarlo.
                    </p>

                    <p>
                        En algunos casos, este gráfico ayuda a detectar
                        <strong>gastos que quizá no sean realmente obligatorios</strong>
                        o que estén <strong>mal clasificados</strong>.
                    </p>

                    <p class="mt-2">
                        Identificar estas variaciones a tiempo te permite
                        <strong>corregir errores</strong> y tener una visión más real
                        de cuánto te cuesta mantener tu hogar cada mes.
                    </p>

                    <p class="mt-2">
                        Al pasar el cursor sobre el gráfico puedes ver
                        el importe exacto de cada mes.
                    </p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>

            </div>
        </div>
    </div>



    <!--Modal Gráfico de evolución de gastos voluntarios-->

    <div class="modal fade" id="infoEvolucionVoluntarios" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">¿Qué muestra este gráfico?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p>
                        Este gráfico muestra la <strong>evolución de tus gastos voluntarios</strong>
                        durante los últimos 6 meses. Representa de forma visual <strong>cómo se están
                            comportando tus hábitos de consumo</strong>
                        con el paso del tiempo.
                    </p>

                    <p>
                        Si los valores <strong>van aumentando mes a mes</strong>, significa que cada vez
                        estás gastando más, esto provoca que <strong>cada vez puedas ahorrar menos</strong> o incluso que
                        tengas que <strong>usar tus ahorros para llegar a fin de mes</strong>.
                    </p>

                    <p>
                        Si esta situación se mantiene, es una señal clara de que
                        <strong>necesitas hacer algunos cambios</strong>, ya que a largo plazo
                        <strong>no es sostenible</strong>.
                    </p>

                    <p>
                        Si los gastos <strong>van disminuyendo con el paso del tiempo</strong>, vas por buen camino:
                        estás recuperando margen y <strong>mejorando tu capacidad de ahorro</strong>.
                    </p>

                    <p>
                        Si los gastos se <strong>mantienen estables</strong>, tu situación no cambia:
                        no mejoras ni empeoras. Ante cualquier imprevisto, podrías verte obligado a usar ahorros
                        que quizá <strong>no estás consiguiendo generar</strong>.
                    </p>


                    <p class="mt-2">
                        Puedes pasar el cursor sobre cada punto para ver el
                        importe exacto de cada mes.
                    </p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Modal de confirmación genérico -->
    <div class="modal fade" id="modalConfirmacion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalConfirmacionTitulo">Confirmar acción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body" id="modalConfirmacionTexto">
                    ¿Estás seguro?
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-danger" id="modalConfirmacionAceptar">
                        Aceptar
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal informativo genérico (éxito / error) -->
    <div class="modal fade" id="modalInfo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalInfoTitulo">Información</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body" id="modalInfoTexto">
                    <!-- texto dinámico -->
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>

            </div>
        </div>
    </div>




    <!--Añadimos chart.js-->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!--Cargamos token crsf-->
    <script>
        window.CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>";
    </script>


    <!-- Flatpickr: librería base -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <!-- Flatpickr plugin: selección de mes/año -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>

    <!-- Locale ------ español-->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>



    <!--Enlazamos con nuestros arvhicos js-->
    <script src="<?= BASE_URL ?>js/validaciones.js"></script>
    <script src="<?= BASE_URL ?>js/dashboard-graficos.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>js/dashboard-edicion.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>js/dashboard-formularios.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>js/dashboard-utils.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>js/dashboard-dom.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>js/dashboard.js?v=<?= time() ?>"></script>


</body>

</html>