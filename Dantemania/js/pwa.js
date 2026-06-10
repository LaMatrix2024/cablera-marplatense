(function initializePwa(global) {
  const installButton = document.querySelector("#install-app");
  const installHelp = document.querySelector("#install-help");
  let installPrompt = null;

  if ("serviceWorker" in navigator && location.protocol !== "file:") {
    global.addEventListener("load", () => {
      navigator.serviceWorker.register("./sw.js").catch(() => {
        installHelp.textContent =
          "No se pudo activar el modo sin conexión en este navegador.";
      });
    });
  }

  global.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();
    installPrompt = event;
    installButton.hidden = false;
    installHelp.textContent =
      "Podés instalarla como aplicación y usarla sin conexión.";
  });

  installButton.addEventListener("click", async () => {
    if (!installPrompt) return;
    installPrompt.prompt();
    await installPrompt.userChoice;
    installPrompt = null;
    installButton.hidden = true;
  });

  global.addEventListener("appinstalled", () => {
    installButton.hidden = true;
    installHelp.textContent = "Mi Hogar ya está instalada en este dispositivo.";
  });
})(globalThis);
