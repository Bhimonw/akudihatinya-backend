<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 18px;
            margin: 0;
            font-weight: bold;
        }
        .info {
            margin-bottom: 20px;
        }
        .summary-card {
            border: 1px solid #ddd;
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 5px;
        }
        .summary-card h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 16px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .stat-item {
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 3px;
        }
        .stat-label {
            font-weight: bold;
            color: #666;
        }
        .stat-value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        .achievement {
            color: #28a745;
        }
        .footer {
            margin-top: 30px;
            font-size: 10px;
            text-align: center;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <p>Tahun {{ $year }}</p>
    </div>

    <div class="info">
        <p><strong>Tanggal Cetak:</strong> {{ $generated_at }}</p>
    </div>

    @foreach($disease_types as $type)
        @if(isset($$type))
            <div class="summary-card">
                <h3>{{ $$type['label'] }}</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-label">Total Target</div>
                        <div class="stat-value">{{ number_format($$type['total_target']) }}</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Total Pasien</div>
                        <div class="stat-value">{{ number_format($$type['total_patients']) }}</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Pasien Sesuai Standar</div>
                        <div class="stat-value">{{ number_format($$type['total_standard']) }}</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Capaian</div>
                        <div class="stat-value achievement">{{ $$type['total_achievement'] }}%</div>
                    </div>
                </div>
                <p style="margin-top: 10px;"><strong>Jumlah Puskesmas:</strong> {{ $$type['puskesmas_count'] }}</p>
            </div>
        @endif
    @endforeach

    <div class="footer">
        <p>Laporan ini dibuat secara otomatis oleh sistem</p>
    </div>
</body>
</html>