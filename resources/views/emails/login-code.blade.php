<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your login code</title>
</head>
{{-- Email clients ignore external CSS, so styles are inlined with brand hex values. --}}
<body style="margin:0; background:#fafafa; font-family:Arial,Helvetica,sans-serif; color:#2d3436;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fafafa; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background:#ffffff; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="background:#1e2229; padding:20px 24px;">
                            <span style="color:#ffc700; font-size:20px; font-weight:bold; letter-spacing:0.5px;">Street Bites</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 24px;">
                            <h1 style="margin:0 0 8px; font-size:20px; color:#1e2229;">Finish signing in</h1>
                            <p style="margin:0 0 24px; font-size:15px; line-height:1.5; color:#636e72;">
                                Enter this code to finish signing in to your account. It expires in 10 minutes.
                            </p>

                            <div style="text-align:center; margin:0 0 28px;">
                                <span style="display:inline-block; font-size:34px; font-weight:bold; letter-spacing:8px; color:#1e2229; background:#fafafa; border-radius:10px; padding:16px 24px;">{{ $code }}</span>
                            </div>

                            <p style="margin:0; font-size:13px; line-height:1.5; color:#636e72;">
                                If you didn’t try to sign in, someone may have your password — change it as soon as you can.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
