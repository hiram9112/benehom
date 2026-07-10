(function () {
  "use strict";

  var rootStyles = window.getComputedStyle(document.documentElement);

  function token(name, fallback) {
    return rootStyles.getPropertyValue(name).trim() || fallback;
  }

  var colors = {
    income: token("--bh-income", "#33A64D"),
    incomeSoft: token("--bh-income-soft", "#E5F3E9"),
    expense: token("--bh-negative", "#B83E3E"),
    expenseSoft: token("--bh-negative-soft", "#FBEBEB"),
    saving: token("--bh-saving", "#33A64D"),
    savingSoft: token("--bh-saving-soft", "#E5F3E9"),
    positive: token("--bh-positive", "#33A64D"),
    positiveSoft: token("--bh-positive-soft", "#E5F3E9"),
    essential: token("--bh-essential", "#4C71A8"),
    essentialSoft: token("--bh-essential-soft", "#E7EEF8"),
    flexible: token("--bh-flexible", "#B45309"),
    flexibleSoft: token("--bh-flexible-soft", "#F6EBDD"),
    negative: token("--bh-negative", "#B83E3E"),
    negativeSoft: token("--bh-negative-soft", "#FBEBEB"),
    info: token("--bh-info", "#163F7F"),
    infoSoft: token("--bh-info-soft", "#E8EFF8"),
    neutral: token("--bh-neutral", "#5C6672"),
    neutralSoft: token("--bh-neutral-soft", "#EEF2F7"),
    textMain: token("--bh-text-main", "#26313F"),
    textMuted: token("--bh-text-muted", "#5C6672"),
    borderColor: token("--bh-border-color", "#E3EAE4"),
    surfaceCard: token("--bh-surface-card", "#FDFEFD"),
  };

  var fontFamily = token("--bh-font-interface", "'Nunito Sans', Arial, sans-serif");
  var reducedMotion = typeof window.matchMedia === "function" && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function tooltip(overrides) {
    return Object.assign({
      backgroundColor: colors.surfaceCard,
      titleColor: colors.textMain,
      bodyColor: colors.textMain,
      borderColor: colors.borderColor,
      borderWidth: 1,
      cornerRadius: 10,
      padding: 10,
      titleFont: { family: fontFamily, weight: "600", size: 13 },
      bodyFont: { family: fontFamily, weight: "400", size: 12 },
    }, overrides || {});
  }

  function legend(overrides) {
    return Object.assign({
      position: "bottom",
      labels: {
        font: { family: fontFamily, size: 12 },
        color: colors.textMain,
        usePointStyle: true,
        pointStyle: "rectRounded",
        boxWidth: 14,
        padding: 14,
      },
    }, overrides || {});
  }

  function applyChartDefaults() {
    if (!window.Chart) return;

    window.Chart.defaults.font.family = fontFamily;
    window.Chart.defaults.color = colors.textMain;
    window.Chart.defaults.borderColor = colors.borderColor;
    window.Chart.defaults.plugins.tooltip = Object.assign(window.Chart.defaults.plugins.tooltip || {}, tooltip());

    if (reducedMotion) {
      window.Chart.defaults.animation = false;
    }
  }

  window.BHChartTheme = {
    colors: colors,
    fontFamily: fontFamily,
    reducedMotion: reducedMotion,
    tooltip: tooltip,
    legend: legend,
    applyChartDefaults: applyChartDefaults,
  };

  applyChartDefaults();
})();
