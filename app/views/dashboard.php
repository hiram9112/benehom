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
    <link rel="stylesheet" href="<?=BASE_URL?>css/custom.css">
</head>

<body >
    <!--Mensajes para cuando somos redirigidos después de agregar ingresos o gastos -->
    <?php 
        //Registro exitoso
        if(isset($_SESSION['mensaje_exitoso'])){
            echo"<p class='alert alert-success text-center'>";
            echo $_SESSION['mensaje_exitoso'];
            echo "</p>";

            //eliminamos mensaje para no mostrarlo otra vez
            unset($_SESSION['mensaje_exitoso']);

        }  
        //Error registrando ya existe el usuario inicie sesión o el usaurio o la contrasñea son incorrectos, damos dos usos a esta varible.
        if(isset($_SESSION['mensaje_error'])){
            echo"<p class='alert alert-danger text-center'>";
            echo $_SESSION['mensaje_error'];
            echo"</p>";

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

                    <!--Barra superior con el mes  -->
                    <div id="selector_mes">

                        <!--Selección del mes por defecto dejamos seleccionado el mes actual si el usuario elige otro enviamos al enrutador para que maneje la nueva petición-->
                        <form method="GET" action="index.php" style="margin-bottom:20px;">
                            <input type="hidden" name="r" value="dashboard/index">
                            <input type="month" id="mes" name="mes"
                                value="<?= isset($_GET['mes']) ?$_GET['mes']: date('Y-m') ?>"
                                onchange="this.form.submit()">
                        </form>
                    </div>

                    <!-- Ingresos-->
                    <div class="card mb-3">
                        <div class="card-header ">
                            <h3 class="titulo">Ingresos</h3>
                        </div>
                        <div class="card-body">
                            <form id="formIngresos" class="formulario-bh">
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
                                <input type="hidden" name="mes_seleccionado" value="<?=$mesSeleccionado?>">

                                <button type="submit">Añadir ingreso</button>
                            </form>

                            <!--Contenedor para manejar de manera dinámica los ingresos utilizando AJAX y PHP-->
                            <div id="lista_ingresos" class="lista-ingresos">
                                <?php if(!empty($ingresos)):?>
                                <ul>
                                    <?php foreach($ingresos as $ingreso):?>
                                    <!--Agregamos manejo de id de forma dinámica para usarlo en ajax-->
                                    <li id="ingreso-<?=$ingreso['id']?>">
                                        <span
                                            class="categoria_ingreso_individual"><?=htmlspecialchars(formatearCategoria($ingreso['categoria']))?></span>:
                                        <span class="cantidad_ingreso"
                                            data-id="<?=$ingreso['id']?>"><?=htmlspecialchars($ingreso['cantidad'])?></span>€
                                        <button class="eliminar_ingreso" data-id="<?=$ingreso['id']?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </li>
                                    <?php endforeach;?>
                                </ul>
                                <?php else:?>
                                <p>No tienes ingresos registrados todavía.</p>
                                <?php endif;?>
                            </div>

                            <!--Usaremos este elemento para mostrar de manera dinámica el total de ingresos -->
                            <p id="total_ingresos_texto" class="mt-2 fw-bold total-texto total-ingreso"></p>
                        </div>

                    </div>



                    <!--Gastos obligatorios-->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="titulo">Gastos Obligatorios</h3>
                        </div>
                        <div class="card-body">
                            <form id="formGastosObligatorios" class="formulario-bh">
                                <label for="gastos_obligatorio">Tipo de gasto: </label>
                                <select name="categoria_gasto_obligatorio" id="categoria_gasto_obligatorio" required>
                                    <option value="" selected disabled>Selecciona un tipo de gastos</option>
                                    <option value="vivienda">Vivienda</option>
                                    <option value="Luz">Luz</option>
                                    <option value="agua">Agua</option>
                                    <option value="gas">Gas</option>
                                    <option value="mercado">Mercado</option>
                                    <option value="ropa">Ropa y zapatos</option>
                                    <option value="material_escolar">Material escolar</option>
                                    <option value="material_trabajo">Material de trabajo</option>
                                    <option value="seguros">Seguros</option>
                                    <option value="medicinas">Medicinas</option>
                                    <option value="internet">Internet</option>
                                    <option value="imprevistos">Imprevistos</option>
                                </select>

                                <label for="cantidad_gasto_obligatorio">Cantidad(€):</label>
                                <input type="number" name="cantidad_gasto_obligatorio" id="cantidad_gasto_obligatorio"
                                    step="0.01" required>

                                <!--Enviamos el valor del mes seleccionado , esto será especialmente útil cuando el usuario queira insertar valores en meses pasados-->
                                <input type="hidden" name="mes_seleccionado" value="<?=$mesSeleccionado?>">

                                <button type="submit">Añadir gasto</button>
                            </form>


                            <!--Contenedor para manejar de manera dinámica los gastos obligatorios utilizando AJAX y PHP-->
                            <div id="lista_gastos_obligatorios" class="lista-gastos">
                                <?php if(!empty($gastosObligatorios)):?>
                                <ul>
                                    <?php foreach($gastosObligatorios as $gastoObligatorio):?>
                                    <!--Agregamos manejo de id de forma dinámica para usarlo en ajax-->
                                    <li id="gasto-<?=$gastoObligatorio['id']?>"
                                        data-tipo="<?=$gastoObligatorio['tipo']?>">
                                        <span
                                            class="categoria_gasto_obli"><?=htmlspecialchars(formatearCategoria($gastoObligatorio['categoria']))?></span>:
                                        <span class="cantidad_gasto_obli cantidad_gasto"
                                            data-id="<?=$gastoObligatorio['id']?>"><?=htmlspecialchars($gastoObligatorio['cantidad'])?></span>€
                                        <button class="eliminar_gasto" data-id="<?=$gastoObligatorio['id']?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </li>
                                    <?php endforeach;?>
                                </ul>
                                <?php else:?>
                                <p>No tienes gastos obligatorios registrados todavía.</p>
                                <?php endif;?>
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
                            <h3 class="titulo">Gastos voluntarios</h3>
                        </div>
                        <div class="card-body">
                            <form id="formGastosVoluntarios" class="formulario-bh">
                                <label for="gastos_voluntarios">Tipo de gasto:</label>
                                <select name="categoria_gasto_voluntario" id="categoria_gasto_voluntario" required>
                                    <option value="" selected disabled>Selecciona un tipo de gastos</option>
                                    <option value="comidas_fuera">Comidas fuera</option>
                                    <option value="pedir_comida">Pedir Comida</option>
                                    <option value="suscripciones">Suscripciones</option>
                                    <option value="prestamo">Préstamo coche</option>
                                    <option value="renting_coche">Renting coche</option>
                                    <option value="pretamo_personal">Préstamo personal</option>
                                    <option value="movil">Móvil financiado</option>
                                    <option value="ocio">Compras financiadas</option>
                                    <option value="combustible">Combustible</option>
                                    <option value="donaciones">Donaciones</option>
                                    <option value="otros_gastos_voluntarios">Otros gastos voluntarios</option>
                                </select>

                                <label for="cantidad_gasto_voluntario">Cantidad(€):</label>
                                <input type="number" name="cantidad_gasto_voluntario" id="cantidad_gasto_voluntario"
                                    step="0.01" required>

                                <!--Enviamos el valor del mes seleccionado , esto será especialmente útil cuando el usuario queira insertar valores en meses pasados-->
                                <input type="hidden" name="mes_seleccionado" value="<?=$mesSeleccionado?>">

                                <button type="submit">Añadir gasto</button>
                            </form>

                            <!--Contenedor para manejar de manera dinámica los gastos obligatorios utilizando AJAX y PHP-->
                            <div id="lista_gastos_voluntarios" class="lista-gastos">
                                <?php if(!empty($gastosVoluntarios)):?>
                                <ul>
                                    <?php foreach($gastosVoluntarios as $gastoVoluntario):?>
                                    <!--Agregamos manejo de id de forma dinámica para usarlo en ajax-->
                                    <li id="gasto-<?=$gastoVoluntario['id']?>"
                                        data-tipo="<?=$gastoVoluntario['tipo']?>">
                                        <span
                                            class="categoria_gasto_volun"><?=htmlspecialchars(formatearCategoria($gastoVoluntario['categoria']))?></span>:
                                        <span class="cantidad_gasto_volun cantidad_gasto"
                                            data-id="<?=$gastoVoluntario['id']?>"><?=htmlspecialchars($gastoVoluntario['cantidad'])?></span>€
                                        <button class="eliminar_gasto" data-id="<?=$gastoVoluntario['id']?>"> <i
                                                class="bi bi-trash"></i></button>
                                    </li>
                                    <?php endforeach;?>
                                </ul>
                                <?php else:?>
                                <p>No tienes gastos voluntarios registrados todavía.</p>
                                <?php endif;?>
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
                    <h5 class="mb-3">Presupuesto mensual</h5>
                    <div class="resumen-flex">
                        <p id="totalIngresosTexto" class="fw-bold mb-1 ingreso-resumenMensual"></p>
                        <p id="ahorro_mensual" class="mb-1 fw-bold ahorro-resumenMensual"></p>
                    </div>
                    <canvas id="graficoPresupuestoMensual"></canvas>                    
                </div>

                <!--Gráfico Ahorros 6m-->    
                <div class="card p-3 mb-3 contenedor-grafico">                    
                    <h5 class="mb-3">Evolución Ahorro</h5>
                    <canvas id="graficoAhorros6m"></canvas>                    
                </div>

                <!--Gráfico evolución gastos voluntarios-->
                <div class="card p-3 mb-3 contenedor-grafico">                    
                    <h5 class="mb-3">Evolución Gastos Voluntarios</h5>
                    <canvas id="graficoVoluntarios6m"></canvas>
                </div>

                <!--Gráfico evolución gastos obligatorios-->
                <div class="card p-3 mb-3 contenedor-grafico">                    
                    <h5 class="mb-3">Evolución Gastos Obligatorios</h5>
                    <canvas id="graficoObligatorios6m"></canvas>
                </div>
            </aside>

        </div>
    </div>


    <!--Añadimos chart.js-->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!--Enlazamos con nuestros arvhicos js-->
    <script src="<?=BASE_URL?>js/validaciones.js"></script>
    <script src="<?=BASE_URL?>js/dashboard.js?v=<?=time()?>"></script>


</body>

</html>