// Paleta BeneHom central — lee tokens de CSS con fallback
const BH_COLORS = {
  income:        getComputedStyle(document.documentElement).getPropertyValue('--bh-income').trim()        || '#33A64D',
  incomeSoft:    getComputedStyle(document.documentElement).getPropertyValue('--bh-income-soft').trim()   || 'rgba(51,166,77,0.12)',
  expense:       getComputedStyle(document.documentElement).getPropertyValue('--bh-expense').trim()       || '#B83E3E',
  expenseSoft:   getComputedStyle(document.documentElement).getPropertyValue('--bh-expense-soft').trim()  || 'rgba(184,62,62,0.10)',
  saving:        getComputedStyle(document.documentElement).getPropertyValue('--bh-saving').trim()        || '#3EB225',
  savingSoft:    getComputedStyle(document.documentElement).getPropertyValue('--bh-saving-soft').trim()   || 'rgba(62,178,37,0.12)',
  info:          getComputedStyle(document.documentElement).getPropertyValue('--bh-info').trim()          || '#163F7F',
  infoSoft:      getComputedStyle(document.documentElement).getPropertyValue('--bh-info-soft').trim()     || 'rgba(22,63,127,0.08)',
  neutral:       getComputedStyle(document.documentElement).getPropertyValue('--bh-neutral').trim()       || '#163F7F',
  neutralSoft:   getComputedStyle(document.documentElement).getPropertyValue('--bh-neutral-soft').trim()  || '#E9F4EC',
  textMain:      getComputedStyle(document.documentElement).getPropertyValue('--bh-text-main').trim()     || '#163F7F',
  textMuted:     getComputedStyle(document.documentElement).getPropertyValue('--bh-text-muted').trim()    || 'rgba(22,63,127,0.72)',
  borderColor:   getComputedStyle(document.documentElement).getPropertyValue('--bh-border-color').trim() || 'rgba(22,63,127,0.14)',
  surfaceCard:   getComputedStyle(document.documentElement).getPropertyValue('--bh-surface-card').trim() || '#FDFEFD',
};

// Variable global para destruir gráficos al cambiar de mes
let graficoPresupuesto = null;
let graficoGastosFlexibles6m = null;
let graficoGastosEsenciales6m = null;
let graficoAhorros6m = null;

// ----------------------------------------------------------------------
// Helpers comunes
// ----------------------------------------------------------------------

const FONT_FAMILY = "'Nunito Sans', Arial, sans-serif";

function formatearEuros(valor) {
  var numero = Number(valor) || 0;
  var opciones = Number.isInteger(numero)
    ? { maximumFractionDigits: 0 }
    : { minimumFractionDigits: 2, maximumFractionDigits: 2 };
  return new Intl.NumberFormat('es-ES', opciones).format(numero) + ' \u20AC';
}

function crearTooltipBeneHom() {
  return {
    backgroundColor: BH_COLORS.surfaceCard,
    titleColor: BH_COLORS.textMain,
    bodyColor: BH_COLORS.textMain,
    borderColor: BH_COLORS.borderColor,
    borderWidth: 1,
    cornerRadius: 10,
    padding: 10,
    titleFont: { family: FONT_FAMILY, weight: '600', size: 13 },
    bodyFont:  { family: FONT_FAMILY, weight: '400', size: 12 },
  };
}

function crearLeyendaInferior() {
  return {
    position: 'bottom',
    labels: {
      font: { family: FONT_FAMILY, size: 12 },
      color: BH_COLORS.textMain,
      usePointStyle: true,
      pointStyle: 'rectRounded',
      boxWidth: 14,
      padding: 14,
    },
  };
}

function crearEscalasConEuros(ocultarEjeX) {
  return {
    x: {
      grid: { display: false },
      ticks: ocultarEjeX
        ? { display: false }
        : { font: { family: FONT_FAMILY, size: 11 }, color: BH_COLORS.textMuted },
    },
    y: {
      beginAtZero: true,
      grid: { color: BH_COLORS.borderColor },
      ticks: {
        font: { family: FONT_FAMILY, size: 11 },
        color: BH_COLORS.textMuted,
        padding: 6,
        callback: function (value) {
          return value + ' \u20AC';
        },
      },
    },
  };
}

function crearOpcionesGrafico(opts) {
  var plugins = {};

  if (opts.tooltip) {
    plugins.tooltip = Object.assign(crearTooltipBeneHom(), opts.tooltip);
  }

  if (opts.legend === true) {
    plugins.legend = crearLeyendaInferior();
  } else if (opts.legend === false) {
    plugins.legend = false;
  } else if (opts.legend && typeof opts.legend === 'object') {
    plugins.legend = opts.legend;
  }

  if (opts.pluginsExtra) {
    for (var k in opts.pluginsExtra) {
      if (opts.pluginsExtra.hasOwnProperty(k)) {
        plugins[k] = opts.pluginsExtra[k];
      }
    }
  }

  return {
    responsive: true,
    maintainAspectRatio: false,
    layout: {
      padding: { top: 5, bottom: 20, left: 0, right: 0 },
    },
    interaction: { mode: 'nearest', intersect: false },
    hover: { mode: 'nearest', intersect: false },
    scales: opts.scales || crearEscalasConEuros(false),
    plugins: plugins,
  };
}

