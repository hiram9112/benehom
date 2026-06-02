//Variable para destruir  gáficos cuando el usuario cambie de mes
let graficoPresupuesto = null;
let graficoGastosFlexibles6m = null;
let graficoGastosEsenciales6m = null;
let graficoAhorros6m = null;

// ----------------------------------------------------------------------------------------------------------------------------------------------------------
// ----------------------Sección para generar gráficos usandoChart.js---------------------------------------

//--------------------------------------------------------------Función para Grafico de presupuesto----------------------------------------------------------------------------------------
async function cargarGraficoPresupuesto() {
  // Seleccionamos el canvas donde irá el gráfico
  const ctx = document
    .getElementById("graficoPresupuestoMensual")
    .getContext("2d");

  //Recogemos el valor del mes selecionado
  const mesSeleccionado = document.getElementById("mes").value;

  //Preparamos la consulta
  const datos = new FormData();
  datos.append("mes", mesSeleccionado);
  datos.append("_csrf", window.CSRF_TOKEN);

  try {
    //Enviamos la consulta y recogemos la respuesta
    const respuesta = await fetch("index.php?r=graficos/estadoGeneral", {
      method: "POST",
      body: datos,
    });

    const data = await respuesta.json();

    if (!data.ok) {
      abrirModalInfo({
        titulo: "No se pudo cargar el gráfico",
        mensaje:
          "No fue posible obtener los datos. Inténtalo de nuevo más tarde.",
      });
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
        labels: ["Ingresos", "Gastos totales", "Ahorro real"],
        datasets: [
          {
            data: [valores.ingresos, valores.gastosTotales, valores.ahorroReal],

            //Configuramos el estilo de las barras, si el ahorro es positivo mostramos un color si es negativo mostramos otro
            backgroundColor: [
              "#4ECDC4",
              "#FFA648",
              valores.ahorroReal >= 0 ? "#4A90E2" : "#FF6B6B",
            ],
            borderRadius: 4,
            barThickness: 28,
          },
        ],
      },
      options: {
        indexAxis: "x",
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: "nearest",
          intersect: false,
        },
        hover: {
          mode: "nearest",
          intersect: false,
        },
        layout: {
          padding: {
            top: 5,
            bottom: 44,
            left: 0,
            right: 0,
          },
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { display: false },
          },
          y: {
            beginAtZero: true,
            grid: {
              color: "rgba(0,0,0,0.08)",
            },
            ticks: {
              font: { size: 11 },
              padding: 6,
              color: "#000000",
              //agregamos el simbolo € al eje y
              callback: function (value) {
                return value + " €";
              },
            },
          },
        },
        plugins: {
          tooltip: {
            //agragamos simbolo de € a los valores
            callbacks: {
              label: function (context) {
                const nombres = ["Ingresos", "Gastos totales", "Ahorro real"];
                return nombres[context.dataIndex] + ": " + context.raw + "€";
              },
            },
          },
          legend: {
            position: "bottom",
            //Generamos etiquetas personalizadas con porcentajes
            labels: {
              font: {
                family: "Arial",
                size: 13,
              },
              usePointStyle: true,
              pointStyle: "rectRounded",
              pointStyleWidth: 14,

              generateLabels(chart) {
                // Cremos referencia a nuestro dataset para facilitar el acceso y evitar
                // repertir código
                const dataset = chart.data.datasets[0];

                //Recogemos los valroes necesario para los calculos
                const ingresos = dataset.data[0];
                const gastosTotales = dataset.data[1];
                const ahorroReal = dataset.data[2];

                //Creamos un array para alcmacenar las etiquetas personalizadas
                const etiquetasFinales = [];

                //Recorremos las etiquetas actuales
                chart.data.labels.forEach((label, index) => {
                  let valor = dataset.data[index];
                  let porcentaje = null;

                  //Solo calculamos porcentaje para gastos totales y ahorro
                  if (index === 1 || index === 2) {
                    porcentaje =
                      ingresos > 0 ? ((valor / ingresos) * 100).toFixed(1) : 0;
                  }

                  //construimos etiqueta
                  const texto =
                    porcentaje !== null ? `${label}(${porcentaje}%)` : label;

                  //agregamos al array de etiquetas personalizadas
                  etiquetasFinales.push({
                    text: texto,
                    fillStyle: dataset.backgroundColor[index],
                    strokeStyle: dataset.backgroundColor[index],
                    pointStyle: "rectRounded",
                    pointStyleWidth: 14,
                    hidden: false,
                  });
                });

                return etiquetasFinales;
              },
            },
          },
        },
      },
    });
  } catch (error) {
    abrirModalInfo({
      titulo: "Problema de conexión",
      mensaje: "No se pudo contactar con el servidor para cargar el gráfico.",
    });
  }
}

