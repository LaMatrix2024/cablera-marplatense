import assert from "node:assert/strict";
import { access, readFile } from "node:fs/promises";
import { resolve } from "node:path";

const htmlRoot = resolve(import.meta.dirname, "..");
const manifest = JSON.parse(
  await readFile(resolve(htmlRoot, "manifest.webmanifest"), "utf8")
);
const serviceWorker = await readFile(resolve(htmlRoot, "sw.js"), "utf8");
const index = await readFile(resolve(htmlRoot, "index.html"), "utf8");

assert.equal(manifest.display, "standalone");
assert.equal(manifest.start_url, "./");
assert.equal(manifest.scope, "./");
assert.ok(index.includes('rel="manifest" href="manifest.webmanifest"'));
assert.ok(index.includes('rel="apple-touch-icon"'));
assert.ok(index.includes('src="js/pwa.js"'));

for (const icon of manifest.icons) {
  await access(resolve(htmlRoot, icon.src));
  assert.ok(serviceWorker.includes(`./${icon.src}`));
}

for (const requiredFile of [
  "index.html",
  "css/styles.css",
  "js/config.js",
  "js/calculations.js",
  "js/app.js",
  "js/pwa.js",
  "assets/apple-touch-icon.png",
]) {
  await access(resolve(htmlRoot, requiredFile));
  assert.ok(
    serviceWorker.includes(`./${requiredFile}`),
    `${requiredFile} debe formar parte del caché inicial.`
  );
}

console.log("OK: manifest, iconos y caché PWA validados.");
