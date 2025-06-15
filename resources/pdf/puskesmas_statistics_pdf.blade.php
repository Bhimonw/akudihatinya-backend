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
            margin-bottom: 5px;
            font-size: 10px;
        }

        .info-row span {
            display: block;
        }

        .info-row strong {
            font-weight: bold;
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
            width: 100px;
            text-align: left;
            padding-left: 5px;
        }

        .data-col {
            width: 60px;
            text-align: center;
        }

        .percentage-col {
            width: 80px;
            text-align: center;
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
            width: 80%;
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
            <span><strong>SASARAN :</strong> {{ $target ?? 0 }}</span>
        </div>
        <div class="info-row">
            <span><strong>PUSKESMAS :</strong> {{ $puskesmas_name ?? 'Nama Puskesmas' }}</span>
        </div>
    </div>

    <table class="main-table">
        <thead>
            <tr>
                <th rowspan="2" class="month-col">BULAN</th>
                <th colspan="2" class="data-col">JUMLAH PENDERITA</th>
                <th colspan="2" class="data-col">PELAYANAN KESEHATAN</th>
                <th rowspan="2" class="percentage-col">% PELAYANAN SESUAI STANDAR</th>
            </tr>
            <tr>
                <th class="data-col">L</th>
                <th class="data-col">P</th>
                <th class="data-col">S</th>
                <th class="data-col">TS</th>
            </tr>
        </thead>
        <tbody>
            @php
                $months = [
                    1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
                    5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
                    9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER',
                ];
                $quarters = [
                    3 => 'TRIWULAN I', 6 => 'TRIWULAN II',
                    9 => 'TRIWULAN III', 12 => 'TRIWULAN IV',
                ];
            @endphp

            {{-- Triwulan I --}}
            @for ($month = 1; $month <= 3; $month++)
                <tr>
                    <td class="month-col">{{ $months[$month] }}</td>
                    <td class="data-col">{{ $monthly_data[$month]['male'] ?? 0 }}</td>
                    <td class="data-col">{{ $monthly_data[$month]['female'] ?? 0 }}</td>
                    <td class="data-col">{{ $monthly_data[$month]['standard'] ?? 0 }}</td>
                    <td class="data-col">{{ $monthly_data[$month]['non_standard'] ?? 0 }}</td>
                    <td class="percentage-col">{{ number_format($monthly_data[$month]['percentage'] ?? 0, 2) }}%</td>
                </tr>
            @endfor
            <tr class="quarter-row">
                <td class="month-col">{{ $quarters[3] }}</td>
                <td class="data-col">{{ $quarterly_data[1]['male'] ?? 0 }}</td>
                <td class="data-col">{{ $quarterly_data[1]['female'] ?? 0 }}</td>
                <td class="data-col">{{ $quarterly_data[1]['standard'] ?? 0 }}</td>
                <td class="data-col">{{ $quarterly_data[1]['non_standard'] ?? 0 }}</td>
                <td class="percentage-col">{{ number_format($quarterly_data[1]['percentage'] ?? 0, 2) }}%</td>
            </tr>

            {{-- Triwulan II --}}
            @for ($month = 4; $month <= 6; $month++)
                <tr>
                    <td class="month-col">{{ $months[$month] }}</td>
                    <td class="data-col">{{ $monthly_data[$month]['male'] ?? 0 }}</td>
                    <td class="data-col">{{ $monthly_data[$month]['female'] ?? 0 }}</td>
                    <td class="data-col">{{ $monthly_data[$month]['standard'] ?? 0 }}</td>
                    <td class="data-col">{{ $monthly_data[$month]['non_standard'] ?? 0 }}</td>
                    <td class="percentage-col">{{ number_format($monthly_data[$month]['percentage'] ?? 0, 2) }}%</td>
                </tr>
            @endfor
            <tr class="quarter-row">
                <td class="month-col">{{ $quarters[6] }}</td>
                <td class="data-col">{{ $quarterly_data[2]['male'] ?? 0 }}</td>
                <td class="data-col">{{ $quarterly_data[2]['female'] ?? 0 }}</td>
                <td class="data-col">{{ $quarterly_data[2]['standard'] ?? 0 }}</td>
                <td class="data-col">{{ $quarterly_data[2]['non_standard'] ?? 0 }}</td>
                <td class="percentage-col">{{ number_format($quarterly_data[2]['percentage'] ?? 0, 2) }}%</td>
            </tr>

            {{-- Triwulan III --}}
            @for ($month = 7; $month <= 9; $month++)
                <tr>
                    <td class="month-col">{{ $months[$month] }}</td>
                    <td class="data-col">{{ $monthly_data[$month]['male'] ?? 0 }}</td>
                    <td class="data-col">{{ $monthly_data[$month]['female'] ?? 0 }}</td>
                    <td class="data-col">{{ $monthly_data[$month]['standard'] ?? 0 }}</td>
                    <td class="data-col">{{ $monthly_data[$month]['non_standard'] ?? 0 }}</td>
                    <td class="percentage-col">{{ number_format($monthly_data[$month]['percentage'] ?? 0, 2) }}%</td>
                </tr>
            @endfor
            <tr class="quarter-row">
                <td class="month-col">{{ $quarters[9] }}</td>
                <td class="data-col">{{ $quarterly_data[3]['male'] ?? 0 }}</td>
                <td class="data-col">{{ $quarterly_data[3]['female'] ?? 0 }}</td>
                <td class="data-col">{{ $quarterly_data[3]['standard'] ?? 0 }}</td>
                <td class="data-col">{{ $quarterly_data[3]['non_standard'] ?? 0 }}</td>
                <td class="percentage-col">{{ number_format($quarterly_data[3]['percentage'] ?? 0, 2) }}%</td>
            </tr>

            {{-- Triwulan IV --}}
            @for ($month = 10; $month <= 12; $month++)
                <tr>
                    <td class="month-col">{{ $months[$month] }}</td>
                    <td class="data-col">{{ $monthly_data[$month]['male'] ?? 0 }}</td>
                    <td class="data-col">{{ $monthly_data[$month]['female'] ?? 0 }}</td>
                    <td class="data-col">{{ $monthly_data[$month]['standard'] ?? 0 }}</td>
                    <td class="data-col">{{ $monthly_data[$month]['non_standard'] ?? 0 }}</td>
                    <td class="percentage-col">{{ number_format($monthly_data[$month]['percentage'] ?? 0, 2) }}%</td>
                </tr>
            @endfor
            <tr class="quarter-row">
                <td class="month-col">{{ $quarters[12] }}</td>
                <td class="data-col">{{ $quarterly_data[4]['male'] ?? 0 }}</td>
                <td class="data-col">{{ $quarterly_data[4]['female'] ?? 0 }}</td>
                <td class="data-col">{{ $quarterly_data[4]['standard'] ?? 0 }}</td>
                <td class="data-col">{{ $quarterly_data[4]['non_standard'] ?? 0 }}</td>
                <td class="percentage-col">{{ number_format($quarterly_data[4]['percentage'] ?? 0, 2) }}%</td>
            </tr>

            {{-- Total Tahunan --}}
            <tr class="total-row">
                <td class="month-col">TOTAL</td>
                <td class="data-col">{{ $yearly_total['male'] ?? 0 }}</td>
                <td class="data-col">{{ $yearly_total['female'] ?? 0 }}</td>
                <td class="data-col">{{ $yearly_total['standard'] ?? 0 }}</td>
                <td class="data-col">{{ $yearly_total['non_standard'] ?? 0 }}</td>
                <td class="percentage-col">{{ number_format($yearly_total['percentage'] ?? 0, 2) }}%</td>
            </tr>
        </tbody>
    </table>

    <table class="summary-table">
        <thead>
            <tr class="summary-header">
                <th colspan="6">REKAPITULASI CAPAIAN TAHUN {{ $year ?? date('Y') }}</th>
            </tr>
            <tr>
                <th colspan="2">JUMLAH PENDERITA</th>
                <th colspan="2">PELAYANAN KESEHATAN</th>
                <th rowspan="2">TOTAL PELAYANAN</th>
                <th rowspan="2">% CAPAIAN PELAYANAN SESUAI STANDAR</th>
            </tr>
            <tr>
                <th>L</th>
                <th>P</th>
                <th>S</th>
                <th>TS</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $yearly_total['male'] ?? 0 }}</td>
                <td>{{ $yearly_total['female'] ?? 0 }}</td>
                <td>{{ $yearly_total['standard'] ?? 0 }}</td>
                <td>{{ $yearly_total['non_standard'] ?? 0 }}</td>
                <td>{{ ($yearly_total['standard'] ?? 0) + ($yearly_total['non_standard'] ?? 0) }}</td>
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