//---------------------------------------------------------------Fucnión para Gráfico de evolución de gastos flexibles------------------------------------------------
async function cargarGraficoGastosFlexibles6m() {
  // Seleccionamos el canvas donde irá el gráfico
  const ctx = document.getElementById("graficoGastosFlexibles6m").getContext("2d");

  //Recogemos el valor del mes selecionado
  const mesSeleccionado = document.getElementById("mes").value;

  //Preparamos la consulta
  const datos = new FormData();
  datos.append("mes", mesSeleccionado);
  datos.append("tipo", "voluntario");
  datos.append("_csrf", window.CSRF_TOKEN);

  try {
    //Enviamos la consulta y recogemos la respuesta
    const respuesta = await fetch("index.php?r=graficos/gastos6m", {
      method: "POST",
      body: datos,
    });

    const data = await respuesta.json();

    if (!data.ok) {
      abrirModalInfo({
        titulo: "No se pudo cargar el gráfico",
        mensaje:
          "No fue posible obtener los datos. Inténtalo de nuevo más tarde.",
      });
      return;
    }

    //Almacenamos los meses y los valores en variables independientes
    const meses = data.data.meses;
    const valores = data.data.valores;

    actualizarResumenVariacionGastos("voluntario", valores);

    //Si ya había un gráfico lo destruimos para actualizarlo
    if (graficoGastosFlexibles6m) {
      graficoGastosFlexibles6m.destroy();
      graficoGastosFlexibles6m = null;
    }

    //Creamos el gráfico
    graficoGastosFlexibles6m = new Chart(ctx, {
      type: "line",
      data: {
        //Introducimos al gráfico cada dato con su valor correspondiente
        labels: meses,
        datasets: [
          {
            label: "Gastos flexibles",
            data: valores,
            borderColor: "#4ECDC4",
            backgroundColor: "rgba(74,144,226,0.25)",
            borderWidth: 2,
            pointRadius: 4,
            pointBackgroundColor: "#4ECDC4",
            tension: 0.35,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: {
          padding: {
            top: 5,
            bottom: 16,
            left: 0,
            right: 0,
          },
        },
        scales: {
          x: {
            ticks: {
              font: { size: 12 },
              color: "#000000",
            },
            grid: { display: false },
          },
          y: {
            beginAtZero: true,
            grid: {
              color: "rgba(0,0,0,0.08)",
            },
            ticks: {
              stepSize: 500,
              font: { size: 12 },
              color: "#000000",
              //Agregamos simbolo € al eje y
              callback: function (value) {
                return value + " €";
              },
            },
          },
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (context) {
                return "Gasto: " + context.raw + "€";
              },
            },
          },
        },
      },
    });
  } catch (error) {
    abrirModalInfo({
      titulo: "Problema de conexión",
      mensaje: "No se pudo contactar con el servidor para cargar el gráfico.",
    });
  }
}

//---------------------------------------------------------------Fucnión para Gráfico de evolución de gastos esenciales------------------------------------------------
async function cargarGraficoGastosEsenciales6m() {
  // Seleccionamos el canvas donde irá el gráfico
  const ctx = document.getElementById("graficoGastosEsenciales6m").getContext("2d");

  //Recogemos el valor del mes selecionado
  const mesSeleccionado = document.getElementById("mes").value;

  //Preparamos la consulta
  const datos = new FormData();
  datos.append("mes", mesSeleccionado);
  datos.append("tipo", "obligatorio");
  datos.append("_csrf", window.CSRF_TOKEN);

  try {
    //Enviamos la consulta y recogemos la respuesta
    const respuesta = await fetch("index.php?r=graficos/gastos6m", {
      method: "POST",
      body: datos,
    });

    const data = await respuesta.json();

    if (!data.ok) {
      abrirModalInfo({
        titulo: "No se pudo cargar el gráfico",
        mensaje:
          "No fue posible obtener los datos. Inténtalo de nuevo más tarde.",
      });
      return;
    }

    //Almacenamos los meses y los valores en variables independientes
    const meses = data.data.meses;
    const valores = data.data.valores;

    actualizarResumenVariacionGastos("obligatorio", valores);

    //Si ya había un gráfico lo destruimos para actualizarlo
    if (graficoGastosEsenciales6m) {
      graficoGastosEsenciales6m.destroy();
      graficoGastosEsenciales6m = null;
    }

    //Creamos el gráfico
    graficoGastosEsenciales6m = new Chart(ctx, {
      type: "line",
      data: {
        //Introducimos al gráfico cada dato con su valor correspondiente
        labels: meses,
        datasets: [
          {
            label: "Gastos esenciales",
            data: valores,
            borderColor: "#4ECDC4",
            backgroundColor: "rgba(74,144,226,0.25)",
            borderWidth: 2,
            pointRadius: 4,
            pointBackgroundColor: "#4ECDC4",
            tension: 0.35,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: {
          padding: {
            top: 5,
            bottom: 16,
            left: 0,
            right: 0,
          },
        },
        scales: {
          x: {
            ticks: {
              font: { size: 12 },
              color: "#000000",
            },
            grid: { display: false },
          },
          y: {
            beginAtZero: true,
            grid: {
              color: "rgba(0,0,0,0.08)",
            },
            ticks: {
              stepSize: 500,
              font: { size: 12 },
              color: "#000000",
              callback: function (value) {
                return value + " €";
              },
            },
          },
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (context) {
                return "Gasto: " + context.raw + "€";
              },
            },
          },
        },
      },
    });
  } catch (error) {
    abrirModalInfo({
      titulo: "Problema de conexión",
      mensaje: "No se pudo contactar con el servidor para cargar el gráfico.",
    });
  }
}

