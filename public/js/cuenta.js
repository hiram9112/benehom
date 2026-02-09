document.addEventListener("DOMContentLoaded", () => {
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
});
