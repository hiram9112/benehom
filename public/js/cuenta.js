document.addEventListener("DOMContentLoaded", () => {
  initCoincidenciaPassword();
  initEliminarCuenta();
});

// Validación de que la confirmación coincide con la nueva contraseña
function initCoincidenciaPassword() {
  const nueva = document.getElementById("password_nueva");
  const confirmacion = document.getElementById("password_confirmacion_nueva");
  const matchError = document.getElementById("passwordMatchError");
  const form = document.getElementById("formCambiarPassword");

  if (!nueva || !confirmacion || !matchError) return;

  function comprobarCoincidencia() {
    const hayDesajuste =
      confirmacion.value !== "" && confirmacion.value !== nueva.value;

    matchError.hidden = !hayDesajuste;
    confirmacion.setAttribute("aria-invalid", hayDesajuste ? "true" : "false");

    return !hayDesajuste;
  }

  confirmacion.addEventListener("input", comprobarCoincidencia);
  nueva.addEventListener("input", comprobarCoincidencia);

  if (form) {
    form.addEventListener("submit", (e) => {
      if (!comprobarCoincidencia()) {
        e.preventDefault();
        confirmacion.focus();
      }
    });
  }
}

// Confirmación mediante modal antes de eliminar la cuenta
function initEliminarCuenta() {
  const formEliminarCuenta = document.getElementById("formEliminarCuenta");

  if (!formEliminarCuenta) return;

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

  formEliminarCuenta.addEventListener("submit", (e) => {
    e.preventDefault();

    abrirModalConfirmacion({
      titulo: "Eliminar cuenta",
      mensaje:
        "¿Seguro que deseas eliminar tu cuenta?\n\n" +
        "Esta acción es irreversible y se eliminarán todos tus datos.",
      onConfirm: () => {
        formEliminarCuenta.submit();
      },
    });
  });
}
