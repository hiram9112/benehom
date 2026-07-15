// ---------------------------------------Función para editar formato de
// categorias--------------------------------

function formatearCategoriaJS(texto) {
  if (window.BH_GASTO_CATEGORIA_LABELS?.[texto]) {
    return window.BH_GASTO_CATEGORIA_LABELS[texto];
  }

  //Reemplazamos los "_" por espacios en blanco
  texto = texto.replace(/_/g, " ");

  //Poenmos en mayúsculas el inicio de cada palabra
  return texto.replace(/\b\w/g, (letra) => letra.toUpperCase());
}

// ---------------------------------------Función para actualizar
// totales---------------------------------------------

function actualizarTotales(valores) {
  const ingresos = Number(valores.ingresos) || 0;
  const gastosEsenciales = Number(valores.gastosEsenciales) || 0;
  const gastosFlexibles = Number(valores.gastosFlexibles) || 0;
  const ahorroReal = Number(valores.ahorroReal) || 0;
  const ahorroPosibleNumero = ingresos - gastosEsenciales;
  const mesActual = nombreMesResumen(document.getElementById("mes")?.value);
  const sinMovimientos = ingresos === 0 && gastosEsenciales === 0 && gastosFlexibles === 0;
  const ingresosAhorrados = ingresos > 0 ? (ahorroReal / ingresos) * 100 : null;
  const pesoGastosFlexibles = ingresos > 0 ? (gastosFlexibles / ingresos) * 100 : null;

  const tIngresos = formatearCantidad(ingresos);
  const tGastosEsenciales = formatearCantidad(gastosEsenciales);
  const tGastosFlexibles = formatearCantidad(gastosFlexibles);
  const tAhorroPosible = formatearCantidad(ahorroPosibleNumero);
  const tAhorro = formatearCantidad(ahorroReal);
  const signoBalance = ahorroReal > 0 ? "+" : ahorroReal < 0 ? "-" : "";
  const tBalanceResumen = `${signoBalance}${formatearCantidad(Math.abs(ahorroReal))}`;
  const ahorroPosibleInfoBtn = `<button type="button" class="bh-btn bh-btn-icon bh-btn-ghost info-btn bh-checkpoint-inline-info" data-bh-popover-title="Ahorro posible" data-bh-popover="Es una referencia: muestra cuánto podrías ahorrar si solo tuvieras ingresos y gastos esenciales." aria-label="Información sobre ahorro posible"><i class="ti ti-info-circle" aria-hidden="true"></i></button>`;
  const ahorroRealInfoBtn = `<button type="button" class="bh-btn bh-btn-icon bh-btn-ghost info-btn bh-checkpoint-inline-info" data-bh-popover-title="Ahorro real" data-bh-popover="Es lo que realmente queda al final del mes después de ingresos, gastos esenciales y gastos flexibles." aria-label="Información sobre ahorro real"><i class="ti ti-info-circle" aria-hidden="true"></i></button>`;

  //Totales de las tarjetas
  document.getElementById("total_ingresos_texto").innerHTML =
    `Ingresos del mes · <strong class="bh-amount">${tIngresos} €</strong>`;
  document.getElementById("total_gastos_esenciales_texto").innerHTML =
    `Gastos esenciales del mes · <strong class="bh-amount">${tGastosEsenciales} €</strong>`;
  document.getElementById("ahorro_posible_texto").innerHTML =
    `<span class="bh-checkpoint-label">Ahorro posible ${ahorroPosibleInfoBtn}</span><strong class="bh-amount">${tAhorroPosible} €</strong>`;
  document.getElementById("total_gastos_flexibles_texto").innerHTML =
    `Gastos flexibles del mes · <strong class="bh-amount">${tGastosFlexibles} €</strong>`;
  document.getElementById("ahorro_real_texto").innerHTML =
    `<span class="bh-checkpoint-label">Ahorro real del mes ${ahorroRealInfoBtn}</span><strong class="bh-amount">${tAhorro} €</strong>`;

  //Resumen mensual superior
  const resumenAhorro = document.getElementById("resumen_ahorro_real");
  const resumenAhorroMes = document.getElementById("resumen_ahorro_mes");
  const resumenIngresosAhorrados = document.getElementById("resumen_ingresos_ahorrados");
  const resumenIngresosAhorradosDetalle = document.getElementById("resumen_ingresos_ahorrados_detalle");
  const resumenGastosFlexibles = document.getElementById("resumen_gastos_flexibles_peso");
  const resumenGastosFlexiblesDetalle = document.getElementById("resumen_gastos_flexibles_peso_detalle");
  const ahorroCard = document.getElementById("resumen_ahorro_card");
  const ingresosAhorradosCard = document.getElementById("resumen_ingresos_ahorrados_card");
  const gastosFlexiblesCard = document.getElementById("resumen_gastos_flexibles_peso_card");

  window.bhDashboardHeroMesSinMovimientos = sinMovimientos;

  if (resumenAhorro) resumenAhorro.textContent = sinMovimientos ? "—" : `${tBalanceResumen} €`;
  if (resumenAhorroMes) resumenAhorroMes.textContent = sinMovimientos ? `Sin movimientos en ${mesActual}` : `Ahorro real de ${mesActual}`;
  if (resumenIngresosAhorrados) resumenIngresosAhorrados.textContent = ingresosAhorrados === null ? "—" : `${formatearPorcentaje(ingresosAhorrados)} %`;
  if (resumenGastosFlexibles) resumenGastosFlexibles.textContent = pesoGastosFlexibles === null ? "—" : `${formatearPorcentaje(pesoGastosFlexibles)} %`;
  if (resumenIngresosAhorradosDetalle) {
    resumenIngresosAhorradosDetalle.textContent = describirRatioAhorro(ingresosAhorrados, mesActual, sinMovimientos);
  }
  if (resumenGastosFlexiblesDetalle) {
    resumenGastosFlexiblesDetalle.textContent = pesoGastosFlexibles === null
      ? describirAusenciaRatio(mesActual, sinMovimientos)
      : `De cada 100 € ingresados, ${formatearCantidad(pesoGastosFlexibles)} € van a gasto flexible`;
  }

  [ahorroCard, ingresosAhorradosCard, gastosFlexiblesCard].forEach((card) => {
    if (!card) return;
    card.classList.remove("is-positive", "is-negative", "is-neutral", "is-empty");
  });

  if (ahorroCard) {
    if (sinMovimientos) {
      ahorroCard.classList.add("is-empty");
    } else if (ahorroReal > 0) {
      ahorroCard.classList.add("is-positive");
    } else if (ahorroReal < 0) {
      ahorroCard.classList.add("is-negative");
    } else {
      ahorroCard.classList.add("is-neutral");
    }
  }

  if (ingresosAhorradosCard) {
    if (ingresosAhorrados === null) {
      ingresosAhorradosCard.classList.add("is-empty");
    } else if (ahorroReal > 0) {
      ingresosAhorradosCard.classList.add("is-positive");
    } else if (ahorroReal < 0) {
      ingresosAhorradosCard.classList.add("is-negative");
    } else {
      ingresosAhorradosCard.classList.add("is-neutral");
    }
  }

  if (gastosFlexiblesCard && pesoGastosFlexibles === null) {
    gastosFlexiblesCard.classList.add("is-empty");
  }

  if (window.bhDashboardHeroSeries) {
    Object.keys(window.bhDashboardHeroSeries).forEach((tipo) => {
      const serie = window.bhDashboardHeroSeries[tipo];
      actualizarResumenVariacionGastos(tipo, serie.valores, serie.meses);
    });
  }

  //Asignamos colores de manera dinámica a los totales de las tarjetas
  const ahorroPosibleElem = document.getElementById("ahorro_posible_texto");
  const ahorroElem = document.getElementById("ahorro_real_texto");

  //Rmovemos clases anteriores
  ahorroPosibleElem?.classList.remove("valor-positivo", "valor-negativo");
  ahorroElem?.classList.remove("valor-positivo", "valor-negativo");

  //Aplicamos color según valor
  ahorroPosibleElem?.classList.add(
    ahorroPosibleNumero >= 0 ? "valor-positivo" : "valor-negativo",
  );
  ahorroElem?.classList.add(ahorroReal >= 0 ? "valor-positivo" : "valor-negativo");

  if (window.BHComponents) {
    window.BHComponents.initEducationPopovers(document);
  }
}

