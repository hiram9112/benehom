function agregarIngresoAlDOM(ingreso) {
  //Intentamos seleccionar  la lista existente
  let lista = document.querySelector("#lista_ingresos ul");

  // Si existe el mensaje de "No tienes ingresos..." lo eliminamos antes de
  // insertar
  const mensaje = document.querySelector("#lista_ingresos p");
  if (mensaje) mensaje.remove();

  //Si no existe la etiqueta ul la creamos
  if (!lista) {
    lista = document.createElement("ul");
    document.getElementById("lista_ingresos").appendChild(lista);
  }

  //creamos el elemento li que representa el nuevo ingreso
  const li = document.createElement("li");

  //le asignamos el id correspondiente
  li.id = `ingreso-${ingreso.id}`;

  //Agregamos el nuevo ingreso al elemento li
  li.innerHTML = `
    <span class="categoria_ingreso_individual">${formatearCategoriaJS(
      ingreso.categoria,
    )}</span>: 
    <span class="cantidad_ingreso" data-id="${ingreso.id}">${formatearCantidad(ingreso.cantidad)}</span>€
    <button class="bh-btn bh-btn-icon bh-btn-ghost eliminar_ingreso" data-id="${ingreso.id}" aria-label="Eliminar ingreso"><i class="bi bi-trash"></i>
    </button>
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
      "<p>No tienes ingresos registrados todavía.</p>";
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

  //Si existe el mensaje de "No tienes gastos..." lo eliminamos antes de insertar
  const mensaje = document.querySelector("#lista_gastos_esenciales p");
  if (mensaje) mensaje.remove();

  //Si no existe la etiqueta ul la creamos
  if (!lista) {
    lista = document.createElement("ul");
    document.getElementById("lista_gastos_esenciales").appendChild(lista);
  }

  //creamos el elemento li que representa el nuevo gasto esencial
  const li = document.createElement("li");

  //le asignamos el id correspondiente y tipo correspondiente
  li.id = `gasto-${gastoEsencial.id}`;
  li.dataset.tipo = "obligatorio";

  //Agregamos el nuevo gasto esencial al elemento li
  li.innerHTML = `
    <span class="categoria_gasto_esencial">${formatearCategoriaJS(
      gastoEsencial.categoria,
    )}</span>: 
    <span class="cantidad_gasto_esencial cantidad_gasto" data-id="${gastoEsencial.id}">${formatearCantidad(gastoEsencial.cantidad)}</span>€
    <button class="bh-btn bh-btn-icon bh-btn-ghost eliminar_gasto" data-id="${gastoEsencial.id}" aria-label="Eliminar gasto"><i class="bi bi-trash"></i></button>
    `;

  //Insertamos el nuevo elemento al inicio de la lista
  lista.prepend(li);
}

// -------------------------------------------Función para agregar el nuevo
// gasto flexible al DOM----------------------

function agregarGastoFlexibleAlDOM(gastoFlexible) {
  //Intentamos seleccionar  la lista existente
  let lista = document.querySelector("#lista_gastos_flexibles ul");

  //Si existe el mensaje de "No tienes gastos..." lo eliminamos antes de insertar
  const mensaje = document.querySelector("#lista_gastos_flexibles p");
  if (mensaje) mensaje.remove();

  //Si no existe la etiqueta ul la creamos
  if (!lista) {
    lista = document.createElement("ul");
    document.getElementById("lista_gastos_flexibles").appendChild(lista);
  }

  //creamos el elemento li que representa el nuevo gasto flexible
  const li = document.createElement("li");

  //le asignamos el id y el tipo correspondiente
  li.id = `gasto-${gastoFlexible.id}`;
  li.dataset.tipo = "voluntario";

  //Agregamos el nuevo gasto flexible al elemento li
  li.innerHTML = `
    <span class="categoria_gasto_flexible">${formatearCategoriaJS(
      gastoFlexible.categoria,
    )}</span>: 
    <span class="cantidad_gasto_flexible cantidad_gasto"  data-id="${gastoFlexible.id}">${formatearCantidad(gastoFlexible.cantidad)}</span>€
    <button class="bh-btn bh-btn-icon bh-btn-ghost eliminar_gasto" data-id="${gastoFlexible.id}" aria-label="Eliminar gasto"><i class="bi bi-trash"></i></button>
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
      `<p>No tienes gastos ${nombreTipo} registrados todavía.</p>`;
  }
}
