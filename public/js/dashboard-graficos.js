// Paleta BeneHom centralizada en chart-theme.js.
const BH_CHART_THEME = window.BHChartTheme || {};
const BH_COLORS = BH_CHART_THEME.colors || {};

// Variable global para destruir gráficos al cambiar de mes
let graficoHistoriaMes = null;
let graficoGastosFlexibles6m = null;
let graficoGastosEsenciales6m = null;
let graficoAhorros6m = null;
let graficoEscalaHabitos = null;
let datosEscalaHabitos = [];
let escalaHabitosActiva = 'mes';
let indiceBarraActiva = 0;
let datosInstantaneaInversion = null;
let estadoInstantaneaInversion = { aportacion: 'todo', rentabilidad: '3' };
let ultimoDisparadorInstantanea = null;
let datosHistoriaMesActuales = null;
const BH_HERO_CHART_MOBILE_QUERY = typeof window.matchMedia === 'function'
  ? window.matchMedia('(max-width: 640px)')
  : null;
window.bhDashboardHeroSeries = window.bhDashboardHeroSeries || {};

// ----------------------------------------------------------------------
// Helpers comunes
// ----------------------------------------------------------------------

const FONT_FAMILY = BH_CHART_THEME.fontFamily || "'Nunito Sans', Arial, sans-serif";
const BH_REDUCED_MOTION = Boolean(BH_CHART_THEME.reducedMotion) || (typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

function formatearEuros(valor) {
  var numero = Number(valor) || 0;
  var opciones = Number.isInteger(numero)
    ? { minimumFractionDigits: 0, maximumFractionDigits: 0 }
    : { minimumFractionDigits: 2, maximumFractionDigits: 2 };

  if (window.BHMoney) {
    return window.BHMoney.formatMoney(numero, opciones);
  }

  return new Intl.NumberFormat('es-ES', opciones).format(numero) + ' \u20AC';
}

function formatearMesGrafico(valor) {
  var partes = String(valor || '').split('-');
  var year = Number(partes[0]);
  var month = Number(partes[1]);

  if (!year || !month) {
    return String(valor || '');
  }

  var nombre = new Intl.DateTimeFormat('es-ES', { month: 'long' })
    .format(new Date(year, month - 1, 1));

  return nombre.charAt(0).toUpperCase() + nombre.slice(1)+ " " + year;
}

function formatearMesEjeGrafico(valor) {
  var partes = String(valor || '').split('-');
  var year = Number(partes[0]);
  var month = Number(partes[1]);

  if (!year || !month) {
    return String(valor || '');
  }

  var abrev = new Intl.DateTimeFormat('es-ES', { month: 'short' })
    .format(new Date(year, month - 1, 1))
    .replace('.', '')
    .trim();

  return abrev.charAt(0).toUpperCase() + abrev.slice(1);
}

function aplicarAbreviaturasMesEjeX(escalas) {
  if (!escalas || !escalas.x || !escalas.x.ticks) return escalas;

  escalas.x.ticks.callback = function (value) {
    var label = this.getLabelForValue(value);

    return formatearMesEjeGrafico(label);
  };

  return escalas;
}

function actualizarResumenGrafico(id, texto) {
  var resumen = document.getElementById(id);

  if (resumen) {
    resumen.textContent = texto;
  }
}

function describirSerieEuros(labels, valores) {
  if (!labels.length || !valores.length) {
    return 'No hay datos suficientes para mostrar este gráfico.';
  }

  return labels.map(function (label, index) {
    return formatearMesGrafico(label) + ': ' + describirValorSerieEuros(valores[index]);
  }).join('; ') + '.';
}

function describirValorSerieEuros(valor) {
  return valor === null || typeof valor === 'undefined'
    ? 'sin datos'
    : formatearEuros(valor);
}

function crearTooltipBeneHom() {
  if (BH_CHART_THEME.tooltip) {
    return BH_CHART_THEME.tooltip();
  }

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
  if (BH_CHART_THEME.legend) {
    return BH_CHART_THEME.legend();
  }

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
          return formatearEuros(value);
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

function obtenerTokenDashboard(nombre, fallback) {
  var valor = getComputedStyle(document.documentElement).getPropertyValue(nombre).trim();
  return valor || fallback || '';
}

function crearItemsCascadaDashboard(valores) {
  var ingresos = Number(valores.ingresos) || 0;
  var esenciales = Number(valores.esenciales) || 0;
  var flexibles = Number(valores.flexibles) || 0;
  var posible = ingresos - esenciales;
  var real = posible - flexibles;

  return [
    { label: 'Ingresos', start: 0, end: ingresos, value: ingresos, color: obtenerTokenDashboard('--bh-positive', BH_COLORS.positive) },
    { label: 'Gastos esenciales', start: ingresos, end: posible, value: -esenciales, color: obtenerTokenDashboard('--bh-essential', BH_COLORS.essential) },
    { label: 'Ahorro posible', start: 0, end: posible, value: posible, color: obtenerTokenDashboard('--bh-waterfall-subtotal-fill', BH_COLORS.positiveSoft), isSubtotal: true },
    { label: 'Gastos flexibles', start: posible, end: real, value: -flexibles, color: obtenerTokenDashboard('--bh-flexible', BH_COLORS.flexible) },
    { label: 'Ahorro real', start: 0, end: real, value: real, color: real < 0 ? obtenerTokenDashboard('--bh-negative', BH_COLORS.negative) : obtenerTokenDashboard('--bh-positive', BH_COLORS.positive) },
  ];
}

function calcularMaximoCascada(items) {
  var maximo = items.reduce(function (actual, item) {
    return Math.max(actual, Number(item.start) || 0, Number(item.end) || 0);
  }, 0);
  var paso = 400;

  return Math.max(paso, Math.ceil(maximo / paso) * paso);
}

function formatearEurosCascada(valor, sinDecimales) {
  var numero = Number(valor) || 0;
  var opcionesEnteras = { maximumFractionDigits: 0 };

  function formatearAbsoluto(cantidad) {
    if (!sinDecimales) return formatearEuros(cantidad);

    return new Intl.NumberFormat('es-ES', opcionesEnteras).format(cantidad) + ' €';
  }

  if (numero < 0) {
    return '−' + formatearAbsoluto(Math.abs(numero));
  }

  return formatearAbsoluto(numero);
}

function esGraficoHistoriaMovil() {
  return Boolean(BH_HERO_CHART_MOBILE_QUERY && BH_HERO_CHART_MOBILE_QUERY.matches);
}

function mesSeleccionadoResumen() {
  if (typeof nombreMesResumen === 'function') {
    return nombreMesResumen(document.getElementById('mes')?.value);
  }

  return 'este mes';
}

function hayMovimientosHistoriaMes(valores) {
  return (Number(valores.ingresos) || 0) !== 0
    || (Number(valores.gastosEsenciales) || 0) !== 0
    || (Number(valores.gastosFlexibles) || 0) !== 0;
}

function crearGraficoCascadaDashboard(canvas, valores, opciones) {
  if (!canvas || !window.Chart) return null;

  var opcionesGrafico = opciones || {};
  var graficoVacio = Boolean(opcionesGrafico.empty);
  var esMovil = opcionesGrafico.mobile ?? esGraficoHistoriaMovil();
  var items = Array.isArray(valores) ? valores : crearItemsCascadaDashboard(valores || {});
  var escalas = crearEscalasConEuros(false);
  var maximoEscala = graficoVacio ? 1 : calcularMaximoCascada(items);

  if (graficoVacio) {
    escalas.y.min = -1;
    escalas.y.max = 1;
    escalas.y.grid = Object.assign({}, escalas.y.grid || {}, {
      display: !esMovil,
      color: obtenerTokenDashboard('--bh-border-color', BH_COLORS.borderColor),
    });
    escalas.y.ticks = Object.assign({}, escalas.y.ticks || {}, { display: false, callback: function () { return ''; } });
  } else {
    escalas.y.max = maximoEscala;
    escalas.y.grid = Object.assign({}, escalas.y.grid || {}, { display: !esMovil });
    escalas.y.ticks = Object.assign({}, escalas.y.ticks || {}, {
      display: !esMovil,
      precision: 0,
      stepSize: 400,
    });
  }

  escalas.y.border = Object.assign({}, escalas.y.border || {}, { display: false });
  escalas.x.ticks = Object.assign({}, escalas.x.ticks || {}, {
    autoSkip: false,
    maxRotation: 0,
    minRotation: 0,
    callback: function (_value, index) {
      var label = items[index] ? items[index].label : '';
      var etiquetas = {
        'Gastos esenciales': ['Gastos', 'esenciales'],
        'Ahorro posible': ['Ahorro', 'posible'],
        'Gastos flexibles': ['Gastos', 'flexibles'],
        'Ahorro real': ['Ahorro', 'real'],
      };

      return etiquetas[label] || label;
    },
  });
  var pluginEtiquetas = {
    id: 'bhDashboardWaterfallLabels',
    afterDatasetsDraw: function (chart) {
      var ctx = chart.ctx;
      var meta = chart.getDatasetMeta(0);
      var yScale = chart.scales.y;

      if (yScale) {
        var ceroY = yScale.getPixelForValue(0);

        if (Number.isFinite(ceroY)) {
          ctx.save();
          ctx.strokeStyle = obtenerTokenDashboard('--bh-border-strong', BH_COLORS.borderColor);
          ctx.lineWidth = 1;
          ctx.setLineDash([]);
          ctx.beginPath();
          ctx.moveTo(chart.chartArea.left, ceroY);
          ctx.lineTo(chart.chartArea.right, ceroY);
          ctx.stroke();
          ctx.restore();
        }
      }

      if (graficoVacio) return;

      if (meta.data.length >= 5 && yScale) {
        var conectores = [
          { from: 0, to: 1, value: items[0] ? items[0].end : 0 },
          { from: 1, to: 2, value: items[1] ? items[1].end : 0 },
          { from: 2, to: 3, value: items[2] ? items[2].end : 0 },
          { from: 3, to: 4, value: items[3] ? items[3].end : 0 },
        ];

        ctx.save();
        ctx.strokeStyle = obtenerTokenDashboard('--bh-text-muted', BH_COLORS.textMuted);
        ctx.lineWidth = 1;
        ctx.setLineDash([4, 4]);

        conectores.forEach(function (conector) {
          var origen = meta.data[conector.from];
          var destino = meta.data[conector.to];

          if (!origen || !destino) return;

          var origenProps = origen.getProps(['x', 'width'], true);
          var destinoProps = destino.getProps(['x', 'width'], true);
          var y = yScale.getPixelForValue(conector.value);
          var x1 = origenProps.x + (origenProps.width / 2);
          var x2 = destinoProps.x - (destinoProps.width / 2);

          if (!Number.isFinite(y) || x2 <= x1) return;

          ctx.beginPath();
          ctx.moveTo(x1, y);
          ctx.lineTo(x2, y);
          ctx.stroke();
        });

        ctx.restore();
      }

      ctx.save();
      ctx.fillStyle = BH_COLORS.textMain || obtenerTokenDashboard('--bh-text-main');
      ctx.font = '600 ' + (esMovil ? '11px ' : '12px ') + FONT_FAMILY;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'bottom';

      meta.data.forEach(function (bar, index) {
        var item = items[index];
        if (!item) return;

        var value = item.value ?? ((Number(item.end) || 0) - (Number(item.start) || 0));
        var props = bar.getProps(['x', 'y', 'base'], true);
        var y = Math.max(12, Math.min(props.y, props.base) - 8);
        ctx.fillText(formatearEurosCascada(value, esMovil), props.x, y);
      });

      ctx.restore();
    },
  };

  return new Chart(canvas.getContext('2d'), {
    type: 'bar',
    data: {
      labels: items.map(function (item) { return item.label; }),
      datasets: [{
        data: items.map(function (item) { return [item.start, item.end]; }),
        backgroundColor: items.map(function (item) { return item.color; }),
        borderColor: items.map(function (item) { return item.color; }),
        borderWidth: 0,
        borderRadius: 6,
        borderSkipped: false,
        barPercentage: esMovil ? 0.9 : 0.88,
        categoryPercentage: esMovil ? 0.94 : 0.9,
        maxBarThickness: esMovil ? 76 : 86,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      aspectRatio: 2,
      animation: opcionesGrafico.animation ?? !BH_REDUCED_MOTION,
      layout: { padding: esMovil ? { top: 26, right: 0, bottom: 0, left: 0 } : { top: 30, right: 4, bottom: 0, left: 4 } },
      plugins: {
        legend: { display: false },
        tooltip: graficoVacio ? { enabled: false } : Object.assign(crearTooltipBeneHom(), {
          callbacks: {
            title: function () {
              return '';
            },
            label: function (context) {
              var item = items[context.dataIndex];
              if (!item) return '';

              return item.label + ' · ' + formatearEurosCascada(item.value, false);
            },
          },
        }),
      },
      scales: escalas,
    },
    plugins: [pluginEtiquetas],
  });
}

function actualizarGraficoHistoriaMes(valores) {
  var canvas = document.getElementById('graficoHistoriaMes');

  if (!canvas) return;

  datosHistoriaMesActuales = {
    ingresos: valores.ingresos,
    gastosEsenciales: valores.gastosEsenciales,
    gastosFlexibles: valores.gastosFlexibles,
    ahorroReal: valores.ahorroReal,
  };

  var tieneMovimientos = hayMovimientosHistoriaMes(datosHistoriaMesActuales);
  var mes = mesSeleccionadoResumen();
  var empty = document.getElementById('graficoHistoriaMesEmpty');
  var emptyMes = document.getElementById('graficoHistoriaMesEmptyMes');

  if (empty) empty.hidden = tieneMovimientos;
  if (emptyMes) emptyMes.textContent = mes;

  if (graficoHistoriaMes) { graficoHistoriaMes.destroy(); graficoHistoriaMes = null; }

  graficoHistoriaMes = crearGraficoCascadaDashboard(
    canvas,
    tieneMovimientos
      ? {
          ingresos: valores.ingresos,
          esenciales: valores.gastosEsenciales,
          flexibles: valores.gastosFlexibles,
        }
      : { ingresos: 0, esenciales: 0, flexibles: 0 },
    { empty: !tieneMovimientos, mobile: esGraficoHistoriaMovil() }
  );

  if (!tieneMovimientos) {
    actualizarResumenGrafico(
      'graficoHistoriaMesResumen',
      'Aún no hay movimientos en ' + mes + '. Usa el botón para añadir el primer ingreso.'
    );
    return;
  }

  actualizarResumenGrafico(
    'graficoHistoriaMesResumen',
    'La historia del mes: ingresos ' + formatearEuros(valores.ingresos) +
      ', gastos esenciales ' + formatearEuros(valores.gastosEsenciales) +
      ', ahorro posible ' + formatearEuros((Number(valores.ingresos) || 0) - (Number(valores.gastosEsenciales) || 0)) +
      ', gastos flexibles ' + formatearEuros(valores.gastosFlexibles) +
      ' y ahorro real ' + formatearEuros(valores.ahorroReal) + '.'
  );
}

function registrarResponsiveHistoriaMes() {
  if (!BH_HERO_CHART_MOBILE_QUERY) return;

  var regenerar = function () {
    if (!datosHistoriaMesActuales) return;
    actualizarGraficoHistoriaMes(datosHistoriaMesActuales);
  };

  if (typeof BH_HERO_CHART_MOBILE_QUERY.addEventListener === 'function') {
    BH_HERO_CHART_MOBILE_QUERY.addEventListener('change', regenerar);
  } else {
    BH_HERO_CHART_MOBILE_QUERY.addListener(regenerar);
  }
}

function registrarCtaHeroVacio() {
  var cta = document.querySelector('[data-hero-empty-cta]');

  if (!cta) return;

  cta.addEventListener('click', function () {
    var destino = document.getElementById('formMovimientoMes');

    if (typeof window.bhSeleccionarTipoMovimiento === 'function') {
      window.bhSeleccionarTipoMovimiento('ingreso');
    }

    var foco = document.getElementById('movimiento_area') || destino?.querySelector('select, input, textarea, button');

    if (destino) {
      destino.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    if (foco) {
      window.setTimeout(function () { foco.focus({ preventScroll: true }); }, 450);
    }
  });
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

  var opciones = {
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

  if (BH_REDUCED_MOTION) {
    opciones.animation = false;
  }

  return opciones;
}

function prepararSerieGastosConHuecos(meses, valores) {
  var primerMesConDatos = valores.findIndex(function (valor) {
    return (Number(valor) || 0) > 0;
  });

  if (primerMesConDatos === -1) {
    return { meses: [], valores: [] };
  }

  return {
    meses: meses.slice(primerMesConDatos),
    valores: valores.slice(primerMesConDatos).map(function (valor) {
      var numero = Number(valor) || 0;

      return numero > 0 ? numero : null;
    }),
  };
}

function prepararSerieAhorrosConHuecos(meses, ahorroPosible, ahorroReal, tieneDatos) {
  var datosPorMes = meses.map(function (_mes, index) {
    var posible = Number(ahorroPosible[index]) || 0;
    var real = Number(ahorroReal[index]) || 0;
    var hayDatos = Array.isArray(tieneDatos)
      ? Boolean(tieneDatos[index])
      : (posible !== 0 || real !== 0);

    return { hayDatos: hayDatos, posible: posible, real: real };
  });
  var primerMesConDatos = datosPorMes.findIndex(function (dato) {
    return dato.hayDatos;
  });

  if (primerMesConDatos === -1) {
    return { meses: [], ahorroPosible: [], ahorroReal: [] };
  }

  return {
    meses: meses.slice(primerMesConDatos),
    ahorroPosible: datosPorMes.slice(primerMesConDatos).map(function (dato) {
      return dato.hayDatos ? dato.posible : null;
    }),
    ahorroReal: datosPorMes.slice(primerMesConDatos).map(function (dato) {
      return dato.hayDatos ? dato.real : null;
    }),
  };
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

function resaltarBarraEscalaHabitos() {
  if (!graficoEscalaHabitos || indiceBarraActiva < 0) return;

  graficoEscalaHabitos.setActiveElements([{ datasetIndex: 0, index: indiceBarraActiva }]);
  graficoEscalaHabitos.tooltip.setActiveElements([{ datasetIndex: 0, index: indiceBarraActiva }]);
  graficoEscalaHabitos.update(BH_REDUCED_MOTION ? 'none' : undefined);
}

function manejarTecladoEscalaHabitos(evento) {
  if (!graficoEscalaHabitos || !datosEscalaHabitos.length) return;

  var ultimo = datosEscalaHabitos.length - 1;

  switch (evento.key) {
    case 'ArrowDown':
    case 'ArrowRight':
      evento.preventDefault();
      indiceBarraActiva = Math.min((indiceBarraActiva < 0 ? -1 : indiceBarraActiva) + 1, ultimo);
      resaltarBarraEscalaHabitos();
      break;
    case 'ArrowUp':
    case 'ArrowLeft':
      evento.preventDefault();
      indiceBarraActiva = Math.max((indiceBarraActiva < 0 ? 1 : indiceBarraActiva) - 1, 0);
      resaltarBarraEscalaHabitos();
      break;
    case 'Enter':
    case ' ':
      evento.preventDefault();
      if (indiceBarraActiva < 0 || typeof window.abrirInstantaneaCategoria !== 'function') return;
      var item = datosEscalaHabitos[indiceBarraActiva];
      window.abrirInstantaneaCategoria(item.categoria, item.label, evento.currentTarget);
      break;
    default:
      break;
  }
}

function inicializarSelectorEscalaHabitos() {
  var botones = document.querySelectorAll('[data-escala-habitos]');
  var botonInfo = document.querySelector('[data-escala-habitos-info]');
  var canvas = document.getElementById('graficoEscalaHabitos');

  if (canvas) canvas.addEventListener('keydown', manejarTecladoEscalaHabitos);

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
    titulo.textContent = 'Si invirtieras tu gasto de ' + (label || datosInstantaneaInversion.label);
  }

  if (subtitulo) {
    subtitulo.textContent = 'Media mensual: ' + formatearEuros(datosInstantaneaInversion.mediaMensual) + '.';
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
    if (generado) generado.textContent = formatearEuros(resultado.eurosGenerados);
  });
}

// ----------------------------------------------------------------------
// Estado general del mes: totales, hero y cascada.
// ----------------------------------------------------------------------
async function cargarEstadoGeneralDashboard() {
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
    window.bhDashboardFlexibleShareLabel = valores.ingresos > 0
      ? formatearPorcentaje((Number(valores.gastosFlexibles) / Number(valores.ingresos)) * 100) + ' %'
      : '0 %';
    actualizarGraficoHistoriaMes(valores);
  } catch (error) {
    abrirModalInfo({ titulo: 'Problema de conexión', mensaje: 'No se pudo contactar con el servidor para cargar los datos del mes.' });
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
    var serie = prepararSerieGastosConHuecos(meses, valores);
    var graficoVacio = serie.valores.length === 0;
    actualizarResumenVariacionGastos('flexible', valores, meses);
    actualizarResumenGrafico(
      'graficoGastosFlexibles6mResumen',
      'Evolución de gastos flexibles: ' + describirSerieEuros(serie.meses, serie.valores)
    );

    if (graficoGastosFlexibles6m) { graficoGastosFlexibles6m.destroy(); graficoGastosFlexibles6m = null; }

    graficoGastosFlexibles6m = new Chart(ctx, {
      type: 'line',
      data: {
        labels: serie.meses,
        datasets: [{
          label: 'Gastos flexibles',
          data: serie.valores,
          borderColor: BH_COLORS.flexible,
          backgroundColor: BH_COLORS.flexibleSoft,
          borderWidth: 2,
          pointRadius: 4,
          pointBackgroundColor: BH_COLORS.flexible,
          spanGaps: false,
          tension: 0.35,
        }],
      },
      options: crearOpcionesGrafico({
        tooltip: {
          callbacks: {
            title: function (items) {
              var label = items && items[0] ? items[0].label : '';

              return formatearMesGrafico(label);
            },
            label: function (context) {
              return context.dataset.label + ': ' + formatearEuros(context.parsed.y);
            },
          },
        },
        legend: false,
        scales: graficoVacio
          ? crearEscalasVaciasConEuros(false)
          : aplicarAbreviaturasMesEjeX(crearEscalasConEuros(false)),
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
    var serie = prepararSerieGastosConHuecos(meses, valores);
    var graficoVacio = serie.valores.length === 0;
    actualizarResumenVariacionGastos('esencial', valores, meses);
    actualizarResumenGrafico(
      'graficoGastosEsenciales6mResumen',
      'Evolución de gastos esenciales: ' + describirSerieEuros(serie.meses, serie.valores)
    );

    if (graficoGastosEsenciales6m) { graficoGastosEsenciales6m.destroy(); graficoGastosEsenciales6m = null; }

    graficoGastosEsenciales6m = new Chart(ctx, {
      type: 'line',
      data: {
        labels: serie.meses,
        datasets: [{
          label: 'Gastos esenciales',
          data: serie.valores,
          borderColor: BH_COLORS.essential,
          backgroundColor: BH_COLORS.essentialSoft,
          borderWidth: 2,
          pointRadius: 4,
          pointBackgroundColor: BH_COLORS.essential,
          spanGaps: false,
          tension: 0.35,
        }],
      },
      options: crearOpcionesGrafico({
        tooltip: {
          callbacks: {
            title: function (items) {
              var label = items && items[0] ? items[0].label : '';

              return formatearMesGrafico(label);
            },
            label: function (context) {
              return context.dataset.label + ': ' + formatearEuros(context.parsed.y);
            },
          },
        },
        legend: false,
        scales: graficoVacio
          ? crearEscalasVaciasConEuros(false)
          : aplicarAbreviaturasMesEjeX(crearEscalasConEuros(false)),
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
    var serie = prepararSerieAhorrosConHuecos(meses, ahorroPosible, ahorroReal, data.data.tieneDatos);
    var graficoVacio = serie.meses.length === 0;
    var resumenAhorro = serie.meses.length
      ? serie.meses.map(function (mes, index) {
        return formatearMesGrafico(mes) + ': ahorro posible ' + describirValorSerieEuros(serie.ahorroPosible[index]) + ', ahorro real ' + describirValorSerieEuros(serie.ahorroReal[index]);
      }).join('; ') + '.'
      : 'No hay datos suficientes para mostrar la evolución del ahorro.';

    actualizarResumenGrafico('graficoAhorros6mResumen', 'Evolución del ahorro: ' + resumenAhorro);

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
              return v >= 0 ? BH_COLORS.positiveSoft : BH_COLORS.negative;
            }),
            barPercentage: 0.9,
            categoryPercentage: 0.6,
          },
          {
            label: 'Ahorro real',
            data: serie.ahorroReal,
            backgroundColor: serie.ahorroReal.map(function (v) {
              return v >= 0 ? BH_COLORS.positive : BH_COLORS.negative;
            }),
            barPercentage: 0.9,
            categoryPercentage: 0.6,
          },
        ],
      },
      options: crearOpcionesGrafico({
        tooltip: {
          callbacks: {
            title: function (items) {
              var label = items && items[0] ? items[0].label : '';

              return formatearMesGrafico(label);
            },
            label: function (context) {
              return context.dataset.label + ': ' + formatearEuros(context.parsed.y);
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
                { text: 'Ahorro posible (+)', fillStyle: BH_COLORS.positiveSoft, strokeStyle: BH_COLORS.positiveSoft, pointStyle: 'rectRounded' },
                { text: 'Ahorro real (+)',     fillStyle: BH_COLORS.positive, strokeStyle: BH_COLORS.positive, pointStyle: 'rectRounded' },
                { text: 'Valores negativos',    fillStyle: BH_COLORS.negative, strokeStyle: BH_COLORS.negative, pointStyle: 'rectRounded' },
              ];
            },
          },
        },
        scales: graficoVacio
          ? crearEscalasVaciasConEuros(true)
          : aplicarAbreviaturasMesEjeX(crearEscalasConEuros(false)),
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
  var colores = datosEscalaHabitos.map(function () {
    return BH_COLORS.flexible;
  });
  var tipoValor = escalaHabitosActiva === 'anio' ? 'proyección anual' : 'media mensual';
  var resumenEscala = labels.length
    ? labels.map(function (label, index) {
      return label + ', ' + tipoValor + ': ' + formatearEuros(valores[index]);
    }).join('; ') + '.'
    : 'No hay categorías de gasto flexible suficientes para mostrar este gráfico.';

  actualizarResumenGrafico('graficoEscalaHabitosResumen', 'Top de gastos flexibles: ' + resumenEscala);

  if (graficoEscalaHabitos) { graficoEscalaHabitos.destroy(); graficoEscalaHabitos = null; }

  var opcionesEscalaHabitos = crearOpcionesGrafico({
    tooltip: {
      position: 'nearest',
      yAlign: 'bottom',
      xAlign: 'center',
      caretPadding: 12,
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

  opcionesEscalaHabitos.layout.padding.top = 28;
  opcionesEscalaHabitos.indexAxis = 'y';
  opcionesEscalaHabitos.animation = BH_REDUCED_MOTION ? false : { duration: 180, easing: 'easeOutQuart' };
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

  indiceBarraActiva = datosEscalaHabitos.length ? 0 : -1;
}

// Exponer globalmente
window.cargarEstadoGeneralDashboard = cargarEstadoGeneralDashboard;
window.cargarGraficoGastosFlexibles6m = cargarGraficoGastosFlexibles6m;
window.cargarGraficoGastosEsenciales6m = cargarGraficoGastosEsenciales6m;
window.cargarGraficoAhorros6m = cargarGraficoAhorros6m;
window.cargarGraficoEscalaHabitos = cargarGraficoEscalaHabitos;
window.abrirInstantaneaCategoria = abrirInstantaneaCategoria;
window.crearGraficoCascadaDashboard = crearGraficoCascadaDashboard;

registrarResponsiveHistoriaMes();
registrarCtaHeroVacio();
