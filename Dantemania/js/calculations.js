(function initializeCalculations(global) {
const EPSILON = 1e-12;

function requireFinitePositive(value, field) {
  if (!Number.isFinite(value) || value <= 0) {
    throw new RangeError(`${field} debe ser mayor que cero.`);
  }
}

function requireFiniteNonNegative(value, field) {
  if (!Number.isFinite(value) || value < 0) {
    throw new RangeError(`${field} no puede ser negativo.`);
  }
}

function normalizeParameters(input) {
  const exchangeRate = Number(input.exchangeRate);
  const financingRate = Number(input.financingPercent) / 100;
  const notaryRate = Number(input.notaryPercent) / 100;
  const realEstateRate = Number(input.realEstatePercent) / 100;
  const annualRate = Number(input.annualRatePercent) / 100;
  const years = Number(input.years);
  const expenseRate = notaryRate + realEstateRate;

  requireFinitePositive(exchangeRate, "La cotización");
  requireFinitePositive(financingRate, "La financiación");
  requireFiniteNonNegative(notaryRate, "El porcentaje de escribanía");
  requireFiniteNonNegative(realEstateRate, "El porcentaje de inmobiliaria");
  requireFiniteNonNegative(annualRate, "La tasa anual");
  requireFinitePositive(years, "El plazo");

  if (financingRate > 1) {
    throw new RangeError("La financiación no puede superar el 100%.");
  }
  if (expenseRate >= financingRate) {
    throw new RangeError(
      "La financiación debe ser mayor que la suma de gastos."
    );
  }

  return {
    exchangeRate,
    financingRate,
    notaryRate,
    realEstateRate,
    expenseRate,
    annualRate,
    years,
  };
}

function monthlyPayment(principal, annualRate, years) {
  requireFiniteNonNegative(principal, "El capital");
  requireFiniteNonNegative(annualRate, "La tasa anual");
  requireFinitePositive(years, "El plazo");

  const payments = Math.round(years * 12);
  if (payments < 1) {
    throw new RangeError("El plazo debe incluir al menos una cuota.");
  }
  if (Math.abs(annualRate) < EPSILON) {
    return principal / payments;
  }

  const monthlyRate = annualRate / 12;
  const factor = Math.pow(1 + monthlyRate, payments);
  return principal * ((monthlyRate * factor) / (factor - 1));
}

function calculateFromProperty(propertyUsd, rawParameters) {
  requireFinitePositive(Number(propertyUsd), "El valor de la propiedad");
  const parameters = normalizeParameters(rawParameters);
  const propertyArs = Number(propertyUsd) * parameters.exchangeRate;
  const bankValue =
    propertyArs / (parameters.financingRate - parameters.expenseRate);
  const expenses = bankValue * parameters.expenseRate;
  const credit = bankValue * parameters.financingRate;

  return {
    propertyUsd: Number(propertyUsd),
    propertyArs,
    bankValue,
    expenses,
    credit,
    payment: monthlyPayment(
      credit,
      parameters.annualRate,
      parameters.years
    ),
  };
}

function calculateFromCredit(credit, rawParameters) {
  requireFinitePositive(Number(credit), "El crédito");
  const parameters = normalizeParameters(rawParameters);
  const numericCredit = Number(credit);
  const bankValue = numericCredit / parameters.financingRate;
  const expenses = bankValue * parameters.expenseRate;
  const propertyArs = numericCredit - expenses;

  return {
    propertyUsd: propertyArs / parameters.exchangeRate,
    propertyArs,
    bankValue,
    expenses,
    credit: numericCredit,
    payment: monthlyPayment(
      numericCredit,
      parameters.annualRate,
      parameters.years
    ),
  };
}

function compareProperties(propertyAUsd, propertyBUsd, parameters) {
  const scenarioA = calculateFromProperty(propertyAUsd, parameters);
  const scenarioB = calculateFromProperty(propertyBUsd, parameters);

  return {
    scenarioA,
    scenarioB,
    differences: {
      credit: scenarioB.credit - scenarioA.credit,
      payment: scenarioB.payment - scenarioA.payment,
      expenses: scenarioB.expenses - scenarioA.expenses,
      propertyArs: scenarioB.propertyArs - scenarioA.propertyArs,
    },
  };
}

global.MiHogarCalculations = Object.freeze({
  normalizeParameters,
  monthlyPayment,
  calculateFromProperty,
  calculateFromCredit,
  compareProperties,
});
})(globalThis);
