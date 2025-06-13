<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $period }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 20px;
            line-height: 1.4;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            width: 100%;
        }

        .header h1 {
            font-size: 16px;
            margin: 0;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 10px;
        }

        .header h2 {
            font-size: 14px;
            margin: 5px 0;
            font-weight: normal;
            text-align: center;
        }

        .info-section {
            margin-bottom: 20px;
            font-size: 10px;
        }

        .info-section table {
            width: 100%;
            border: none;
        }

        .info-section td {
            border: none;
            padding: 2px 0;
            vertical-align: top;
        }

        .info-section .label {
            width: 150px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #000;
            padding: 8px 4px;
            text-align: center;
            vertical-align: middle;
            font-size: 10px;
            word-wrap: break-word;
        }

        th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-transform: uppercase;
        }

        .puskesmas-name {
            text-align: left;
            font-weight: normal;
            width: 200px;
            padding-left: 8px;
        }

        .number-col { 
            width: 40px; 
            text-align: center;
        }
        
        .target-col { 
            width: 80px; 
        }
        
        .data-col { 
            width: 80px; 
        }
        
        .percentage-col { 
            width: 80px; 
        }

        .total-row {
            background-color: #e0e0e0;
            font-weight: bold;
        }

        .footer {
            margin-top: 30px;
            font-size: 9px;
            text-align: right;
        }

        .summary-box {
            border: 1px solid #000;
            padding: 15px;
            margin: 20px 0;
            background-color: #f9f9f9;
        }

        .summary-box h3 {
            margin: 0 0 10px 0;
            font-size: 12px;
            text-align: center;
        }

        .summary-stats {
            display: flex;
            justify-content: space-around;
            text-align: center;
        }

        .summary-item {
            flex: 1;
        }

        .summary-item .value {
            font-size: 14px;
            font-weight: bold;
            color: #2c5aa0;
        }

        .summary-item .label {
            font-size: 9px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <h2>Periode: {{ $period }}</h2>
    </div>

    <div class="info-section">
        <table>
            <tr>
                <td class="label">Jenis Penyakit:</td>
                <td>{{ $disease_label }}</td>
            </tr>
            <tr>
                <td class="label">Tahun:</td>
                <td>{{ $year }}</td>
            </tr>
            @if($month)
            <tr>
                <td class="label">Bulan:</td>
                <td>{{ date('F', mktime(0, 0, 0, $month, 1)) }}</td>
            </tr>
            @endif
            <tr>
                <td class="label">Tanggal Dibuat:</td>
                <td>{{ $generated_at }}</td>
            </tr>
        </table>
    </div>

    <div class="summary-box">
        <h3>RINGKASAN STATISTIK</h3>
        <div class="summary-stats">
            <div class="summary-item">
                <div class="value">{{ number_format($totals['target']) }}</div>
                <div class="label">Total Target</div>
            </div>
            <div class="summary-item">
                <div class="value">{{ number_format($totals['total_patients']) }}</div>
                <div class="label">Total Pasien</div>
            </div>
            <div class="summary-item">
                <div class="value">{{ number_format($totals['standard_patients']) }}</div>
                <div class="label">Standar Pelayanan</div>
            </div>
            <div class="summary-item">
                <div class="value">{{ $totals['percentage'] }}%</div>
                <div class="label">Persentase Capaian</div>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="number-col">NO</th>
                <th class="puskesmas-name">NAMA PUSKESMAS</th>
                <th class="target-col">TARGET</th>
                <th class="data-col">TOTAL PASIEN</th>
                <th class="data-col">STANDAR PELAYANAN</th>
                <th class="percentage-col">PERSENTASE (%)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($statistics_data as $data)
            <tr>
                <td class="number-col">{{ $data['no'] }}</td>
                <td class="puskesmas-name">{{ $data['puskesmas_name'] }}</td>
                <td class="target-col">{{ number_format($data['target']) }}</td>
                <td class="data-col">{{ number_format($data['total_patients']) }}</td>
                <td class="data-col">{{ number_format($data['standard_patients']) }}</td>
                <td class="percentage-col">{{ $data['percentage'] }}%</td>
            </tr>
            @endforeach
            
            <!-- Total Row -->
            <tr class="total-row">
                <td colspan="2" style="text-align: center; font-weight: bold;">TOTAL</td>
                <td class="target-col">{{ number_format($totals['target']) }}</td>
                <td class="data-col">{{ number_format($totals['total_patients']) }}</td>
                <td class="data-col">{{ number_format($totals['standard_patients']) }}</td>
                <td class="percentage-col">{{ $totals['percentage'] }}%</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Laporan dibuat pada: {{ $generated_at }}</p>
        <p>Sistem Informasi Kesehatan - Akudihatinya</p>
    </div>
</body>
</html>