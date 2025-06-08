<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title> <!-- Gunakan variabel $title -->
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            /* Adjusted for potentially more columns */
        }

        .title {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            /* Adjusted */
            margin-bottom: 5px;
        }

        .subtitle {
            text-align: center;
            font-size: 12px;
            /* Adjusted */
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 3px;
            /* Adjusted */
            text-align: center;
            vertical-align: middle;
        }

        th {
            background: #cfe2f3;
            font-weight: bold;
        }

        .total {
            font-weight: bold;
            background: #e2e3e5;
        }

        .footer {
            margin-top: 30px;
            font-size: 9px;
            /* Adjusted */
            text-align: right;
            color: #888;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>

<body>
    <div class="title">{{ $main_title }}</div>
    <div class="subtitle">Pelayanan Kesehatan Pada Penderita {{ $disease_type_label }} Tahun {{ $year }}</div>

    @php
        $quarterNames = ['I', 'II', 'III', 'IV'];
        // $monthNames sudah dikirim dari ExportService sebagai $months
        // $puskesmasCollection = $data['puskesmas_data']; // Akses langsung
        // $grandTotalData = $data['grand_total']; // Akses langsung
        $puskesmasCollection = $puskesmasData; // Akses langsung
        // grandTotalData sudah benar diakses langsung
    @endphp

    @foreach (array_chunk($months, 3) as $chunkIndex => $monthChunkInLoop)
        <!-- Gunakan $months -->
        <table>
            <thead>
                <tr>
                    <th rowspan="3">NO</th>
                    <th rowspan="3">NAMA PUSKESMAS</th>
                    <th rowspan="3">SASARAN</th>
                    @foreach ($monthChunkInLoop as $monthName)
                        <th colspan="5">{{ strtoupper($monthName) }}</th>
                    @endforeach
                    <th colspan="3" rowspan="2">{{ 'TOTAL STANDAR TRIWULAN ' . $quarterNames[$chunkIndex] }}</th>
                    <th rowspan="3">TARGET PENDERITA DM/HT SETAHUN</th>
                    <th colspan="5">JANUARI</th>
                    <th colspan="5">FEBRUARI</th>
                    <th colspan="5">MARET</th>
                    <th colspan="5">TRIWULAN I</th>
                    <th colspan="5">APRIL</th>
                    <th colspan="5">MEI</th>
                    <th colspan="5">JUNI</th>
                    <th colspan="5">TRIWULAN II</th>
                    <th colspan="5">JULI</th>
                    <th colspan="5">AGUSTUS</th>
                    <th colspan="5">SEPTEMBER</th>
                    <th colspan="5">TRIWULAN III</th>
                    <th colspan="5">OKTOBER</th>
                    <th colspan="5">NOVEMBER</th>
                    <th colspan="5">DESEMBER</th>
                    <th colspan="5">TRIWULAN IV</th>
                    <th rowspan="3">TOTAL PASIEN</th> {{-- Changed from TOTAL PASIEN (S) TAHUNAN --}}
                    <th rowspan="3">% CAPAIAN PELAYANAN SESUAI STANDAR TAHUNAN</th>
                </tr>
                <tr>
                    @foreach ($monthChunkInLoop as $monthName)
                        <th colspan="3">S</th>
                        <th rowspan="2">TS</th>
                        <th rowspan="2">%S</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($monthChunkInLoop as $monthName)
                        <th>L</th>
                        <th>P</th>
                        <th>TOTAL</th>
                    @endforeach
                    <th>L</th>
                    <th>P</th>
                    <th>TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($puskesmasCollection as $i => $puskesmas_row)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $puskesmas_row['name'] }}</td>
                        <td>{{ $puskesmas_row['target'] }}</td>
                        @php
                            $currentMonthOffset = $chunkIndex * 3;
                        @endphp
                        @for ($m_idx = 0; $m_idx < 3; $m_idx++)
                            @php
                                $currentDisplayMonthIndex = $currentMonthOffset + $m_idx;
                                $monthData = $puskesmas_row['monthly'][$currentDisplayMonthIndex] ?? [
                                    'l' => 0,
                                    'p' => 0,
                                    'total' => 0,
                                    'ts' => 0,
                                    'ps' => 0,
                                ];
                            @endphp
                            <td>{{ $monthData['l'] }}</td>
                            <td>{{ $monthData['p'] }}</td>
                            <td>{{ $monthData['total'] }}</td>
                            <td>{{ $monthData['ts'] }}</td>
                            <td>{{ $monthData['ps'] }}%</td>
                        @endfor
                        @php
                            $quarterData = $puskesmas_row['quarterly'][$chunkIndex] ?? [
                                'l' => 0,
                                'p' => 0,
                                'total' => 0,
                                'ts' => 0,
                                'ps' => 0,
                            ];
                        @endphp
                        <td>{{ $quarterData['l'] ?? 0 }}</td> {{-- Assuming 'l' is in quarterData --}}
                        <td>{{ $quarterData['p'] ?? 0 }}</td> {{-- Assuming 'p' is in quarterData --}}
                        <td>{{ $quarterData['total'] }}</td>
                        <td>{{ $puskesmas_row['total_pasien_tahunan'] }}</td>
                        <td>{{ $puskesmas_row['persen_capaian_tahunan'] }}%</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="2">Total</td> {{-- Adjusted colspan --}}
                    <td>{{ $grandTotalData['target'] }}</td>
                    @php
                        $currentMonthOffset = $chunkIndex * 3;
                    @endphp
                    @for ($m_idx = 0; $m_idx < 3; $m_idx++)
                        @php
                            $currentDisplayMonthIndex = $currentMonthOffset + $m_idx;
                            $totalMonthData = $grandTotalData['monthly'][$currentDisplayMonthIndex] ?? [
                                'l' => 0,
                                'p' => 0,
                                'total' => 0,
                                'ts' => 0,
                                'ps' => 0,
                            ];
                        @endphp
                        <td>{{ $totalMonthData['l'] }}</td>
                        <td>{{ $totalMonthData['p'] }}</td>
                        <td>{{ $totalMonthData['total'] }}</td>
                        <td>{{ $totalMonthData['ts'] }}</td>
                        <td>{{ $totalMonthData['ps'] }}%</td>
                    @endfor
                    @php
                        $totalQuarterData = $grandTotalData['quarterly'][$chunkIndex] ?? [
                            'l' => 0,
                            'p' => 0,
                            'total' => 0,
                            'ts' => 0,
                            'ps' => 0,
                        ];
                    @endphp
                    <td>{{ $totalQuarterData['l'] ?? 0 }}</td> {{-- Assuming 'l' is in quarterData for grand total --}}
                    <td>{{ $totalQuarterData['p'] ?? 0 }}</td> {{-- Assuming 'p' is in quarterData for grand total --}}
                    <td>{{ $totalQuarterData['total'] }}</td>
                    <td>{{ $grandTotalData['total_pasien_tahunan'] }}</td>
                    <td>{{ $grandTotalData['persen_capaian_tahunan'] }}%</td>
                </tr>
            </tbody>
        </table>
        @if (!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
    <div class="footer">
        Dicetak oleh: {{ $export_meta['generated_by'] }} ({{ $export_meta['user_role'] }})<br>
        Tanggal: {{ $export_meta['generated_at'] }}
    </div>
</body>

</html>