async function cargarGraficoAhorros6m() {
  // Seleccionamos el canvas donde irá el gráfico
  const ctx = document.getElementById("graficoAhorros6m").getContext("2d");

  //Recogemos el valor del mes selecionado
  const mesSeleccionado = document.getElementById("mes").value;

  //Preparamos la consulta
  const datos = new FormData();
  datos.append("mes", mesSeleccionado);
  datos.append("_csrf", window.CSRF_TOKEN);

  try {
    //Enviamos la consulta y recogemos la respuesta
    const respuesta = await fetch("index.php?r=graficos/ahorros6m", {
      method: "POST",
      body: datos,
    });

    const data = await respuesta.json();

    if (!data.ok) {
      abrirModalInfo({
        titulo: "No se pudo cargar el gráfico",
        mensaje:
          "No fue posible obtener los datos. Inténtalo de nuevo más tarde.",
      });
      return;
    }

    const meses = data.data.meses;
    const ahorroPosible = data.data.ahorroPosible;
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
            label: "Ahorro posible",
            data: ahorroPosible,
            backgroundColor: ahorroPosible.map((v) =>
              v >= 0 ? "#4ECDC4" : "#FF6B6B",
            ),
            barPercentage: 0.9,
            categoryPercentage: 0.6,
          },
          {
            label: "Ahorro real",
            data: ahorroReal,
            backgroundColor: ahorroReal.map((v) =>
              v >= 0 ? "#1A535C" : "#FF6B6B",
            ),
            barPercentage: 0.9,
            categoryPercentage: 0.6,
          },
        ],
      },
      options: {
        indexAxis: "x",
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: "point",
          intersect: true,
        },
        hover: {
          mode: "point",
          intersect: true,
        },
        layout: {
          padding: {
            top: 5,
            bottom: 20,
            left: 0,
            right: 0,
          },
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { display: false },
          },
          y: {
            beginAtZero: true,
            grid: {
              color: "rgba(0,0,0,0.08)",
            },
            ticks: {
              font: { size: 11 },
              color: "#000000",
              padding: 6,
              callback: function (value) {
                return value + " €";
              },
            },
          },
        },

        plugins: {
          legend: {
            position: "bottom",
            //Generamos leyenda personalizad para mostrar lso tres oclores incluyendo el de valores negativos
            labels: {
              font: {
                family: "Arial",
                size: 13,
              },
              usePointStyle: true,
              pointStyle: "rectRounded",
              pointStyleWidth: 14,

              generateLabels() {
                return [
                  {
                    text: "Ahorro posible (+)",
                    fillStyle: "#4ECDC4",
                    strokeStyle: "#4ECDC4",
                    pointStyle: "rectRounded",
                  },
                  {
                    text: "Ahorro (+)",
                    fillStyle: "#1A535C",
                    strokeStyle: "#1A535C",
                    pointStyle: "rectRounded",
                  },
                  {
                    text: "Valores (-)",
                    fillStyle: "#FF6B6B",
                    strokeStyle: "#FF6B6B",
                    pointStyle: "rectRounded",
                  },
                ];
              },
            },
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                return context.dataset.label + ": " + context.raw + " €";
              },
            },
          },
        },
      },
    });
  } catch (error) {
    abrirModalInfo({
      titulo: "Problema de conexión",
      mensaje: "No se pudo contactar con el servidor para cargar el gráfico.",
    });
  }
}

//Hacemos globales las funciones que actualizan los gráficos
window.cargarGraficoPresupuesto = cargarGraficoPresupuesto;
window.cargarGraficoGastosFlexibles6m = cargarGraficoGastosFlexibles6m;
window.cargarGraficoGastosEsenciales6m = cargarGraficoGastosEsenciales6m;
window.cargarGraficoAhorros6m = cargarGraficoAhorros6m;
