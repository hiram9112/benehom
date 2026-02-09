document.addEventListener("DOMContentLoaded", () => {
  // ----------------------------------------------------SECCIÓN EVENTOS
  // CLICKS----------------------------------------------------------
  // ----------------------------------------------------------------------------------------------------------------------------

  document.addEventListener("click", async (e) => {
    //Bloqueamos escucha si el modo edición está activo
    if (window.modoEdición) return;

    // ---------------------------------------Eliminar un
    // ingreso---------------------------------------- Verificamos si el click es
    // para eliminar un ingreso
    if (e.target.closest(".eliminar_ingreso")) {
      const id = e.target.closest(".eliminar_ingreso").dataset.id;

      abrirModalConfirmacion({
        titulo: "Eliminar ingreso",
        mensaje: "¿Seguro que deseas eliminar este ingreso?",
        onConfirm: async () => {
          try {
            const datos = new FormData();
            datos.append("id", id);
            datos.append("_csrf", window.CSRF_TOKEN);

            const respuesta = await fetch("index.php?r=ingreso/eliminarAjax", {
              method: "POST",
              body: datos,
            });

            const data = await respuesta.json();

            if (data.ok) {
              eliminarIngresoDelDOM(id);
              cargarGraficoPresupuesto();
              cargarGraficoVoluntarios6m();
              cargarGraficoObligatorios6m();
              cargarGraficoAhorros6m();
            } else {
              abrirModalInfo({
                titulo: "No se pudo eliminar el ingreso",
                mensaje:
                  data.msg ||
                  "La operación no pudo completarse. Inténtalo de nuevo.",
              });
            }
          } catch (error) {
            abrirModalInfo({
              titulo: "Problema de conexión",
              mensaje:
                "No se pudo contactar con el servidor. Comprueba tu conexión e inténtalo de nuevo.",
            });
          }
        },
      });

      return;
    }
    // ---------------------------------------Actualizar un
    // ingreso------------------------------------------- Verificamos si el click es
    // para modificar un ingreso
    if (e.target.classList.contains("cantidad_ingreso")) {
      //Llamamos a la función auxiliar correspondiente
      editarIngresoInline(e.target);
    }

    // ---------------------------------------Eliminar un gasto
    // ----------------------------------------

    // Verificamos si el click es para eliminar un gasto como los tenenmos tenemos
    // todo en una misma tabla podemos gestionar todos los gastos juntos
    if (e.target.closest(".eliminar_gasto")) {
      //recogemos el id del gasto que se va a eliminar
      const id = e.target.closest(".eliminar_gasto").dataset.id;

      //Pedimos confirmación
      abrirModalConfirmacion({
        titulo: "Eliminar gasto",
        mensaje: "¿Seguro que deseas eliminar este gasto?",
        onConfirm: async () => {
          try {
            const datos = new FormData();
            datos.append("id", id);
            datos.append("_csrf", window.CSRF_TOKEN);

            const respuesta = await fetch(
              "index.php?r=gasto/eliminarGastoAjax",
              {
                method: "POST",
                body: datos,
              },
            );

            const data = await respuesta.json();

            if (data.ok) {
              eliminarGastoDelDOM(id);

              cargarGraficoPresupuesto();
              cargarGraficoVoluntarios6m();
              cargarGraficoObligatorios6m();
              cargarGraficoAhorros6m();
            } else {
              abrirModalInfo({
                titulo: "No se pudo eliminar el gasto",
                mensaje:
                  data.msg ||
                  "La operación no pudo completarse. Inténtalo de nuevo.",
              });
            }
          } catch (error) {
            abrirModalInfo({
              titulo: "Problema de conexión",
              mensaje:
                "No se pudo contactar con el servidor. Comprueba tu conexión e inténtalo de nuevo.",
            });
          }
        },
      });

      return;
    }

    // ---------------------------------------Actualizar un gasto
    // -------------------------------------------

    //Verificamos si el click es para modificar un gasto
    if (e.target.classList.contains("cantidad_gasto")) {
      //Llamamos a la función auxiliar correspondiente
      editarGastoInline(e.target);
    }
  });

  //Cargamos el gráfico al entrar
  cargarGraficoAhorros6m();
  cargarGraficoPresupuesto();
  cargarGraficoVoluntarios6m();
  cargarGraficoObligatorios6m();

  //Actulizamos el gráfico al cambiar el mes
  document.getElementById("mes").addEventListener("change", () => {
    cargarGraficoPresupuesto();
    cargarGraficoVoluntarios6m();
    cargarGraficoObligatorios6m();
    cargarGraficoAhorros6m();
  });
});
