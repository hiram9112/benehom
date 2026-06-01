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
  const obligatorios = Number(valores.obligatorios) || 0;
  const voluntarios = Number(valores.voluntarios) || 0;
  const ahorroReal = Number(valores.ahorroReal) || 0;
  const gastosTotales = Number(valores.gastosTotales) || 0;

  const capacidadNumero = ingresos - obligatorios;
  const ingresosAhorrados = ingresos > 0 ? (ahorroReal / ingresos) * 100 : 0;
  const pesoBase = ingresos > 0 ? (obligatorios / ingresos) * 100 : 0;
  const ingresosUsados = ingresos > 0 ? (gastosTotales / ingresos) * 100 : 0;

  const tIngresos = formatearCantidad(ingresos);
  const tOblig = formatearCantidad(obligatorios);
  const tVolun = formatearCantidad(voluntarios);
  const tAhorro = formatearCantidad(ahorroReal);

  //Calculamos capacidad de ahorro

  const capacidad = formatearCantidad(capacidadNumero);

  //Totales de las tarjetas
  document.getElementById("total_ingresos_texto").innerHTML =
    `Ingresos del mes: <strong>${tIngresos}€</strong>`;
  document.getElementById("total_gastos_obligatorios_texto").innerHTML =
    `Gastos esenciales del mes: <strong>${tOblig}€</strong>`;
  document.getElementById("capacidad_ahorro_texto").innerHTML =
    `Capacidad de ahorro: <strong>${capacidad}</strong>`;
  document.getElementById("total_gastos_voluntarios_texto").innerHTML =
    `Gastos flexibles del mes: <strong>${tVolun}€</strong>`;
  document.getElementById("ahorro_real_texto").innerHTML =
    `Ahorro del mes: <strong>${tAhorro}€</strong>`;

  //Resumen mensual superior
  const resumenEstado = document.getElementById("resumen_estado_mes");
  const resumenAhorro = document.getElementById("resumen_ahorro_real");
  const resumenIngresosAhorrados = document.getElementById("resumen_ingresos_ahorrados");
  const resumenBase = document.getElementById("resumen_peso_base");
  const resumenIngresosUsados = document.getElementById("resumen_ingresos_usados");
  const estadoCard = document.getElementById("resumen_estado_card");
  const ahorroCard = document.getElementById("resumen_ahorro_card");

  if (resumenEstado) {
    resumenEstado.textContent = ahorroReal >= 0 ? "Mes en positivo" : "Mes en negativo";
  }

  if (resumenAhorro) resumenAhorro.textContent = `${tAhorro}€`;
  if (resumenIngresosAhorrados) resumenIngresosAhorrados.textContent = `${formatearPorcentaje(ingresosAhorrados)}%`;
  if (resumenBase) resumenBase.textContent = `${formatearPorcentaje(pesoBase)}%`;
  if (resumenIngresosUsados) resumenIngresosUsados.textContent = `${formatearPorcentaje(ingresosUsados)}%`;

  [estadoCard, ahorroCard].forEach((card) => {
    if (!card) return;
    card.classList.remove("is-positive", "is-negative");
    card.classList.add(ahorroReal >= 0 ? "is-positive" : "is-negative");
  });

  //Asignamos colores de manera dinámica a los totales de las tarjetas
  const capacidadElem = document.getElementById("capacidad_ahorro_texto");
  const ahorroElem = document.getElementById("ahorro_real_texto");

  //Rmovemos clases anteriores
  capacidadElem.classList.remove("valor-positivo", "valor-negativo");
  ahorroElem.classList.remove("valor-positivo", "valor-negativo");

  //Aplicamos color según valor
  capacidadElem.classList.add(
    capacidadNumero >= 0 ? "valor-positivo" : "valor-negativo",
  );
  ahorroElem.classList.add(ahorroReal >= 0 ? "valor-positivo" : "valor-negativo");
}

//Función para formatear cantidades
function formatearCantidad(valor) {
  const numero = Number(valor);

  if (Number.isInteger(numero)) {
    return numero.toString();
  }

  return numero.toFixed(2).replace(".", ",");
}

function formatearPorcentaje(valor) {
  const numero = Number(valor) || 0;

  if (Number.isInteger(numero)) {
    return numero.toString();
  }

  return numero.toFixed(1).replace(".", ",");
}