function actualizarResumenVariacionGastos(tipo, valores, meses) {
  const esEsencial = tipo === "esencial";
  const elemento = document.getElementById(
    esEsencial
      ? "resumen_variacion_esenciales"
      : "resumen_variacion_flexibles",
  );
  const card = document.getElementById(
    esEsencial
      ? "resumen_variacion_esenciales_card"
      : "resumen_variacion_flexibles_card",
  );
  const mesAnterior = document.getElementById(
    esEsencial
      ? "resumen_variacion_esenciales_mes"
      : "resumen_variacion_flexibles_mes",
  );
  const detalle = document.getElementById(
    esEsencial
      ? "resumen_variacion_esenciales_detalle"
      : "resumen_variacion_flexibles_detalle",
  );

  if (!elemento) return;

  if (window.bhDashboardHeroSeries) {
    window.bhDashboardHeroSeries[tipo] = { valores, meses };
  }

  if (!Array.isArray(valores) || valores.length < 2) {
    elemento.textContent = "—";
    if (detalle) detalle.textContent = "Sin datos del mes anterior";
    if (card) {
      card.hidden = false;
      card.classList.remove("is-positive", "is-warning", "is-danger");
      card.classList.add("is-empty");
    }
    return;
  }

  const actual = Number(valores[valores.length - 1]) || 0;
  const anterior = Number(valores[valores.length - 2]) || 0;
  const mesAnteriorTexto = Array.isArray(meses) ? nombreMesResumen(meses[meses.length - 2]) : "mes anterior";

  if (window.bhDashboardHeroMesSinMovimientos || anterior <= 0) {
    elemento.textContent = "—";
    if (detalle) detalle.textContent = `Sin datos de ${mesAnteriorTexto}`;

    if (mesAnterior && Array.isArray(meses)) {
      mesAnterior.textContent = mesAnteriorTexto;
    }

    if (card) {
      card.hidden = false;
      card.classList.remove("is-positive", "is-warning", "is-danger");
      card.classList.add("is-empty");
    }

    return;
  }

  const variacion = ((actual - anterior) / anterior) * 100;
  const deltaAbs = Math.abs(actual - anterior);

  elemento.textContent = `${iconoVariacion(variacion)} ${formatearPorcentajeConSigno(variacion)} %`;
  if (detalle) {
    detalle.textContent = deltaAbs === 0
      ? `Sin variación respecto a ${mesAnteriorTexto}`
      : `${formatearCantidad(deltaAbs)} € ${actual > anterior ? "más" : "menos"} que en ${mesAnteriorTexto}`;
  }

  if (mesAnterior && Array.isArray(meses)) {
    mesAnterior.textContent = mesAnteriorTexto;
  }

  if (card) {
    card.hidden = false;
    card.classList.remove("is-positive", "is-warning", "is-danger", "is-empty");
    card.classList.add(variacion > 0 ? "is-danger" : "is-positive");
  }
}

