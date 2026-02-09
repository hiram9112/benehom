// estado global que evita que un clic interfiera con el blur/enter
window.modoEdición = false;
//Esperamos que el DOM este completamente cargado
document.addEventListener("DOMContentLoaded", () => {
  //Formulario eliminar cuenta
  const formEliminarCuenta = document.getElementById("formEliminarCuenta");

  if (formEliminarCuenta) {
    formEliminarCuenta.addEventListener("submit", (e) => {
      e.preventDefault(); // Bloqueamos el submit normal

      abrirModalConfirmacion({
        titulo: "Eliminar cuenta",
        mensaje:
          "¿Seguro que deseas eliminar tu cuenta?\n\n" +
          "Esta acción es irreversible y se eliminarán todos tus datos.",
        onConfirm: () => {
          formEliminarCuenta.submit(); // AQUÍ se envía de verdad
        },
      });
    });
  }

  // =========================
  // MODAL DE CONFIRMACIÓN
  // =========================
  let accionConfirmada = null;

  function abrirModalConfirmacion({ titulo, mensaje, onConfirm }) {
    const modal = new bootstrap.Modal(
      document.getElementById("modalConfirmacion"),
    );

    document.getElementById("modalConfirmacionTitulo").textContent = titulo;
    document.getElementById("modalConfirmacionTexto").textContent = mensaje;

    accionConfirmada = onConfirm;

    modal.show();
  }

  document
    .getElementById("modalConfirmacionAceptar")
    .addEventListener("click", () => {
      if (typeof accionConfirmada === "function") {
        accionConfirmada();
      }

      accionConfirmada = null;

      bootstrap.Modal.getInstance(
        document.getElementById("modalConfirmacion"),
      ).hide();
    });

  // Exponemos la función para usarla en otros listeners
  window.abrirModalConfirmacion = abrirModalConfirmacion;
});


// -------------------------------------------Función para agregar el nuevo
// Ingreso al DOM----------------------


// --------------------------------------Función para actualizar un
// ingreso---------------------------------------
async function editarIngresoInline(span) {
  //Activamos modo edición
  window.modoEdición = true;

  //Guaradmos el id del ingreso y el valor actual
  const id = span.dataset.id;
  const valorActual = span.textContent;

  //Creamos input para permitir la edición
  const input = document.createElement("input");
  input.type = "number";
  input.step = "0.01";
  input.value = valorActual;
  input.classList.add("input-edicion");

  // Reemplazamos el elemento span por el el input y para permitir escribir al
  // usuario
  span.replaceWith(input);
  input.focus();

  //Función para guardar los cambios
  const guardar = async () => {
    //Recogemos el nuevo valor
    const nuevoValor = input.value;

    //Preparemos y hacemos la petición al servidor
    const datos = new FormData();
    datos.append("id", id);
    datos.append("cantidad", nuevoValor);
    datos.append("_csrf", window.CSRF_TOKEN);

    const respuesta = await fetch("index.php?r=ingreso/editarAjax", {
      method: "POST",
      body: datos,
    });

    //Recogemos la respuesta del servidor
    const data = await respuesta.json();

    if (data.ok) {
      //Creamos nuevo span actualizado
      const nuevoSpan = document.createElement("span");
      nuevoSpan.textContent = formatearCantidad(nuevoValor);
      nuevoSpan.dataset.id = id;
      nuevoSpan.classList.add("cantidad_ingreso");

      //Reemplazamos el input con el span que contiene el nuevo valor
      input.replaceWith(nuevoSpan);

      //Actualizamos gráficos
      window.cargarGraficoPresupuesto();
      window.cargarGraficoVoluntarios6m();
      window.cargarGraficoObligatorios6m();
      window.cargarGraficoAhorros6m();
    } else {
      //SI falla la edición restauramos el valor anterior
      alert("Error al editar el ingreso");
      input.replaceWith(span);
    }
    //desactivamos modo edición porque ya terminó
    window.modoEdición = false;
  };

  //Agregamos escucha para guardar con enter y cuando pierda el foco

  input.addEventListener("keydown", (ev) => {
    if (ev.key === "Enter") guardar();
  });

  input.addEventListener("blur", guardar);
}



// --------------------------------------Función para actualizar un gasto
// -----------------------------------
async function editarGastoInline(span) {
  //Activamos modo edición
  window.modoEdición = true;

  //Guaradmos el id del ingreso y el valor actual
  const id = span.dataset.id;
  const valorActual = span.textContent;

  //Creamos input para permitir la edición
  const input = document.createElement("input");
  input.type = "number";
  input.step = "0.01";
  input.value = valorActual;
  input.classList.add("input-edicion");

  // Reemplazamos el elemento span por el el input y para permitir escribir al
  // usuario
  span.replaceWith(input);
  input.focus();

  //Función para guardar los cambios
  const guardar = async () => {
    //Recogemos el nuevo valor
    const nuevoValor = input.value;

    //Preparemos y hacemos la petición al servidor
    const datos = new FormData();
    datos.append("id", id);
    datos.append("cantidad", nuevoValor);
    datos.append("_csrf", window.CSRF_TOKEN);

    const respuesta = await fetch("index.php?r=gasto/editarGastoAjax", {
      method: "POST",
      body: datos,
    });

    //Recogemos la respuesta del servidor
    const data = await respuesta.json();

    if (data.ok) {
      //Recuperamos el li más cercano para obetener el tipo de gasto
      const li = input.closest("li");
      const tipo = li.dataset.tipo;

      //Creamos nuevo span actualizado
      const nuevoSpan = document.createElement("span");
      nuevoSpan.textContent = formatearCantidad(nuevoValor);
      nuevoSpan.dataset.id = id;

      //Agregamos la clase según el tipo de gasto
      if (tipo === "obligatorio") {
        nuevoSpan.classList.add("cantidad_gasto_obli", "cantidad_gasto");
      } else {
        nuevoSpan.classList.add("cantidad_gasto_volun", "cantidad_gasto");
      }

      //Reemplazamos el input con el span que contiene el nuevo valor
      input.replaceWith(nuevoSpan);

      //Actualizamos gráficos
      window.cargarGraficoPresupuesto();
      window.cargarGraficoVoluntarios6m();
      window.cargarGraficoObligatorios6m();
      window.cargarGraficoAhorros6m();
    } else {
      //SI falla la edición restauramos el valor anterior
      alert("Error al editar el gasto obligatorio");
      input.replaceWith(span);
    }

    //desactivamos modo edición porque ya terminó
    window.modoEdición = false;
  };

  //Agregamos escucha para guardar con enter y cuando pierda el foco

  input.addEventListener("keydown", (ev) => {
    if (ev.key === "Enter") guardar();
  });

  input.addEventListener("blur", guardar);
}


