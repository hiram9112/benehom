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
let graficoEscalaHabitos = null;
let datosEscalaHabitos = [];
let escalaHabitosActiva = 'mes';
let datosInstantaneaInversion = null;
let estadoInstantaneaInversion = { aportacion: 'todo', rentabilidad: '3' };
let ultimoDisparadorInstantanea = null;

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

function crearEscalasVaciasConEuros(ocultarEjeX) {
  var escalas = crearEscalasConEuros(ocultarEjeX);

  escalas.y.min = 0;
  escalas.y.max = 250;
  escalas.y.ticks.stepSize = 50;

  return escalas;
}

function crearEscalasHorizontalesVaciasConEuros() {
  return {
    x: {
      min: 0,
      max: 250,
      beginAtZero: true,
      grid: { color: BH_COLORS.borderColor },
      ticks: {
        stepSize: 50,
        font: { family: FONT_FAMILY, size: 11 },
        color: BH_COLORS.textMuted,
        callback: function (value) { return formatearEuros(value); },
      },
    },
    y: {
      grid: { display: false },
      ticks: { font: { family: FONT_FAMILY, size: 11 }, color: BH_COLORS.textMain },
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

function filtrarMesesConValores(meses, valores) {
  var mesesFiltrados = [];
  var valoresFiltrados = [];

  meses.forEach(function (mes, index) {
    var valor = Number(valores[index]) || 0;

    if (valor > 0) {
      mesesFiltrados.push(mes);
      valoresFiltrados.push(valor);
    }
  });

  return { meses: mesesFiltrados, valores: valoresFiltrados };
}

function filtrarAhorrosConDatos(meses, ahorroPosible, ahorroReal, tieneDatos) {
  var mesesFiltrados = [];
  var posibleFiltrado = [];
  var realFiltrado = [];

  meses.forEach(function (mes, index) {
    var posible = Number(ahorroPosible[index]) || 0;
    var real = Number(ahorroReal[index]) || 0;
    var hayDatos = Array.isArray(tieneDatos)
      ? Boolean(tieneDatos[index])
      : (posible !== 0 || real !== 0);

    if (hayDatos) {
      mesesFiltrados.push(mes);
      posibleFiltrado.push(posible);
      realFiltrado.push(real);
    }
  });

  return { meses: mesesFiltrados, ahorroPosible: posibleFiltrado, ahorroReal: realFiltrado };
}

function redimensionarGraficoEvolucionGastos(tipo) {
  var grafico = tipo === 'esencial'
    ? graficoGastosEsenciales6m
    : graficoGastosFlexibles6m;

  if (!grafico) return;

  requestAnimationFrame(function () {
    grafico.resize();
  });
}

function inicializarSelectorEvolucionGastos() {
  var botones = document.querySelectorAll('[data-evolucion-gastos-tab]');
  var botonInfo = document.querySelector('[data-evolucion-gastos-info]');

  if (!botones.length) return;

  function activar(tipo) {
    botones.forEach(function (boton) {
      var activo = boton.dataset.evolucionGastosTab === tipo;
      var panel = document.getElementById(boton.getAttribute('aria-controls'));

      boton.classList.toggle('is-active', activo);
      boton.setAttribute('aria-pressed', activo ? 'true' : 'false');

      if (panel) {
        panel.hidden = !activo;
      }
    });

    if (botonInfo) {
      botonInfo.dataset.bsTarget = tipo === 'esencial'
        ? '#infoEvolucionEsenciales'
        : '#infoEvolucionFlexibles';
    }

    redimensionarGraficoEvolucionGastos(tipo);
  }

  botones.forEach(function (boton) {
    boton.addEventListener('click', function () {
      activar(boton.dataset.evolucionGastosTab);
    });
  });

  activar('flexible');
}

document.addEventListener('DOMContentLoaded', inicializarSelectorEvolucionGastos);

function inicializarSelectorEscalaHabitos() {
  var botones = document.querySelectorAll('[data-escala-habitos]');
  var botonInfo = document.querySelector('[data-escala-habitos-info]');

  if (!botones.length) return;

  botones.forEach(function (boton) {
    boton.addEventListener('click', function () {
      escalaHabitosActiva = boton.dataset.escalaHabitos;

      botones.forEach(function (botonInterno) {
        var activo = botonInterno.dataset.escalaHabitos === escalaHabitosActiva;
        botonInterno.classList.toggle('is-active', activo);
        botonInterno.setAttribute('aria-pressed', activo ? 'true' : 'false');
      });

      if (botonInfo) {
        botonInfo.dataset.bsTarget = escalaHabitosActiva === 'anio'
          ? '#infoEscalaHabitosProyeccion'
          : '#infoEscalaHabitosMedia';
      }

      renderizarGraficoEscalaHabitos();
    });
  });
}

document.addEventListener('DOMContentLoaded', inicializarSelectorEscalaHabitos);

function inicializarInstantaneaInversion() {
  var modal = document.getElementById('modalInstantaneaInversion');
  var botonesAportacion = document.querySelectorAll('[data-instantanea-aportacion]');
  var botonesRentabilidad = document.querySelectorAll('[data-instantanea-rentabilidad]');

  if (!modal) return;

  function activarBotones(botones, atributo, valorActivo) {
    botones.forEach(function (boton) {
      var activo = boton.dataset[atributo] === valorActivo;
      boton.classList.toggle('is-active', activo);
      boton.setAttribute('aria-pressed', activo ? 'true' : 'false');
    });
  }

  botonesAportacion.forEach(function (boton) {
    boton.addEventListener('click', function () {
      estadoInstantaneaInversion.aportacion = boton.dataset.instantaneaAportacion;
      activarBotones(botonesAportacion, 'instantaneaAportacion', estadoInstantaneaInversion.aportacion);
      renderizarInstantaneaInversion();
    });
  });

  botonesRentabilidad.forEach(function (boton) {
    boton.addEventListener('click', function () {
      estadoInstantaneaInversion.rentabilidad = boton.dataset.instantaneaRentabilidad;
      activarBotones(botonesRentabilidad, 'instantaneaRentabilidad', estadoInstantaneaInversion.rentabilidad);
      renderizarInstantaneaInversion();
    });
  });

  modal.addEventListener('shown.bs.modal', function () {
    var primerBoton = modal.querySelector('[data-instantanea-aportacion="todo"]');
    if (primerBoton) primerBoton.focus();
  });

  modal.addEventListener('hidden.bs.modal', function () {
    if (ultimoDisparadorInstantanea && typeof ultimoDisparadorInstantanea.focus === 'function') {
      ultimoDisparadorInstantanea.focus({ preventScroll: true });
    }
  });
}

document.addEventListener('DOMContentLoaded', inicializarInstantaneaInversion);

function resetearInstantaneaInversion() {
  estadoInstantaneaInversion = { aportacion: 'todo', rentabilidad: '3' };

  document.querySelectorAll('[data-instantanea-aportacion]').forEach(function (boton) {
    var activo = boton.dataset.instantaneaAportacion === 'todo';
    boton.classList.toggle('is-active', activo);
    boton.setAttribute('aria-pressed', activo ? 'true' : 'false');
  });

  document.querySelectorAll('[data-instantanea-rentabilidad]').forEach(function (boton) {
    var activo = boton.dataset.instantaneaRentabilidad === '3';
    boton.classList.toggle('is-active', activo);
    boton.setAttribute('aria-pressed', activo ? 'true' : 'false');
  });
}

async function abrirInstantaneaCategoria(categoria, label, disparador) {
  var mesInput = document.getElementById('mes');

  if (!categoria || !mesInput) return;

  ultimoDisparadorInstantanea = disparador || document.activeElement;

  var datos = new FormData();
  datos.append('categoria', categoria);
  datos.append('mes', mesInput.value);
  datos.append('_csrf', window.CSRF_TOKEN);

  try {
    var respuesta = await fetch('index.php?r=proyecciones/simularCategoriaAjax', {
      method: 'POST',
      body: datos,
    });
    var data = await respuesta.json();

    if (!data.ok) {
      abrirModalInfo({ titulo: 'No se pudo calcular la instantánea', mensaje: data.msg || 'Inténtalo de nuevo más tarde.' });
      return;
    }

    datosInstantaneaInversion = data.data;
    resetearInstantaneaInversion();
    pintarCabeceraInstantaneaInversion(label || data.data.label);
    renderizarInstantaneaInversion();

    var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalInstantaneaInversion'));
    modal.show();
  } catch (error) {
    abrirModalInfo({ titulo: 'Problema de conexión', mensaje: 'No se pudo contactar con el servidor para calcular la instantánea.' });
  }
}

function pintarCabeceraInstantaneaInversion(label) {
  var titulo = document.getElementById('modalInstantaneaTitulo');
  var subtitulo = document.getElementById('modalInstantaneaSubtitulo');

  if (!datosInstantaneaInversion) return;

  if (titulo) {
    titulo.textContent = 'Si invirtieras tu gasto en ' + (label || datosInstantaneaInversion.label);
  }

  if (subtitulo) {
    var meses = Number(datosInstantaneaInversion.mesesUsados) || 0;
    subtitulo.textContent = 'Media mensual: ' + formatearEuros(datosInstantaneaInversion.mediaMensual) + '. Calculada con ' + meses + (meses === 1 ? ' mes con datos.' : ' meses con datos.');
  }
}

function renderizarInstantaneaInversion() {
  if (!datosInstantaneaInversion || !datosInstantaneaInversion.escenarios) return;

  var escenario = datosInstantaneaInversion.escenarios[estadoInstantaneaInversion.aportacion]?.[estadoInstantaneaInversion.rentabilidad];

  if (!escenario) return;

  [5, 10, 15].forEach(function (plazo) {
    var resultado = escenario[String(plazo)];
    var valor = document.getElementById('instantaneaValor' + plazo);
    var generado = document.getElementById('instantaneaGenerado' + plazo);

    if (!resultado) return;
    if (valor) valor.textContent = formatearEuros(resultado.valorFinalEstimado);
    if (generado) generado.textContent = formatearEuros(resultado.eurosGenerados) + ' generados';
  });
}

// ----------------------------------------------------------------------
// Gráfico: Presupuesto mensual
// ----------------------------------------------------------------------
async function cargarGraficoPresupuesto() {
  var canvas = document.getElementById('graficoPresupuestoMensual');
  var ctx = canvas.getContext('2d');
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
  var canvas = document.getElementById('graficoGastosFlexibles6m');
  var ctx = canvas.getContext('2d');
  var mesSeleccionado = document.getElementById('mes').value;
  var datos = new FormData();
  datos.append('mes', mesSeleccionado);
  datos.append('tipo', 'flexible');
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
    var serie = filtrarMesesConValores(meses, valores);
    var graficoVacio = serie.valores.length === 0;
    actualizarResumenVariacionGastos('flexible', serie.valores);

    if (graficoGastosFlexibles6m) { graficoGastosFlexibles6m.destroy(); graficoGastosFlexibles6m = null; }

    graficoGastosFlexibles6m = new Chart(ctx, {
      type: 'line',
      data: {
        labels: serie.meses,
        datasets: [{
          label: 'Gastos flexibles',
          data: serie.valores,
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
        scales: graficoVacio ? crearEscalasVaciasConEuros(false) : crearEscalasConEuros(false),
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
  var canvas = document.getElementById('graficoGastosEsenciales6m');
  var ctx = canvas.getContext('2d');
  var mesSeleccionado = document.getElementById('mes').value;
  var datos = new FormData();
  datos.append('mes', mesSeleccionado);
  datos.append('tipo', 'esencial');
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
    var serie = filtrarMesesConValores(meses, valores);
    var graficoVacio = serie.valores.length === 0;
    actualizarResumenVariacionGastos('esencial', serie.valores);

    if (graficoGastosEsenciales6m) { graficoGastosEsenciales6m.destroy(); graficoGastosEsenciales6m = null; }

    graficoGastosEsenciales6m = new Chart(ctx, {
      type: 'line',
      data: {
        labels: serie.meses,
        datasets: [{
          label: 'Gastos esenciales',
          data: serie.valores,
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
        scales: graficoVacio ? crearEscalasVaciasConEuros(false) : crearEscalasConEuros(false),
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
  var canvas = document.getElementById('graficoAhorros6m');
  var ctx = canvas.getContext('2d');
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
    var serie = filtrarAhorrosConDatos(meses, ahorroPosible, ahorroReal, data.data.tieneDatos);

    if (graficoAhorros6m) { graficoAhorros6m.destroy(); graficoAhorros6m = null; }

    graficoAhorros6m = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: serie.meses,
        datasets: [
          {
            label: 'Ahorro posible',
            data: serie.ahorroPosible,
            backgroundColor: serie.ahorroPosible.map(function (v) {
              return v >= 0 ? BH_COLORS.income : BH_COLORS.expense;
            }),
            barPercentage: 0.9,
            categoryPercentage: 0.6,
          },
          {
            label: 'Ahorro real',
            data: serie.ahorroReal,
            backgroundColor: serie.ahorroReal.map(function (v) {
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

// ----------------------------------------------------------------------
// Gráfico: La escala real de tus hábitos
// ----------------------------------------------------------------------
async function cargarGraficoEscalaHabitos() {
  var canvas = document.getElementById('graficoEscalaHabitos');
  var mesInput = document.getElementById('mes');

  if (!canvas || !mesInput) return;

  var datos = new FormData();
  datos.append('mes', mesInput.value);
  datos.append('_csrf', window.CSRF_TOKEN);

  try {
    var respuesta = await fetch('index.php?r=graficos/topCategorias', {
      method: 'POST',
      body: datos,
    });
    var data = await respuesta.json();

    if (!data.ok) {
      abrirModalInfo({ titulo: 'No se pudo cargar el gráfico', mensaje: 'No fue posible obtener los datos. Inténtalo de nuevo más tarde.' });
      return;
    }

    datosEscalaHabitos = data.data.categorias || [];
    renderizarGraficoEscalaHabitos();
  } catch (error) {
    abrirModalInfo({ titulo: 'Problema de conexión', mensaje: 'No se pudo contactar con el servidor para cargar el gráfico.' });
  }
}

function renderizarGraficoEscalaHabitos() {
  var canvas = document.getElementById('graficoEscalaHabitos');

  if (!canvas) return;

  var labels = datosEscalaHabitos.map(function (item) { return item.label; });
  var valores = datosEscalaHabitos.map(function (item) {
    return escalaHabitosActiva === 'anio' ? item.anual : item.mediaMensual;
  });
  var graficoVacio = valores.length === 0;
  var colores = datosEscalaHabitos.map(function (_item, index) {
    var paleta = [BH_COLORS.expense, BH_COLORS.info, BH_COLORS.saving, BH_COLORS.neutral, BH_COLORS.income];
    return paleta[index % paleta.length];
  });

  if (graficoEscalaHabitos) { graficoEscalaHabitos.destroy(); graficoEscalaHabitos = null; }

  var opcionesEscalaHabitos = crearOpcionesGrafico({
    tooltip: {
      callbacks: {
        label: function (context) {
          return context.dataset.label + ': ' + formatearEuros(context.parsed.x);
        },
      },
    },
    legend: false,
    scales: graficoVacio ? crearEscalasHorizontalesVaciasConEuros() : {
      x: {
        beginAtZero: true,
        grid: { color: BH_COLORS.borderColor },
        ticks: {
          font: { family: FONT_FAMILY, size: 11 },
          color: BH_COLORS.textMuted,
          callback: function (value) { return formatearEuros(value); },
        },
      },
      y: {
        grid: { display: false },
        ticks: { font: { family: FONT_FAMILY, size: 11 }, color: BH_COLORS.textMain },
      },
    },
  });

  opcionesEscalaHabitos.indexAxis = 'y';
  opcionesEscalaHabitos.animation = { duration: 180, easing: 'easeOutQuart' };
  opcionesEscalaHabitos.onHover = function (event, elementos) {
    if (event.native && event.native.target) {
      event.native.target.style.cursor = elementos.length ? 'pointer' : 'default';
    }
  };
  opcionesEscalaHabitos.onClick = function (_event, elementos) {
    if (!elementos.length || typeof window.abrirInstantaneaCategoria !== 'function') return;

    var item = datosEscalaHabitos[elementos[0].index];
    window.abrirInstantaneaCategoria(item.categoria, item.label, canvas);
  };

  graficoEscalaHabitos = new Chart(canvas.getContext('2d'), {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: escalaHabitosActiva === 'anio' ? 'Proyección a 12 meses' : 'Media mensual',
        data: valores,
        backgroundColor: colores,
        borderRadius: 12,
        barThickness: 24,
      }],
    },
    options: opcionesEscalaHabitos,
  });
}

// Exponer globalmente
window.cargarGraficoPresupuesto = cargarGraficoPresupuesto;
window.cargarGraficoGastosFlexibles6m = cargarGraficoGastosFlexibles6m;
window.cargarGraficoGastosEsenciales6m = cargarGraficoGastosEsenciales6m;
window.cargarGraficoAhorros6m = cargarGraficoAhorros6m;
window.cargarGraficoEscalaHabitos = cargarGraficoEscalaHabitos;
window.abrirInstantaneaCategoria = abrirInstantaneaCategoria;
