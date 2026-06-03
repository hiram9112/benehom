function crearEstadoVacioDashboard(tipo) {
  const estados = {
    ingresos: {
      icono: "bi-wallet2",
      titulo: "Sin ingresos este mes",
      texto:
        "Añade tu primer ingreso para calcular el ahorro posible y el balance real del mes.",
    },
    esenciales: {
      icono: "bi-house-heart",
      titulo: "Sin gastos esenciales",
      texto:
        "Registra vivienda, suministros o gastos necesarios para ver tu ahorro posible.",
    },
    flexibles: {
      icono: "bi-basket2",
      titulo: "Sin gastos flexibles",
      texto:
        "Añade ocio, compras o decisiones variables para comparar ahorro posible y ahorro real.",
    },
  };

  const estado = estados[tipo];

  return `
    <div class="bh-empty-state bh-dashboard-empty-state">
      <span class="bh-empty-state-icon" aria-hidden="true"><i class="bi ${estado.icono}"></i></span>
      <h4 class="bh-empty-state-title">${estado.titulo}</h4>
      <p class="bh-empty-state-text">${estado.texto}</p>
    </div>`;
}

function agregarIngresoAlDOM(ingreso) {
  //Intentamos seleccionar  la lista existente
  let lista = document.querySelector("#lista_ingresos ul");

  // Si existe el estado vacío lo eliminamos antes de insertar
  const estadoVacio = document.querySelector("#lista_ingresos .bh-empty-state");
  if (estadoVacio) estadoVacio.remove();

  //Si no existe la etiqueta ul la creamos
  if (!lista) {
    lista = document.createElement("ul");
    document.getElementById("lista_ingresos").appendChild(lista);
  }

  //creamos el elemento li que representa el nuevo ingreso
  const li = document.createElement("li");
  li.classList.add("bh-movement-item");

  //le asignamos el id correspondiente
  li.id = `ingreso-${ingreso.id}`;

  //Agregamos el nuevo ingreso al elemento li
  li.innerHTML = `
    <div class="bh-movement-main">
      <span class="bh-movement-label categoria_ingreso_individual">${formatearCategoriaJS(
        ingreso.categoria,
      )}</span>
    </div>
    <div class="bh-movement-side">
      <span class="bh-movement-amount cantidad_ingreso" data-id="${ingreso.id}">${formatearCantidad(ingreso.cantidad)}</span><span class="bh-money-symbol">€</span>
      <button class="bh-btn bh-btn-icon bh-btn-ghost eliminar_ingreso" data-id="${ingreso.id}" aria-label="Eliminar ingreso"><i class="bi bi-trash"></i></button>
    </div>
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
  if (li) li.remove();

  //Comprbamos si la lista sigue existiendo y si tiene elementos
  const lista = document.querySelector("#lista_ingresos ul");

  if (!lista || lista.children.length === 0) {
    document.getElementById("lista_ingresos").innerHTML =
      crearEstadoVacioDashboard("ingresos");
  }
}


// --------------------------------------------------------------------------------------------------------------------------------
// --------------------------FUNCIONES AUXILIARES SECCIÓN GASTOS
// ------------------------------------------------------

// -------------------------------------------Función para agregar el nuevo
// gasto esencial al DOM----------------------

function agregarGastoEsencialAlDOM(gastoEsencial) {
  //Intentamos seleccionar  la lista existente
  let lista = document.querySelector("#lista_gastos_esenciales ul");

  //Si existe el estado vacío lo eliminamos antes de insertar
  const estadoVacio = document.querySelector(
    "#lista_gastos_esenciales .bh-empty-state",
  );
  if (estadoVacio) estadoVacio.remove();

  //Si no existe la etiqueta ul la creamos
  if (!lista) {
    lista = document.createElement("ul");
    document.getElementById("lista_gastos_esenciales").appendChild(lista);
  }

  //creamos el elemento li que representa el nuevo gasto esencial
  const li = document.createElement("li");
  li.classList.add("bh-movement-item");

  //le asignamos el id correspondiente y tipo correspondiente
  li.id = `gasto-${gastoEsencial.id}`;
  li.dataset.tipo = "obligatorio";

  //Agregamos el nuevo gasto esencial al elemento li
  li.innerHTML = `
    <div class="bh-movement-main">
      <span class="bh-movement-label categoria_gasto_esencial">${formatearCategoriaJS(
        gastoEsencial.categoria,
      )}</span>
    </div>
    <div class="bh-movement-side">
      <span class="bh-movement-amount cantidad_gasto_esencial cantidad_gasto" data-id="${gastoEsencial.id}">${formatearCantidad(gastoEsencial.cantidad)}</span><span class="bh-money-symbol">€</span>
      <button class="bh-btn bh-btn-icon bh-btn-ghost eliminar_gasto" data-id="${gastoEsencial.id}" aria-label="Eliminar gasto"><i class="bi bi-trash"></i></button>
    </div>
    `;

  //Insertamos el nuevo elemento al inicio de la lista
  lista.prepend(li);
}

// -------------------------------------------Función para agregar el nuevo
// gasto flexible al DOM----------------------

function agregarGastoFlexibleAlDOM(gastoFlexible) {
  //Intentamos seleccionar  la lista existente
  let lista = document.querySelector("#lista_gastos_flexibles ul");

  //Si existe el estado vacío lo eliminamos antes de insertar
  const estadoVacio = document.querySelector(
    "#lista_gastos_flexibles .bh-empty-state",
  );
  if (estadoVacio) estadoVacio.remove();

  //Si no existe la etiqueta ul la creamos
  if (!lista) {
    lista = document.createElement("ul");
    document.getElementById("lista_gastos_flexibles").appendChild(lista);
  }

  //creamos el elemento li que representa el nuevo gasto flexible
  const li = document.createElement("li");
  li.classList.add("bh-movement-item");

  //le asignamos el id y el tipo correspondiente
  li.id = `gasto-${gastoFlexible.id}`;
  li.dataset.tipo = "voluntario";

  //Agregamos el nuevo gasto flexible al elemento li
  li.innerHTML = `
    <div class="bh-movement-main">
      <span class="bh-movement-label categoria_gasto_flexible">${formatearCategoriaJS(
        gastoFlexible.categoria,
      )}</span>
    </div>
    <div class="bh-movement-side">
      <span class="bh-movement-amount cantidad_gasto_flexible cantidad_gasto" data-id="${gastoFlexible.id}">${formatearCantidad(gastoFlexible.cantidad)}</span><span class="bh-money-symbol">€</span>
      <button class="bh-btn bh-btn-icon bh-btn-ghost eliminar_gasto" data-id="${gastoFlexible.id}" aria-label="Eliminar gasto"><i class="bi bi-trash"></i></button>
    </div>
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
  if (li) li.remove();

  //Determinamos que contenedor revisaremos
  const contenedorID =
    tipo === "obligatorio"
      ? "lista_gastos_esenciales"
      : "lista_gastos_flexibles";

  //Seleccionamos la lista correspondiente
  const lista = document.querySelector(`#${contenedorID} ul`);

  if (!lista || lista.children.length === 0) {
    const nombreTipo = tipo === "obligatorio" ? "esenciales" : "flexibles";
    document.getElementById(contenedorID).innerHTML =
      crearEstadoVacioDashboard(nombreTipo);
  }
}
