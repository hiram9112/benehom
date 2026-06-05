document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".js-meta-form").forEach((formulario) => {
    const actualizarModo = () => {
      const modoSeleccionado = formulario.querySelector('input[name="modo_calculo"]:checked')?.value || "aportacion";

      formulario.querySelectorAll("[data-mode-group]").forEach((grupo) => {
        const activo = grupo.dataset.modeGroup === modoSeleccionado;
        grupo.hidden = !activo;
        grupo.querySelectorAll("input, select, textarea").forEach((campo) => {
          campo.disabled = !activo;
        });
      });
    };

    formulario.querySelectorAll('input[name="modo_calculo"]').forEach((radio) => {
      radio.addEventListener("change", actualizarModo);
    });

    actualizarModo();
  });

  document.querySelectorAll("[data-confirm]").forEach((boton) => {
    boton.addEventListener("click", (event) => {
      if (!window.confirm(boton.dataset.confirm)) {
        event.preventDefault();
      }
    });
  });

  const ahorroElemento = document.getElementById("ahorro_mensual_disponible");
  const ahorroAsignadoElemento = document.getElementById("ahorro_asignado_metas");
  const ahorroDisponibleElemento = document.getElementById("ahorro_disponible_metas");
  const metaCapacidadDisponibleElemento = document.getElementById("meta_capacidad_disponible");

  if (!ahorroElemento) return;

  const formatearCantidad = (valor) => {
    const numero = Number(valor) || 0;

    return new Intl.NumberFormat("es-ES", {
      minimumFractionDigits: Number.isInteger(numero) ? 0 : 2,
      maximumFractionDigits: 2,
    }).format(numero);
  };

  const normalizarCantidadParaInput = (valor) => valor.trim().replace(/\./g, "").replace(",", ".");

  const mostrarError = (mensaje) => {
    ahorroElemento.dataset.error = mensaje;
    ahorroElemento.setAttribute("title", mensaje);
  };

  const limpiarError = () => {
    delete ahorroElemento.dataset.error;
    ahorroElemento.removeAttribute("title");
  };

  const editarAhorroInline = () => {
    if (window.modoEdición) return;

    window.modoEdición = true;

    const valorAnterior = ahorroElemento.dataset.value || normalizarCantidadParaInput(ahorroElemento.textContent);
    const input = document.createElement("input");
    let guardando = false;
    let cancelado = false;

    input.type = "number";
    input.step = "0.01";
    input.min = "0";
    input.inputMode = "decimal";
    input.value = normalizarCantidadParaInput(valorAnterior);
    input.classList.add("bh-input", "bh-inline-edit-input", "bh-simulator-inline-input");
    input.setAttribute("aria-label", "Ahorro mensual disponible");

    ahorroElemento.replaceWith(input);
    input.focus();
    input.select();

    const restaurar = () => {
      input.replaceWith(ahorroElemento);
      window.modoEdición = false;
    };

    const guardar = async () => {
      if (cancelado) return;
      if (guardando) return;
      guardando = true;

      const nuevoValor = input.value;

      if (nuevoValor === "" || Number(nuevoValor) < 0) {
        mostrarError("Introduce un ahorro mensual igual o superior a 0.");
        restaurar();
        return;
      }

      const datos = new FormData();
      datos.append("ahorro_mensual", nuevoValor);
      datos.append("_csrf", window.CSRF_TOKEN || "");

      try {
        const respuesta = await fetch("index.php?r=simulador/actualizarAhorroMensualAjax", {
          method: "POST",
          body: datos,
        });
        const data = await respuesta.json();

        if (!data.ok) {
          mostrarError(data.msg || "No se pudo actualizar el ahorro mensual.");
          restaurar();
          return;
        }

        limpiarError();
        ahorroElemento.textContent = formatearCantidad(data.ahorroMensualDisponible);
        ahorroElemento.dataset.value = data.ahorroMensualDisponible;

        if (ahorroAsignadoElemento) {
          ahorroAsignadoElemento.textContent = `${formatearCantidad(data.ahorroAsignadoMetas)} €`;
        }

        if (ahorroDisponibleElemento) {
          ahorroDisponibleElemento.textContent = `${formatearCantidad(data.ahorroDisponibleMetas)} €`;
        }

        if (metaCapacidadDisponibleElemento) {
          metaCapacidadDisponibleElemento.textContent = `${formatearCantidad(data.ahorroDisponibleMetas)} €`;
        }

        restaurar();
      } catch (error) {
        mostrarError("No se pudo contactar con el servidor. Inténtalo de nuevo.");
        restaurar();
      }
    };

    input.addEventListener("keydown", (event) => {
      if (event.key === "Enter") guardar();
      if (event.key === "Escape") {
        cancelado = true;
        restaurar();
      }
    });

    input.addEventListener("blur", guardar);
  };

  ahorroElemento.addEventListener("click", editarAhorroInline);
  ahorroElemento.addEventListener("keydown", (event) => {
    if (event.key !== "Enter" && event.key !== " ") return;

    event.preventDefault();
    editarAhorroInline();
  });
});
