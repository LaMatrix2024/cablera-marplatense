import assert from "node:assert/strict";
await import("../js/calculations.js");

const {
  calculateFromCredit,
  calculateFromProperty,
  compareProperties,
  monthlyPayment,
  normalizeParameters,
} = globalThis.MiHogarCalculations;

const parameters = {
  exchangeRate: 1000,
  financingPercent: 90,
  notaryPercent: 4.5,
  realEstatePercent: 3,
  annualRatePercent: 6,
  years: 30,
};

function closeTo(actual, expected, tolerance = 0.01) {
  assert.ok(
    Math.abs(actual - expected) <= tolerance,
    `${actual} no está dentro de ${tolerance} de ${expected}`
  );
}

const property120 = calculateFromProperty(120000, parameters);
closeTo(property120.propertyArs, 120000000);
closeTo(property120.bankValue, 145454545.45454547);
closeTo(property120.expenses, 10909090.90909091);
closeTo(property120.credit, 130909090.90909092);
closeTo(property120.payment, 784866.14);

const property135 = calculateFromProperty(135000, parameters);
closeTo(property135.credit, 147272727.27272728);
closeTo(property135.payment, 882974.41);

const credit180 = calculateFromCredit(180000000, parameters);
closeTo(credit180.bankValue, 200000000);
closeTo(credit180.expenses, 15000000);
closeTo(credit180.propertyArs, 165000000);
closeTo(credit180.propertyUsd, 165000);
closeTo(credit180.payment, 1079190.95);

const roundTrip = calculateFromCredit(property120.credit, parameters);
closeTo(roundTrip.propertyUsd, 120000);

const comparison = compareProperties(120000, 135000, parameters);
closeTo(
  comparison.differences.credit,
  property135.credit - property120.credit
);

closeTo(monthlyPayment(1200, 0, 1), 100);
assert.throws(
  () =>
    normalizeParameters({
      ...parameters,
      financingPercent: 7,
    }),
  /mayor que la suma de gastos/
);

console.log("OK: 19 verificaciones financieras superadas.");
