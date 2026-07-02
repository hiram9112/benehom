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

  // =========================
  // MODAL INFORMATIVO (ERROR / INFO)
  // =========================
  function abrirModalInfo({ titulo, mensaje }) {
    const modal = new bootstrap.Modal(document.getElementById("modalInfo"));

    document.getElementById("modalInfoTitulo").textContent = titulo;
    document.getElementById("modalInfoTexto").textContent = mensaje;

    modal.show();
  }

  // Exponemos la función para usarla en AJAX
  window.abrirModalInfo = abrirModalInfo;

  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((elemento) => {
    bootstrap.Tooltip.getOrCreateInstance(elemento);
  });

  const dashboardAside = document.querySelector(".bh-dashboard-aside");

  if (dashboardAside) {
    const actualizarStickyAside = () => {
      const offset = 16;
      const top = Math.min(
        offset,
        window.innerHeight - dashboardAside.offsetHeight - offset,
      );

      dashboardAside.style.setProperty(
        "--bh-dashboard-aside-sticky-top",
        `${top}px`,
      );
    };

    if (typeof ResizeObserver !== "undefined") {
      const observer = new ResizeObserver(actualizarStickyAside);
      observer.observe(dashboardAside);
    }

    window.addEventListener("resize", actualizarStickyAside);
    actualizarStickyAside();
  }

  const summaryDetails = document.querySelector("[data-summary-details]");
  const summaryInlineAnchor = document.querySelector(
    "[data-summary-inline-anchor]",
  );
  const summaryOffcanvasSlot = document.querySelector(
    "[data-summary-offcanvas-slot]",
  );
  const summaryOffcanvas = document.getElementById("resumenMensualPanel");

  if (summaryDetails && summaryInlineAnchor && summaryOffcanvasSlot) {
    const mobileSummaryQuery = window.matchMedia("(max-width: 767.98px)");

    const syncSummaryPlacement = () => {
      if (mobileSummaryQuery.matches) {
        if (summaryDetails.parentElement !== summaryOffcanvasSlot) {
          summaryOffcanvasSlot.appendChild(summaryDetails);
        }

        return;
      }

      if (summaryDetails.parentElement !== summaryInlineAnchor.parentElement) {
        summaryInlineAnchor.before(summaryDetails);
      }

      if (summaryOffcanvas && typeof bootstrap !== "undefined") {
        bootstrap.Offcanvas.getInstance(summaryOffcanvas)?.hide();
      }
    };

    if (typeof mobileSummaryQuery.addEventListener === "function") {
      mobileSummaryQuery.addEventListener("change", syncSummaryPlacement);
    } else {
      mobileSummaryQuery.addListener(syncSummaryPlacement);
    }

    syncSummaryPlacement();
  }

  let activeSummaryCard = null;
  let summaryCardReturnTimer = null;

  const closeActiveSummaryCard = () => {
    if (!activeSummaryCard) {
      return;
    }

    activeSummaryCard.classList.remove("is-flipped");
    activeSummaryCard.setAttribute("aria-expanded", "false");
    activeSummaryCard = null;
    clearTimeout(summaryCardReturnTimer);
    summaryCardReturnTimer = null;
  };

  document.querySelectorAll("[data-summary-flip]").forEach((card) => {
    const alternarCard = () => {
      if (activeSummaryCard === card) {
        closeActiveSummaryCard();
        return;
      }

      closeActiveSummaryCard();
      card.classList.add("is-flipped");
      card.setAttribute("aria-expanded", "true");
      activeSummaryCard = card;
      summaryCardReturnTimer = setTimeout(closeActiveSummaryCard, 30000);
    };

    card.addEventListener("click", alternarCard);
    card.addEventListener("keydown", (event) => {
      if (event.key !== "Enter" && event.key !== " ") {
        return;
      }

      event.preventDefault();
      alternarCard();
    });
  });

});

function normalizarCantidadParaInput(valor) {
  return valor.trim().replace(/\./g, "").replace(",", ".");
}

