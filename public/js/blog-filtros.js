(function () {
  "use strict";

  var normalizar = function (texto) {
    return (texto || "")
      .toString()
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .trim();
  };

  document.addEventListener("DOMContentLoaded", function () {
    var library = document.querySelector(".bh-blog-library");
    if (!library) return;

    var filtros = Array.prototype.slice.call(library.querySelectorAll("[data-blog-filter]"));
    var buscador = library.querySelector("[data-blog-search]");
    var tarjetas = Array.prototype.slice.call(library.querySelectorAll(".bh-blog-filterable"));
    var vacio = library.querySelector("[data-blog-empty]");
    var contadorValor = library.querySelector("[data-blog-count-value]");
    var contadorTemas = library.querySelector("[data-blog-count-temas]");

    if (!tarjetas.length) return;

    var categoriaActiva = "";
    var consulta = "";

    var aplicar = function () {
      var sinFiltros = categoriaActiva === "" && consulta === "";
      var visibles = 0;

      tarjetas.forEach(function (tarjeta) {
        var esDestacado = tarjeta.hasAttribute("data-blog-featured");
        var mostrar;

        if (esDestacado) {
          mostrar = sinFiltros;
        } else {
          var coincideCategoria =
            categoriaActiva === "" || tarjeta.getAttribute("data-categoria") === categoriaActiva;
          var coincideTexto =
            consulta === "" || normalizar(tarjeta.getAttribute("data-busqueda")).indexOf(consulta) !== -1;
          mostrar = coincideCategoria && coincideTexto;
        }

        tarjeta.hidden = !mostrar;
        if (mostrar) visibles += 1;
      });

      if (contadorValor) contadorValor.textContent = String(visibles);
      if (contadorTemas) contadorTemas.hidden = !sinFiltros;
      if (vacio) vacio.hidden = visibles !== 0;
    };

    filtros.forEach(function (boton) {
      boton.addEventListener("click", function () {
        categoriaActiva = boton.getAttribute("data-blog-filter") || "";

        filtros.forEach(function (otro) {
          var activo = otro === boton;
          otro.classList.toggle("is-active", activo);
          otro.setAttribute("aria-pressed", activo ? "true" : "false");
        });

        aplicar();
      });
    });

    if (buscador) {
      buscador.addEventListener("input", function () {
        consulta = normalizar(buscador.value);
        aplicar();
      });
    }

    aplicar();
  });
})();
