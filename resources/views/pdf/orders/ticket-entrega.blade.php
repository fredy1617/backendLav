<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket de entrega</title>
    <style>
        @page {
            margin: 8px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #222;
        }

        .center {
            text-align: center;
        }

        .title {
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .subtitle {
            font-size: 10px;
            font-weight: bold;
        }

        .line {
            border-top: 1px dashed #555;
            margin: 8px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td, th {
            padding: 3px 1px;
            vertical-align: top;
        }

        .right {
            text-align: right;
        }

        .total {
            font-size: 11px;
            font-weight: bold;
        }

        .paid {
            font-weight: bold;
            text-align: center;
            margin-top: 8px;
            border: 1px solid #333;
            padding: 6px;
        }

        .footer {
            text-align: center;
            margin-top: 14px;
            font-size: 8px;
        }
    </style>
</head>
<body>

    <div class="center">
        <div class="title">LAVANDERÍA</div>
        <div class="subtitle">TICKET DE ENTREGA</div>
        <div>Orden #{{ $order->id }}</div>
    </div>

    <div class="line"></div>

    <strong>Cliente:</strong>
    {{ $order->customer->name ?? 'Público general' }}<br>

    <strong>Teléfono:</strong>
    {{ $order->customer->phone ?? 'No registrado' }}<br>

    <strong>Fecha de recepción:</strong>
    {{ $order->received_at
        ? \Carbon\Carbon::parse($order->received_at)->format('d/m/Y')
        : '-' }}<br>

    <strong>Fecha de entrega:</strong>
    {{ now()->format('d/m/Y H:i') }}<br>

    <strong>Estado:</strong> ENTREGADA

    <div class="line"></div>

    <strong>PRENDAS Y SERVICIOS</strong>

    <table>
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="right">Cant.</th>
                <th class="right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->details as $detail)
                <tr>
                    <td>{{ $detail->service_name ?? 'Servicio' }}</td>
                    <td class="right">
                        {{ rtrim(rtrim(number_format((float) $detail->quantity, 2, '.', ''), '0'), '.') }}
                    </td>
                    <td class="right">
                        ${{ number_format((float) $detail->subtotal, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="line"></div>

    <table>
        <tr>
            <td>Total de la orden</td>
            <td class="right total">
                ${{ number_format($total, 2) }}
            </td>
        </tr>
    </table>

    <div class="line"></div>

    <strong>RESUMEN DE PAGOS</strong>

    <table>
        <thead>
            <tr>
                <th>Fecha / método</th>
                <th class="right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($order->payments->sortBy('created_at') as $payment)
                <tr>
                    <td>
                        {{ $payment->created_at
                            ? $payment->created_at->format('d/m/Y H:i')
                            : '-' }}<br>
                        {{ $payment->payment_method ?? 'No especificado' }}
                    </td>
                    <td class="right">
                        ${{ number_format((float) $payment->amount, 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="center">
                        Sin pagos registrados
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="line"></div>

    <table>
        <tr>
            <td>Total pagado</td>
            <td class="right total">
                ${{ number_format($totalPagado, 2) }}
            </td>
        </tr>
        <tr>
            <td>Saldo pendiente</td>
            <td class="right">
                ${{ number_format($saldo, 2) }}
            </td>
        </tr>
    </table>

    @if ($saldo <= 0.009)
        <div class="paid">
            ORDEN LIQUIDADA<br>
            PAGO COMPLETO
        </div>
    @endif

    <div class="footer">
        Gracias por su preferencia.<br>
        Conserve este ticket como comprobante de entrega.
    </div>

</body>
</html>
