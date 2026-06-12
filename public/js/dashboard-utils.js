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
  const ingresosAhorrados = ingresos > 0 ? (ahorroReal / ingresos) * 100 : 0;
  const pesoGastosFlexibles = ingresos > 0 ? (gastosFlexibles / ingresos) * 100 : 0;

  const tIngresos = formatearCantidad(ingresos);
  const tGastosEsenciales = formatearCantidad(gastosEsenciales);
  const tGastosFlexibles = formatearCantidad(gastosFlexibles);
  const tAhorroPosible = formatearCantidad(ahorroPosibleNumero);
  const tAhorro = formatearCantidad(ahorroReal);
  const signoBalance = ahorroReal > 0 ? "+" : ahorroReal < 0 ? "-" : "";
  const tBalanceResumen = `${signoBalance}${formatearCantidad(Math.abs(ahorroReal))}`;

  //Totales de las tarjetas
  document.getElementById("total_ingresos_texto").innerHTML =
    `Ingresos del mes: <strong>${tIngresos}€</strong>`;
  document.getElementById("total_gastos_esenciales_texto").innerHTML =
    `Gastos esenciales del mes: <strong>${tGastosEsenciales}€</strong>`;
  document.getElementById("ahorro_posible_texto").innerHTML =
    `Ahorro posible: <strong>${tAhorroPosible}€</strong>`;
  document.getElementById("total_gastos_flexibles_texto").innerHTML =
    `Gastos flexibles del mes: <strong>${tGastosFlexibles}€</strong>`;
  document.getElementById("ahorro_real_texto").innerHTML =
    `Ahorro real del mes: <strong>${tAhorro}€</strong>`;

  //Resumen mensual superior
  const resumenAhorro = document.getElementById("resumen_ahorro_real");
  const resumenIngresosAhorrados = document.getElementById("resumen_ingresos_ahorrados");
  const resumenGastosFlexibles = document.getElementById("resumen_gastos_flexibles_peso");
  const ahorroCard = document.getElementById("resumen_ahorro_card");

  if (resumenAhorro) resumenAhorro.textContent = `${tBalanceResumen}€`;
  if (resumenIngresosAhorrados) resumenIngresosAhorrados.textContent = `${formatearPorcentaje(ingresosAhorrados)}%`;
  if (resumenGastosFlexibles) resumenGastosFlexibles.textContent = `${formatearPorcentaje(pesoGastosFlexibles)}%`;

  [ahorroCard].forEach((card) => {
    if (!card) return;
    card.classList.remove("is-positive", "is-negative");
    card.classList.add(ahorroReal >= 0 ? "is-positive" : "is-negative");
  });

  //Asignamos colores de manera dinámica a los totales de las tarjetas
  const ahorroPosibleElem = document.getElementById("ahorro_posible_texto");
  const ahorroElem = document.getElementById("ahorro_real_texto");

  //Rmovemos clases anteriores
  ahorroPosibleElem.classList.remove("valor-positivo", "valor-negativo");
  ahorroElem.classList.remove("valor-positivo", "valor-negativo");

  //Aplicamos color según valor
  ahorroPosibleElem.classList.add(
    ahorroPosibleNumero >= 0 ? "valor-positivo" : "valor-negativo",
  );
  ahorroElem.classList.add(ahorroReal >= 0 ? "valor-positivo" : "valor-negativo");
}

function actualizarResumenVariacionGastos(tipo, valores) {
  const elemento = document.getElementById(
    tipo === "esencial"
      ? "resumen_variacion_esenciales"
      : "resumen_variacion_flexibles",
  );

  if (!elemento) return;

  const actual = Number(valores[valores.length - 1]) || 0;
  const anterior = Number(valores[valores.length - 2]) || 0;
  const variacion = anterior > 0 ? ((actual - anterior) / anterior) * 100 : 0;

  elemento.textContent = formatearPorcentajeConSigno(variacion);
}

function formatearPorcentajeConSigno(valor) {
  const numero = Number(valor) || 0;

  if (numero === 0) {
    return "0%";
  }

  return `${numero > 0 ? "+" : ""}${formatearPorcentaje(numero)}%`;
}

//Función para formatear cantidades
function formatearCantidad(valor) {
  const numero = Number(valor) || 0;
  const opciones = Number.isInteger(numero)
    ? { maximumFractionDigits: 0 }
    : { minimumFractionDigits: 2, maximumFractionDigits: 2 };

  return new Intl.NumberFormat("es-ES", opciones).format(numero);
}

function formatearPorcentaje(valor) {
  const numero = Number(valor) || 0;

  if (Number.isInteger(numero)) {
    return numero.toString();
  }

  return numero.toFixed(1).replace(".", ",");
}