function crearInputEdicion(valorActual) {
  const input = document.createElement("input");
  input.type = "number";
  input.step = "0.01";
  input.min = "0";
  input.inputMode = "decimal";
  input.value = normalizarCantidadParaInput(valorActual);
  input.classList.add("bh-input", "bh-inline-edit-input");

  return input;
}

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

  //Creamos input BeneHom para permitir la edición
  const input = crearInputEdicion(valorActual);
  let guardando = false;

  // Reemplazamos el elemento span por el el input y para permitir escribir al
  // usuario
  span.replaceWith(input);
  input.focus();

  //Función para guardar los cambios
  const guardar = async () => {
    if (guardando) return;
    guardando = true;

    //Recogemos el nuevo valor
    const nuevoValor = input.value;

    if (nuevoValor === "" || Number(nuevoValor) < 0) {
      abrirModalInfo({
        titulo: "Cantidad no válida",
        mensaje: "Introduce una cantidad igual o superior a 0.",
      });
      input.replaceWith(span);
      window.modoEdición = false;
      return;
    }

    //Preparemos y hacemos la petición al servidor
    const datos = new FormData();
    datos.append("id", id);
    datos.append("cantidad", nuevoValor);
    datos.append("_csrf", window.CSRF_TOKEN);

    try {
      const respuesta = await fetch("index.php?r=ingreso/editarAjax", {
        method: "POST",
        body: datos,
      });

      //Recogemos la respuesta del servidor
      const data = await respuesta.json();

      if (data.ok) {
        const li = input.closest("li");

        //Creamos nuevo span actualizado
        const nuevoSpan = document.createElement("span");
        nuevoSpan.textContent = formatearCantidad(nuevoValor);
        nuevoSpan.dataset.id = id;
        nuevoSpan.classList.add("bh-movement-amount", "cantidad_ingreso");

        //Reemplazamos el input con el span que contiene el nuevo valor
        input.replaceWith(nuevoSpan);

        if (li) {
          li.dataset.cantidad = nuevoValor;
          ordenarMovimientosPorCantidadDesc("lista_ingresos");
        }

        //Actualizamos gráficos
        window.cargarGraficoPresupuesto();
        window.cargarGraficoGastosFlexibles6m();
        window.cargarGraficoGastosEsenciales6m();
        window.cargarGraficoAhorros6m();
        window.cargarGraficoEscalaHabitos();
      } else {
        //SI falla la edición restauramos el valor anterior
        abrirModalInfo({
          titulo: "No se pudo guardar el cambio",
          mensaje:
            data.msg ||
            "La modificación no pudo completarse. Inténtalo de nuevo.",
        });
        input.replaceWith(span);
      }
    } catch (error) {
      abrirModalInfo({
        titulo: "Problema de conexión",
        mensaje: "No se pudo contactar con el servidor. Inténtalo de nuevo.",
      });
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

  //Creamos input BeneHom para permitir la edición
  const input = crearInputEdicion(valorActual);
  let guardando = false;

  // Reemplazamos el elemento span por el el input y para permitir escribir al
  // usuario
  span.replaceWith(input);
  input.focus();

  //Función para guardar los cambios
  const guardar = async () => {
    if (guardando) return;
    guardando = true;

    //Recogemos el nuevo valor
    const nuevoValor = input.value;

    if (nuevoValor === "" || Number(nuevoValor) < 0) {
      abrirModalInfo({
        titulo: "Cantidad no válida",
        mensaje: "Introduce una cantidad igual o superior a 0.",
      });
      input.replaceWith(span);
      window.modoEdición = false;
      return;
    }

    //Preparemos y hacemos la petición al servidor
    const datos = new FormData();
    datos.append("id", id);
    datos.append("cantidad", nuevoValor);
    datos.append("_csrf", window.CSRF_TOKEN);

    try {
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
        if (tipo === "esencial") {
          nuevoSpan.classList.add(
            "bh-movement-amount",
            "cantidad_gasto_esencial",
            "cantidad_gasto",
          );
        } else {
          nuevoSpan.classList.add(
            "bh-movement-amount",
            "cantidad_gasto_flexible",
            "cantidad_gasto",
          );
        }

        //Reemplazamos el input con el span que contiene el nuevo valor
        input.replaceWith(nuevoSpan);

        li.dataset.cantidad = nuevoValor;
        ordenarMovimientosPorCantidadDesc(
          tipo === "esencial"
            ? "lista_gastos_esenciales"
            : "lista_gastos_flexibles",
        );

        //Actualizamos gráficos
        window.cargarGraficoPresupuesto();
        window.cargarGraficoGastosFlexibles6m();
        window.cargarGraficoGastosEsenciales6m();
        window.cargarGraficoAhorros6m();
        window.cargarGraficoEscalaHabitos();
      } else {
        //SI falla la edición restauramos el valor anterior
        abrirModalInfo({
          titulo: "No se pudo guardar el cambio",
          mensaje:
            data.msg ||
            "La modificación no pudo completarse. Inténtalo de nuevo.",
        });
        input.replaceWith(span);
      }
    } catch (error) {
      abrirModalInfo({
        titulo: "Problema de conexión",
        mensaje: "No se pudo contactar con el servidor. Inténtalo de nuevo.",
      });
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

// Inicializa el selector de mes/año con Flatpickr
const mesInput = document.getElementById("mes");

if (mesInput && window.flatpickr && window.monthSelectPlugin) {
  flatpickr(mesInput, {
    locale: "es",
    dateFormat: "Y-m", // Formato que espera el backend
    defaultDate: mesInput.value,
    disableMobile: true,

    altInput: true,
    altInputClass: "bh-input bh-month-input",
    altFormat: "F Y", // lo que ve el usuario

    plugins: [
      new monthSelectPlugin({
        shorthand: true, // Ene, Feb, Mar...
        dateFormat: "Y-m",
        altFormat: "F Y", // Texto visible: "Enero 2026"
      }),
    ],
    onChange: function () {
      // Enviar automáticamente al cambiar el mes
      if (this._input.form) {
        this._input.form.submit();
      }
    },
  });
}