// ----------------------------------------------------------------------
// Gráfico: Presupuesto mensual
// ----------------------------------------------------------------------
async function cargarGraficoPresupuesto() {
  var ctx = document.getElementById('graficoPresupuestoMensual').getContext('2d');
  var mesSeleccionado = document.getElementById('mes').value;
  var datos = new FormData();
  datos.append('mes', mesSeleccionado);
  datos.append('_csrf', window.CSRF_TOKEN);

  try {
    var respuesta = await fetch('index.php?r=graficos/estadoGeneral', {
      method: 'POST',
      body: datos,
    });
    var data = await respuesta.json();
    if (!data.ok) {
      abrirModalInfo({ titulo: 'No se pudo cargar el gráfico', mensaje: 'No fue posible obtener los datos. Inténtalo de nuevo más tarde.' });
      return;
    }

    var valores = data.data;
    actualizarTotales(valores);

    if (graficoPresupuesto) { graficoPresupuesto.destroy(); graficoPresupuesto = null; }

    graficoPresupuesto = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['Ingresos', 'Gastos totales', 'Ahorro real'],
        datasets: [{
          data: [valores.ingresos, valores.gastosTotales, valores.ahorroReal],
          backgroundColor: [
            BH_COLORS.income,
            BH_COLORS.expense,
            valores.ahorroReal >= 0 ? BH_COLORS.saving : BH_COLORS.expense,
          ],
          borderRadius: 10,
          barThickness: 28,
        }],
      },
      options: crearOpcionesGrafico({
        tooltip: {
          callbacks: {
            label: function (context) {
              var nombres = ['Ingresos', 'Gastos totales', 'Ahorro real'];
              return nombres[context.dataIndex] + ': ' + context.parsed.y + '\u20AC';
            },
          },
        },
        legend: true,
        scales: crearEscalasConEuros(true),
      }),
    });
  } catch (error) {
    abrirModalInfo({ titulo: 'Problema de conexión', mensaje: 'No se pudo contactar con el servidor para cargar el gráfico.' });
  }
}

// ----------------------------------------------------------------------
// Gráfico: Evolución gastos flexibles 6m
// ----------------------------------------------------------------------
async function cargarGraficoGastosFlexibles6m() {
  var ctx = document.getElementById('graficoGastosFlexibles6m').getContext('2d');
  var mesSeleccionado = document.getElementById('mes').value;
  var datos = new FormData();
  datos.append('mes', mesSeleccionado);
  datos.append('tipo', 'voluntario');
  datos.append('_csrf', window.CSRF_TOKEN);

  try {
    var respuesta = await fetch('index.php?r=graficos/gastos6m', {
      method: 'POST',
      body: datos,
    });
    var data = await respuesta.json();
    if (!data.ok) {
      abrirModalInfo({ titulo: 'No se pudo cargar el gráfico', mensaje: 'No fue posible obtener los datos. Inténtalo de nuevo más tarde.' });
      return;
    }

    var meses = data.data.meses;
    var valores = data.data.valores;
    actualizarResumenVariacionGastos('voluntario', valores);

    if (graficoGastosFlexibles6m) { graficoGastosFlexibles6m.destroy(); graficoGastosFlexibles6m = null; }

    graficoGastosFlexibles6m = new Chart(ctx, {
      type: 'line',
      data: {
        labels: meses,
        datasets: [{
          label: 'Gastos flexibles',
          data: valores,
          borderColor: BH_COLORS.expense,
          backgroundColor: BH_COLORS.expenseSoft,
          borderWidth: 2,
          pointRadius: 4,
          pointBackgroundColor: BH_COLORS.expense,
          tension: 0.35,
        }],
      },
      options: crearOpcionesGrafico({
        tooltip: {
          callbacks: {
            label: function (context) {
              return context.dataset.label + ': ' + context.parsed.y + '\u20AC';
            },
          },
        },
        legend: false,
        scales: crearEscalasConEuros(false),
      }),
    });
  } catch (error) {
    abrirModalInfo({ titulo: 'Problema de conexión', mensaje: 'No se pudo contactar con el servidor para cargar el gráfico.' });
  }
}

