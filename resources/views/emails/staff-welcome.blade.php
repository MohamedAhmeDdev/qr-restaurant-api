<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $restaurant->name }}</title>
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
        .credentials-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .cred-item {
            font-size: 14px;
            color: #334155;
            margin-bottom: 8px;
        }
        .cred-item:last-child {
            margin-bottom: 0;
        }
        .cred-label {
            font-weight: 600;
            color: #0f172a;
        }
        .btn-wrapper {
            text-align: center;
            margin: 32px 0;
        }
        .btn {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff !important;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            padding: 12px 32px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }
        .footer {
            background-color: #f8fafc;
            padding: 24px 40px;
            border-top: 1px solid #e2e8f0;
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
        }
        .footer a {
            color: #2563eb;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1>{{ $restaurant->name }}</h1>
            </div>

            <div class="content">
                <p class="title">Hello {{ $user->name }},</p>
                <p class="text">
                    You have been added as a staff member at <strong>{{ $restaurant->name }}</strong> restaurant. Use the credentials below to log in to your account:
                </p>

                <div class="credentials-box">
                    @if(!empty($role))
                    <div class="cred-item">
                        <span class="cred-label">Role:</span> {{ ucfirst($role->name) }}
                    </div>
                    @endif
                    <div class="cred-item">
                        <span class="cred-label">Email:</span> {{ $user->email }}
                    </div>
                    <div class="cred-item">
                        <span class="cred-label">Temporary Password:</span> <code>{{ $plainPassword }}</code>
                    </div>
                </div>

                <div class="btn-wrapper">
                    <a href="{{ $loginUrl }}" class="btn" target="_blank">Log In to Staff Portal</a>
                </div>

                <p class="text" style="font-size: 13px; color: #6b7280; margin-bottom: 0;">
                    🔒 For security reasons, please change your password immediately after logging in.
                </p>
            </div>

            <div class="footer">
                <p style="margin-top: 0; margin-bottom: 8px;">
                    Having trouble with the button? Copy and paste this link into your browser:
                </p>
                <a href="{{ $loginUrl }}" target="_blank">{{ $loginUrl }}</a>
            </div>
        </div>
    </div>
</body>
</html>