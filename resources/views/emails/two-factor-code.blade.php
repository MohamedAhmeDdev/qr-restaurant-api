<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Verification Code</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f5f7;
            margin: 0;
            padding: 0;
            color: #1f2937;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f4f5f7;
            padding: 40px 0;
        }
        .container {
            max-width: 520px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
        .header {
            background-color: #0f172a;
            padding: 32px 40px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            font-size: 20px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.5px;
        }
        .content {
            padding: 40px;
        }
        .title {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin-top: 0;
            margin-bottom: 16px;
        }
        .text {
            font-size: 15px;
            line-height: 1.6;
            color: #4b5563;
            margin-top: 0;
            margin-bottom: 24px;
        }
        .code-box {
            background-color: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin: 28px 0;
        }
        .code-number {
            font-family: 'Courier New', Courier, monospace;
            font-size: 34px;
            font-weight: 800;
            letter-spacing: 8px;
            color: #2563eb;
            margin: 0;
        }
        .footer {
            background-color: #f8fafc;
            padding: 24px 40px;
            border-top: 1px solid #e2e8f0;
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <!-- Header / Branding -->
            <div class="header">
                <h1>{{ config('app.name', 'Restaurant Portal') }}</h1>
            </div>

            <!-- Main Body -->
            <div class="content">
                <p class="title">Security Verification Code</p>
                <p class="text">
                    Use the verification code below to complete your login attempt.
                </p>

                <!-- Highlighted Code Box -->
                <div class="code-box">
                    <p class="code-number">{{ $code }}</p>
                </div>

                <p class="text" style="font-size: 13px; color: #6b7280; margin-bottom: 0;">
                    ⏱️ This verification code is valid for <strong>10 minutes</strong>. Never share this code with anyone.
                </p>
            </div>

            <!-- Footer Warning -->
            <div class="footer">
                <p style="margin: 0;">
                    🔒 If you did not attempt to log in to your account, please ignore this email or change your password immediately.
                </p>
            </div>
        </div>
    </div>
</body>
</html>