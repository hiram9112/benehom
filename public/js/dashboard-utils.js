// ---------------------------------------Función para editar formato de
// categorias--------------------------------

function formatearCategoriaJS(texto) {
  //Reemplazamos los "_" por espacios en blanco
  texto = texto.replace(/_/g, " ");

  //Poenmos en mayúsculas el inicio de cada palabra
  return texto.replace(/\b\w/g, (letra) => letra.toUpperCase());
}

// ---------------------------------------Función para actualizar
// totales---------------------------------------------

function actualizarTotales(valores) {
  const tIngresos = formatearCantidad(valores.ingresos);
  const tOblig = formatearCantidad(valores.obligatorios);
  const tVolun = formatearCantidad(valores.voluntarios);
  const tAhorro = formatearCantidad(valores.ahorroReal);

  //Calculamos capacidad de ahorro

  const capacidad = formatearCantidad(
    Number(valores.ingresos) - Number(valores.obligatorios),
  );

  //Totales de las tarjetas
  document.getElementById("total_ingresos_texto").innerHTML =
    `Ingresos del mes: <strong>${tIngresos}€</strong>`;
  document.getElementById("total_gastos_obligatorios_texto").innerHTML =
    `Gastos obligatorios del mes: <strong>${tOblig}€</strong>`;
  document.getElementById("capacidad_ahorro_texto").innerHTML =
    `Capacidad de ahorro: <strong>${capacidad}</strong>`;
  document.getElementById("total_gastos_voluntarios_texto").innerHTML =
    `Gastos voluntarios  del mes: <strong>${tVolun}€</strong>`;
  document.getElementById("ahorro_real_texto").innerHTML =
    `Ahorro del mes: <strong>${tAhorro}€</strong>`;

  //Totales del primero gráfico
  document.getElementById("ahorro_mensual").innerHTML =
    `Ahorro: <strong>${tAhorro}€</strong>`;
  document.getElementById("totalIngresosTexto").innerHTML =
    `Ingresos: <strong>${tIngresos}</strong>`;

  ////Asignamos colores de manera dinámica a los totales del primer gráfico
  const ingresoSUp = document.getElementById("totalIngresosTexto");
  const ahorroSup = document.getElementById("ahorro_mensual");

  //Rmovemos clases anteriores
  ingresoSUp.classList.remove("valor-positivo", "valor-negativo");
  ahorroSup.classList.remove("valor-positivo", "valor-negativo");

  //Aplicamos color según valor al ahorro y fijo al ingreso
  ingresoSUp.classList.add("valor-positivo");
  ahorroSup.classList.add(tAhorro >= 0 ? "valor-positivo" : "valor-negativo");

  //Asignamos colores de manera dinámica a los totales de las tarjetas
  const capacidadElem = document.getElementById("capacidad_ahorro_texto");
  const ahorroElem = document.getElementById("ahorro_real_texto");

  //Rmovemos clases anteriores
  capacidadElem.classList.remove("valor-positivo", "valor-negativo");
  ahorroElem.classList.remove("valor-positivo", "valor-negativo");

  //Aplicamos color según valor
  capacidadElem.classList.add(
    capacidad >= 0 ? "valor-positivo" : "valor-negativo",
  );
  ahorroElem.classList.add(tAhorro >= 0 ? "valor-positivo" : "valor-negativo");
}

//Función para formatear cantidades
function formatearCantidad(valor) {
  const numero = Number(valor);

  if (Number.isInteger(numero)) {
    return numero.toString();
  }

  return numero.toFixed(2).replace(".", ",");
}