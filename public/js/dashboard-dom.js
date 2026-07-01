function crearEstadoVacioDashboard(tipo) {
  const estados = {
    ingresos: {
      icono: "bi-wallet2",
      titulo: "Sin ingresos",
      texto: "Añade un ingreso.",
    },
    esenciales: {
      icono: "bi-house-heart",
      titulo: "Sin gastos esenciales",
      texto: "Añade un gasto esencial.",
    },
    flexibles: {
      icono: "bi-basket2",
      titulo: "Sin gastos flexibles",
      texto: "Añade un gasto flexible.",
    },
  };

  const estado = estados[tipo];

  return `
    <div class="bh-empty-state bh-dashboard-empty-state">
      <span class="bh-empty-state-icon" aria-hidden="true"><i class="bi ${estado.icono}" aria-hidden="true"></i></span>
      <h4 class="bh-empty-state-title">${estado.titulo}</h4>
      <p class="bh-empty-state-text">${estado.texto}</p>
    </div>`;
}

function obtenerCantidadMovimiento(li) {
  const cantidad = Number(li.dataset.cantidad);

  if (!Number.isNaN(cantidad)) {
    return cantidad;
  }

  const textoCantidad = li
    .querySelector(".bh-movement-amount")
    ?.textContent.trim()
    .replace(/\./g, "")
    .replace(",", ".");

  return Number(textoCantidad) || 0;
}

function ordenarMovimientosPorCantidadDesc(contenedorId) {
  const lista = document.querySelector(`#${contenedorId} ul`);

  if (!lista) {
    return;
  }

  [...lista.children]
    .sort((a, b) => {
      const diferenciaCantidad =
        obtenerCantidadMovimiento(b) - obtenerCantidadMovimiento(a);

      if (diferenciaCantidad !== 0) {
        return diferenciaCantidad;
      }

      return (Number(b.dataset.id) || 0) - (Number(a.dataset.id) || 0);
    })
    .forEach((li) => lista.appendChild(li));
}

function eliminarEstadoVacioDashboard(contenedorId) {
  document
    .querySelectorAll(`#${contenedorId} .bh-empty-state, #${contenedorId} .bh-form-empty-state`)
    .forEach((estadoVacio) => estadoVacio.remove());
}

function agregarIngresoAlDOM(ingreso) {
  //Intentamos seleccionar  la lista existente
  let lista = document.querySelector("#lista_ingresos ul");

  // Si existe el estado vacío lo eliminamos antes de insertar
  eliminarEstadoVacioDashboard("lista_ingresos");

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
  li.dataset.id = ingreso.id;
  li.dataset.cantidad = ingreso.cantidad;

  //Agregamos el nuevo ingreso al elemento li
  const categoriaIngresoLabel = formatearCategoriaJS(ingreso.categoria);
  li.innerHTML = `
    <div class="bh-movement-main">
      <span class="bh-movement-label categoria_ingreso_individual">${categoriaIngresoLabel}</span>
    </div>
    <div class="bh-movement-side">
      <span class="bh-movement-amount cantidad_ingreso" data-id="${ingreso.id}">${formatearCantidad(ingreso.cantidad)}</span><span class="bh-money-symbol">€</span>
      <button class="bh-btn bh-btn-icon bh-btn-ghost eliminar_ingreso" data-id="${ingreso.id}" aria-label="Eliminar ingreso ${categoriaIngresoLabel}"><i class="bi bi-trash" aria-hidden="true"></i></button>
    </div>
    `;

  //Insertamos el nuevo elemento al inicio de la lista
  lista.prepend(li);
  ordenarMovimientosPorCantidadDesc("lista_ingresos");
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
  eliminarEstadoVacioDashboard("lista_gastos_esenciales");

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
  li.dataset.id = gastoEsencial.id;
  li.dataset.cantidad = gastoEsencial.cantidad;
  li.dataset.tipo = "esencial";

  //Agregamos el nuevo gasto esencial al elemento li
  const categoriaGastoEsencialLabel = formatearCategoriaJS(gastoEsencial.categoria);
  li.innerHTML = `
    <div class="bh-movement-main">
      <span class="bh-movement-label categoria_gasto_esencial">${categoriaGastoEsencialLabel}</span>
    </div>
    <div class="bh-movement-side">
      <span class="bh-movement-amount cantidad_gasto_esencial cantidad_gasto" data-id="${gastoEsencial.id}">${formatearCantidad(gastoEsencial.cantidad)}</span><span class="bh-money-symbol">€</span>
      <button class="bh-btn bh-btn-icon bh-btn-ghost eliminar_gasto" data-id="${gastoEsencial.id}" aria-label="Eliminar gasto esencial ${categoriaGastoEsencialLabel}"><i class="bi bi-trash" aria-hidden="true"></i></button>
    </div>
    `;

  //Insertamos el nuevo elemento al inicio de la lista
  lista.prepend(li);
  ordenarMovimientosPorCantidadDesc("lista_gastos_esenciales");
}

// -------------------------------------------Función para agregar el nuevo
// gasto flexible al DOM----------------------

function agregarGastoFlexibleAlDOM(gastoFlexible) {
  //Intentamos seleccionar  la lista existente
  let lista = document.querySelector("#lista_gastos_flexibles ul");

  //Si existe el estado vacío lo eliminamos antes de insertar
  eliminarEstadoVacioDashboard("lista_gastos_flexibles");

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
  li.dataset.id = gastoFlexible.id;
  li.dataset.cantidad = gastoFlexible.cantidad;
  li.dataset.tipo = "flexible";

  //Agregamos el nuevo gasto flexible al elemento li
  const categoriaGastoFlexibleLabel = formatearCategoriaJS(gastoFlexible.categoria);
  li.innerHTML = `
    <div class="bh-movement-main">
      <span class="bh-movement-label categoria_gasto_flexible">${categoriaGastoFlexibleLabel}</span>
    </div>
    <div class="bh-movement-side">
      <span class="bh-movement-amount cantidad_gasto_flexible cantidad_gasto" data-id="${gastoFlexible.id}">${formatearCantidad(gastoFlexible.cantidad)}</span><span class="bh-money-symbol">€</span>
      <button class="bh-btn bh-btn-icon bh-btn-ghost eliminar_gasto" data-id="${gastoFlexible.id}" aria-label="Eliminar gasto flexible ${categoriaGastoFlexibleLabel}"><i class="bi bi-trash" aria-hidden="true"></i></button>
    </div>
    `;

  //Insertamos el nuevo elemento al inicio de la lista
  lista.prepend(li);
  ordenarMovimientosPorCantidadDesc("lista_gastos_flexibles");
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
    tipo === "esencial"
      ? "lista_gastos_esenciales"
      : "lista_gastos_flexibles";

  //Seleccionamos la lista correspondiente
  const lista = document.querySelector(`#${contenedorID} ul`);

  if (!lista || lista.children.length === 0) {
    const nombreTipo = tipo === "esencial" ? "esenciales" : "flexibles";
    document.getElementById(contenedorID).innerHTML =
      crearEstadoVacioDashboard(nombreTipo);
  }
}
