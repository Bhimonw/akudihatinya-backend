<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rekapitulasi Tahunan {{ $disease_label }} {{ $year }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 15px;
        }

        .page-break {
            page-break-before: always;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            width: 100%;
        }

        .header h1 {
            font-size: 14px;
            margin: 0;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
        }

        .header h2 {
            font-size: 12px;
            margin: 5px 0;
            font-weight: normal;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 auto 20px auto;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #000;
            padding: 3px;
            text-align: center;
            vertical-align: middle;
            font-size: 8px;
            word-wrap: break-word;
        }

        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .puskesmas-name {
            text-align: left;
            font-weight: normal;
            width: 120px;
        }

        .total-row {
            background-color: #e0e0e0;
            font-weight: bold;
        }

        .number-col { width: 25px; }
        .target-col { width: 40px; }
        .data-col { width: 25px; }
        .percentage-col { width: 35px; }

        .footer {
            margin-top: 20px;
            font-size: 8px;
        }
    </style>
</head>
<body>
    @foreach($quarters_data as $index => $quarter_data)
        @if($index > 0)
            <div class="page-break"></div>
        @endif
        
        <div class="header">
            <h1>REKAPITULASI CAPAIAN STANDAR PELAYANAN MINIMAL BIDANG KESEHATAN</h1>
            <h2>Pelayanan Kesehatan Pada Penderita {{ $quarter_data['disease_label'] }} Tahun {{ $quarter_data['year'] }}</h2>
            <h2>TRIWULAN {{ $quarter_data['quarter'] }}</h2>
        </div>

        <table>
            <thead>
                <tr>
                    <th rowspan="3" class="number-col">NO</th>
                    <th rowspan="3" class="puskesmas-name">NAMA PUSKESMAS</th>
                    <th rowspan="3" class="target-col">SASARAN</th>
                    <th colspan="{{ count($quarter_data['months']) * 6 }}">{{ strtoupper(implode(' - ', $quarter_data['months'])) }}</th>
                    <th rowspan="2" colspan="3">TOTAL STANDAR<br>TRIWULAN {{ $quarter_data['quarter'] }}</th>
                    <th rowspan="3" class="data-col">TOTAL PASIEN (S)<br>TRIWULAN {{ $quarter_data['quarter'] }}</th>
                    <th rowspan="3" class="percentage-col">% CAPAIAN PELAYANAN SESUAI STANDAR<br>TRIWULAN {{ $quarter_data['quarter'] }}</th>
                </tr>
                <tr>
                    @foreach($quarter_data['months'] as $month)
                        <th colspan="6">{{ strtoupper($month) }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach($quarter_data['months'] as $month)
                        <th class="data-col">S</th>
                        <th class="data-col">L</th>
                        <th class="data-col">P</th>
                        <th class="data-col">TOTAL</th>
                        <th class="data-col">TS</th>
                        <th class="percentage-col">%S</th>
                    @endforeach
                    <th class="data-col">L</th>
                    <th class="data-col">P</th>
                    <th class="data-col">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quarter_data['puskesmas_data'] as $puskesmas_index => $puskesmas)
                    <tr>
                        <td>{{ $puskesmas_index + 1 }}</td>
                        <td class="puskesmas-name">{{ $puskesmas['name'] }}</td>
                        <td>{{ number_format($puskesmas['target']) }}</td>
                        @foreach($puskesmas['monthly'] as $monthData)
                            <td>{{ $monthData['standard'] }}</td>
                            <td>{{ $monthData['male'] }}</td>
                            <td>{{ $monthData['female'] }}</td>
                            <td>{{ $monthData['total'] }}</td>
                            <td>{{ $monthData['non_standard'] }}</td>
                            <td>{{ number_format($monthData['percentage'], 0) }}%</td>
                        @endforeach
                        <td>{{ $puskesmas['quarterly'][0]['male'] }}</td>
                        <td>{{ $puskesmas['quarterly'][0]['female'] }}</td>
                        <td>{{ $puskesmas['quarterly'][0]['total'] }}</td>
                        <td>{{ $puskesmas['total_patients'] }}</td>
                        <td>{{ number_format($puskesmas['achievement_percentage'], 0) }}%</td>
                    </tr>
                @endforeach

                <!-- Total Row -->
                <tr class="total-row">
                    <td colspan="2">Total</td>
                    <td>{{ number_format($quarter_data['grand_total']['target']) }}</td>
                    @foreach($quarter_data['grand_total']['monthly'] as $monthData)
                        <td>{{ $monthData['standard'] }}</td>
                        <td>{{ $monthData['male'] }}</td>
                        <td>{{ $monthData['female'] }}</td>
                        <td>{{ $monthData['total'] }}</td>
                        <td>{{ $monthData['non_standard'] }}</td>
                        <td>{{ number_format($monthData['percentage'], 0) }}%</td>
                    @endforeach
                    <td>{{ $quarter_data['grand_total']['quarterly'][0]['male'] }}</td>
                    <td>{{ $quarter_data['grand_total']['quarterly'][0]['female'] }}</td>
                    <td>{{ $quarter_data['grand_total']['quarterly'][0]['total'] }}</td>
                    <td>{{ $quarter_data['grand_total']['total_patients'] }}</td>
                    <td>{{ number_format($quarter_data['grand_total']['achievement_percentage'], 0) }}%</td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            <p><strong>Keterangan:</strong></p>
            <p>S = Standar, L = Laki-laki, P = Perempuan, TS = Tidak Sesuai Standar, %S = Persentase Standar</p>
            <p>Dicetak pada: {{ $generated_at }}</p>
        </div>
    @endforeach
</body>
</html>