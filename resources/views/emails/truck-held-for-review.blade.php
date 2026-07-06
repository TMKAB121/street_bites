<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>A truck is awaiting review</title>
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
                            <h1 style="margin:0 0 8px; font-size:20px; color:#1e2229;">A truck is awaiting review</h1>
                            <p style="margin:0 0 20px; font-size:15px; line-height:1.5; color:#636e72;">
                                Automatic screening flagged a food truck, so it’s held out of
                                discovery until an admin approves or removes it.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px; font-size:14px; color:#2d3436;">
                                <tr>
                                    <td style="padding:6px 0; color:#636e72; width:90px;">Truck</td>
                                    <td style="padding:6px 0; font-weight:bold;">{{ $truckName }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0; color:#636e72;">Owner</td>
                                    <td style="padding:6px 0;">{{ $ownerEmail }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0; color:#636e72;">Reason</td>
                                    <td style="padding:6px 0;">{{ $reason }}</td>
                                </tr>
                            </table>

                            <div style="text-align:center; margin:0 0 8px;">
                                <a href="{{ $reviewUrl }}" style="display:inline-block; background:#ffc700; color:#1e2229; font-size:15px; font-weight:bold; text-decoration:none; border-radius:10px; padding:14px 28px;">Open the moderation queue</a>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
