<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 16px;
            margin: 0;
            padding: 0;
        }

        .header p {
            margin: 5px 0;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
        }

        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .footer {
            margin-top: 20px;
            font-size: 10px;
            text-align: center;
        }

        .target-info {
            margin: 10px 0;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <p>Diekspor oleh: {{ $generated_by }} ({{ $user_role }})</p>
        <p>Generated: {{ $generated_at }}</p>
    </div>

    <div class="target-info">
        Sasaran: {{ $statistics['target'] }}
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($statistics['monthly_data'] as $month => $data)
                <tr>
                    <td>{{ \Carbon\Carbon::create()->month($month)->locale('id')->monthName }}</td>
                    <td>{{ $data['male'] }}</td>
                    <td>{{ $data['female'] }}</td>
                    <td>{{ $data['non_standard'] }}</td>
                    <td>{{ $data['standard'] }}</td>
                    <td>{{ number_format($data['percentage'], 2) }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Dokumen ini digenerate secara otomatis oleh sistem</p>
    </div>
</body>

</html>
