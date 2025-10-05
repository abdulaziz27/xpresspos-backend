<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ ucfirst(str_replace('_', ' ', $reportType)) }} Report - {{ $storeName }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #6c757d;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #6c757d;
            font-size: 24px;
        }
        .header h2 {
            margin: 5px 0;
            color: #666;
            font-size: 16px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            font-size: 10px;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #495057;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 10px;
            color: #666;
            text-align: center;
        }
        .data-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $storeName }}</h1>
        <h2>{{ ucfirst(str_replace('_', ' ', $reportType)) }} Report</h2>
        <p>Generated on {{ $generatedAt->format('M j, Y \a\t g:i A') }}</p>
    </div>

    <div class="section">
        <div class="section-title">📊 Report Data</div>

        <div class="data-section">
            <p><strong>Report Type:</strong> {{ ucfirst(str_replace('_', ' ', $reportType)) }}</p>
            <p><strong>Generated At:</strong> {{ $generatedAt->format('M j, Y \a\t g:i A') }}</p>
            <p><strong>Store:</strong> {{ $storeName }}</p>
        </div>

        @if(is_array($data))
            @foreach($data as $key => $value)
                @if(is_array($value) && count($value) > 0)
                    <div class="section">
                        <div class="section-title">{{ ucfirst(str_replace('_', ' ', $key)) }}</div>

                        @if(isset($value[0]) && is_array($value[0]))
                            <!-- Table format for array of objects -->
                            <table>
                                <thead>
                                    <tr>
                                        @foreach(array_keys($value[0]) as $header)
                                        <th>{{ ucfirst(str_replace('_', ' ', $header)) }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($value as $row)
                                    <tr>
                                        @foreach($row as $cell)
                                        <td>{{ is_numeric($cell) ? number_format($cell, 2) : $cell }}</td>
                                        @endforeach
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <!-- Simple key-value pairs -->
                            <table>
                                <thead>
                                    <tr>
                                        <th>Key</th>
                                        <th>Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($value as $subKey => $subValue)
                                    <tr>
                                        <td>{{ ucfirst(str_replace('_', ' ', $subKey)) }}</td>
                                        <td>{{ is_array($subValue) ? json_encode($subValue) : (is_numeric($subValue) ? number_format($subValue, 2) : $subValue) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @else
                    <div class="data-section">
                        <p><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong>
                            {{ is_array($value) ? json_encode($value) : (is_numeric($value) ? number_format($value, 2) : $value) }}
                        </p>
                    </div>
                @endif
            @endforeach
        @else
            <div class="data-section">
                <p>{{ $data }}</p>
            </div>
        @endif
    </div>

    <div class="footer">
        <p>This {{ ucfirst(str_replace('_', ' ', $reportType)) }} report was generated automatically by POS Xpress on {{ $generatedAt->format('M j, Y \a\t g:i A') }}</p>
        <p>© {{ date('Y') }} POS Xpress. All rights reserved.</p>
    </div>
</body>
</html>
