<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Smart Discussion — Desktop Token</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #0a0f1e; color: white; font-family: 'Nunito', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #111827; border-radius: 12px; padding: 40px 32px; max-width: 480px; width: 100%; text-align: center; }
        .icon { font-size: 48px; margin-bottom: 16px; }
        h1 { font-size: 20px; margin-bottom: 8px; color: #4ade80; }
        p { color: #9ca3af; font-size: 14px; margin-bottom: 24px; }
        .token-box { background: #1f2937; border: 1px solid #374151; border-radius: 8px; padding: 14px 16px; font-family: monospace; font-size: 12px; word-break: break-all; color: #e5e7eb; margin-bottom: 16px; text-align: left; }
        button { background: #16a34a; color: white; border: none; border-radius: 6px; padding: 10px 24px; font-size: 14px; font-weight: bold; cursor: pointer; }
        button:hover { background: #15803d; }
        .note { color: #6b7280; font-size: 12px; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✅</div>
        <h1>Google Sign In Successful</h1>
        <p>Copy the token below and paste it into the desktop app.</p>
        <div class="token-box" id="token">{{ $token }}</div>
        <button onclick="copyToken()">Copy Token</button>
        <p class="note">You can close this tab after copying.</p>
    </div>
    <script>
        function copyToken() {
            navigator.clipboard.writeText(document.getElementById('token').innerText);
            document.querySelector('button').textContent = 'Copied!';
        }
    </script>
</body>
</html>
