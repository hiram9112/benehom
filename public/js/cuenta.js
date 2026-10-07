document.addEventListener("DOMContentLoaded", () => {
  initCoincidenciaPassword();
  initListaTokensMcp();
  initCreacionTokenMcp();
  initRevocacionTokenMcp();
  initEliminarCuenta();
});

const MCP_TOKEN_VISIBLE_LIMIT = 5;

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

function initCreacionTokenMcp() {
  const form = document.querySelector("[data-mcp-token-form]");
  const panel = document.querySelector("[data-mcp-token-created]");
  const secretInput = document.querySelector("[data-mcp-token-secret]");
  const copyButton = document.querySelector("[data-mcp-copy]");
  const copyLabel = document.querySelector("[data-mcp-copy-label]");
  const copyStatus = document.querySelector("[data-mcp-copy-status]");

  if (!form || !panel || !secretInput || !copyButton || !copyLabel || !copyStatus) return;

  const clearSecret = () => {
    secretInput.value = "";
    panel.hidden = true;
    copyStatus.textContent = "";
    copyLabel.textContent = "Copiar token";
  };

  if (secretInput.value !== "") {
    copyButton.hidden = false;
  }

  copyButton.addEventListener("click", async () => {
    if (secretInput.value === "") return;

    try {
      if (!window.isSecureContext || !navigator.clipboard) {
        throw new Error("Clipboard API no disponible");
      }

      await navigator.clipboard.writeText(secretInput.value);
      copyLabel.textContent = "Token copiado";
      copyStatus.textContent = "Token copiado. Guárdalo en un lugar seguro.";
    } catch (error) {
      secretInput.focus();
      secretInput.select();
      copyStatus.textContent = "No se ha podido copiar automáticamente. El token está seleccionado para que puedas copiarlo manualmente.";
    }
  });

  window.addEventListener("pagehide", clearSecret);
  window.addEventListener("pageshow", (event) => {
    if (event.persisted) clearSecret();
  });

  const ajaxAction = form.dataset.ajaxAction;
  if (!ajaxAction || typeof window.fetch !== "function") return;

  const submitButton = form.querySelector("[data-mcp-submit]");
  const nameInput = form.querySelector("#mcp_token_nombre");
  const formError = form.querySelector("[data-mcp-form-error]");
  const tokenList = document.querySelector("[data-mcp-token-list]");
  const tokenTable = document.querySelector("[data-mcp-token-table]");
  const tokenEmpty = document.querySelector("[data-mcp-token-empty]");
  const rowTemplate = document.querySelector("[data-mcp-token-row-template]");

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (!submitButton || !nameInput || !formError) return;

    formError.hidden = true;
    formError.textContent = "";
    nameInput.removeAttribute("aria-invalid");
    submitButton.disabled = true;
    submitButton.textContent = "Creando token...";
    form.setAttribute("aria-busy", "true");

    try {
      const response = await fetch(ajaxAction, {
        method: "POST",
        body: new FormData(form),
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      });
      const data = await readMcpJsonResponse(
        response,
        "No se ha podido crear el token MCP. Inténtalo de nuevo.",
      );
      if (typeof data.token !== "string" || !data.record) {
        throw new Error("No se ha recibido el token creado.");
      }

      secretInput.value = data.token;
      panel.hidden = false;
      copyButton.hidden = false;
      copyLabel.textContent = "Copiar token";
      copyStatus.textContent = "";
      form.reset();

      if (tokenList && tokenTable && tokenEmpty && rowTemplate) {
        addMcpTokenRow(data.record, tokenList, tokenTable, tokenEmpty, rowTemplate);
      }

      panel.focus({ preventScroll: true });
      panel.scrollIntoView({ block: "start" });
    } catch (error) {
      formError.textContent = error instanceof Error
        ? error.message
        : "No se ha podido crear el token MCP. Inténtalo de nuevo.";
      formError.hidden = false;
      nameInput.setAttribute("aria-invalid", "true");
      nameInput.focus();
    } finally {
      submitButton.disabled = false;
      submitButton.textContent = "Crear token MCP";
      form.removeAttribute("aria-busy");
    }
  });
}

