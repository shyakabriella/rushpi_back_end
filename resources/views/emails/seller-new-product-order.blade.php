<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>New RushPi order</title>
</head>

<body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#0f172a;">
    <div style="max-width:680px;margin:0 auto;padding:28px 14px;">
        <div style="overflow:hidden;border-radius:22px;background:#ffffff;box-shadow:0 14px 40px rgba(15,23,42,.10);">
            <div style="background:#0754d8;padding:24px 28px;">
                <img
                    src="{{ $logoUrl }}"
                    alt="RushPi"
                    style="display:block;max-width:170px;max-height:55px;"
                >
            </div>

            <div style="padding:30px 28px;">
                <p style="margin:0 0 8px;color:#64748b;font-size:14px;">
                    Hello {{ $recipientName }},
                </p>

                <h1 style="margin:0;color:#0f172a;font-size:25px;line-height:1.3;">
                    Your shop received a new order
                </h1>

                <p style="margin:10px 0 0;color:#475569;line-height:1.7;">
                    A customer ordered products from
                    <strong>{{ $sellerName }}</strong>.
                    Review the order in your RushPi seller dashboard.
                </p>

                <div style="margin:24px 0;border-radius:16px;background:#eff6ff;padding:18px;">
                    <table
                        role="presentation"
                        style="width:100%;border-collapse:collapse;font-size:14px;"
                    >
                        <tr>
                            <td style="padding:5px 0;color:#64748b;">
                                Order number
                            </td>
                            <td style="padding:5px 0;text-align:right;font-weight:700;">
                                {{ $order['order_number'] }}
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:5px 0;color:#64748b;">
                                Status
                            </td>
                            <td style="padding:5px 0;text-align:right;font-weight:700;color:#d97706;">
                                Pending approval
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:5px 0;color:#64748b;">
                                Ordered
                            </td>
                            <td style="padding:5px 0;text-align:right;">
                                {{ $order['placed_at'] }}
                            </td>
                        </tr>
                    </table>
                </div>

                <h2 style="margin:26px 0 12px;font-size:18px;">
                    Products from your shop
                </h2>

                <div style="border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
                    @foreach ($items as $item)
                        <div style="padding:16px;border-bottom:1px solid #e2e8f0;">
                            <div style="font-weight:700;color:#0f172a;">
                                {{ $item['product_name'] }}
                            </div>

                            @if (! empty($item['variant_name']))
                                <div style="margin-top:4px;color:#64748b;font-size:13px;">
                                    {{ $item['variant_name'] }}
                                </div>
                            @endif

                            <div style="margin-top:8px;color:#475569;font-size:14px;">
                                {{ $item['quantity'] }}
                                ×
                                {{ number_format((float) $item['unit_price'], 0) }}
                                {{ $order['currency'] }}

                                <strong style="float:right;color:#0f172a;">
                                    {{ number_format((float) $item['line_total'], 0) }}
                                    {{ $order['currency'] }}
                                </strong>
                            </div>
                        </div>
                    @endforeach

                    <div style="padding:17px;background:#f8fafc;text-align:right;font-size:16px;">
                        Shop subtotal:
                        <strong>
                            {{ number_format((float) $order['seller_subtotal'], 0) }}
                            {{ $order['currency'] }}
                        </strong>
                    </div>
                </div>

                <h2 style="margin:26px 0 12px;font-size:18px;">
                    Customer information
                </h2>

                <div style="border-radius:16px;background:#f8fafc;padding:18px;color:#475569;font-size:14px;line-height:1.8;">
                    <div>
                        <strong>Name:</strong>
                        {{ $order['customer_name'] }}
                    </div>

                    <div>
                        <strong>Email:</strong>
                        {{ $order['customer_email'] }}
                    </div>

                    <div>
                        <strong>Phone:</strong>
                        {{ $order['customer_phone'] }}
                    </div>

                    <div>
                        <strong>Delivery area:</strong>
                        {{ $order['delivery_area'] }}
                    </div>
                </div>

                <div style="margin-top:18px;border-left:4px solid #f59e0b;border-radius:10px;background:#fffbeb;padding:14px 16px;color:#92400e;font-size:13px;line-height:1.6;">
                    For customer privacy, the email address and phone
                    number are incomplete. Complete contact details will
                    become available after an administrator approves the
                    order.
                </div>

                <div style="margin-top:26px;text-align:center;">
                    <a
                        href="{{ $ordersUrl }}"
                        style="display:inline-block;border-radius:999px;background:#0754d8;padding:14px 28px;color:#ffffff;text-decoration:none;font-weight:700;"
                    >
                        View seller orders
                    </a>
                </div>

                <p style="margin:28px 0 0;text-align:center;color:#94a3b8;font-size:12px;">
                    This notification was sent by RushPi.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
