(function initializeApplication(global) {
const DEFAULTS = global.MiHogarConfig;
const {
  calculateFromCredit,
  calculateFromProperty,
  compareProperties,
} = global.MiHogarCalculations;

const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => [...document.querySelectorAll(selector)];

const elements = {
  form: $("#calculator-form"),
  error: $("#form-error"),
  empty: $("#results-empty"),
  results: $("#results-content"),
  exchangeRate: $("#exchange-rate"),
  financingPercent: $("#financing-percent"),
  notaryPercent: $("#notary-percent"),
  realEstatePercent: $("#real-estate-percent"),
  annualRatePercent: $("#annual-rate-percent"),
  years: $("#years"),
};

let activeScenario = "property";

const arsFormatter = new Intl.NumberFormat("es-AR", {
  style: "currency",
  currency: "ARS",
  maximumFractionDigits: 0,
});

const usdFormatter = new Intl.NumberFormat("es-AR", {
  style: "currency",
  currency: "USD",
  maximumFractionDigits: 2,
});

function formatArs(value) {
  return arsFormatter.format(value);
}

function formatUsd(value) {
  return usdFormatter.format(value);
}

function readParameters() {
  return {
    exchangeRate: elements.exchangeRate.value,
    financingPercent: elements.financingPercent.value,
    notaryPercent: elements.notaryPercent.value,
    realEstatePercent: elements.realEstatePercent.value,
    annualRatePercent: elements.annualRatePercent.value,
    years: elements.years.value,
  };
}

function setDefaults() {
  elements.financingPercent.value = DEFAULTS.financingPercent;
  elements.notaryPercent.value = DEFAULTS.notaryPercent;
  elements.realEstatePercent.value = DEFAULTS.realEstatePercent;
  elements.annualRatePercent.value = DEFAULTS.annualRatePercent;
  elements.years.value = DEFAULTS.years;
}

function activateScenario(scenario) {
  activeScenario = scenario;
  $$(".scenario-tab").forEach((tab) => {
    const isActive = tab.dataset.scenario === scenario;
    tab.classList.toggle("active", isActive);
    tab.setAttribute("aria-selected", String(isActive));
  });
  $$(".scenario-panel").forEach((panel) => {
    const isActive = panel.dataset.panel === scenario;
    panel.classList.toggle("active", isActive);
    panel.hidden = !isActive;
  });
  elements.error.textContent = "";
}

function resultItem(label, value) {
  return `<div class="result-item"><small>${label}</small><strong>${value}</strong></div>`;
}

function renderSingle(result, options = {}) {
  const propertyLabel = options.creditMode
    ? "Valor máximo de propiedad"
    : "Valor de la propiedad";
  const heroLabel = options.creditMode
    ? "Propiedad máxima estimada"
    : "Cuota mensual estimada";
  const heroValue = options.creditMode
    ? formatUsd(result.propertyUsd)
    : formatArs(result.payment);

  elements.results.innerHTML = `
    <div class="result-header">
      <div>
        <h3>Resumen de la operación</h3>
        <p>Sistema francés · ${elements.years.value} años · ${elements.annualRatePercent.value}% anual</p>
      </div>
      <span class="result-badge">Estimación</span>
    </div>
    <div class="result-hero">
      <small>${heroLabel}</small>
      <strong>${heroValue}</strong>
    </div>
    <div class="result-grid">
      ${resultItem(propertyLabel, formatArs(result.propertyArs))}
      ${resultItem("Valor reconocido por banco", formatArs(result.bankValue))}
      ${resultItem("Gastos estimados", formatArs(result.expenses))}
      ${resultItem("Crédito necesario", formatArs(result.credit))}
      ${resultItem("Valor propiedad en USD", formatUsd(result.propertyUsd))}
      ${resultItem("Cuota mensual", formatArs(result.payment))}
    </div>
  `;
  showResults();
}

function signedArs(value) {
  const sign = value > 0 ? "+" : value < 0 ? "−" : "";
  return `${sign}${formatArs(Math.abs(value))}`;
}

function renderComparison(comparison) {
  const { scenarioA: a, scenarioB: b, differences: diff } = comparison;
  elements.results.innerHTML = `
    <div class="result-header">
      <div>
        <h3>Comparación</h3>
        <p>La diferencia muestra B respecto de A.</p>
      </div>
      <span class="result-badge">A vs. B</span>
    </div>
    <table class="comparison-table">
      <thead>
        <tr><th>Concepto</th><th>Propiedad A</th><th>Propiedad B</th></tr>
      </thead>
      <tbody>
        <tr><td>Valor USD</td><td>${formatUsd(a.propertyUsd)}</td><td>${formatUsd(b.propertyUsd)}</td></tr>
        <tr><td>Valor ARS</td><td>${formatArs(a.propertyArs)}</td><td>${formatArs(b.propertyArs)}</td></tr>
        <tr><td>Crédito</td><td>${formatArs(a.credit)}</td><td>${formatArs(b.credit)}</td></tr>
        <tr><td>Valor banco</td><td>${formatArs(a.bankValue)}</td><td>${formatArs(b.bankValue)}</td></tr>
        <tr><td>Gastos</td><td>${formatArs(a.expenses)}</td><td>${formatArs(b.expenses)}</td></tr>
        <tr><td>Cuota</td><td>${formatArs(a.payment)}</td><td>${formatArs(b.payment)}</td></tr>
      </tbody>
    </table>
    <div class="difference-list">
      <div><small>Diferencia crédito</small><strong>${signedArs(diff.credit)}</strong></div>
      <div><small>Diferencia cuota</small><strong>${signedArs(diff.payment)}</strong></div>
      <div><small>Diferencia gastos</small><strong>${signedArs(diff.expenses)}</strong></div>
    </div>
  `;
  showResults();
}

function showResults() {
  elements.empty.hidden = true;
  elements.results.hidden = false;
}

function calculate(event) {
  event.preventDefault();
  elements.error.textContent = "";

  try {
    const parameters = readParameters();
    if (activeScenario === "property") {
      renderSingle(calculateFromProperty($("#property-usd").value, parameters));
    } else if (activeScenario === "credit") {
      renderSingle(calculateFromCredit($("#credit-ars").value, parameters), {
        creditMode: true,
      });
    } else {
      renderComparison(
        compareProperties(
          $("#property-a-usd").value,
          $("#property-b-usd").value,
          parameters
        )
      );
    }
  } catch (error) {
    elements.error.textContent = error.message;
  }
}

$$(".scenario-tab").forEach((tab) => {
  tab.addEventListener("click", () => activateScenario(tab.dataset.scenario));
});

elements.form.addEventListener("submit", calculate);
$("#reset-settings").addEventListener("click", () => {
  setDefaults();
  elements.exchangeRate.value = "";
  elements.error.textContent = "";
});

$("[data-scroll-settings]").addEventListener("click", () => {
  $("#parametros").scrollIntoView({ behavior: "smooth" });
});

setDefaults();
})(globalThis);
