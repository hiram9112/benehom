document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-bh-password-requirements]").forEach((lista) => {
    const inputId = lista.dataset.bhPasswordRequirements;
    const input = document.getElementById(inputId);

    if (!input) return;

    const reglas = {
      length: (v) => v.length >= 8,
      upper: (v) => /[A-Z]/.test(v),
      lower: (v) => /[a-z]/.test(v),
      number: (v) => /[0-9]/.test(v),
    };

    function evaluarRequisitos() {
      const valor = input.value;

      lista.querySelectorAll("li[data-req]").forEach((li) => {
        const regla = reglas[li.dataset.req];
        const cumple = typeof regla === "function" ? regla(valor) : false;

        li.classList.toggle("is-met", cumple);

        li.setAttribute("aria-checked", cumple ? "true" : "false");
      });
    }

    input.addEventListener("input", evaluarRequisitos);
    evaluarRequisitos();
  });
});
