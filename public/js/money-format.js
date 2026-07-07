(function () {
  "use strict";

  function toNumber(value) {
    var number = Number(value);
    return Number.isFinite(number) ? number : 0;
  }

  function formatAmount(value, options) {
    var settings = options || {};
    var number = toNumber(value);
    var formatter = new Intl.NumberFormat("es-ES", {
      minimumFractionDigits: settings.minimumFractionDigits ?? 2,
      maximumFractionDigits: settings.maximumFractionDigits ?? 2,
    });

    return formatter.format(number);
  }

  function formatMoney(value, options) {
    return formatAmount(value, options) + " \u20AC";
  }

  function formatDeltaMoney(value, options) {
    var number = toNumber(value);
    var sign = number > 0 ? "+" : number < 0 ? "-" : "";

    return sign + formatMoney(Math.abs(number), options);
  }

  window.BHMoney = {
    formatAmount: formatAmount,
    formatMoney: formatMoney,
    formatDeltaMoney: formatDeltaMoney,
  };
})();
