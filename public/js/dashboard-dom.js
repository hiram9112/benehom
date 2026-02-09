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
    <button class="eliminar_ingreso" data-id="${ingreso.id}"><i class="bi bi-trash"></i>
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
// gasto obligatorio al DOM----------------------

function agregarGastoObligAlDOM(gasto_oblig) {
  //Intentamos seleccionar  la lista existente
  let lista = document.querySelector("#lista_gastos_obligatorios ul");

  //Si existe el mensaje de "No tienes gastos..." lo eliminamos antes de insertar
  const mensaje = document.querySelector("#lista_gastos_obligatorios p");
  if (mensaje) mensaje.remove();

  //Si no existe la etiqueta ul la creamos
  if (!lista) {
    lista = document.createElement("ul");
    document.getElementById("lista_gastos_obligatorios").appendChild(lista);
  }

  //creamos el elemento li que representa el nuevo gasto obligatorio
  const li = document.createElement("li");

  //le asignamos el id correspondiente y tipo correspondiente
  li.id = `gasto-${gasto_oblig.id}`;
  li.dataset.tipo = "obligatorio";

  //Agregamos el nuevo gasto obligatorio al elemento li
  li.innerHTML = `
    <span class="categoria_gasto_obli">${formatearCategoriaJS(
      gasto_oblig.categoria,
    )}</span>: 
    <span class="cantidad_gasto_obli cantidad_gasto" data-id="${gasto_oblig.id}">${formatearCantidad(gasto_oblig.cantidad)}</span>€
    <button class="eliminar_gasto" data-id="${gasto_oblig.id}"><i class="bi bi-trash"></i></button>
    `;

  //Insertamos el nuevo elemento al inicio de la lista
  lista.prepend(li);
}

// -------------------------------------------Función para agregar el nuevo
// gasto Voluntario al DOM----------------------

function agregarGastoVolunAlDOM(gasto_volun) {
  //Intentamos seleccionar  la lista existente
  let lista = document.querySelector("#lista_gastos_voluntarios ul");

  //Si existe el mensaje de "No tienes gastos..." lo eliminamos antes de insertar
  const mensaje = document.querySelector("#lista_gastos_voluntarios p");
  if (mensaje) mensaje.remove();

  //Si no existe la etiqueta ul la creamos
  if (!lista) {
    lista = document.createElement("ul");
    document.getElementById("lista_gastos_voluntarios").appendChild(lista);
  }

  //creamos el elemento li que representa el nuevo gasto voluntario
  const li = document.createElement("li");

  //le asignamos el id y el tipo correspondiente
  li.id = `gasto-${gasto_volun.id}`;
  li.dataset.tipo = "voluntario";

  //Agregamos el nuevo gasto voluntario al elemento li
  li.innerHTML = `
    <span class="categoria_gasto_volun">${formatearCategoriaJS(
      gasto_volun.categoria,
    )}</span>: 
    <span class="cantidad_gasto_volun cantidad_gasto"  data-id="${gasto_volun.id}">${formatearCantidad(gasto_volun.cantidad)}</span>€
    <button class="eliminar_gasto" data-id="${gasto_volun.id}"><i class="bi bi-trash"></i></button>
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
      ? "lista_gastos_obligatorios"
      : "lista_gastos_voluntarios";

  //Seleccionamos la lista correspondiente
  const lista = document.querySelector(`#${contenedorID} ul`);

  if (!lista || lista.children.length === 0) {
    document.getElementById(contenedorID).innerHTML =
      `<p>No tienes gastos ${tipo}s registrados todavía.</p>`;
  }
}