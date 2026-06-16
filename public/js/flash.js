(function () {
  "use strict";

  var dismissFlash = function (message) {
    if (message.dataset.dismissing === "true") return;

    message.dataset.dismissing = "true";
    message.classList.add("is-dismissing");

    var removeMessage = function () {
      return message.remove();
    };
    var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    if (reduceMotion) {
      removeMessage();
      return;
    }

    message.addEventListener("transitionend", removeMessage, { once: true });
    window.setTimeout(removeMessage, 300);
  };

  var prepararFlash = function (message) {
    var btn = message.querySelector("[data-flash-dismiss]");
    if (btn) {
      btn.addEventListener("click", function () {
        return dismissFlash(message);
      });
    }

    if (message.hasAttribute("data-flash-autodismiss")) {
      var duracion = parseInt(message.getAttribute("data-flash-autodismiss"), 10);
      if (isNaN(duracion) || duracion <= 0) duracion = 6500;
      window.setTimeout(function () {
        return dismissFlash(message);
      }, duracion);
    }
  };

  var variantesFlash = {
    success: { clase: "bh-flash-success", icono: "bi-check-circle", role: "status", autodismiss: true },
    warning: { clase: "bh-flash-warning", icono: "bi-exclamation-triangle", role: "status", autodismiss: true },
    error: { clase: "bh-flash-error", icono: "bi-exclamation-circle", role: "alert", autodismiss: false },
  };

  window.mostrarFlash = function (texto, tipo, duracionMs) {
    if (!texto) return;

    var variante = variantesFlash[tipo] || variantesFlash.error;

    var stack = document.querySelector(".bh-flash-stack");

    if (!stack) {
      stack = document.createElement("div");
      stack.className = "bh-flash-stack";
      stack.setAttribute("aria-live", "polite");
      stack.setAttribute("aria-atomic", "true");
      document.body.prepend(stack);
    }

    var message = document.createElement("div");

    message.className = "bh-flash " + variante.clase;
    message.setAttribute("role", variante.role);
    message.setAttribute("data-flash-message", "");

    if (variante.autodismiss || (typeof duracionMs === "number" && duracionMs > 0)) {
      var duracion = typeof duracionMs === "number" && duracionMs > 0 ? String(duracionMs) : "";
      message.setAttribute("data-flash-autodismiss", duracion);
    }

    message.innerHTML =
      '<i class="bi ' +
      variante.icono +
      '" aria-hidden="true"></i>' +
      "<p></p>" +
      '<button type="button" class="bh-flash-close" data-flash-dismiss aria-label="Cerrar mensaje">' +
      '<i class="bi bi-x-lg" aria-hidden="true"></i>' +
      "</button>";

    message.querySelector("p").textContent = texto;
    stack.append(message);
    prepararFlash(message);
  };

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-flash-message]").forEach(prepararFlash);
  });
})();
