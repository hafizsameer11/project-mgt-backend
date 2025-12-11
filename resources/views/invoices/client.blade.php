<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $invoice['invoice_no'] }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #4F46E5;
        }
        .company-info h1 {
            color: #4F46E5;
            font-size: 24px;
            margin-bottom: 10px;
        }
        .invoice-info {
            text-align: right;
        }
        .invoice-info h2 {
            color: #4F46E5;
            font-size: 20px;
            margin-bottom: 10px;
        }
        .details {
            margin-bottom: 30px;
        }
        .details-row {
            display: flex;
            margin-bottom: 15px;
        }
        .details-label {
            width: 150px;
            font-weight: bold;
            color: #666;
        }
        .details-value {
            flex: 1;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        thead {
            background-color: #4F46E5;
            color: white;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            font-weight: bold;
        }
        tbody tr:hover {
            background-color: #f5f5f5;
        }
        .total-row {
            background-color: #f9fafb;
            font-weight: bold;
        }
        .summary {
            margin-top: 30px;
            padding: 20px;
            background-color: #f9fafb;
            border-radius: 5px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
        }
        .summary-total {
            font-size: 18px;
            font-weight: bold;
            color: #4F46E5;
            border-top: 2px solid #4F46E5;
            padding-top: 10px;
            margin-top: 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-paid {
            background-color: #10b981;
            color: white;
        }
        .status-pending {
            background-color: #f59e0b;
            color: white;
        }
        .status-partial {
            background-color: #3b82f6;
            color: white;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="company-info">
                <h1>ProjectHub</h1>
                <p>Project Management System</p>
                <p>Email: info@projecthub.com</p>
            </div>
            <div class="invoice-info">
                <h2>INVOICE</h2>
                <p><strong>Invoice #:</strong> {{ $invoice['invoice_no'] }}</p>
                <p><strong>Date:</strong> {{ $invoice['date'] }}</p>
                <p>
                    <span class="status-badge status-{{ strtolower($invoice['status']) }}">
                        {{ strtoupper($invoice['status']) }}
                    </span>
                </p>
            </div>
        </div>

        <div class="details">
            <div class="details-row">
                <div class="details-label">Bill To:</div>
                <div class="details-value">
                    <strong>{{ $invoice['client_name'] }}</strong><br>
                    {{ $invoice['client_email'] ?? '' }}<br>
                    {{ $invoice['client_phone'] ?? '' }}
                </div>
            </div>
            <div class="details-row">
                <div class="details-label">Project:</div>
                <div class="details-value">{{ $invoice['project_name'] }}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Total Invoice Amount</td>
                    <td>${{ number_format($invoice['total_amount'], 2) }}</td>
                </tr>
                <tr>
                    <td>Amount Paid</td>
                    <td>${{ number_format($invoice['amount_paid'], 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td>Remaining Balance</td>
                    <td>${{ number_format($invoice['remaining'], 2) }}</td>
                </tr>
            </tbody>
        </table>

        @if(isset($invoice['notes']) && $invoice['notes'])
        <div style="margin-top: 20px; padding: 15px; background-color: #f9fafb; border-radius: 5px;">
            <strong>Notes:</strong>
            <p>{{ $invoice['notes'] }}</p>
        </div>
        @endif

        <div class="summary">
            <div class="summary-row">
                <span>Invoice Total:</span>
                <span>${{ number_format($invoice['total_amount'], 2) }}</span>
            </div>
            <div class="summary-row">
                <span>Amount Paid:</span>
                <span>${{ number_format($invoice['amount_paid'], 2) }}</span>
            </div>
            <div class="summary-row summary-total">
                <span>Outstanding Balance:</span>
                <span style="color: #dc2626;">${{ number_format($invoice['remaining'], 2) }}</span>
            </div>
        </div>

        <div class="footer">
            <p>Thank you for your business!</p>
            <p>Please make payment within 30 days of invoice date.</p>
            <p>This is a computer-generated invoice and does not require a signature.</p>
        </div>
    </div>
</body>
</html>