function describirRatioAhorro(ratio, mes, sinMovimientos) {
  if (ratio === null) {
    return describirAusenciaRatio(mes, sinMovimientos);
  }

  return `De cada 100 € ingresados, guardas ${formatearCantidad(ratio)} €`;
}

function describirAusenciaRatio(mes, sinMovimientos) {
  return sinMovimientos ? `Sin movimientos en ${mes}` : `Sin ingresos en ${mes}`;
}

function iconoVariacion(valor) {
  const numero = Number(valor) || 0;

  if (numero > 0) return "↗";
  if (numero < 0) return "↘";

  return "→";
}

function nombreMesResumen(valor) {
  const partes = String(valor || "").split("-");
  const year = Number(partes[0]);
  const month = Number(partes[1]);

  if (!year || !month) {
    return "mes anterior";
  }

  return new Intl.DateTimeFormat("es-ES", { month: "long" })
    .format(new Date(year, month - 1, 1));
}

function formatearPorcentajeConSigno(valor) {
  const numero = Number(valor) || 0;

  if (numero === 0) {
    return "0";
  }

  return `${numero > 0 ? "+" : ""}${formatearPorcentaje(numero)}`;
}

//Función para formatear cantidades
function formatearCantidad(valor) {
  const numero = Number(valor) || 0;

  if (window.BHMoney) {
    return window.BHMoney.formatAmount(numero);
  }

  return new Intl.NumberFormat("es-ES", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(numero);
}

function formatearPorcentaje(valor) {
  const numero = Number(valor) || 0;

  if (Number.isInteger(numero)) {
    return numero.toString();
  }

  return numero.toFixed(1).replace(".", ",");
}
