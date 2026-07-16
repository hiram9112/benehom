document.addEventListener("DOMContentLoaded", () => {
  const pickers = document.querySelectorAll("[data-category-picker]");
  const catalogo = window.BH_GASTO_CATEGORIAS || {};

  pickers.forEach((picker) => {
    const tipo = picker.dataset.categoryType;
    const grupos = catalogo[tipo] || {};
    const areaSelect = picker.querySelector("[data-area-select]");
    const conceptSelect = picker.querySelector("[data-concept-select]");
    const help = picker.querySelector("[data-category-help]");
    const form = picker.closest("form");

    if (!areaSelect || !conceptSelect) {
      return;
    }

    Object.entries(grupos).forEach(([valor, grupo]) => {
      const option = document.createElement("option");
      option.value = valor;
      option.textContent = grupo.label;
      areaSelect.appendChild(option);
    });

    const resetConcepto = () => {
      conceptSelect.innerHTML = '<option value="" selected>Elige primero un área</option>';
      conceptSelect.disabled = true;

      if (help) {
        help.textContent = "Elige el área para ver solo los gastos relacionados.";
      }
    };

    areaSelect.addEventListener("change", () => {
      const grupo = grupos[areaSelect.value];
      resetConcepto();

      if (!grupo) {
        return;
      }

      conceptSelect.innerHTML = '<option value="" selected disabled>Selecciona un concepto</option>';
      conceptSelect.disabled = false;

      Object.entries(grupo.items || grupo.conceptos || {}).forEach(([valor, label]) => {
        const option = document.createElement("option");
        option.value = valor;
        option.textContent = label;
        conceptSelect.appendChild(option);
      });

      if (help) {
        help.textContent = grupo.help || "Selecciona el concepto que mejor encaje con este gasto.";
      }
    });

    if (form) {
      form.addEventListener("reset", () => {
        window.setTimeout(resetConcepto, 0);
      });
    }
  });
});
