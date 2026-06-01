document.addEventListener("DOMContentLoaded", () => {
  // ----------------------------------------------------SECCIÓN Enventos submit
  // Formularios-------------------------------------
  // ----------------------------------------------------------------------------------------------------------------------------

  // ------------------------------------------Crear nuevo
  // ingreso-------------------------------------------------- Seleccionamos el
  // fomulario de ingresos
  const formIngresos = document.getElementById("formIngresos");

  // Escuchamos el evento submit y usamos una función de tipo async para poder
  // emplear el await
  if (formIngresos) {
    formIngresos.addEventListener("submit", async (e) => {
      //evitamos que la pagina se recargue
      e.preventDefault();

      //capturamos los datos del formulario usando FormData
      const datos = new FormData(formIngresos);

      try {
        //Enviamos petición al servidor
        const respuesta = await fetch("index.php?r=ingreso/agregarAjax", {
          method: "POST",
          body: datos,
        });

        //Convertimos la respuesta a formato JSON
        const data = await respuesta.json();

        //Si el servidor confirma éxito
        if (data.ok) {
          //LLamamos a la función auxiliar correpondiente
          agregarIngresoAlDOM(data.ingreso);

          //Actualizamos gráficos
          cargarGraficoPresupuesto();
          cargarGraficoGastosFlexibles6m();
          cargarGraficoGastosEsenciales6m();
          cargarGraficoAhorros6m();

          //Limpiamos campos  del formulario
          formIngresos.reset();
        } else {
          abrirModalInfo({
            titulo: "No se pudo completar la operación",
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
    });
  }

  // ------------------------------------------Crear nuevo gasto
  // esencial--------------------------------------- Seleccionamos el fomulario
  // de gastos esenciales
  const formGastosEsenciales = document.getElementById(
    "formGastosEsenciales",
  );

  // Escuchamos el evento submit y usamos una función de tipo async para poder
  // emplear el await
  if (formGastosEsenciales) {
    formGastosEsenciales.addEventListener("submit", async (e) => {
      //evitamos que la pagina se recargue
      e.preventDefault();

      //capturamos los datos del formulario usando FormData
      const datos = new FormData(formGastosEsenciales);

      try {
        //Enviamos petición al servidor
        const respuesta = await fetch(
          "index.php?r=gasto/agregarGastoEsencialAjax",
          {
            method: "POST",
            body: datos,
          },
        );

        //Convertimos la respuesta a formato JSON
        const data = await respuesta.json();

        //Si el servidor confirma éxito
        if (data.ok) {
          //LLamamos a la función auxiliar correpondiente
          agregarGastoEsencialAlDOM(data.gasto_esencial);

          //Actualizamos gráficos
          cargarGraficoPresupuesto();
          cargarGraficoGastosFlexibles6m();
          cargarGraficoGastosEsenciales6m();
          cargarGraficoAhorros6m();

          //Limpiamos campos  del formulario
          formGastosEsenciales.reset();
        } else {
          abrirModalInfo({
            titulo: "No se pudo completar la operación",
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
    });
  }

  // ------------------------------------------Crear nuevo gasto
  // flexible--------------------------------------- Seleccionamos el fomulario
  // de gastos flexibles
  const formGastosFlexibles = document.getElementById(
    "formGastosFlexibles",
  );

  // Escuchamos el evento submit y usamos una función de tipo async para poder
  // emplear el await
  if (formGastosFlexibles) {
    formGastosFlexibles.addEventListener("submit", async (e) => {
      //evitamos que la pagina se recargue
      e.preventDefault();

      //capturamos los datos del formulario usando FormData
      const datos = new FormData(formGastosFlexibles);

      try {
        //Enviamos petición al servidor
        const respuesta = await fetch(
          "index.php?r=gasto/agregarGastoFlexibleAjax",
          {
            method: "POST",
            body: datos,
          },
        );

        //Convertimos la respuesta a formato JSON
        const data = await respuesta.json();

        //Si el servidor confirma éxito
        if (data.ok) {
          //LLamamos a la función auxiliar correpondiente
          agregarGastoFlexibleAlDOM(data.gasto_flexible);

          //Actualizamos gráficos
          cargarGraficoPresupuesto();
          cargarGraficoGastosFlexibles6m();
          cargarGraficoGastosEsenciales6m();
          cargarGraficoAhorros6m();

          //Limpiamos campos  del formulario
          formGastosFlexibles.reset();
        } else {
          abrirModalInfo({
            titulo: "No se pudo completar la operación",
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
    });
  }
});
