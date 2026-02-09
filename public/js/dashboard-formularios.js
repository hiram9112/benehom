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
        console.log("respuesta del servidor :", data);

        //Si el servidor confirma éxito
        if (data.ok) {
          //LLamamos a la función auxiliar correpondiente
          agregarIngresoAlDOM(data.ingreso);

          //Actualizamos gráficos
          cargarGraficoPresupuesto();
          cargarGraficoVoluntarios6m();
          cargarGraficoObligatorios6m();
          cargarGraficoAhorros6m();

          //Limpiamos campos  del formulario
          formIngresos.reset();
        } else {
          alert(data.msg || "Error al agregar el ingreso");
        }
      } catch (error) {
        console.error("Error en la solicitud AJAX: ", error);
        alert("Error de conexión con el servidor");
      }
    });
  }

  // ------------------------------------------Crear nuevo gasto
  // obligatorio--------------------------------------- Seleccionamos el fomulario
  // de gatos obligatorios
  const formGastosObligatorios = document.getElementById(
    "formGastosObligatorios",
  );

  // Escuchamos el evento submit y usamos una función de tipo async para poder
  // emplear el await
  if (formGastosObligatorios) {
    formGastosObligatorios.addEventListener("submit", async (e) => {
      //evitamos que la pagina se recargue
      e.preventDefault();

      //capturamos los datos del formulario usando FormData
      const datos = new FormData(formGastosObligatorios);

      try {
        //Enviamos petición al servidor
        const respuesta = await fetch(
          "index.php?r=gasto/agregarGastoObligAjax",
          {
            method: "POST",
            body: datos,
          },
        );

        //Convertimos la respuesta a formato JSON
        const data = await respuesta.json();
        console.log("respuesta del servidor :", data);

        //Si el servidor confirma éxito
        if (data.ok) {
          //LLamamos a la función auxiliar correpondiente
          agregarGastoObligAlDOM(data.gasto_oblig);

          //Actualizamos gráficos
          cargarGraficoPresupuesto();
          cargarGraficoVoluntarios6m();
          cargarGraficoObligatorios6m();
          cargarGraficoAhorros6m();

          //Limpiamos campos  del formulario
          formGastosObligatorios.reset();
        } else {
          alert(data.msg || "Error al agregar el gasto obligatorio");
        }
      } catch (error) {
        console.error("Error en la solicitud AJAX: ", error);
        alert("Error de conexión con el servidor");
      }
    });
  }

  // ------------------------------------------Crear nuevo gasto
  // Voluntario--------------------------------------- Seleccionamos el fomulario
  // de gatos Voluntarios
  const formGastosVoluntarios = document.getElementById(
    "formGastosVoluntarios",
  );

  // Escuchamos el evento submit y usamos una función de tipo async para poder
  // emplear el await
  if (formGastosVoluntarios) {
    formGastosVoluntarios.addEventListener("submit", async (e) => {
      //evitamos que la pagina se recargue
      e.preventDefault();

      //capturamos los datos del formulario usando FormData
      const datos = new FormData(formGastosVoluntarios);

      try {
        //Enviamos petición al servidor
        const respuesta = await fetch(
          "index.php?r=gasto/agregarGastoVolunAjax",
          {
            method: "POST",
            body: datos,
          },
        );

        //Convertimos la respuesta a formato JSON
        const data = await respuesta.json();
        console.log("respuesta del servidor :", data);

        //Si el servidor confirma éxito
        if (data.ok) {
          //LLamamos a la función auxiliar correpondiente
          agregarGastoVolunAlDOM(data.gasto_volun);

          //Actualizamos gráficos
          cargarGraficoPresupuesto();
          cargarGraficoVoluntarios6m();
          cargarGraficoObligatorios6m();
          cargarGraficoAhorros6m();

          //Limpiamos campos  del formulario
          formGastosVoluntarios.reset();
        } else {
          alert(data.msg || "Error al agregar el gasto voluntario");
        }
      } catch (error) {
        console.error("Error en la solicitud AJAX: ", error);
        alert("Error de conexión con el servidor");
      }
    });
  }
});
