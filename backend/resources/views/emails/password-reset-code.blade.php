<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 480px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #0a3d1f, #1e8449); padding: 32px 24px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 20px; }
        .header p { color: #a8e6cf; margin: 4px 0 0; font-size: 13px; }
        .body { padding: 32px 24px; text-align: center; }
        .body p { color: #555; font-size: 14px; margin: 0 0 20px; }
        .code { display: inline-block; font-size: 40px; font-weight: bold; letter-spacing: 10px; color: #145a32; background: #f0faf4; border: 2px dashed #1e8449; border-radius: 10px; padding: 16px 32px; margin: 8px 0 24px; }
        .note { font-size: 12px; color: #999; margin-top: 16px; }
        .footer { background: #f9f9f9; padding: 16px 24px; text-align: center; font-size: 11px; color: #aaa; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Science Laboratory Borrowing System</h1>
            <p>Cagayan de Oro College — PHINMA Education Network</p>
        </div>
        <div class="body">
            <p>You requested to reset your password. Use the code below to proceed:</p>
            <div class="code">{{ $code }}</div>
            <p>This code is valid for <strong>10 minutes</strong>. Do not share it with anyone.</p>
            <p class="note">If you did not request a password reset, you can safely ignore this email.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Cagayan de Oro College — PHINMA Education Network
        </div>
    </div>
</body>
</html>
