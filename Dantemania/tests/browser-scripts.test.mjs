import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import vm from "node:vm";

const listeners = new Map();
const nodes = new Map();

function createNode() {
  return {
    value: "",
    hidden: false,
    textContent: "",
    innerHTML: "",
    dataset: {},
    classList: { toggle() {} },
    addEventListener(type, callback) {
      listeners.set(`${this.id}:${type}`, callback);
    },
    setAttribute() {},
    scrollIntoView() {},
  };
}

const requiredIds = [
  "calculator-form",
  "form-error",
  "results-empty",
  "results-content",
  "exchange-rate",
  "financing-percent",
  "notary-percent",
  "real-estate-percent",
  "annual-rate-percent",
  "years",
  "property-usd",
  "credit-ars",
  "property-a-usd",
  "property-b-usd",
  "reset-settings",
  "parametros",
];

for (const id of requiredIds) {
  const node = createNode();
  node.id = id;
  nodes.set(`#${id}`, node);
}

const settingsShortcut = createNode();
settingsShortcut.id = "settings-shortcut";
nodes.set("[data-scroll-settings]", settingsShortcut);

const context = vm.createContext({
  console,
  Intl,
  Number,
  Math,
  Object,
  RangeError,
  globalThis: null,
  document: {
    querySelector(selector) {
      return nodes.get(selector) ?? null;
    },
    querySelectorAll(selector) {
      if (selector === ".scenario-tab" || selector === ".scenario-panel") {
        return [];
      }
      return [];
    },
  },
});
context.globalThis = context;

for (const file of ["config.js", "calculations.js", "app.js"]) {
  const source = await readFile(new URL(`../js/${file}`, import.meta.url), "utf8");
  vm.runInContext(source, context, { filename: file });
}

assert.equal(nodes.get("#financing-percent").value, 90);
assert.equal(nodes.get("#notary-percent").value, 4.5);
assert.equal(nodes.get("#real-estate-percent").value, 3);
assert.equal(nodes.get("#annual-rate-percent").value, 6);
assert.equal(nodes.get("#years").value, 30);
assert.ok(
  listeners.has("calculator-form:submit"),
  "El formulario debe registrar su controlador submit."
);

console.log("OK: scripts cargados juntos y formulario inicializado.");
