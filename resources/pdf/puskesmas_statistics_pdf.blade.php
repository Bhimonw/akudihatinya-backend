<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Rekapitulasi Capaian Standar Pelayanan Minimal Bidang Kesehatan</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 15px;
            line-height: 1.2;
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
            margin-bottom: 5px;
        }

        .header h2 {
            font-size: 12px;
            margin: 3px 0;
            font-weight: normal;
            text-align: center;
        }

        .info-section {
            margin-bottom: 15px;
            font-size: 9px;
        }

        .info-row {
            display: flex;
            margin-bottom: 3px;
        }

        .info-label {
            width: 100px;
            font-weight: bold;
        }

        .info-separator {
            width: 20px;
            text-align: center;
        }

        .info-value {
            flex: 1;
        }

        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            table-layout: fixed;
        }

        .main-table th,
        .main-table td {
            border: 1px solid #000;
            padding: 4px 2px;
            text-align: center;
            vertical-align: middle;
            font-size: 9px;
            word-wrap: break-word;
        }

        .main-table th {
            background-color: #e6e6fa;
            font-weight: bold;
            text-transform: uppercase;
        }

        .month-col {
            width: 80px;
            text-align: left;
            padding-left: 5px;
        }

        .data-col {
            width: 40px;
        }

        .total-col {
            width: 50px;
        }

        .percentage-col {
            width: 45px;
        }

        .quarter-row {
            background-color: #ffff99;
            font-weight: bold;
        }

        .total-row {
            background-color: #e0e0e0;
            font-weight: bold;
        }

        .summary-table {
            width: 60%;
            border-collapse: collapse;
            margin: 15px 0;
            margin-left: auto;
            table-layout: fixed;
        }

        .summary-table th,
        .summary-table td {
            border: 1px solid #000;
            padding: 4px 2px;
            text-align: center;
            vertical-align: middle;
            font-size: 9px;
        }

        .summary-table th {
            background-color: #ffff99;
            font-weight: bold;
        }

        .summary-header {
            background-color: #ffff99;
        }

        .footer {
            margin-top: 20px;
            font-size: 8px;
            text-align: right;
        }

        .page-break {
            page-break-before: always;
        }

        @media print {
            body {
                margin: 0;
                padding: 10px;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Rekapitulasi Capaian Standar Pelayanan Minimal Bidang Kesehatan</h1>
        <h2>Pelayanan Kesehatan Pada Penderita {{ $disease_type_label ?? 'Penyakit' }}</h2>
    </div>

    <div class="info-section">
        <div class="info-row">
            <div class="info-label">SASARAN</div>
            <div class="info-separator">:</div>
            <div class="info-value">{{ $target ?? 0 }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">PUSKESMAS</div>
            <div class="info-separator">:</div>
            <div class="info-value">{{ $puskesmas_name ?? 'Nama Puskesmas' }}</div>
        </div>
    </div>

    <table class="main-table">
        <thead>
            <tr>
                <th rowspan="2" class="month-col">BULAN</th>
                <th colspan="3" class="data-col">S</th>
                <th rowspan="2" class="total-col">TS</th>
                <th rowspan="2" class="percentage-col">% S</th>
            </tr>
            <tr>
                <th class="data-col">L</th>
                <th class="data-col">P</th>
                <th class="data-col">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @php
                $months = [
                    1 => 'JANUARI',
                    2 => 'FEBRUARI',
                    3 => 'MARET',
                    4 => 'APRIL',
                    5 => 'MEI',
                    6 => 'JUNI',
                    7 => 'JULI',
                    8 => 'AGUSTUS',
                    9 => 'SEPTEMBER',
                    10 => 'OKTOBER',
                    11 => 'NOVEMBER',
                    12 => 'DESEMBER',
                ];
                $quarters = [
                    3 => 'TRIWULAN I',
                    6 => 'TRIWULAN II',
                    9 => 'TRIWULAN III',
                    12 => 'TRIWULAN IV',
                ];
            @endphp

            @for ($month = 1; $month <= 12; $month++)
                <tr>
                    <td class="month-col">{{ $months[$month] }}</td>
                    <td class="data-col">{{ $monthly_data[$month]['male'] ?? 0 }}</td>
                    <td class="data-col">{{ $monthly_data[$month]['female'] ?? 0 }}</td>
                    <td class="data-col">
                        {{ ($monthly_data[$month]['male'] ?? 0) + ($monthly_data[$month]['female'] ?? 0) }}</td>
                    <td class="total-col">{{ $monthly_data[$month]['non_standard'] ?? 0 }}</td>
                    <td class="percentage-col">{{ number_format($monthly_data[$month]['percentage'] ?? 0, 2) }}%</td>
                </tr>

                @if (in_array($month, [3, 6, 9, 12]))
                    <tr class="quarter-row">
                        <td class="month-col">{{ $quarters[$month] }}</td>
                        <td class="data-col">{{ $monthly_data[$month]['male'] ?? 0 }}</td>
                        <td class="data-col">{{ $monthly_data[$month]['female'] ?? 0 }}</td>
                        <td class="data-col">
                            {{ ($monthly_data[$month]['male'] ?? 0) + ($monthly_data[$month]['female'] ?? 0) }}</td>
                        <td class="total-col">{{ $monthly_data[$month]['non_standard'] ?? 0 }}</td>
                        <td class="percentage-col">{{ number_format($monthly_data[$month]['percentage'] ?? 0, 2) }}%
                        </td>
                    </tr>
                @endif
            @endfor

            <tr class="total-row">
                <td class="month-col">TOTAL</td>
                <td class="data-col">{{ $yearly_total['male'] ?? 0 }}</td>
                <td class="data-col">{{ $yearly_total['female'] ?? 0 }}</td>
                <td class="data-col">{{ $yearly_total['total'] ?? 0 }}</td>
                <td class="total-col">{{ $yearly_total['non_standard'] ?? 0 }}</td>
                <td class="percentage-col">{{ number_format($yearly_total['percentage'] ?? 0, 2) }}%</td>
            </tr>
        </tbody>
    </table>

    <table class="summary-table">
        <thead>
            <tr class="summary-header">
                <th colspan="6">TOTAL CAPAIAN TAHUN {{ $year ?? date('Y') }}</th>
            </tr>
            <tr>
                <th colspan="3">S</th>
                <th rowspan="2">TS</th>
                <th rowspan="2">TOTAL PELAYANAN</th>
                <th rowspan="2">% CAPAIAN PELAYANAN SESUAI STANDAR</th>
            </tr>
            <tr>
                <th>L</th>
                <th>P</th>
                <th>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <tr class="summary-header">
                <td>{{ $yearly_total['male'] ?? 0 }}</td>
                <td>{{ $yearly_total['female'] ?? 0 }}</td>
                <td>{{ $yearly_total['total'] ?? 0 }}</td>
                <td>{{ $yearly_total['non_standard'] ?? 0 }}</td>
                <td>{{ $yearly_total['total_services'] ?? 0 }}</td>
                <td>{{ number_format($yearly_total['percentage'] ?? 0, 2) }}%</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Dicetak pada: {{ date('d/m/Y H:i:s') }}</p>
        <p>Sistem Informasi Kesehatan Puskesmas</p>
    </div>
</body>

</html>
