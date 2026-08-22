<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join {{ config('app.name', 'QRRestaurant') }}</title>
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
            <!-- Header / Branding -->
            <div class="header">
                <h1>{{ config('app.name', 'QRRestaurant') }}</h1>
            </div>

            <!-- Main Body -->
            <div class="content">
                <p class="title">You're Invited!</p>
                <p class="text">
                    You have been invited to set up your organization and restaurant on the {{ config('app.name', 'QRRestaurant') }} platform. Click the button below to complete your onboarding process.
                </p>

                <div class="btn-wrapper">
                    <a href="{{ $inviteUrl }}" class="btn" target="_blank">Complete Onboarding</a>
                </div>

                <p class="text" style="font-size: 13px; color: #6b7280; margin-bottom: 0;">
                    ⏱️ This invitation link will automatically expire in <strong>1 hour</strong> (at {{ $formattedExpiresAt }}). If you were not expecting this invitation, you can safely ignore this email.
                </p>
            </div>

            <!-- Footer / Raw URL Fallback -->
            <div class="footer">
                <p style="margin-top: 0; margin-bottom: 8px;">
                    Having trouble with the button? Copy and paste this link into your browser:
                </p>
                <a href="{{ $inviteUrl }}" target="_blank">{{ $inviteUrl }}</a>
            </div>
        </div>
    </div>
</body>
</html>