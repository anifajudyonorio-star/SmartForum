<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; background: #f0fdf4; margin: 0; padding: 2rem; }
        .card { background: #fff; border-radius: 8px; max-width: 420px; margin: 0 auto; padding: 2rem; border: 1px solid #d1fae5; }
        .brand { color: #166534; font-weight: 700; font-size: 1.1rem; margin-bottom: 1rem; }
        .code { font-size: 2.5rem; font-weight: 700; letter-spacing: 0.5rem; color: #166534; text-align: center; background: #f0fdf4; border-radius: 8px; padding: 1rem; margin: 1.5rem 0; }
        .note { font-size: 0.85rem; color: #6b7280; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">SmartForum</div>
        <p>Hi {{ $name }},</p>
        <p>Your email verification code is:</p>
        <div class="code">{{ $code }}</div>
        <p class="note">This code expires in 10 minutes. If you didn't create an account, you can ignore this email.</p>
    </div>
</body>
</html>
