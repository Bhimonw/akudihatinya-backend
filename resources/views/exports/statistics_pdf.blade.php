<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 16px;
            margin: 0;
            font-weight: bold;
        }
        .header h2 {
            font-size: 14px;
            margin: 5px 0;
            font-weight: normal;
        }
        .info {
            margin-bottom: 15px;
        }
        .info p {
            margin: 2px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 4px;
            text-align: center;
            vertical-align: middle;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 9px;
        }
        .puskesmas-name {
            text-align: left;
            font-weight: bold;
        }
        .grand-total {
            background-color: #e0e0e0;
            font-weight: bold;
        }
        .rotate {
            writing-mode: vertical-rl;
            text-orientation: mixed;
        }
        .footer {
            margin-top: 20px;
            font-size: 8px;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <h2>{{ $disease_label }}</h2>
    </div>

    <div class="info">
        <p><strong>Tahun:</strong> {{ $year }}</p>
        <p><strong>Tanggal Cetak:</strong> {{ $generated_at }}</p>
        <p><strong>Jumlah Puskesmas:</strong> {{ count($puskesmas_data) }}</p>
    </div>

    <!-- Monthly Data Table -->
    <table>
        <thead>
            <tr>
                <th rowspan="3">No</th>
                <th rowspan="3">Nama Puskesmas</th>
                <th rowspan="3">Target</th>
                <th colspan="60">Data Bulanan</th>
                <th rowspan="3">Total Pasien</th>
                <th rowspan="3">% Capaian</th>
            </tr>
            <tr>
                @foreach($months as $month)
                    <th colspan="5">{{ $month }}</th>
                @endforeach
            </tr>
            <tr>
                @for($i = 0; $i < 12; $i++)
                    <th>L</th>
                    <th>P</th>
                    <th>SS</th>
                    <th>TS</th>
                    <th>%</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @foreach($puskesmas_data as $index => $puskesmas)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="puskesmas-name">{{ $puskesmas['name'] }}</td>
                    <td>{{ number_format($puskesmas['target']) }}</td>
                    @foreach($puskesmas['monthly'] as $monthData)
                        <td>{{ $monthData['male'] }}</td>
                        <td>{{ $monthData['female'] }}</td>
                        <td>{{ $monthData['standard'] }}</td>
                        <td>{{ $monthData['non_standard'] }}</td>
                        <td>{{ $monthData['percentage'] }}%</td>
                    @endforeach
                    <td>{{ $puskesmas['total_patients'] }}</td>
                    <td>{{ $puskesmas['achievement_percentage'] }}%</td>
                </tr>
            @endforeach
            
            <!-- Grand Total Row -->
            <tr class="grand-total">
                <td colspan="2">TOTAL</td>
                <td>{{ number_format($grand_total['target']) }}</td>
                @foreach($grand_total['monthly'] as $monthData)
                    <td>{{ $monthData['male'] }}</td>
                    <td>{{ $monthData['female'] }}</td>
                    <td>{{ $monthData['standard'] }}</td>
                    <td>{{ $monthData['non_standard'] }}</td>
                    <td>{{ $monthData['percentage'] }}%</td>
                @endforeach
                <td>{{ $grand_total['total_patients'] }}</td>
                <td>{{ $grand_total['achievement_percentage'] }}%</td>
            </tr>
        </tbody>
    </table>

    <!-- Quarterly Data Table -->
    <table>
        <thead>
            <tr>
                <th rowspan="2">No</th>
                <th rowspan="2">Nama Puskesmas</th>
                <th rowspan="2">Target</th>
                <th colspan="20">Data Triwulanan</th>
            </tr>
            <tr>
                @foreach($quarters as $quarter)
                    <th colspan="5">{{ $quarter }}</th>
                @endforeach
            </tr>
            <tr>
                <th></th>
                <th></th>
                <th></th>
                @for($i = 0; $i < 4; $i++)
                    <th>L</th>
                    <th>P</th>
                    <th>SS</th>
                    <th>TS</th>
                    <th>%</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @foreach($puskesmas_data as $index => $puskesmas)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="puskesmas-name">{{ $puskesmas['name'] }}</td>
                    <td>{{ number_format($puskesmas['target']) }}</td>
                    @foreach($puskesmas['quarterly'] as $quarterData)
                        <td>{{ $quarterData['male'] }}</td>
                        <td>{{ $quarterData['female'] }}</td>
                        <td>{{ $quarterData['standard'] }}</td>
                        <td>{{ $quarterData['non_standard'] }}</td>
                        <td>{{ $quarterData['percentage'] }}%</td>
                    @endforeach
                </tr>
            @endforeach
            
            <!-- Grand Total Row -->
            <tr class="grand-total">
                <td colspan="2">TOTAL</td>
                <td>{{ number_format($grand_total['target']) }}</td>
                @foreach($grand_total['quarterly'] as $quarterData)
                    <td>{{ $quarterData['male'] }}</td>
                    <td>{{ $quarterData['female'] }}</td>
                    <td>{{ $quarterData['standard'] }}</td>
                    <td>{{ $quarterData['non_standard'] }}</td>
                    <td>{{ $quarterData['percentage'] }}%</td>
                @endforeach
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Dicetak pada: {{ $generated_at }}</p>
        <p>Keterangan: L = Laki-laki, P = Perempuan, SS = Sesuai Standar, TS = Tidak Sesuai Standar</p>
    </div>
</body>
</html>