// ----------------------------------------------------------------------
// Gráfico: Evolución gastos esenciales 6m
// ----------------------------------------------------------------------
async function cargarGraficoGastosEsenciales6m() {
  var ctx = document.getElementById('graficoGastosEsenciales6m').getContext('2d');
  var mesSeleccionado = document.getElementById('mes').value;
  var datos = new FormData();
  datos.append('mes', mesSeleccionado);
  datos.append('tipo', 'obligatorio');
  datos.append('_csrf', window.CSRF_TOKEN);

  try {
    var respuesta = await fetch('index.php?r=graficos/gastos6m', {
      method: 'POST',
      body: datos,
    });
    var data = await respuesta.json();
    if (!data.ok) {
      abrirModalInfo({ titulo: 'No se pudo cargar el gráfico', mensaje: 'No fue posible obtener los datos. Inténtalo de nuevo más tarde.' });
      return;
    }

    var meses = data.data.meses;
    var valores = data.data.valores;
    actualizarResumenVariacionGastos('obligatorio', valores);

    if (graficoGastosEsenciales6m) { graficoGastosEsenciales6m.destroy(); graficoGastosEsenciales6m = null; }

    graficoGastosEsenciales6m = new Chart(ctx, {
      type: 'line',
      data: {
        labels: meses,
        datasets: [{
          label: 'Gastos esenciales',
          data: valores,
          borderColor: BH_COLORS.info,
          backgroundColor: BH_COLORS.infoSoft,
          borderWidth: 2,
          pointRadius: 4,
          pointBackgroundColor: BH_COLORS.info,
          tension: 0.35,
        }],
      },
      options: crearOpcionesGrafico({
        tooltip: {
          callbacks: {
            label: function (context) {
              return context.dataset.label + ': ' + context.parsed.y + '\u20AC';
            },
          },
        },
        legend: false,
        scales: crearEscalasConEuros(false),
      }),
    });
  } catch (error) {
    abrirModalInfo({ titulo: 'Problema de conexión', mensaje: 'No se pudo contactar con el servidor para cargar el gráfico.' });
  }
}

// ----------------------------------------------------------------------
// Gráfico: Ahorro posible vs real 6m
// ----------------------------------------------------------------------
async function cargarGraficoAhorros6m() {
  var ctx = document.getElementById('graficoAhorros6m').getContext('2d');
  var mesSeleccionado = document.getElementById('mes').value;
  var datos = new FormData();
  datos.append('mes', mesSeleccionado);
  datos.append('_csrf', window.CSRF_TOKEN);

  try {
    var respuesta = await fetch('index.php?r=graficos/ahorros6m', {
      method: 'POST',
      body: datos,
    });
    var data = await respuesta.json();
    if (!data.ok) return;

    var meses = data.data.meses;
    var ahorroPosible = data.data.ahorroPosible;
    var ahorroReal = data.data.ahorroReal;

    if (graficoAhorros6m) { graficoAhorros6m.destroy(); graficoAhorros6m = null; }

    graficoAhorros6m = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: meses,
        datasets: [
          {
            label: 'Ahorro posible',
            data: ahorroPosible,
            backgroundColor: ahorroPosible.map(function (v) {
              return v >= 0 ? BH_COLORS.income : BH_COLORS.expense;
            }),
            barPercentage: 0.9,
            categoryPercentage: 0.6,
          },
          {
            label: 'Ahorro real',
            data: ahorroReal,
            backgroundColor: ahorroReal.map(function (v) {
              return v >= 0 ? BH_COLORS.info : BH_COLORS.expense;
            }),
            barPercentage: 0.9,
            categoryPercentage: 0.6,
          },
        ],
      },
      options: crearOpcionesGrafico({
        tooltip: {
          callbacks: {
            label: function (context) {
              return context.dataset.label + ': ' + context.parsed.y + ' \u20AC';
            },
          },
        },
        legend: {
          position: 'bottom',
          labels: {
            font: { family: FONT_FAMILY, size: 12 },
            color: BH_COLORS.textMain,
            usePointStyle: true,
            pointStyle: 'rectRounded',
            boxWidth: 14,
            padding: 14,
            generateLabels: function () {
              return [
                { text: 'Ahorro posible (+)', fillStyle: BH_COLORS.income, strokeStyle: BH_COLORS.income, pointStyle: 'rectRounded' },
                { text: 'Ahorro real (+)',     fillStyle: BH_COLORS.info, strokeStyle: BH_COLORS.info, pointStyle: 'rectRounded' },
                { text: 'Valores negativos',    fillStyle: BH_COLORS.expense, strokeStyle: BH_COLORS.expense, pointStyle: 'rectRounded' },
              ];
            },
          },
        },
        scales: crearEscalasConEuros(true),
      }),
    });
  } catch (error) {
    abrirModalInfo({ titulo: 'Problema de conexión', mensaje: 'No se pudo contactar con el servidor para cargar el gráfico.' });
  }
}

// Exponer globalmente
window.cargarGraficoPresupuesto = cargarGraficoPresupuesto;
window.cargarGraficoGastosFlexibles6m = cargarGraficoGastosFlexibles6m;
window.cargarGraficoGastosEsenciales6m = cargarGraficoGastosEsenciales6m;
window.cargarGraficoAhorros6m = cargarGraficoAhorros6m;
