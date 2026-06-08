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

  const formatearCantidad = (valor) => {
    const numero = Number(valor) || 0;

    return new Intl.NumberFormat("es-ES", {
      maximumFractionDigits: 0,
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

  document.querySelectorAll("[data-inflacion-card]").forEach((card) => {
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
          mostrarFlash(mensaje);
          restore();
          return;
        }

        const formData = new FormData();
        formData.append("id", card.dataset.inflacionId || "");
        formData.append("campo", field);
        formData.append("valor", newValue);
        formData.append("_csrf", window.CSRF_TOKEN || "");

        try {
          const response = await fetch("index.php?r=simulador/actualizarInflacionSimulacionAjax", {
            method: "POST",
            body: formData,
          });
          const data = await response.json();

          if (!data.ok) {
            const mensaje = data.msg || "No se pudo actualizar la simulación.";
            element.setAttribute("title", mensaje);
            mostrarFlash(mensaje);
            restore();
            return;
          }

          element.setAttribute("title", "Haz clic para editar");
          restore();
          updateCard(data);
        } catch (error) {
          const mensaje = "No se pudo contactar con el servidor. Inténtalo de nuevo.";
          element.setAttribute("title", mensaje);
          mostrarFlash(mensaje);
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
  });

  document.querySelectorAll("[data-investment-card]").forEach((card) => {
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
          mostrarFlash(mensaje);
          restore();
          return;
        }

        const formData = new FormData();
        formData.append("id", card.dataset.investmentId || "");
        formData.append("campo", field);
        formData.append("valor", newValue);
        formData.append("_csrf", window.CSRF_TOKEN || "");

        try {
          const response = await fetch("index.php?r=simulador/actualizarEscenarioInversionAjax", {
            method: "POST",
            body: formData,
          });
          const data = await response.json();

          if (!data.ok) {
            const mensaje = data.msg || "No se pudo actualizar el escenario.";
            element.setAttribute("title", mensaje);
            mostrarFlash(mensaje);
            restore();
            return;
          }

          element.setAttribute("title", "Haz clic para editar");
          restore();
          updateCard(data);
        } catch (error) {
          const mensaje = "No se pudo contactar con el servidor. Inténtalo de nuevo.";
          element.setAttribute("title", mensaje);
          mostrarFlash(mensaje);
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
  });

  document.querySelectorAll("[data-hipoteca-card]").forEach((card) => {
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
          mostrarFlash(mensaje);
          restore();
          return;
        }

        const formData = new FormData();
        formData.append("id", card.dataset.hipotecaId || "");
        formData.append("campo", field);
        formData.append("valor", newValue);
        formData.append("_csrf", window.CSRF_TOKEN || "");

        try {
          const response = await fetch("index.php?r=simulador/actualizarCalculadoraHipotecaAjax", {
            method: "POST",
            body: formData,
          });
          const data = await response.json();

          if (!data.ok) {
            const mensaje = data.msg || "No se pudo actualizar la calculadora.";
            element.setAttribute("title", mensaje);
            mostrarFlash(mensaje);
            restore();
            return;
          }

          element.setAttribute("title", "Haz clic para editar");
          restore();
          updateCard(data);
        } catch (error) {
          const mensaje = "No se pudo contactar con el servidor. Inténtalo de nuevo.";
          element.setAttribute("title", mensaje);
          mostrarFlash(mensaje);
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
  });

  document.querySelectorAll("[data-meta-card]").forEach((card) => {
    const categoriaSelect = card.querySelector("[data-simulation-category]");
    const porcentajeSelect = card.querySelector("[data-simulation-percent]");
    const mensaje = card.querySelector("[data-simulation-message]");
    const badgeSimulacion = card.querySelector("[data-simulation-badge]");
    const limpiarBoton = card.querySelector("[data-simulation-clear]");
    const objetivoElemento = card.querySelector("[data-meta-target-amount]");
    const aportacionElemento = card.querySelector('[data-simulation-value="aportacion"]');
    const plazoElemento = card.querySelector('[data-simulation-value="plazo"]');
    const mejoraElemento = card.querySelector('[data-simulation-value="mejora"]');
    const fechaElemento = card.querySelector('[data-simulation-value="fecha"]');

    if (!categoriaSelect || !porcentajeSelect || !aportacionElemento || !plazoElemento || !fechaElemento) return;

    const elementosSimulados = [aportacionElemento, plazoElemento, fechaElemento];
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

    elementosSimulados.forEach((elemento) => {
      const etiqueta = obtenerEtiqueta(elemento);

      elemento.dataset.originalText = elemento.textContent;

      if (etiqueta) {
        etiqueta.dataset.originalText = etiqueta.textContent;
      }
    });

    const limpiarSimulacion = (texto = "") => {
      elementosSimulados.forEach((elemento) => {
        const etiqueta = obtenerEtiqueta(elemento);

        elemento.textContent = elemento.dataset.originalText || elemento.textContent;
        elemento.closest("p")?.classList.remove("is-simulated");

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

      if (badgeSimulacion) {
        badgeSimulacion.hidden = true;
      }
    };

    const resetearControlesYSimulacion = () => {
      categoriaSelect.value = "";
      porcentajeSelect.value = "";
      actualizarEstadoPorcentaje();
      limpiarSimulacion();
    };

    const aplicarSimulacion = () => {
      const opcionCategoria = categoriaSelect.selectedOptions[0];
      const totalCategoria = Number(opcionCategoria?.dataset.total || 0);
      const porcentaje = Number(porcentajeSelect.value || 0);
      const importeObjetivo = Number(card.dataset.importeObjetivo || 0);
      const aportacionOriginal = Number(card.dataset.aportacionOriginal || 0);
      const plazoOriginal = Number(card.dataset.plazoOriginal || 0);

      if (!opcionCategoria?.value || porcentaje <= 0) {
        limpiarSimulacion();
        return;
      }

      if (totalCategoria <= 0 || importeObjetivo <= 0 || aportacionOriginal <= 0 || plazoOriginal <= 0) {
        limpiarSimulacion("No hay impacto calculable para esta meta con los datos actuales.");
        return;
      }

      const aportacionExtra = totalCategoria * (porcentaje / 100);
      const aportacionSimulada = aportacionOriginal + aportacionExtra;
      const plazoSimulado = Math.ceil(importeObjetivo / aportacionSimulada);
      const mejoraMeses = plazoOriginal - plazoSimulado;

      if (mejoraMeses <= 0) {
        limpiarSimulacion("Con esa reducción no se aprecia una mejora de plazo en esta estimación.");
        return;
      }

      const categoriaTexto = opcionCategoria.dataset.label || opcionCategoria.textContent.trim();
      const aportacionEtiqueta = obtenerEtiqueta(aportacionElemento);
      const plazoEtiqueta = obtenerEtiqueta(plazoElemento);
      const fechaEtiqueta = obtenerEtiqueta(fechaElemento);

      if (aportacionEtiqueta) aportacionEtiqueta.textContent = "Aportación simulada";
      if (plazoEtiqueta) plazoEtiqueta.textContent = "Plazo simulado";
      if (fechaEtiqueta) fechaEtiqueta.textContent = "Fecha simulada";

      aportacionElemento.textContent = `${formatearCantidad(aportacionSimulada)} €`;
      plazoElemento.textContent = formatearPlazo(plazoSimulado);
      fechaElemento.textContent = formatearFechaDesdeHoy(plazoSimulado);

      elementosSimulados.forEach((elemento) => {
        elemento.closest("p")?.classList.add("is-simulated");
      });

      if (mejoraElemento) {
        mejoraElemento.textContent = `-${mejoraMeses} ${mejoraMeses === 1 ? "mes" : "meses"}`;
        mejoraElemento.hidden = false;
      }

      if (mensaje) {
        mensaje.textContent = `Si redujeras aproximadamente ${formatearCantidad(aportacionExtra)} € al mes en ${categoriaTexto}, podrías aportarlos a esta meta. Esta simulación no modifica tus gastos ni la meta guardada.`;
        mensaje.hidden = false;
      }


      porcentajeSelect.classList.add("is-saving-selected");

      if (badgeSimulacion) {
        badgeSimulacion.hidden = false;
      }
    };

    categoriaSelect.addEventListener("change", () => {
      limpiarSimulacion();
      porcentajeSelect.value = "";
      actualizarEstadoPorcentaje();
    });

    porcentajeSelect.addEventListener("change", () => {
      if (porcentajeSelect.value === "") {
        limpiarSimulacion();
        return;
      }

      aplicarSimulacion();
    });

    if (limpiarBoton) {
      limpiarBoton.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        resetearControlesYSimulacion();
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
          mostrarFlash(mensaje);
          restaurar();
          return;
        }

        const datos = new FormData();
        datos.append("id", objetivoElemento.dataset.metaId || "");
        datos.append("importe_objetivo", nuevoValor);
        datos.append("_csrf", window.CSRF_TOKEN || "");

        try {
          const respuesta = await fetch("index.php?r=simulador/actualizarImporteMetaAjax", {
            method: "POST",
            body: datos,
          });
          const data = await respuesta.json();

          if (!data.ok) {
            const mensaje = data.msg || "No se pudo actualizar el importe objetivo.";
            objetivoElemento.setAttribute("title", mensaje);
            mostrarFlash(mensaje);
            restaurar();
            return;
          }

          resetearControlesYSimulacion();

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
          mostrarFlash(mensaje);
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
  });

  const ahorroElemento = document.getElementById("ahorro_mensual_disponible");
  const ahorroAsignadoElemento = document.getElementById("ahorro_asignado_metas");
  const ahorroDisponibleElemento = document.getElementById("ahorro_disponible_metas");
  const metaCapacidadDisponibleElemento = document.getElementById("meta_capacidad_disponible");

  if (!ahorroElemento) return;

  const normalizarCantidadParaInput = (valor) => valor.trim().replace(/\./g, "").replace(",", ".");

  const mostrarError = (mensaje) => {
    ahorroElemento.dataset.error = mensaje;
    ahorroElemento.setAttribute("title", mensaje);
    mostrarFlash(mensaje);
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
