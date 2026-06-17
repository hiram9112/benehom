(() => {
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

  const formatearCantidad = (valor) => {
    const numero = Number(valor) || 0;

    return new Intl.NumberFormat("es-ES", {
      maximumFractionDigits: 2,
    }).format(numero);
  };

  const formatearEuros = (valor) => `${formatearCantidad(valor)} €`;

  const formatearPlazo = (meses) => {
    const mesesEnteros = Number(meses);

    if (!Number.isFinite(mesesEnteros) || mesesEnteros <= 0) {
      return "No calculable";
    }

    const totalMeses = Math.ceil(mesesEnteros);
    const anios = Math.floor(totalMeses / 12);
    const restoMeses = totalMeses % 12;

    if (anios === 0) {
      return `${totalMeses} ${totalMeses === 1 ? "mes" : "meses"}`;
    }

    if (restoMeses === 0) {
      return `${anios} ${anios === 1 ? "año" : "años"}`;
    }

    return `${anios} ${anios === 1 ? "año" : "años"} y ${restoMeses} ${restoMeses === 1 ? "mes" : "meses"}`;
  };

  const formatearFechaDesdeHoy = (meses) => {
    const hoy = new Date();
    const fecha = new Date(hoy.getFullYear(), hoy.getMonth() + meses, 1);
    const ultimoDiaMes = new Date(fecha.getFullYear(), fecha.getMonth() + 1, 0).getDate();

    fecha.setDate(Math.min(hoy.getDate(), ultimoDiaMes));

    return new Intl.DateTimeFormat("es-ES", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    }).format(fecha);
  };

  const formatearFechaISO = (fechaISO) => {
    if (!fechaISO) return "Sin fecha estimada";

    const partes = String(fechaISO).split("-").map(Number);

    if (partes.length !== 3 || partes.some((parte) => !Number.isFinite(parte))) {
      return "Sin fecha estimada";
    }

    return new Intl.DateTimeFormat("es-ES", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    }).format(new Date(partes[0], partes[1] - 1, partes[2]));
  };

  const formatearOpcionConImporte = (texto, importe, prefijo = "") => {
    const etiqueta = String(texto || "").trim();
    const importeTexto = `${prefijo}${formatearCantidad(importe)} €`;

    return `${etiqueta} --> ${importeTexto}`;
  };

  const actualizarTextoEditable = (elemento, texto) => {
    const textoElemento = elemento.querySelector("[data-editable-text]");

    if (textoElemento) {
      textoElemento.textContent = texto;
      return;
    }

    elemento.textContent = texto;
  };

  const inicializarTarjetaInflacion = (card) => {
    const editableElements = card.querySelectorAll("[data-inflacion-field]");

    const moneyFields = ["cantidad_inicial", "poder_adquisitivo_final", "perdida_estimada", "cantidad_futura_necesaria", "diferencia_necesaria"];
    const updateMoneyValue = (field, value) => {
      const element = card.querySelector(`[data-inflacion-value="${field}"]`);

      if (!element) return;

      actualizarTextoEditable(element, `${formatearCantidad(value)} €`);

      if (element.dataset.inflacionField) {
        element.dataset.value = value;
      }
    };

    const updatePercentValue = (field, value) => {
      const element = card.querySelector(`[data-inflacion-value="${field}"]`);

      if (!element) return;

      actualizarTextoEditable(element, `${formatearCantidad(value)}%`);
      element.dataset.value = value;
    };

    const updateYearsValue = (field, value) => {
      const element = card.querySelector(`[data-inflacion-value="${field}"]`);

      if (!element) return;

      const anios = Number(value);
      actualizarTextoEditable(element, `${anios} ${anios === 1 ? "año" : "años"}`);
      element.dataset.value = value;
    };

    const updateCard = (data) => {
      moneyFields.forEach((field) => {
        const responseKey = {
          cantidad_inicial: "cantidadInicial",
          poder_adquisitivo_final: "poderAdquisitivoFinal",
          perdida_estimada: "perdidaEstimada",
          cantidad_futura_necesaria: "cantidadFuturaNecesaria",
          diferencia_necesaria: "diferenciaNecesaria",
        }[field];

        updateMoneyValue(field, data[responseKey]);
      });

      updatePercentValue("inflacion_anual", data.inflacionAnual);
      updateYearsValue("plazo_anios", data.plazoAnios);
    };

    const editInline = (element) => {
      if (element.dataset.editing === "true") return;

      element.dataset.editing = "true";

      const field = element.dataset.inflacionField || "";
      const previousValue = String(element.dataset.value || "");
      const input = document.createElement("input");
      let saving = false;
      let cancelled = false;

      input.type = "number";

      if (field === "plazo_anios") {
        input.step = "1";
        input.min = "1";
      } else {
        input.step = "0.01";
        input.min = "0";
      }

      input.inputMode = "decimal";
      input.value = previousValue;
      input.classList.add("bh-input", "bh-inline-edit-input", "bh-inflation-inline-input");
      input.setAttribute("aria-label", element.getAttribute("aria-label") || "Editar valor");

      element.replaceWith(input);
      input.focus();
      input.select();

      const restore = () => {
        input.replaceWith(element);
        element.dataset.editing = "false";
      };

      const save = async () => {
        if (cancelled || saving) return;

        saving = true;

        const newValue = input.value;

        if (newValue === "" || Number(newValue) < 0) {
          const mensaje = "Introduce un valor igual o superior a 0.";
          element.setAttribute("title", mensaje);
          mostrarFlash(mensaje, "error", 5000);
          restore();
          return;
        }

        const formData = new FormData();
        formData.append("id", card.dataset.inflacionId || "");
        formData.append("campo", field);
        formData.append("valor", newValue);
        formData.append("_csrf", window.CSRF_TOKEN || "");

        try {
          const response = await fetch("index.php?r=proyecciones/actualizarInflacionProyeccionAjax", {
            method: "POST",
            body: formData,
          });
          const data = await response.json();

          if (!data.ok) {
            const mensaje = data.msg || "No se pudo actualizar la proyección.";
            element.setAttribute("title", mensaje);
            mostrarFlash(mensaje, "error", 5000);
            restore();
            return;
          }

          element.setAttribute("title", "Haz clic para editar");
          restore();
          updateCard(data);
        } catch (error) {
          const mensaje = "No se pudo contactar con el servidor. Inténtalo de nuevo.";
          element.setAttribute("title", mensaje);
          mostrarFlash(mensaje, "error", 5000);
          restore();
        }
      };

      input.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
          event.preventDefault();
          save();
        }

        if (event.key === "Escape") {
          cancelled = true;
          restore();
        }
      });

      input.addEventListener("blur", save);
    };

    editableElements.forEach((element) => {
      element.addEventListener("click", () => editInline(element));
      element.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" && event.key !== " ") return;

        event.preventDefault();
        editInline(element);
      });
    });
  };

  document.querySelectorAll("[data-inflacion-card]").forEach(inicializarTarjetaInflacion);

  const inicializarTarjetaInversion = (card) => {
    const editableElements = card.querySelectorAll("[data-investment-field]");

    const moneyFields = ["capital_inicial", "aportacion_mensual", "capital_total_aportado", "valor_final_estimado", "rendimiento_estimado"];
    const updateMoneyValue = (field, value) => {
      const element = card.querySelector(`[data-investment-value="${field}"]`);

      if (!element) return;

      actualizarTextoEditable(element, `${formatearCantidad(value)} €`);

      if (element.dataset.investmentField) {
        element.dataset.value = value;
      }
    };

    const updatePercentValue = (field, value) => {
      const element = card.querySelector(`[data-investment-value="${field}"]`);

      if (!element) return;

      actualizarTextoEditable(element, `${formatearCantidad(value)}%`);
      element.dataset.value = value;
    };

    const updateCard = (data) => {
      moneyFields.forEach((field) => {
        const responseKey = {
          capital_inicial: "capitalInicial",
          aportacion_mensual: "aportacionMensual",
          capital_total_aportado: "capitalTotalAportado",
          valor_final_estimado: "valorFinalEstimado",
          rendimiento_estimado: "rendimientoEstimado",
        }[field];

        updateMoneyValue(field, data[responseKey]);
      });

      updatePercentValue("rentabilidad_anual", data.rentabilidadAnual);
    };

    const editInline = (element) => {
      if (element.dataset.editing === "true") return;

      element.dataset.editing = "true";

      const field = element.dataset.investmentField || "";
      const previousValue = String(element.dataset.value || "");
      const input = document.createElement("input");
      let saving = false;
      let cancelled = false;

      input.type = "number";
      input.step = "0.01";
      input.min = "0";
      input.inputMode = "decimal";
      input.value = Number(previousValue) === 0 ? "" : previousValue;
      input.classList.add("bh-input", "bh-inline-edit-input", "bh-investment-inline-input");
      input.setAttribute("aria-label", element.getAttribute("aria-label") || "Editar valor del escenario");

      element.replaceWith(input);
      input.focus();
      input.select();

      const restore = () => {
        input.replaceWith(element);
        element.dataset.editing = "false";
      };

      const save = async () => {
        if (cancelled || saving) return;

        saving = true;

        const newValue = input.value;

        if (newValue === "" || Number(newValue) < 0) {
          const mensaje = "Introduce un valor igual o superior a 0.";
          element.setAttribute("title", mensaje);
          mostrarFlash(mensaje, "error", 5000);
          restore();
          return;
        }

        const formData = new FormData();
        formData.append("id", card.dataset.investmentId || "");
        formData.append("campo", field);
        formData.append("valor", newValue);
        formData.append("_csrf", window.CSRF_TOKEN || "");

        try {
          const response = await fetch("index.php?r=proyecciones/actualizarEscenarioInversionAjax", {
            method: "POST",
            body: formData,
          });
          const data = await response.json();

          if (!data.ok) {
            const mensaje = data.msg || "No se pudo actualizar el escenario.";
            element.setAttribute("title", mensaje);
            if (data.tipo === "capacidad") {
              mostrarFlash(mensaje, "warning", 11500);
            } else {
              mostrarFlash(mensaje, "error", 5000);
            }
            restore();
            return;
          }

          element.setAttribute("title", "Haz clic para editar");
          restore();
          updateCard(data);
          actualizarCapacidadMetas(data);
        } catch (error) {
          const mensaje = "No se pudo contactar con el servidor. Inténtalo de nuevo.";
          element.setAttribute("title", mensaje);
          mostrarFlash(mensaje, "error", 5000);
          restore();
        }
      };

      input.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
          event.preventDefault();
          save();
        }

        if (event.key === "Escape") {
          cancelled = true;
          restore();
        }
      });

      input.addEventListener("blur", save);
    };

    editableElements.forEach((element) => {
      element.addEventListener("click", () => editInline(element));
      element.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" && event.key !== " ") return;

        event.preventDefault();
        editInline(element);
      });
    });
  };

  document.querySelectorAll("[data-investment-card]").forEach(inicializarTarjetaInversion);

  const inicializarTarjetaHipoteca = (card) => {
    const editableElements = card.querySelectorAll("[data-hipoteca-field]");

    const moneyFields = ["importe_prestamo", "cuota_mensual", "total_intereses", "total_pagado"];
    const updateMoneyValue = (field, value) => {
      const element = card.querySelector(`[data-hipoteca-value="${field}"]`);

      if (!element) return;

      actualizarTextoEditable(element, `${formatearCantidad(value)} €`);

      if (element.dataset.hipotecaField) {
        element.dataset.value = value;
      }
    };

    const updatePercentValue = (field, value) => {
      const element = card.querySelector(`[data-hipoteca-value="${field}"]`);

      if (!element) return;

      actualizarTextoEditable(element, `${formatearCantidad(value)}%`);
      element.dataset.value = value;
    };

    const updateYearsValue = (field, value) => {
      const element = card.querySelector(`[data-hipoteca-value="${field}"]`);

      if (!element) return;

      const anios = Number(value);
      actualizarTextoEditable(element, `${anios} ${anios === 1 ? "año" : "años"}`);
      element.dataset.value = value;
    };

    const updateCard = (data) => {
      moneyFields.forEach((field) => {
        const responseKey = {
          importe_prestamo: "importePrestamo",
          cuota_mensual: "cuotaMensual",
          total_intereses: "totalIntereses",
          total_pagado: "totalPagado",
        }[field];

        updateMoneyValue(field, data[responseKey]);
      });

      updatePercentValue("interes_anual", data.interesAnual);
      updateYearsValue("plazo_anios", data.plazoAnios);
    };

    const editInline = (element) => {
      if (element.dataset.editing === "true") return;

      element.dataset.editing = "true";

      const field = element.dataset.hipotecaField || "";
      const previousValue = String(element.dataset.value || "");
      const input = document.createElement("input");
      let saving = false;
      let cancelled = false;

      input.type = "number";

      if (field === "plazo_anios") {
        input.step = "1";
        input.min = "1";
      } else {
        input.step = "0.01";
        input.min = "0";
      }

      input.inputMode = "decimal";
      input.value = previousValue;
      input.classList.add("bh-input", "bh-inline-edit-input", "bh-hipoteca-inline-input");
      input.setAttribute("aria-label", element.getAttribute("aria-label") || "Editar valor");

      element.replaceWith(input);
      input.focus();
      input.select();

      const restore = () => {
        input.replaceWith(element);
        element.dataset.editing = "false";
      };

      const save = async () => {
        if (cancelled || saving) return;

        saving = true;

        const newValue = input.value;

        if (newValue === "" || Number(newValue) < 0) {
          const mensaje = "Introduce un valor igual o superior a 0.";
          element.setAttribute("title", mensaje);
          mostrarFlash(mensaje, "error", 5000);
          restore();
          return;
        }

        const formData = new FormData();
        formData.append("id", card.dataset.hipotecaId || "");
        formData.append("campo", field);
        formData.append("valor", newValue);
        formData.append("_csrf", window.CSRF_TOKEN || "");

        try {
          const response = await fetch("index.php?r=proyecciones/actualizarCalculadoraHipotecaAjax", {
            method: "POST",
            body: formData,
          });
          const data = await response.json();

          if (!data.ok) {
            const mensaje = data.msg || "No se pudo actualizar la calculadora.";
            element.setAttribute("title", mensaje);
            mostrarFlash(mensaje, "error", 5000);
            restore();
            return;
          }

          element.setAttribute("title", "Haz clic para editar");
          restore();
          updateCard(data);
        } catch (error) {
          const mensaje = "No se pudo contactar con el servidor. Inténtalo de nuevo.";
          element.setAttribute("title", mensaje);
          mostrarFlash(mensaje, "error", 5000);
          restore();
        }
      };

      input.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
          event.preventDefault();
          save();
        }

        if (event.key === "Escape") {
          cancelled = true;
          restore();
        }
      });

      input.addEventListener("blur", save);
    };

    editableElements.forEach((element) => {
      element.addEventListener("click", () => editInline(element));
      element.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" && event.key !== " ") return;

        event.preventDefault();
        editInline(element);
      });
    });
  };

  document.querySelectorAll("[data-hipoteca-card]").forEach(inicializarTarjetaHipoteca);

  const inicializarTarjetaMeta = (card) => {
    const categoriaSelect = card.querySelector("[data-projection-category]");
    const porcentajeSelect = card.querySelector("[data-projection-percent]");
    const mensaje = card.querySelector("[data-projection-message]");
    const badgeProyeccion = card.querySelector("[data-projection-badge]");
    const limpiarBoton = card.querySelector("[data-projection-clear]");
    const objetivoElemento = card.querySelector("[data-meta-target-amount]");
    const aportacionElemento = card.querySelector('[data-projection-value="aportacion"]');
    const plazoElemento = card.querySelector('[data-projection-value="plazo"]');
    const mejoraElemento = card.querySelector('[data-projection-value="mejora"]');
    const fechaElemento = card.querySelector('[data-projection-value="fecha"]');

    if (!categoriaSelect || !porcentajeSelect || !aportacionElemento || !plazoElemento || !fechaElemento) return;

    const elementosProyectados = [aportacionElemento, plazoElemento, fechaElemento];
    const obtenerEtiqueta = (elemento) => elemento.closest("p")?.querySelector("span") || null;

    const actualizarEstadoPorcentaje = () => {
      const opcionCategoria = categoriaSelect.selectedOptions[0];
      const totalCategoria = Number(opcionCategoria?.dataset.total || 0);

      if (categoriaSelect.value === "") {
        porcentajeSelect.value = "";
        porcentajeSelect.disabled = true;
        categoriaSelect.classList.remove("is-expense-selected");
        porcentajeSelect.classList.remove("is-saving-selected");
        porcentajeSelect.options[0].textContent = "Elige primero una categoría";

        Array.from(porcentajeSelect.options).slice(1).forEach((option) => {
          option.textContent = option.dataset.percentLabel || `${option.value}%`;
        });

        return;
      }

      porcentajeSelect.disabled = false;
      categoriaSelect.classList.add("is-expense-selected");
      porcentajeSelect.options[0].textContent = "Selecciona porcentaje";

      Array.from(porcentajeSelect.options).slice(1).forEach((option) => {
        const porcentaje = Number(option.value || 0);
        const etiqueta = option.dataset.percentLabel || `${porcentaje}%`;
        const reduccion = totalCategoria * (porcentaje / 100);

        option.textContent = formatearOpcionConImporte(etiqueta, reduccion, "+");
      });
    };

    elementosProyectados.forEach((elemento) => {
      const etiqueta = obtenerEtiqueta(elemento);

      elemento.dataset.originalText = elemento.textContent;

      if (etiqueta) {
        etiqueta.dataset.originalText = etiqueta.textContent;
      }
    });

    const limpiarProyeccion = (texto = "") => {
      elementosProyectados.forEach((elemento) => {
        const etiqueta = obtenerEtiqueta(elemento);

        elemento.textContent = elemento.dataset.originalText || elemento.textContent;
        elemento.closest("p")?.classList.remove("is-projected");

        if (etiqueta?.dataset.originalText) {
          etiqueta.textContent = etiqueta.dataset.originalText;
        }
      });

      if (mejoraElemento) {
        mejoraElemento.textContent = "";
        mejoraElemento.hidden = true;
      }

      if (mensaje) {
        mensaje.textContent = texto;
        mensaje.hidden = texto === "";
      }

      porcentajeSelect.classList.remove("is-saving-selected");

      if (badgeProyeccion) {
        badgeProyeccion.hidden = true;
      }
    };

    const resetearControlesYProyeccion = () => {
      categoriaSelect.value = "";
      porcentajeSelect.value = "";
      actualizarEstadoPorcentaje();
      limpiarProyeccion();
    };

    const aplicarProyeccion = () => {
      const opcionCategoria = categoriaSelect.selectedOptions[0];
      const totalCategoria = Number(opcionCategoria?.dataset.total || 0);
      const porcentaje = Number(porcentajeSelect.value || 0);
      const importeObjetivo = Number(card.dataset.importeObjetivo || 0);
      const aportacionOriginal = Number(card.dataset.aportacionOriginal || 0);
      const plazoOriginal = Number(card.dataset.plazoOriginal || 0);

      if (!opcionCategoria?.value || porcentaje <= 0) {
        limpiarProyeccion();
        return;
      }

      if (totalCategoria <= 0 || importeObjetivo <= 0 || aportacionOriginal <= 0 || plazoOriginal <= 0) {
        limpiarProyeccion("No hay impacto calculable para esta meta con los datos actuales.");
        return;
      }

      const aportacionExtra = totalCategoria * (porcentaje / 100);
      const aportacionProyectada = aportacionOriginal + aportacionExtra;
      const plazoProyectado = Math.ceil(importeObjetivo / aportacionProyectada);
      const mejoraMeses = plazoOriginal - plazoProyectado;

      if (mejoraMeses <= 0) {
        limpiarProyeccion("Con esa reducción no se aprecia una mejora de plazo en esta estimación.");
        return;
      }

      const categoriaTexto = opcionCategoria.dataset.label || opcionCategoria.textContent.trim();
      const aportacionEtiqueta = obtenerEtiqueta(aportacionElemento);
      const plazoEtiqueta = obtenerEtiqueta(plazoElemento);
      const fechaEtiqueta = obtenerEtiqueta(fechaElemento);

      if (aportacionEtiqueta) aportacionEtiqueta.textContent = "Aportación proyectada";
      if (plazoEtiqueta) plazoEtiqueta.textContent = "Plazo proyectado";
      if (fechaEtiqueta) fechaEtiqueta.textContent = "Fecha proyectada";

      aportacionElemento.textContent = `${formatearCantidad(aportacionProyectada)} €`;
      plazoElemento.textContent = formatearPlazo(plazoProyectado);
      fechaElemento.textContent = formatearFechaDesdeHoy(plazoProyectado);

      elementosProyectados.forEach((elemento) => {
        elemento.closest("p")?.classList.add("is-projected");
      });

      if (mejoraElemento) {
        mejoraElemento.textContent = `--> ${mejoraMeses} ${mejoraMeses === 1 ? "mes" : "meses"} antes`;
        mejoraElemento.hidden = false;
      }

      if (mensaje) {
        mensaje.textContent = `Si redujeras aproximadamente ${formatearCantidad(aportacionExtra)} € al mes en ${categoriaTexto}, podrías aportarlos a esta meta. Esta proyección no modifica tus gastos ni la meta guardada.`;
        mensaje.hidden = false;
      }


      porcentajeSelect.classList.add("is-saving-selected");

      if (badgeProyeccion) {
        badgeProyeccion.hidden = false;
      }
    };

    categoriaSelect.addEventListener("change", () => {
      limpiarProyeccion();
      porcentajeSelect.value = "";
      actualizarEstadoPorcentaje();
    });

    porcentajeSelect.addEventListener("change", () => {
      if (porcentajeSelect.value === "") {
        limpiarProyeccion();
        return;
      }

      aplicarProyeccion();
    });

    if (limpiarBoton) {
      limpiarBoton.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        resetearControlesYProyeccion();
      });
    }

    const editarObjetivoInline = () => {
      if (!objetivoElemento || objetivoElemento.dataset.editing === "true") return;

      objetivoElemento.dataset.editing = "true";

      const valorAnterior = objetivoElemento.dataset.value || "";
      const input = document.createElement("input");
      let guardando = false;
      let cancelado = false;

      input.type = "number";
      input.step = "0.01";
      input.min = "0.01";
      input.inputMode = "decimal";
      input.value = valorAnterior;
      input.classList.add("bh-input", "bh-inline-edit-input", "bh-meta-target-inline-input");
      input.setAttribute("aria-label", "Importe objetivo de la meta");

      objetivoElemento.replaceWith(input);
      input.focus();
      input.select();

      const restaurar = () => {
        input.replaceWith(objetivoElemento);
        objetivoElemento.dataset.editing = "false";
      };

      const guardar = async () => {
        if (cancelado || guardando) return;

        guardando = true;

        const nuevoValor = input.value;

        if (nuevoValor === "" || Number(nuevoValor) <= 0) {
          const mensaje = "El importe objetivo debe ser mayor que 0.";
          objetivoElemento.setAttribute("title", mensaje);
          mostrarFlash(mensaje, "error", 5000);
          restaurar();
          return;
        }

        const datos = new FormData();
        datos.append("id", objetivoElemento.dataset.metaId || "");
        datos.append("importe_objetivo", nuevoValor);
        datos.append("_csrf", window.CSRF_TOKEN || "");

        try {
          const respuesta = await fetch("index.php?r=proyecciones/actualizarImporteMetaAjax", {
            method: "POST",
            body: datos,
          });
          const data = await respuesta.json();

          if (!data.ok) {
            const mensaje = data.msg || "No se pudo actualizar el importe objetivo.";
            objetivoElemento.setAttribute("title", mensaje);
            mostrarFlash(mensaje, "error", 5000);
            restaurar();
            return;
          }

          resetearControlesYProyeccion();

          const importeObjetivo = Number(data.importeObjetivo) || 0;
          const plazoTexto = formatearPlazo(data.plazoMesesEstimado);
          const fechaTexto = formatearFechaISO(data.fechaFinalizacionEstimada);

          card.dataset.importeObjetivo = importeObjetivo;
          card.dataset.plazoOriginal = data.plazoMesesEstimado ?? "";
          actualizarTextoEditable(objetivoElemento, `${formatearCantidad(importeObjetivo)} €`);
          objetivoElemento.dataset.value = importeObjetivo;
          objetivoElemento.setAttribute("title", "Haz clic para editar");
          plazoElemento.textContent = plazoTexto;
          plazoElemento.dataset.originalText = plazoTexto;
          fechaElemento.textContent = fechaTexto;
          fechaElemento.dataset.originalText = fechaTexto;

          restaurar();
        } catch (error) {
          const mensaje = "No se pudo contactar con el servidor. Inténtalo de nuevo.";
          objetivoElemento.setAttribute("title", mensaje);
          mostrarFlash(mensaje, "error", 5000);
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

    if (objetivoElemento) {
      objetivoElemento.addEventListener("click", editarObjetivoInline);
      objetivoElemento.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" && event.key !== " ") return;

        event.preventDefault();
        editarObjetivoInline();
      });
    }

    actualizarEstadoPorcentaje();
  };

  document.querySelectorAll("[data-meta-card]").forEach(inicializarTarjetaMeta);

  // ---- Crear / eliminar proyecciones sin recargar (AJAX) ----
  // Evita la recarga completa (y el salto de scroll): inserta o quita la card,
  // refresca contadores y capacidad, y muestra el flash fijo en la parte superior.

  const inicializadoresPorSeccion = {
    meta: inicializarTarjetaMeta,
    inversion: inicializarTarjetaInversion,
    inflacion: inicializarTarjetaInflacion,
    hipoteca: inicializarTarjetaHipoteca,
  };

  const cerrarOffcanvas = (elemento) => {
    const panel = elemento.closest(".offcanvas");

    if (!panel || !window.bootstrap || !window.bootstrap.Offcanvas) return;

    window.bootstrap.Offcanvas.getOrCreateInstance(panel).hide();
  };

  const actualizarBadgeSeccion = (seccion, total) => {
    const badge = document.querySelector(`[data-section-count="${seccion}"]`);

    if (!badge) return;

    const palabra = total === 1 ? badge.dataset.countOne : badge.dataset.countMany;
    badge.textContent = `${total} ${palabra || ""}`.trim();
  };

  const actualizarCapacidadMetas = (data) => {
    if (typeof data.ahorroAsignadoMetas === "undefined") return;

    const asignado = document.getElementById("ahorro_asignado_metas");
    const disponible = document.getElementById("ahorro_disponible_metas");
    const capacidad = document.getElementById("meta_capacidad_disponible");
    const capacidadInversion = document.getElementById("inversion_capacidad_disponible");

    if (asignado) asignado.textContent = formatearEuros(data.ahorroAsignadoMetas);
    if (disponible) disponible.textContent = formatearEuros(data.ahorroDisponibleMetas);
    if (capacidad) capacidad.textContent = formatearEuros(data.ahorroDisponibleMetas);
    if (capacidadInversion) capacidadInversion.textContent = formatearEuros(data.ahorroDisponibleMetas);
  };

  const sincronizarEstadoVacio = (seccion) => {
    const lista = document.querySelector(`[data-section-list="${seccion}"]`);
    const vacio = document.querySelector(`[data-section-empty="${seccion}"]`);

    if (!lista || !vacio) return;

    vacio.hidden = lista.children.length > 0;
  };

  const enviarFormularioCreacion = async (form) => {
    const seccion = form.dataset.section;
    const url = form.dataset.ajaxAction;
    const lista = document.querySelector(`[data-section-list="${seccion}"]`);

    if (!url || !lista) return;

    const boton = form.querySelector('button[type="submit"]');

    if (boton) boton.disabled = true;

    try {
      const respuesta = await fetch(url, { method: "POST", body: new FormData(form) });
      const data = await respuesta.json();

      if (!data.ok) {
        // Error de capacidad: cerramos el offcanvas para que el aviso quede visible
        // y lo mostramos como warning autocerrable (homogéneo con el aviso al editar).
        // El resto de validaciones (nombre, importe…) mantienen el panel abierto.
        if (data.tipo === "capacidad") {
          cerrarOffcanvas(form);
          mostrarFlash(data.msg, "warning", 11500);
        } else {
          mostrarFlash(data.msg || "No se pudo crear la proyección.", "error", 5000);
        }
        return;
      }

      lista.insertAdjacentHTML("afterbegin", (data.cardHtml || "").trim());

      const nuevaCard = lista.firstElementChild;
      const inicializar = inicializadoresPorSeccion[seccion];

      if (nuevaCard && typeof inicializar === "function") inicializar(nuevaCard);

      sincronizarEstadoVacio(seccion);

      if (typeof data.count === "number") actualizarBadgeSeccion(seccion, data.count);

      actualizarCapacidadMetas(data);

      form.reset();

      const modoMarcado = form.querySelector('input[name="modo_calculo"]:checked');

      if (modoMarcado) modoMarcado.dispatchEvent(new Event("change", { bubbles: true }));

      cerrarOffcanvas(form);
      mostrarFlash(data.msg, "success");
    } catch (error) {
      mostrarFlash("No se pudo contactar con el servidor. Inténtalo de nuevo.", "error", 5000);
    } finally {
      if (boton) boton.disabled = false;
    }
  };

  const seccionesEliminacion = [
    { clase: "bh-mortgage-delete-form", seccion: "hipoteca", url: "index.php?r=proyecciones/eliminarCalculadoraHipotecaAjax" },
    { clase: "bh-inflation-delete-form", seccion: "inflacion", url: "index.php?r=proyecciones/eliminarInflacionProyeccionAjax" },
    { clase: "bh-investment-delete-form", seccion: "inversion", url: "index.php?r=proyecciones/eliminarEscenarioInversionAjax" },
    { clase: "bh-meta-delete-form", seccion: "meta", url: "index.php?r=proyecciones/eliminarMetaAhorroAjax" },
  ];

  const resolverEliminacion = (form) => seccionesEliminacion.find((item) => form.classList.contains(item.clase));

  const enviarFormularioEliminacion = async (form, config, boton) => {
    if (boton && boton.dataset.confirm && !window.confirm(boton.dataset.confirm)) return;

    const card = form.closest("[data-meta-card], [data-investment-card], [data-inflacion-card], [data-hipoteca-card]");

    if (boton) boton.disabled = true;

    try {
      const respuesta = await fetch(config.url, { method: "POST", body: new FormData(form) });
      const data = await respuesta.json();

      if (!data.ok) {
        mostrarFlash(data.msg || "No se pudo eliminar la proyección.");
        if (boton) boton.disabled = false;
        return;
      }

      if (card) card.remove();

      sincronizarEstadoVacio(config.seccion);

      if (typeof data.count === "number") actualizarBadgeSeccion(config.seccion, data.count);

      actualizarCapacidadMetas(data);
      mostrarFlash(data.msg, "success");
    } catch (error) {
      mostrarFlash("No se pudo contactar con el servidor. Inténtalo de nuevo.");
      if (boton) boton.disabled = false;
    }
  };

  document.addEventListener("submit", (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) return;

    if (form.matches("[data-ajax-create]")) {
      event.preventDefault();
      enviarFormularioCreacion(form);
      return;
    }

    const config = resolverEliminacion(form);

    if (config) {
      event.preventDefault();
      enviarFormularioEliminacion(form, config, event.submitter);
    }
  });

  const ahorroElemento = document.getElementById("ahorro_mensual_disponible");
  const ahorroAsignadoElemento = document.getElementById("ahorro_asignado_metas");
  const ahorroDisponibleElemento = document.getElementById("ahorro_disponible_metas");
  const metaCapacidadDisponibleElemento = document.getElementById("meta_capacidad_disponible");

  if (!ahorroElemento) return;

  const normalizarDecimal = (valor) => String(valor || "").trim().replace(",", ".");

  const validarAhorroMensual = (valor) => {
    const texto = String(valor || "").trim();

    if (texto === "") {
      return { ok: false, mensaje: "Introduce un ahorro mensual válido." };
    }

    if (texto.startsWith("-")) {
      return { ok: false, mensaje: "El ahorro mensual no puede ser negativo." };
    }

    if (/^\d+[.,]\d{3,}$/.test(texto)) {
      return { ok: false, mensaje: "El ahorro mensual solo puede tener hasta 2 decimales." };
    }

    if (!/^\d+(?:[.,]\d{1,2})?$/.test(texto)) {
      return { ok: false, mensaje: "Introduce un ahorro mensual válido." };
    }

    const valorNormalizado = normalizarDecimal(texto);
    const numero = Number(valorNormalizado);

    if (!Number.isFinite(numero)) {
      return { ok: false, mensaje: "Introduce un ahorro mensual válido." };
    }

    return { ok: true, valor: valorNormalizado };
  };

  const mostrarError = (mensaje) => {
    ahorroElemento.dataset.error = mensaje;
    ahorroElemento.setAttribute("title", mensaje);
    mostrarFlash(mensaje, "error", 5000);
  };

  const limpiarError = () => {
    delete ahorroElemento.dataset.error;
    ahorroElemento.removeAttribute("title");
  };

  const editarAhorroInline = () => {
    if (window.modoEdición) return;

    window.modoEdición = true;

    const valorAnterior = normalizarDecimal(ahorroElemento.dataset.value || "0");
    const input = document.createElement("input");
    let guardando = false;
    let cancelado = false;

    input.type = "text";
    input.inputMode = "decimal";
    input.value = valorAnterior;
    input.classList.add("bh-input", "bh-inline-edit-input", "bh-projections-inline-input");
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

      const validacion = validarAhorroMensual(input.value);

      if (!validacion.ok) {
        mostrarError(validacion.mensaje);
        restaurar();
        return;
      }

      const datos = new FormData();
      datos.append("ahorro_mensual", validacion.valor);
      datos.append("_csrf", window.CSRF_TOKEN || "");

      try {
        const respuesta = await fetch("index.php?r=proyecciones/actualizarAhorroMensualAjax", {
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

        const supera = Number(data.ahorroAsignadoMetas) > Number(data.ahorroMensualDisponible);
        if (supera && window.BH_AVISO_AHORRO_SUPERA) {
          mostrarFlash(window.BH_AVISO_AHORRO_SUPERA, "warning", 11500);
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
})();

(function () {
  "use strict";

  const avisos = window.BH_PROYECCIONES_AVISOS;
  if (!Array.isArray(avisos) || typeof window.mostrarFlash !== "function") return;

  avisos.forEach((aviso) => {
    if (aviso && aviso.texto) {
      window.mostrarFlash(aviso.texto, aviso.tipo || "error");
    }
  });
})();
