//Variable para destruir  gáficos cuando el usuario cambie de mes
let graficoPresupuesto = null;
let graficoVoluntarios6m = null;
let graficoObligatorios6m = null;
let graficoAhorros6m = null;

//Esperamos que el DOM este completamente cargado
document.addEventListener("DOMContentLoaded", () => {

    // Varible para controlar si un gasto esta en edición. Evita que el listener
    // global de clic interfiera con el blur/enter
    let modoEdición = false;
    // ----------------------------------------------------SECCIÓN Enventos submit
    // Formularios-------------------------------------
    // ----------------------------------------------------------------------------------------------------------------------------

    // ------------------------------------------Crear nuevo
    // ingreso-------------------------------------------------- Seleccionamos el
    // fomulario de ingresos
    const formIngresos = document.getElementById("formIngresos");

    // Escuchamos el evento submit y usamos una función de tipo async para poder
    // emplear el await
    formIngresos.addEventListener("submit", async (e) => {
        //evitamos que la pagina se recargue
        e.preventDefault();

        //capturamos los datos del formulario usando FormData
        const datos = new FormData(formIngresos);

        try {
            //Enviamos petición al servidor
            const respuesta = await fetch("index.php?r=ingreso/agregarAjax", {
                method: "POST",
                body: datos
            });

            //Convertimos la respuesta a formato JSON
            const data = await respuesta.json();
            console.log("respuesta del servidor :", data);

            //Si el servidor confirma éxito
            if (data.ok) {
                //LLamamos a la función auxiliar correpondiente
                agregarIngresoAlDOM(data.ingreso);

                //Actualizamos gráficos
                cargarGraficoPresupuesto();
                cargarGraficoVoluntarios6m();
                cargarGraficoObligatorios6m();
                cargarGraficoAhorros6m();

                //Limpiamos campos  del formulario
                formIngresos.reset();
            } else {
                alert(data.msg || "Error al agregar el ingreso");
            }
        } catch (error) {
            console.error("Error en la solicitud AJAX: ", error);
            alert("Error de conexión con el servidor")
        }
    });

    // ------------------------------------------Crear nuevo gasto
    // obligatorio--------------------------------------- Seleccionamos el fomulario
    // de gatos obligatorios
    const formGastosObligatorios = document.getElementById(
        "formGastosObligatorios"
    );

    // Escuchamos el evento submit y usamos una función de tipo async para poder
    // emplear el await
    formGastosObligatorios.addEventListener("submit", async (e) => {
        //evitamos que la pagina se recargue
        e.preventDefault();

        //capturamos los datos del formulario usando FormData
        const datos = new FormData(formGastosObligatorios);

        try {
            //Enviamos petición al servidor
            const respuesta = await fetch("index.php?r=gasto/agregarGastoObligAjax", {
                method: "POST",
                body: datos
            });

            //Convertimos la respuesta a formato JSON
            const data = await respuesta.json();
            console.log("respuesta del servidor :", data);

            //Si el servidor confirma éxito
            if (data.ok) {
                //LLamamos a la función auxiliar correpondiente
                agregarGastoObligAlDOM(data.gasto_oblig);

                //Actualizamos gráficos
                cargarGraficoPresupuesto();
                cargarGraficoVoluntarios6m();
                cargarGraficoObligatorios6m();
                cargarGraficoAhorros6m();

                //Limpiamos campos  del formulario
                formGastosObligatorios.reset();
            } else {
                alert(data.msg || "Error al agregar el gasto obligatorio");
            }
        } catch (error) {
            console.error("Error en la solicitud AJAX: ", error);
            alert("Error de conexión con el servidor")
        }

    });

    // ------------------------------------------Crear nuevo gasto
    // Voluntario--------------------------------------- Seleccionamos el fomulario
    // de gatos Voluntarios
    const formGastosVoluntarios = document.getElementById("formGastosVoluntarios");

    // Escuchamos el evento submit y usamos una función de tipo async para poder
    // emplear el await
    formGastosVoluntarios.addEventListener("submit", async (e) => {
        //evitamos que la pagina se recargue
        e.preventDefault();

        //capturamos los datos del formulario usando FormData
        const datos = new FormData(formGastosVoluntarios);

        try {
            //Enviamos petición al servidor
            const respuesta = await fetch("index.php?r=gasto/agregarGastoVolunAjax", {
                method: "POST",
                body: datos
            });

            //Convertimos la respuesta a formato JSON
            const data = await respuesta.json();
            console.log("respuesta del servidor :", data);

            //Si el servidor confirma éxito
            if (data.ok) {
                //LLamamos a la función auxiliar correpondiente
                agregarGastoVolunAlDOM(data.gasto_volun);

                //Actualizamos gráficos
                cargarGraficoPresupuesto();
                cargarGraficoVoluntarios6m();
                cargarGraficoObligatorios6m();
                cargarGraficoAhorros6m();

                //Limpiamos campos  del formulario
                formGastosVoluntarios.reset();
            } else {
                alert(data.msg || "Error al agregar el gasto voluntario");
            }
        } catch (error) {
            console.error("Error en la solicitud AJAX: ", error);
            alert("Error de conexión con el servidor")
        }

    });

    // ----------------------------------------------------SECCIÓN EVENTOS
    // CLICKS----------------------------------------------------------
    // ----------------------------------------------------------------------------------------------------------------------------

    document.addEventListener("click", async (e) => {

        //Bloqueamos escucha si el modo edición está activo
        if (modoEdición)
            return;

        // ---------------------------------------Eliminar un
        // ingreso---------------------------------------- Verificamos si el click es
        // para eliminar un ingreso
        if (e.target.closest(".eliminar_ingreso")) {

            //recogemos el id del ingreso que se va a eliminar
            const id = e
                .target
                .closest(".eliminar_ingreso")
                .dataset
                .id;

            //Pedimos confirmación
            if (!confirm("¿Seguro que quieres eliminar este ingreso?"))
                return;

            try {
                //Creamos un FORMDATA para enviar el id
                const datos = new FormData();
                datos.append("id", id);

                //Enviamos petición al servidor
                const respuesta = await fetch("index.php?r=ingreso/eliminarAjax", {
                    method: "POST",
                    body: datos
                });

                //Recogemos respuesta del servidor
                const data = await respuesta.json();

                //Eliminamos el ingreso del DOM si todo fue bien en servidor
                if (data.ok) {
                    //Llamamos a la función auxiliar correspondiente
                    eliminarIngresoDelDOM(id);

                    //Actualizamos gráficos
                    cargarGraficoPresupuesto();
                    cargarGraficoVoluntarios6m();
                    cargarGraficoObligatorios6m();
                    cargarGraficoAhorros6m();
                } else {
                    alert(data.msg || "Error al eliminar el ingreso");
                }
            } catch (error) {
                console.error("Error AJAX:", error);
                alert("Error de conexión con el servidor");
            }
        }

        // ---------------------------------------Actualizar un
        // ingreso------------------------------------------- Verificamos si el click es
        // para modificar un ingreso
        if (e.target.classList.contains("cantidad_ingreso")) {
            //Llamamos a la función auxiliar correspondiente
            editarIngresoInline(e.target);

        }

        // ---------------------------------------Eliminar un gasto
        // ----------------------------------------

        // Verificamos si el click es para eliminar un gasto como los tenenmos tenemos
        // todo en una misma tabla podemos gestionar todos los gastos juntos
        if (e.target.closest(".eliminar_gasto")) {

            //recogemos el id del gasto que se va a eliminar
            const id = e
                .target
                .closest(".eliminar_gasto")
                .dataset
                .id;

            //Pedimos confirmación
            if (!confirm("¿Seguro que quieres eliminar este gasto ?"))
                return;

            try {
                //Creamos un FORMDATA para enviar el id
                const datos = new FormData();
                datos.append("id", id);

                //Enviamos petición al servidor
                const respuesta = await fetch("index.php?r=gasto/eliminarGastoAjax", {
                    method: "POST",
                    body: datos
                });

                //Recogemos respuesta del servidor
                const data = await respuesta.json();

                //Eliminamos el ingreso del DOM si todo fue bien en servidor
                if (data.ok) {
                    //Llamamos a la función auxiliar correspondiente
                    eliminarGastoDelDOM(id);

                    //Actualizamos gráficos
                    cargarGraficoPresupuesto();
                    cargarGraficoVoluntarios6m();
                    cargarGraficoObligatorios6m();
                    cargarGraficoAhorros6m();
                } else {
                    alert(data.msg || "Error al eliminar el gasto");
                }
            } catch (error) {
                console.error("Error AJAX:", error);
                alert("Error de conexión con el servidor");
            }
        }

        // ---------------------------------------Actualizar un gasto
        // -------------------------------------------

        //Verificamos si el click es para modificar un gasto
        if (e.target.classList.contains("cantidad_gasto")) {
            //Llamamos a la función auxiliar correspondiente
            editarGastoInline(e.target);

        }

    });

    // ----------------------------------------------------------------------------------------------------------------------------------------------------------
    // ----------------------Sección para generar gráficos usandoChart.js--------------------------------------- 


    //--------------------------------------------------------------Función para Grafico de presupuesto----------------------------------------------------------------------------------------
    async function cargarGraficoPresupuesto() {

        // Seleccionamos el canvas donde irá el gráfico
        const ctx = document
            .getElementById("graficoPresupuestoMensual")
            .getContext("2d");       



        //Recogemos el valor del mes selecionado
        const mesSeleccionado = document
            .getElementById("mes")
            .value;

        //Preparamos la consulta
        const datos = new FormData();
        datos.append("mes", mesSeleccionado);

        try {

            //Enviamos la consulta y recogemos la respuesta
            const respuesta = await fetch("index.php?r=graficos/estadoGeneral", {
                method: "POST",
                body: datos
            });

            const data = await respuesta.json();

            if (!data.ok) {
                console.error(data.msg);
                return;
            }

            const valores = data.data;

            // Aprovechamos el calculo delos totale y la impletación de esta función para
            // actualizar  los totales de cada fomulario con una sola línea
            actualizarTotales(valores);            

            //Si ya había un gráfico lo destruimos para actualizarlo
            if (graficoPresupuesto) {
                graficoPresupuesto.destroy();
                graficoPresupuesto = null;
            }

            //Creamos el gráfico
            graficoPresupuesto = new Chart(ctx, {
                type: "bar",
                data: {
                    //Introducimos al gráfico cada dato con su valor correspondiente
                    labels: [
                        "Ingresos", "Gastos Totales", "Ahorro real"
                    ],
                    datasets: [
                        {
                            data: [
                                valores.ingresos, valores.gastosTotales, valores.ahorroReal
                            ],

                            //Configuramos el estilo de las barras, si el ahorro es positivo mostramos un color si es negativo mostramos otro                            
                            backgroundColor: ["#4ECDC4", "#FFA648", valores.ahorroReal >= 0 ? "#4A90E2" : "#FF6B6B"],
                            borderRadius: 4,
                            barThickness: 28
                        }
                    ]
                },
                options: {
                    indexAxis: 'x',
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction:{
                        mode:"nearest",
                        intersect:false
                    },
                    hover:{
                        mode:"nearest",
                        intersect:false
                    },
                    layout: {
                        padding: {
                            top: 5,
                            bottom:80,
                            left: 0,
                            right: 0
                        }

                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: "rgba(0,0,0,0.08)"
                            },
                            ticks: {
                                font: { size: 11 },
                                padding: 6,
                                color:"#000000",
                                //agregamos el simbolo € al eje y
                                callback: function(value){
                                    return value+" €";
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip:{
                            //agragamos simbolo de € a los valores
                            callbacks:{
                                label:function(context){
                                    const nombres=["Ingresos","Gastos Totales","Ahorro Real"];
                                    return nombres[context.dataIndex] + ": " + context.raw +"€";
                                }
                            }

                        },
                        legend: {
                            position: "bottom",
                            //Generamos etiquetas personalizadas con porcentajes
                            labels: {
                                font: {
                                    family: "Arial",
                                    size: 13
                                },
                                usePointStyle: true,
                                pointStyle: "rectRounded",
                                pointStyleWidth: 14,

                                generateLabels(chart) {

                                    // Cremos referencia a nuestro dataset para facilitar el acceso y evitar
                                    // repertir código
                                    const dataset = chart
                                        .data
                                        .datasets[0];

                                    //Recogemos los valroes necesario para los calculos
                                    const ingresos = dataset.data[0];
                                    const gastosTotales = dataset.data[1];
                                    const ahorroReal = dataset.data[2];

                                    //Creamos un array para alcmacenar las etiquetas personalizadas
                                    const etiquetasFinales = [];

                                    //Recorremos las etiquetas actuales
                                    chart.data.labels.forEach((label, index) => {
                                        let valor = dataset.data[index];
                                        let porcentaje = null

                                        //Solo calculamos porcentaje para gastos totales y ahorro
                                        if (index === 1 || index === 2) {
                                            porcentaje = ingresos > 0 ? ((valor / ingresos) * 100).toFixed(1) : 0;
                                        }

                                        //construimos etiqueta 
                                        const texto = porcentaje !== null ? `${label}(${porcentaje}%)` : label;

                                        //agregamos al array de etiquetas personalizadas
                                        etiquetasFinales.push({
                                            text: texto,
                                            fillStyle: dataset.backgroundColor[index],
                                            strokeStyle: dataset.backgroundColor[index],
                                            pointStyle: "rectRounded",
                                            pointStyleWidth: 14,
                                            hidden: false
                                        });

                                    });


                                    return etiquetasFinales;

                                }
                            }
                        }
                    }
                }
            });

        } catch (error) {
            console.error("Error cargando gráfico: ", error);
        }
    }


    //---------------------------------------------------------------Fucnión para Gráfico de evolución de gastos voluntarios------------------------------------------------
    async function cargarGraficoVoluntarios6m() {

        // Seleccionamos el canvas donde irá el gráfico
        const ctx = document
            .getElementById("graficoVoluntarios6m")
            .getContext("2d");


        //Recogemos el valor del mes selecionado
        const mesSeleccionado = document
            .getElementById("mes")
            .value;

        //Preparamos la consulta
        const datos = new FormData();
        datos.append("mes", mesSeleccionado);
        datos.append("tipo", "voluntario");

        try {

            //Enviamos la consulta y recogemos la respuesta
            const respuesta = await fetch("index.php?r=graficos/gastos6m", {
                method: "POST",
                body: datos
            });

            const data = await respuesta.json();

            if (!data.ok) {
                console.error(data.msg);
                return;
            }

            //Almacenamos los meses y los valores en variables independientes
            const meses = data.data.meses;
            const valores = data.data.valores;



            //Si ya había un gráfico lo destruimos para actualizarlo
            if (graficoVoluntarios6m) {
                graficoVoluntarios6m.destroy();
                graficoVoluntarios6m = null;
            }

            //Creamos el gráfico
            graficoVoluntarios6m = new Chart(ctx, {
                type: "line",
                data: {
                    //Introducimos al gráfico cada dato con su valor correspondiente
                    labels: meses,
                    datasets: [
                        {
                            label: "Gastos Voluntarios",
                            data: valores,
                            borderColor: "#4ECDC4",
                            backgroundColor: "rgba(74,144,226,0.25)",
                            borderWidth: 2,
                            pointRadius: 4,
                            pointBackgroundColor: "#4ECDC4",
                            tension: 0.35
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout:{
                        padding:{
                            top:5,
                            bottom:40,
                            left:0,
                            right:0
                        }                        
                    },
                    scales: {
                        x: {
                            ticks: {
                                font: { size: 12 },
                                color: "#000000"
                            },
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: "rgba(0,0,0,0.08)"
                            },
                            ticks: {
                                stepSize: 500,
                                font: { size: 12 },
                                color: "#000000",
                                //Agregamos simbolo € al eje y
                                callback:function(value){
                                    return value+ " €";
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip:{
                            callbacks:{
                                label:function(context){
                                    return "Gasto: " + context.raw +"€";
                                }
                            }
                        }
                    }
                }
            });

        } catch (error) {
            console.error("Error cargando gráfico: ", error);
        }
    }

    //---------------------------------------------------------------Fucnión para Gráfico de evolución de gastos obligatorios------------------------------------------------
    async function cargarGraficoObligatorios6m() {

        // Seleccionamos el canvas donde irá el gráfico
        const ctx = document
            .getElementById("graficoObligatorios6m")
            .getContext("2d");


        //Recogemos el valor del mes selecionado
        const mesSeleccionado = document
            .getElementById("mes")
            .value;

        //Preparamos la consulta
        const datos = new FormData();
        datos.append("mes", mesSeleccionado);
        datos.append("tipo", "obligatorio");

        try {

            //Enviamos la consulta y recogemos la respuesta
            const respuesta = await fetch("index.php?r=graficos/gastos6m", {
                method: "POST",
                body: datos
            });

            const data = await respuesta.json();

            if (!data.ok) {
                console.error(data.msg);
                return;
            }

            //Almacenamos los meses y los valores en variables independientes
            const meses = data.data.meses;
            const valores = data.data.valores;



            //Si ya había un gráfico lo destruimos para actualizarlo
            if (graficoObligatorios6m) {
                graficoObligatorios6m.destroy();
                graficoObligatorios6m = null;
            }

            //Creamos el gráfico
            graficoObligatorios6m = new Chart(ctx, {
                type: "line",
                data: {
                    //Introducimos al gráfico cada dato con su valor correspondiente
                    labels: meses,
                    datasets: [
                        {
                            label: "Gastos Obligatorios",
                            data: valores,
                            borderColor: "#4ECDC4",
                            backgroundColor: "rgba(74,144,226,0.25)",
                            borderWidth: 2,
                            pointRadius: 4,
                            pointBackgroundColor: "#4ECDC4",
                            tension: 0.35
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout:{
                        padding:{
                            top:5,
                            bottom:40,
                            left:0,
                            right:0
                        }                        
                    },
                    scales: {
                        x: {
                            ticks: {
                                font: { size: 12 },
                                color: "#000000"
                            },
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: "rgba(0,0,0,0.08)"
                            },
                            ticks: {
                                stepSize: 500,
                                font: { size: 12 },
                                color: "#000000",
                                callback:function(value){
                                    return value+ " €";
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip:{
                            callbacks:{
                                label:function(context){
                                    return "Gasto: " + context.raw +"€";
                                }
                            }
                        }
                    }
                }
            });

        } catch (error) {
            console.error("Error cargando gráfico: ", error);
        }
    }

    async function cargarGraficoAhorros6m() {

        // Seleccionamos el canvas donde irá el gráfico
        const ctx = document
            .getElementById("graficoAhorros6m")
            .getContext("2d");


        //Recogemos el valor del mes selecionado
        const mesSeleccionado = document
            .getElementById("mes")
            .value;

        //Preparamos la consulta
        const datos = new FormData();
        datos.append("mes", mesSeleccionado);

        try {

            //Enviamos la consulta y recogemos la respuesta
            const respuesta = await fetch("index.php?r=graficos/ahorros6m", {
                method: "POST",
                body: datos
            });

            const data = await respuesta.json();

            if (!data.ok) {
                console.error(data.msg);
                return;
            }

            const meses = data.data.meses;
            const capacidad = data.data.capacidad;
            const ahorroReal = data.data.ahorroReal;


            //Si ya había un gráfico lo destruimos para actualizarlo
            if (graficoAhorros6m) {
                graficoAhorros6m.destroy();
                graficoAhorros6m = null;
            }

            //Creamos el gráfico
            graficoAhorros6m = new Chart(ctx, {
                type: "bar",
                data: {
                    //Introducimos al gráfico cada dato con su valor correspondiente
                    labels: meses,
                    datasets: [
                        {
                            label: "Capacidad de ahorro",
                            data: capacidad,
                            backgroundColor: capacidad.map(v => v >= 0 ? "#4ECDC4" : "#FF6B6B"),
                            barPercentage:0.9,
                            categoryPercentage:0.6,

                        },
                        {
                            label: "Ahorro Real",
                            data: ahorroReal,
                            backgroundColor: ahorroReal.map(v => v >= 0 ? "#1A535C" : "#FF6B6B"),
                            barPercentage:0.9,
                            categoryPercentage:0.6
                        }
                    ]
                },
                options: {
                    indexAxis: 'x',
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction:{
                        mode:"point",
                        intersect:true
                    },
                    hover:{
                        mode:"point",
                        intersect:true
                    },
                    layout: {
                        padding: {
                            top: 5,
                            bottom: 40,
                            left: 0,
                            right: 0
                        }

                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: "rgba(0,0,0,0.08)"
                            },
                            ticks: {
                                font: { size: 11 },
                                color: "#000000",
                                padding: 6,
                                callback:function(value){
                                    return value  + " €";
                                }
                            }
                        }
                    },

                    plugins: {
                        legend: {
                            position: "bottom",
                            //Generamos leyenda personalizad para mostrar lso tres oclores incluyendo el de valores negativos
                            labels: {
                                font: {
                                    family: "Arial",
                                    size: 13
                                },
                                usePointStyle: true,
                                pointStyle: "rectRounded",
                                pointStyleWidth: 14,

                                generateLabels() {
                                    return [
                                        {
                                            text: "Capacidad (+)",
                                            fillStyle: "#4ECDC4",
                                            strokeStyle: "#4ECDC4",
                                            pointStyle: "rectRounded"
                                        },
                                        {
                                            text: "Ahorro (+)",
                                            fillStyle: "#1A535C",
                                            strokeStyle: "#1A535C",
                                            pointStyle: "rectRounded"
                                        },
                                        {
                                            text: "Valores (-)",
                                            fillStyle: "#FF6B6B",
                                            strokeStyle: "#FF6B6B",
                                            pointStyle: "rectRounded"
                                        }

                                    ];
                                }
                            }
                        },
                        tooltip:{
                            callbacks:{
                                label:function(context){
                                    return context.dataset.label + ": " + context.raw + " €";
                                }
                            }
                        }

                    }
                }
            });

        } catch (error) {
            console.error("Error cargando gráfico: ", error);
        }
    }

    //Cargamos el gráfico al entrar
    cargarGraficoAhorros6m();
    cargarGraficoPresupuesto();
    cargarGraficoVoluntarios6m();
    cargarGraficoObligatorios6m();

    //Actulizamos el gráfico al cambiar el mes
    document
        .getElementById("mes")
        .addEventListener("change", () => {
            cargarGraficoPresupuesto();
            cargarGraficoVoluntarios6m();
            cargarGraficoObligatorios6m();
            cargarGraficoAhorros6m();
        });

    //Hacemos globales las funciones que actualizan los gráficos
    window.cargarGraficoPresupuesto = cargarGraficoPresupuesto;
    window.cargarGraficoVoluntarios6m = cargarGraficoVoluntarios6m;
    window.cargarGraficoObligatorios6m = cargarGraficoObligatorios6m;
    window.cargarGraficoAhorros6m = cargarGraficoAhorros6m;
});

// -------------------------------------------------------------------------------------------------------------
// --------------------------FUNCIONES AUXILIARES SECCIÓN
// INGRESOS----------------------------------------------
// -------------------------------------------Función para agregar el nuevo
// Ingreso al DOM----------------------

function agregarIngresoAlDOM(ingreso) {

    //Intentamos seleccionar  la lista existente
    let lista = document.querySelector('#lista_ingresos ul');

    // Si existe el mensaje de "No tienes ingresos..." lo eliminamos antes de
    // insertar
    const mensaje = document.querySelector('#lista_ingresos p');
    if (mensaje)
        mensaje.remove();

    //Si no existe la etiqueta ul la creamos
    if (!lista) {
        lista = document.createElement("ul");
        document
            .getElementById("lista_ingresos")
            .appendChild(lista);
    }

    //creamos el elemento li que representa el nuevo ingreso
    const li = document.createElement("li");

    //le asignamos el id correspondiente
    li.id = `ingreso-${ingreso.id}`;

    //Agregamos el nuevo ingreso al elemento li
    li.innerHTML = `
    <span class="categoria_ingreso_individual">${formatearCategoriaJS(
        ingreso.categoria
    )}</span>: 
    <span class="cantidad_ingreso" data-id="${ingreso.id}">${ingreso.cantidad}</span>€
    <button class="eliminar_ingreso" data-id="${ingreso.id}"><i class="bi bi-trash"></i>
    </button>
    `;

    //Insertamos el nuevo elemento al inicio de la lista
    lista.prepend(li);
}

// -------------------------------------Función para eliminar  ingreso del
// DOM----------------------------------

function eliminarIngresoDelDOM(id) {
    //Seleccionamos el elemento correspondiente
    const li = document.getElementById("ingreso-" + id);

    //Si existe lo eliminamos
    if (li)
        li.remove();

    //Comprbamos si la lista sigue existiendo y si tiene elementos
    const lista = document.querySelector("#lista_ingresos ul");

    if (!lista || lista.children.length === 0) {
        document
            .getElementById("lista_ingresos")
            .innerHTML = "<p>No tienes ingresos registrados todavía.</p>";
    }

}

// --------------------------------------Función para actualizar un
// ingreso---------------------------------------
async function editarIngresoInline(span) {

    //Guaradmos el id del ingreso y el valor actual
    const id = span.dataset.id;
    const valorActual = span.textContent;

    //Creamos input para permitir la edición
    const input = document.createElement("input");
    input.type = "number";
    input.step = "0.01";
    input.value = valorActual;
    input
        .classList
        .add("input-edicion");

    // Reemplazamos el elemento span por el el input y para permitir escribir al
    // usuario
    span.replaceWith(input);
    input.focus();

    //Función para guardar los cambios
    const guardar = async () => {

        //Recogemos el nuevo valor
        const nuevoValor = input.value;

        //Preparemos y hacemos la petición al servidor
        const datos = new FormData();
        datos.append("id", id);
        datos.append("cantidad", nuevoValor);

        const respuesta = await fetch("index.php?r=ingreso/editarAjax", {
            method: "POST",
            body: datos
        });

        //Recogemos la respuesta del servidor
        const data = await respuesta.json();

        if (data.ok) {

            //Creamos nuevo span actualizado
            const nuevoSpan = document.createElement("span");
            nuevoSpan.textContent = nuevoValor;
            nuevoSpan.dataset.id = id;
            nuevoSpan
                .classList
                .add("cantidad_ingreso");

            //Reemplazamos el input con el span que contiene el nuevo valor
            input.replaceWith(nuevoSpan);

            //Actualizamos gráficos 
            window.cargarGraficoPresupuesto();
            window.cargarGraficoVoluntarios6m();
            window.cargarGraficoObligatorios6m();
            window.cargarGraficoAhorros6m();
        } else {

            //SI falla la edición restauramos el valor anterior
            alert("Error al editar el ingreso");
            input.replaceWith(span);
        }
    }

    //Agregamos escucha para guardar con enter y cuando pierda el foco

    input.addEventListener("keydown", (ev) => {
        if (ev.key === "Enter")
            guardar();
    }
    );

    input.addEventListener("blur", guardar);

}

// --------------------------------------------------------------------------------------------------------------------------------
// --------------------------FUNCIONES AUXILIARES SECCIÓN GASTOS
// ------------------------------------------------------

// -------------------------------------------Función para agregar el nuevo
// gasto obligatorio al DOM----------------------

function agregarGastoObligAlDOM(gasto_oblig) {

    //Intentamos seleccionar  la lista existente
    let lista = document.querySelector('#lista_gastos_obligatorios ul');

    //Si existe el mensaje de "No tienes gastos..." lo eliminamos antes de insertar
    const mensaje = document.querySelector('#lista_gastos_obligatorios p');
    if (mensaje)
        mensaje.remove();

    //Si no existe la etiqueta ul la creamos
    if (!lista) {
        lista = document.createElement("ul");
        document
            .getElementById("lista_gastos_obligatorios")
            .appendChild(lista);
    }

    //creamos el elemento li que representa el nuevo gasto obligatorio
    const li = document.createElement("li");

    //le asignamos el id correspondiente y tipo correspondiente
    li.id = `gasto-${gasto_oblig.id}`;
    li.dataset.tipo = "obligatorio";

    //Agregamos el nuevo gasto obligatorio al elemento li
    li.innerHTML = `
    <span class="categoria_gasto_obli">${formatearCategoriaJS(
        gasto_oblig.categoria
    )}</span>: 
    <span class="cantidad_gasto_obli cantidad_gasto" data-id="${gasto_oblig.id}">${gasto_oblig.cantidad}</span>€
    <button class="eliminar_gasto" data-id="${gasto_oblig.id}"><i class="bi bi-trash"></i></button>
    `;

    //Insertamos el nuevo elemento al inicio de la lista
    lista.prepend(li);

}

// -------------------------------------------Función para agregar el nuevo
// gasto Voluntario al DOM----------------------

function agregarGastoVolunAlDOM(gasto_volun) {

    //Intentamos seleccionar  la lista existente
    let lista = document.querySelector('#lista_gastos_voluntarios ul');

    //Si existe el mensaje de "No tienes gastos..." lo eliminamos antes de insertar
    const mensaje = document.querySelector('#lista_gastos_voluntarios p');
    if (mensaje)
        mensaje.remove();

    //Si no existe la etiqueta ul la creamos
    if (!lista) {
        lista = document.createElement("ul");
        document
            .getElementById("lista_gastos_voluntarios")
            .appendChild(lista);
    }

    //creamos el elemento li que representa el nuevo gasto voluntario
    const li = document.createElement("li");

    //le asignamos el id y el tipo correspondiente
    li.id = `gasto-${gasto_volun.id}`;
    li.dataset.tipo = "voluntario";

    //Agregamos el nuevo gasto voluntario al elemento li
    li.innerHTML = `
    <span class="categoria_gasto_volun">${formatearCategoriaJS(
        gasto_volun.categoria
    )}</span>: 
    <span class="cantidad_gasto_volun cantidad_gasto"  data-id="${gasto_volun.id}">${gasto_volun.cantidad}</span>€
    <button class="eliminar_gasto" data-id="${gasto_volun.id}"><i class="bi bi-trash"></i></button>
    `;

    //Insertamos el nuevo elemento al inicio de la lista
    lista.prepend(li);

}

// -------------------------------------Función para eliminar  gasto del
// DOM----------------------------------

function eliminarGastoDelDOM(id) {
    //Seleccionamos el elemento correspondiente
    const li = document.getElementById("gasto-" + id);

    //Obtenemos el tipo antes de eliminar
    const tipo = li.dataset.tipo;

    //Si existe lo eliminamos
    if (li)
        li.remove();

    //Determinamos que contenedor revisaremos
    const contenedorID = tipo === "obligatorio"
        ? "lista_gastos_obligatorios"
        : "lista_gastos_voluntarios";

    //Seleccionamos la lista correspondiente
    const lista = document.querySelector(`#${contenedorID} ul`);

    if (!lista || lista.children.length === 0) {
        document
            .getElementById(contenedorID)
            .innerHTML = `<p>No tienes gastos ${tipo}s registrados todavía.</p>`;
    }

}

// --------------------------------------Función para actualizar un gasto
// -----------------------------------
async function editarGastoInline(span) {

    //Activamos modo edición
    modoEdición = true;

    //Guaradmos el id del ingreso y el valor actual
    const id = span.dataset.id;
    const valorActual = span.textContent;

    //Creamos input para permitir la edición
    const input = document.createElement("input");
    input.type = "number";
    input.step = "0.01";
    input.value = valorActual;
    input
        .classList
        .add("input-edicion");

    // Reemplazamos el elemento span por el el input y para permitir escribir al
    // usuario
    span.replaceWith(input);
    input.focus();

    //Función para guardar los cambios
    const guardar = async () => {

        //Recogemos el nuevo valor
        const nuevoValor = input.value;

        //Preparemos y hacemos la petición al servidor
        const datos = new FormData();
        datos.append("id", id);
        datos.append("cantidad", nuevoValor);

        const respuesta = await fetch("index.php?r=gasto/editarGastoAjax", {
            method: "POST",
            body: datos
        });

        //Recogemos la respuesta del servidor
        const data = await respuesta.json();

        if (data.ok) {

            //Recuperamos el li más cercano para obetener el tipo de gasto
            const li = input.closest("li");
            const tipo = li.dataset.tipo;

            //Creamos nuevo span actualizado
            const nuevoSpan = document.createElement("span");
            nuevoSpan.textContent = nuevoValor;
            nuevoSpan.dataset.id = id;

            //Agregamos la clase según el tipo de gasto
            if (tipo === "obligatorio") {
                nuevoSpan
                    .classList
                    .add("cantidad_gasto_obli", "cantidad_gasto");
            } else {
                nuevoSpan
                    .classList
                    .add("cantidad_gasto_volun", "cantidad_gasto");
            }

            //Reemplazamos el input con el span que contiene el nuevo valor
            input.replaceWith(nuevoSpan);

            //Actualizamos gráficos
            window.cargarGraficoPresupuesto();
            window.cargarGraficoVoluntarios6m();
            window.cargarGraficoObligatorios6m();
            window.cargarGraficoAhorros6m();
        } else {

            //SI falla la edición restauramos el valor anterior
            alert("Error al editar el gasto obligatorio");
            input.replaceWith(span);
        }

        //desactivamos modo edición porque ya terminó
        modoEdición = false;
    }

    //Agregamos escucha para guardar con enter y cuando pierda el foco

    input.addEventListener("keydown", (ev) => {
        if (ev.key === "Enter")
            guardar();
    }
    );

    input.addEventListener("blur", guardar);

}

// ---------------------------------------Función para editar formato de
// categorias--------------------------------

function formatearCategoriaJS(texto) {

    //Reemplazamos los "_" por espacios en blanco
    texto = texto.replace(/_/g, " ");

    //Poenmos en mayúsculas el inicio de cada palabra
    return texto.replace(/\b\w/g, letra => letra.toUpperCase());

}

// ---------------------------------------Función para actualizar
// totales---------------------------------------------

function actualizarTotales(valores) {
    const tIngresos = Number(valores.ingresos).toFixed(2);
    const tOblig = Number(valores.obligatorios).toFixed(2);
    const tVolun = Number(valores.voluntarios).toFixed(2);
    const tAhorro = Number(valores.ahorroReal).toFixed(2);

    //Calculamos capacidad de ahorro

    const capacidad = Number(tIngresos - tOblig).toFixed(2);
    //Totales de las tarjetas
    document.getElementById("total_ingresos_texto").innerHTML = `Ingresos del mes: <strong>${tIngresos}€</strong>`;
    document.getElementById("total_gastos_obligatorios_texto").innerHTML = `Gastos obligatorios del mes: <strong>${tOblig}€</strong>`;
    document.getElementById("capacidad_ahorro_texto").innerHTML = `Capacidad de ahorro: <strong>${capacidad}</strong>`;
    document.getElementById("total_gastos_voluntarios_texto").innerHTML = `Gastos voluntarios  del mes: <strong>${tVolun}€</strong>`;
    document.getElementById("ahorro_real_texto").innerHTML = `Ahorro del mes: <strong>${tAhorro}€</strong>`;
    
    
    //Totales del primero gráfico
    document.getElementById("ahorro_mensual").innerHTML = `Ahorro: <strong>${tAhorro}€</strong>`;    
    document.getElementById("totalIngresosTexto").innerHTML = `Ingresos: <strong>${tIngresos}</strong>`;

    ////Asignamos colores de manera dinámica a los totales del primer gráfico
    const ingresoSUp=document.getElementById("totalIngresosTexto");
    const ahorroSup=document.getElementById("ahorro_mensual");

    //Rmovemos clases anteriores
    ingresoSUp.classList.remove("valor-positivo","valor-negativo");
    ahorroSup.classList.remove("valor-positivo","valor-negativo");

    //Aplicamos color según valor al ahorro y fijo al ingreso
    ingresoSUp.classList.add( "valor-positivo");
    ahorroSup.classList.add(tAhorro>=0 ? "valor-positivo" : "valor-negativo");

    
    //Asignamos colores de manera dinámica a los totales de las tarjetas
    const capacidadElem=document.getElementById("capacidad_ahorro_texto");
    const ahorroElem=document.getElementById("ahorro_real_texto");

    //Rmovemos clases anteriores
    capacidadElem.classList.remove("valor-positivo","valor-negativo");
    ahorroElem.classList.remove("valor-positivo","valor-negativo");

    //Aplicamos color según valor
    capacidadElem.classList.add(capacidad>=0 ? "valor-positivo" : "valor-negativo");
    ahorroElem.classList.add(tAhorro>=0 ? "valor-positivo" : "valor-negativo");

}


