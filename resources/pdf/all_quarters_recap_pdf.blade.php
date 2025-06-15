<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Rekapitulasi Tahunan {{ $disease_label }} {{ $year }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }

        .page-break {
            page-break-before: always;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            width: 100%;
        }

        .header h1 {
            font-size: 12px;
            margin: 0 0 5px 0;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            line-height: 1.3;
        }

        .header h2 {
            font-size: 10px;
            margin: 3px 0;
            font-weight: normal;
            text-align: center;
            line-height: 1.2;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 auto 15px auto;
            table-layout: auto;
            font-size: 7px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 2px 1px;
            text-align: center;
            vertical-align: middle;
            word-wrap: break-word;
            overflow: hidden;
        }

        th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 6px;
            line-height: 1.1;
        }

        .puskesmas-name {
            text-align: left;
            font-weight: normal;
            width: 100px;
            max-width: 100px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .total-row {
            background-color: #e0e0e0;
            font-weight: bold;
        }

        .number-col {
            width: 20px;
            min-width: 20px;
        }

        .target-col {
            width: 35px;
            min-width: 35px;
        }

        .data-col {
            width: 22px;
            min-width: 22px;
        }

        .percentage-col {
            width: 28px;
            min-width: 28px;
        }

        .footer {
            margin-top: 15px;
            font-size: 7px;
            line-height: 1.3;
        }

        .footer p {
            margin: 2px 0;
        }
    </style>
</head>

<body>
    @foreach ($quarters_data as $index => $quarter_data)
        @if ($index > 0)
            <div class="page-break"></div>
        @endif

        <div class="header">
            <h1>REKAPITULASI CAPAIAN STANDAR PELAYANAN MINIMAL BIDANG KESEHATAN</h1>
            <h2>Pelayanan Kesehatan Pada Penderita {{ $quarter_data['disease_label'] }} Tahun
                {{ $quarter_data['year'] }}</h2>
            <h2>TRIWULAN {{ $quarter_data['quarter'] }}</h2>
        </div>

        <table>
            <thead>
                <tr>
                    <th rowspan="3" class="number-col">NO</th>
                    <th rowspan="3" class="puskesmas-name">NAMA PUSKESMAS</th>
                    <th rowspan="3" class="target-col">SASARAN</th>
                    <th colspan="{{ count($quarter_data['months']) * 6 }}">
                        {{ strtoupper(implode(' - ', $quarter_data['months'])) }}</th>
                    <th rowspan="2" colspan="3">TOTAL STANDAR<br>TW {{ $quarter_data['quarter'] }}</th>
                    <th rowspan="3" class="data-col">TOTAL<br>PASIEN<br>TW {{ $quarter_data['quarter'] }}</th>
                    <th rowspan="3" class="percentage-col">%<br>CAPAIAN<br>TW {{ $quarter_data['quarter'] }}</th>
                </tr>
                <tr>
                    @foreach ($quarter_data['months'] as $month)
                        <th colspan="6">{{ strtoupper(substr($month, 0, 3)) }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($quarter_data['months'] as $month)
                        <th class="data-col">S</th>
                        <th class="data-col">L</th>
                        <th class="data-col">P</th>
                        <th class="data-col">TOT</th>
                        <th class="data-col">TS</th>
                        <th class="percentage-col">%S</th>
                    @endforeach
                    <th class="data-col">L</th>
                    <th class="data-col">P</th>
                    <th class="data-col">S</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quarter_data['puskesmas_data'] as $puskesmas_index => $puskesmas)
                    <tr>
                        <td>{{ $puskesmas_index + 1 }}</td>
                        <td class="puskesmas-name">{{ $puskesmas['name'] }}</td>
                        <td>{{ number_format($puskesmas['target']) }}</td>
                        @foreach ($puskesmas['monthly'] as $monthData)
                            <td>{{ $monthData['standard'] }}</td>
                            <td>{{ $monthData['male'] }}</td>
                            <td>{{ $monthData['female'] }}</td>
                            <td>{{ $monthData['total'] }}</td>
                            <td>{{ $monthData['non_standard'] }}</td>
                            <td>{{ number_format($monthData['percentage'], 0) }}%</td>
                        @endforeach
                        <td>{{ $puskesmas['quarterly'][0]['male'] }}</td>
                        <td>{{ $puskesmas['quarterly'][0]['female'] }}</td>
                        <td>{{ $puskesmas['quarterly'][0]['standard'] }}</td>
                        <td>{{ $puskesmas['total_patients'] }}</td>
                        <td>{{ number_format($puskesmas['achievement_percentage'], 0) }}%</td>
                    </tr>
                @endforeach

                <!-- Total Row -->
                <tr class="total-row">
                    <td colspan="2">Total</td>
                    <td>{{ number_format($quarter_data['grand_total']['target']) }}</td>
                    @foreach ($quarter_data['grand_total']['monthly'] as $monthData)
                        <td>{{ $monthData['standard'] }}</td>
                        <td>{{ $monthData['male'] }}</td>
                        <td>{{ $monthData['female'] }}</td>
                        <td>{{ $monthData['total'] }}</td>
                        <td>{{ $monthData['non_standard'] }}</td>
                        <td>{{ number_format($monthData['percentage'], 0) }}%</td>
                    @endforeach
                    <td>{{ $quarter_data['grand_total']['quarterly'][0]['male'] }}</td>
                    <td>{{ $quarter_data['grand_total']['quarterly'][0]['female'] }}</td>
                    <td>{{ $quarter_data['grand_total']['quarterly'][0]['standard'] }}</td>
                    <td>{{ $quarter_data['grand_total']['total_patients'] }}</td>
                    <td>{{ number_format($quarter_data['grand_total']['achievement_percentage'], 0) }}%</td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            <p><strong>Keterangan:</strong></p>
            <p>S = Standar, L = Laki-laki, P = Perempuan, TOT = Total, TS = Tidak Sesuai Standar, %S = Persentase
                Standar</p>
            <p>TW = Triwulan | Dicetak pada: {{ $generated_at }}</p>
        </div>
    @endforeach
</body>

</html>
