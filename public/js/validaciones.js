//Esperamos que el contenido HMTL esté completamente cargado antes de ejecutar el código
document.addEventListener("DOMContentLoaded", () => {
  //Función reutilizable validar el campo numérico de los formularios
  function validarFormulario(formID, inputID) {
    //Seleccionamos el formulario y el campo numérico según su ID usando literales
    const form = document.querySelector(`#${formID}`);
    const input = document.querySelector(`#${inputID}`);

    //Si no existe terminamos la función
    if (!form || !input) return;

    //Agregamos un el Listenner al evento submit de cada formulario
    form.addEventListener("submit", (e) => {
      //Convertimos el valor del input a número decimal
      const valor = parseFloat(input.value);

      //Comprobamos si el valor no es un número o es menor o igual a 0
      if (isNaN(valor) || valor <= 0) {
        //Detenemos el envío del formulario
        e.preventDefault();
        //Evitamos que el evento sea propague al resto de listeners
        e.stopImmediatePropagation();

        //mostramos mensaje de alerta al usuario
        abrirModalInfo({
          titulo: "Cantidad no válida",
          mensaje: "La cantidad introducida debe ser un número mayor que 0.",
        });

        //colocamos el cursor de nuevo en el campo para corregir el valor
        input.focus();
      }
    });
  }

  //Aplicamos la validación a los tres formularios
  validarFormulario("formIngresos", "cantidad_ingreso");
  validarFormulario("formGastosObligatorios", "cantidad_gasto_obligatorio");
  validarFormulario("formGastosVoluntarios", "cantidad_gasto_voluntario");
});
