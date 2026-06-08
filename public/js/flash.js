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
      window.setTimeout(function () {
        return dismissFlash(message);
      }, 6500);
    }
  };

  window.mostrarFlash = function (texto, tipo) {
    if (!texto) return;
    if (tipo === undefined) tipo = "error";

    var stack = document.querySelector(".bh-flash-stack");

    if (!stack) {
      stack = document.createElement("div");
      stack.className = "bh-flash-stack";
      stack.setAttribute("aria-live", "polite");
      stack.setAttribute("aria-atomic", "true");
      document.body.prepend(stack);
    }

    var message = document.createElement("div");
    var isSuccess = tipo === "success";

    message.className = "bh-flash " + (isSuccess ? "bh-flash-success" : "bh-flash-error");
    message.setAttribute("role", isSuccess ? "status" : "alert");
    message.setAttribute("data-flash-message", "");

    if (isSuccess) {
      message.setAttribute("data-flash-autodismiss", "");
    }

    message.innerHTML =
      '<i class="bi ' +
      (isSuccess ? "bi-check-circle" : "bi-exclamation-circle") +
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
