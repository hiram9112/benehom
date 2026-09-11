document.addEventListener("DOMContentLoaded", () => {
  const formMovimiento = document.getElementById("formMovimientoMes");

  if (!formMovimiento) {
    return;
  }

  const tipoSelect = formMovimiento.querySelector("[data-movement-type]");
  const submitButton = formMovimiento.querySelector("[data-movement-submit]");
  const cantidadInput = document.getElementById("movimiento_cantidad");
  const areaSelect = formMovimiento.querySelector("[data-area-select]");
  const conceptSelect = formMovimiento.querySelector("[data-concept-select]");
  const catalogo = window.BH_MOVIMIENTO_CATEGORIAS || {};

  const configuracion = {
    ingreso: {
      endpoint: "index.php?r=ingreso/agregarAjax",
      cantidad: "cantidad_ingreso",
      categoria: "categoria_ingreso",
      respuesta: "ingreso",
      agregar: agregarIngresoAlDOM,
      actualizar: (ingreso) => actualizarCantidadMovimientoEnDOM(ingreso, "lista_ingresos", "ingreso"),
      foco: "#movimiento_area",
    },
    esencial: {
      endpoint: "index.php?r=gasto/agregarGastoEsencialAjax",
      cantidad: "cantidad_gasto_esencial",
      categoria: "categoria_gasto_esencial",
      respuesta: "gasto_esencial",
      agregar: agregarGastoEsencialAlDOM,
      actualizar: (gasto) => actualizarCantidadMovimientoEnDOM(gasto, "lista_gastos_esenciales", "gasto"),
      foco: "#movimiento_area",
    },
    flexible: {
      endpoint: "index.php?r=gasto/agregarGastoFlexibleAjax",
      cantidad: "cantidad_gasto_flexible",
      categoria: "categoria_gasto_flexible",
      respuesta: "gasto_flexible",
      agregar: agregarGastoFlexibleAlDOM,
      actualizar: (gasto) => actualizarCantidadMovimientoEnDOM(gasto, "lista_gastos_flexibles", "gasto"),
      foco: "#movimiento_area",
    },
  };

  function resetConcepto() {
    if (!conceptSelect) return;

    conceptSelect.innerHTML = '<option value="" selected disabled>Selecciona un concepto</option>';
    conceptSelect.disabled = true;
  }

  function cargarConceptos(tipo) {
    const grupo = catalogo[tipo]?.[areaSelect?.value];

    resetConcepto();

    if (!grupo || !conceptSelect) {
      return;
    }

    const conceptos = grupo.conceptos || grupo.items || {};

    Object.entries(conceptos).forEach(([valor, label]) => {
      const option = document.createElement("option");
      option.value = valor;
      option.textContent = label;
      conceptSelect.appendChild(option);
    });

    conceptSelect.disabled = false;
  }

  function actualizarCatalogoCategorias(tipo) {
    if (!areaSelect) return;

    const grupos = catalogo[tipo] || {};
    areaSelect.innerHTML = '<option value="" selected disabled>Selecciona un área</option>';
    areaSelect.disabled = !tipo || !configuracion[tipo];
    resetConcepto();

    if (areaSelect.disabled) {
      return;
    }

    Object.entries(grupos).forEach(([valor, grupo]) => {
      const option = document.createElement("option");
      option.value = valor;
      option.textContent = grupo.label;
      areaSelect.appendChild(option);
    });
  }

  function actualizarFormularioMovimiento(tipo) {
    actualizarCatalogoCategorias(tipo);

    if (submitButton) {
      submitButton.textContent = "+ Añadir";
    }
  }

  function crearDatosMovimiento(tipo) {
    const config = configuracion[tipo] || configuracion.ingreso;
    const datos = new FormData();
    const csrf = formMovimiento.querySelector('[name="_csrf"]');
    const mes = formMovimiento.querySelector('[name="mes_seleccionado"]');

    if (csrf) datos.append("_csrf", csrf.value);
    if (mes) datos.append("mes_seleccionado", mes.value);
    if (conceptSelect) datos.append(config.categoria, conceptSelect.value);
    if (cantidadInput) datos.append(config.cantidad, cantidadInput.value);

    return datos;
  }

  function refrescarDashboard() {
    cargarEstadoGeneralDashboard();
    cargarGraficoGastosFlexibles6m();
    cargarGraficoGastosEsenciales6m();
    cargarGraficoAhorros6m();
    cargarGraficoEscalaHabitos();
  }

  function completarAlta(data, config, tipo) {
    if (data.acumulado) {
      config.actualizar(data[config.respuesta]);
    } else {
      config.agregar(data[config.respuesta]);
    }

    refrescarDashboard();
    formMovimiento.reset();
    if (tipoSelect) tipoSelect.value = tipo;
    actualizarFormularioMovimiento(tipo);
  }

  function textoMes(mes) {
    const [anio, numeroMes] = mes.split("-");
    const meses = [
      "enero", "febrero", "marzo", "abril", "mayo", "junio",
      "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre",
    ];

    return `${meses[Number(numeroMes) - 1]} de ${anio}`;
  }

  function solicitarAcumulacion(datos, data, config, tipo) {
    const confirmacion = data.confirmacion;
    const categoria = formatearCategoriaJS(confirmacion.categoria);
    const mensaje = `Ya tienes registrados ${formatearCantidad(confirmacion.cantidad_actual)} € en ${categoria} en ${textoMes(confirmacion.mes)}. ¿Deseas añadir ${formatearCantidad(confirmacion.cantidad_nueva)} € al importe existente?`;

    abrirModalConfirmacion({
      titulo: "Añadir al importe mensual",
      mensaje,
      onConfirm: async () => {
        datos.append("confirmar_acumulacion", "1");

        try {
          const respuesta = await fetch(config.endpoint, {
            method: "POST",
            body: datos,
          });
          const confirmado = await respuesta.json();

          if (confirmado.ok) {
            completarAlta(confirmado, config, tipo);
          } else {
            abrirModalInfo({
              titulo: "No se pudo completar la operación",
              mensaje: confirmado.msg || "La operación no pudo completarse. Inténtalo de nuevo.",
            });
          }
        } catch (error) {
          abrirModalInfo({
            titulo: "Problema de conexión",
            mensaje: "No se pudo contactar con el servidor. Comprueba tu conexión e inténtalo de nuevo.",
          });
        }
      },
    });
  }

  function enfocarPrimerCampo(tipo) {
    const config = configuracion[tipo] || configuracion.ingreso;
    const foco = formMovimiento.querySelector(config.foco) || cantidadInput;

    if (foco) {
      window.setTimeout(() => foco.focus({ preventScroll: true }), 450);
    }
  }

  tipoSelect?.addEventListener("change", () => {
    actualizarFormularioMovimiento(tipoSelect.value);
    enfocarPrimerCampo(tipoSelect.value);
  });

  areaSelect?.addEventListener("change", () => {
    cargarConceptos(tipoSelect?.value || "");
  });

  document.querySelectorAll("[data-movimiento-atajo]").forEach((atajo) => {
    atajo.addEventListener("click", () => {
      const tipo = atajo.dataset.movimientoAtajo;

      if (tipoSelect && configuracion[tipo]) {
        tipoSelect.value = tipo;
        actualizarFormularioMovimiento(tipo);
      }

      formMovimiento.scrollIntoView({ behavior: "smooth", block: "start" });
      enfocarPrimerCampo(tipo);
    });
  });

  window.bhSeleccionarTipoMovimiento = (tipo) => {
    if (!tipoSelect || !configuracion[tipo]) return;

    tipoSelect.value = tipo;
    actualizarFormularioMovimiento(tipo);
  };

  formMovimiento.addEventListener("submit", async (e) => {
    e.preventDefault();

    if (!formMovimiento.reportValidity()) {
      return;
    }

    const tipo = tipoSelect?.value || "ingreso";
    const config = configuracion[tipo] || configuracion.ingreso;
    const datos = crearDatosMovimiento(tipo);

    try {
      const respuesta = await fetch(config.endpoint, {
        method: "POST",
        body: datos,
      });

      const data = await respuesta.json();

      if (data.ok) {
        completarAlta(data, config, tipo);
      } else if (data.requiere_confirmacion) {
        solicitarAcumulacion(datos, data, config, tipo);
      } else {
        abrirModalInfo({
          titulo: "No se pudo completar la operación",
          mensaje: data.msg || "La operación no pudo completarse. Inténtalo de nuevo.",
        });
      }
    } catch (error) {
      abrirModalInfo({
        titulo: "Problema de conexión",
        mensaje: "No se pudo contactar con el servidor. Comprueba tu conexión e inténtalo de nuevo.",
      });
    }
  });

  actualizarFormularioMovimiento(tipoSelect?.value || "");
});