function addMcpTokenRow(record, tokenList, tokenTable, tokenEmpty, rowTemplate) {
  const id = Number(record.id);
  if (!Number.isInteger(id) || id <= 0 || tokenList.querySelector(`[data-mcp-token-id="${id}"]`)) return;

  const fragment = rowTemplate.content.cloneNode(true);
  const row = fragment.querySelector("tr");

  row.dataset.mcpTokenId = String(id);
  row.querySelector("[data-mcp-token-name]").textContent = String(record.nombre || "");
  row.querySelector("[data-mcp-token-created-at]").textContent = "Ahora";
  row.querySelector("[data-mcp-token-last-used]").textContent = record.last_used_at || "Sin uso";
  row.querySelector("[data-mcp-token-status]").textContent = record.revoked_at ? "Revocado" : "Activo";
  row.querySelector('input[name="token_id"]').value = String(id);

  tokenList.prepend(fragment);
  tokenTable.hidden = false;
  tokenEmpty.hidden = true;
  updateMcpTokenListVisibility();
}

function initListaTokensMcp() {
  const toggle = document.querySelector("[data-mcp-token-list-toggle]");
  if (!toggle) return;

  updateMcpTokenListVisibility();

  toggle.addEventListener("click", () => {
    const expanded = toggle.getAttribute("aria-expanded") === "true";
    toggle.setAttribute("aria-expanded", expanded ? "false" : "true");
    updateMcpTokenListVisibility();
  });
}

function updateMcpTokenListVisibility() {
  const tokenList = document.querySelector("[data-mcp-token-list]");
  const toggle = document.querySelector("[data-mcp-token-list-toggle]");
  const toggleLabel = toggle && toggle.querySelector("[data-mcp-token-list-toggle-label]");
  const toggleIcon = toggle && toggle.querySelector("[data-mcp-token-list-toggle-icon]");
  if (!tokenList || !toggle || !toggleLabel || !toggleIcon) return;

  const rows = Array.from(tokenList.querySelectorAll(":scope > tr[data-mcp-token-id]"));
  const expanded = toggle.getAttribute("aria-expanded") === "true";

  rows.forEach((row, index) => {
    row.hidden = !expanded && index >= MCP_TOKEN_VISIBLE_LIMIT;
  });

  toggle.hidden = rows.length <= MCP_TOKEN_VISIBLE_LIMIT;
  toggleLabel.textContent = expanded ? "Ver menos" : "Ver más";
  toggleIcon.classList.toggle("ti-chevron-down", !expanded);
  toggleIcon.classList.toggle("ti-chevron-up", expanded);
}

function initRevocacionTokenMcp() {
  const tokenList = document.querySelector("[data-mcp-token-list]");
  if (!tokenList || typeof window.fetch !== "function") return;

  tokenList.addEventListener("submit", async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches("[data-mcp-revoke-form]")) return;

    const ajaxAction = form.dataset.ajaxAction;
    const row = form.closest("[data-mcp-token-id]");
    const submitButton = form.querySelector("[data-mcp-revoke-submit]");
    const status = row && row.querySelector("[data-mcp-token-status]");
    if (!ajaxAction || !row || !submitButton || !status) return;

    event.preventDefault();
    submitButton.disabled = true;
    submitButton.textContent = "Revocando...";
    form.setAttribute("aria-busy", "true");

    try {
      const response = await fetch(ajaxAction, {
        method: "POST",
        body: new FormData(form),
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      });
      const data = await readMcpJsonResponse(
        response,
        "No se ha podido revocar ese token MCP.",
      );

      if (Number(data.token_id) !== Number(row.dataset.mcpTokenId)) {
        throw new Error("No se ha podido confirmar la revocación del token MCP.");
      }

      status.textContent = "Revocado";
      form.remove();
      status.focus();

      if (typeof window.mostrarFlash === "function") {
        window.mostrarFlash("Token MCP revocado.", "success", 5000);
      }
    } catch (error) {
      if (typeof window.mostrarFlash === "function") {
        window.mostrarFlash(
          error instanceof Error ? error.message : "No se ha podido revocar ese token MCP.",
          "error",
        );
      }

      submitButton.disabled = false;
      submitButton.textContent = "Revocar";
      form.removeAttribute("aria-busy");
    }
  });
}

async function readMcpJsonResponse(response, fallbackMessage) {
  const contentType = response.headers.get("content-type") || "";
  if (!contentType.includes("application/json")) {
    throw new Error(fallbackMessage);
  }

  const payload = await response.json();
  if (!response.ok || payload.ok !== true) {
    const message = payload.error && payload.error.message
      ? payload.error.message
      : fallbackMessage;
    throw new Error(message);
  }

  return payload.data || {};
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
