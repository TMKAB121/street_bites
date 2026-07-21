<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your truck claim was approved</title>
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
                            <h1 style="margin:0 0 8px; font-size:20px; color:#1e2229;">Your claim was approved</h1>
                            <p style="margin:0 0 20px; font-size:15px; line-height:1.5; color:#636e72;">
                                <strong>{{ $truckName }}</strong> is now yours. You can update its
                                location, hours, menu, photos, and social links any time from your
                                profile — keeping it live is the best way to get found.
                            </p>

                            <div style="text-align:center; margin:0 0 8px;">
                                <a href="{{ $truckUrl }}" style="display:inline-block; background:#ffc700; color:#1e2229; font-size:15px; font-weight:bold; text-decoration:none; border-radius:10px; padding:14px 28px;">View your truck</a>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
