<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env_loader.php';

lcm_load_dotenv_to_process();

$apiKey = (string)lcm_config_value('FIREBASE_WEB_API_KEY', '');
$projectId = (string)lcm_config_value('FIREBASE_PROJECT_ID', 'mis-appspwa-claudio');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prueba Firebase LCM</title>
    <style>
        body { font-family: Arial, sans-serif; background: #111; color: #f4f1ea; margin: 0; padding: 32px; }
        main { max-width: 560px; margin: 0 auto; }
        label { display: block; margin: 16px 0 6px; }
        input, button, textarea { width: 100%; box-sizing: border-box; font: inherit; }
        input { padding: 12px; border-radius: 6px; border: 1px solid #555; background: #202020; color: #f4f1ea; }
        button { margin-top: 18px; padding: 12px; border: 0; border-radius: 6px; background: #ff6b35; color: #111; font-weight: 700; cursor: pointer; }
        button.secondary { background: #333; color: #f4f1ea; }
        pre { white-space: pre-wrap; background: #202020; border: 1px solid #444; border-radius: 6px; padding: 12px; min-height: 120px; }
        .error { color: #ff9f8a; }
    </style>
</head>
<body>
<main>
    <h1>Prueba Firebase LCM</h1>
    <p>La contraseña se usa solo en el navegador para Firebase Email/Password. No se envia a PHP ni se guarda.</p>

    <label for="email">Correo</label>
    <input id="email" type="email" autocomplete="username" value="aguileraclaudiomdq@gmail.com">

    <label for="password">Contraseña</label>
    <input id="password" type="password" autocomplete="current-password">

    <button id="login" type="button">Ingresar</button>
    <button id="again" class="secondary" type="button" disabled>Consultar /api/v1/auth/me otra vez</button>

    <h2>Resultado</h2>
    <pre id="output">Esperando login...</pre>
</main>

<script>
const firebaseApiKey = <?php echo json_encode($apiKey, JSON_UNESCAPED_SLASHES); ?>;
const firebaseProjectId = <?php echo json_encode($projectId, JSON_UNESCAPED_SLASHES); ?>;
let idToken = "";

const output = document.getElementById("output");
const write = (value, isError = false) => {
  output.className = isError ? "error" : "";
  output.textContent = typeof value === "string" ? value : JSON.stringify(value, null, 2);
};

const callMe = async () => {
  const response = await fetch("/api/v1/auth/me", {
    headers: { Authorization: `Bearer ${idToken}`, Accept: "application/json" }
  });
  const body = await response.json().catch(() => null);
  return { status: response.status, body };
};

document.getElementById("login").addEventListener("click", async () => {
  try {
    if (!firebaseApiKey) {
      write("Falta FIREBASE_WEB_API_KEY en plantel.env.", true);
      return;
    }

    const email = document.getElementById("email").value.trim().toLowerCase();
    const password = document.getElementById("password").value;
    write("Autenticando contra Firebase...");

    const firebaseResponse = await fetch(`https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=${encodeURIComponent(firebaseApiKey)}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email, password, returnSecureToken: true })
    });
    const firebaseBody = await firebaseResponse.json();
    if (!firebaseResponse.ok) {
      write({ firebase: "ERROR", status: firebaseResponse.status, code: firebaseBody?.error?.message || "firebase_login_failed" }, true);
      return;
    }

    idToken = firebaseBody.idToken;
    document.getElementById("password").value = "";
    document.getElementById("again").disabled = false;

    const me = await callMe();
    write({
      firebase: "OK",
      projectId: firebaseProjectId,
      localIdHash: await crypto.subtle.digest("SHA-256", new TextEncoder().encode(firebaseBody.localId)).then(buf => Array.from(new Uint8Array(buf)).map(b => b.toString(16).padStart(2, "0")).join("").slice(0, 16)),
      authMe: me
    }, me.status >= 400);
  } catch (error) {
    write(String(error), true);
  }
});

document.getElementById("again").addEventListener("click", async () => {
  try {
    write(await callMe());
  } catch (error) {
    write(String(error), true);
  }
});
</script>
</body>
</html>

