<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket de recepción</title>

    <style>
        @page {
            margin: 7px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #222;
        }

        .center {
            text-align: center;
        }

        h2 {
            font-size: 16px;
            margin: 0 0 5px;
        }

        .line {
            border-top: 1px dashed #555;
            margin: 8px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 4px 0;
            vertical-align: top;
        }

        .right {
            text-align: right;
        }

        .total {
            font-size: 13px;
            font-weight: bold;
        }

        .small {
            font-size: 9px;
        }
    </style>
</head>
<body>
    <div class="center">
        <h2>LAVANDERÍA</h2>
        <strong>COMPROBANTE DE RECEPCIÓN</strong>
        <div>Conserve este ticket para recoger sus prendas</div>
    </div>

    <div class="line"></div>

    <strong>Folio:</strong> {{ $order->id }}<br>
    <strong>Cliente:</strong> {{ $order->customer->name ?? 'Público general' }}<br>
    <strong>Teléfono:</strong> {{ $order->customer->phone ?? 'N/A' }}<br>
    <strong>Recepción:</strong>
    {{ \Carbon\Carbon::parse($order->received_at)->format('d/m/Y') }}<br>

    @if($order->estimated_delivery_at)
        <strong>Entrega estimada:</strong>
        {{ \Carbon\Carbon::parse($order->estimated_delivery_at)->format('d/m/Y') }}<br>
    @endif

    <div class="line"></div>

    <table>
        <thead>
            <tr>
                <td><strong>Servicio</strong></td>
                <td class="right"><strong>Importe</strong></td>
            </tr>
        </thead>
        <tbody>
            @foreach($order->details as $detail)
                <tr>
                    <td>
                        {{ $detail->service_name }}<br>
                        <span class="small">
                            {{ rtrim(rtrim(number_format((float)$detail->quantity, 3, '.', ''), '0'), '.') }}
                            × ${{ number_format((float)$detail->unit_price, 2) }}
                        </span>
                    </td>
                    <td class="right">
                        ${{ number_format((float)$detail->subtotal, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="line"></div>

    <table>
        <tr class="total">
            <td>TOTAL</td>
            <td class="right">
                ${{ number_format((float)$order->total, 2) }}
            </td>
        </tr>
    </table>

    @if($order->observations)
        <div class="line"></div>
        <strong>Observaciones:</strong><br>
        {{ $order->observations }}
    @endif

    <div class="line"></div>

    <div class="center small">
        Este documento acredita la recepción de las prendas,
        no el pago de la orden.

        <br><br>
        Gracias por su preferencia.
    </div>
</body>
</html>