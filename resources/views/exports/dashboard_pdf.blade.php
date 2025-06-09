<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @php
        // Debugging di awal file
        $debugFile = storage_path('logs/pdf_debug.log');
        file_put_contents($debugFile, "Data received: " . json_encode([
            'puskesmasData' => is_array($puskesmasData) ? count($puskesmasData) : 'not array',
            'months' => $months,
            'year' => $year,
            'disease_type_label' => $disease_type_label
        ]) . "\n", FILE_APPEND);
    @endphp
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
        }

        .letterhead {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .letterhead-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .letterhead-subtitle {
            font-size: 12px;
            margin-bottom: 5px;
        }

        .title {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .subtitle {
            text-align: center;
            font-size: 12px;
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
        // Ensure $puskesmasCollection is an array, even if empty
        $puskesmasCollection = $puskesmasCollection ?? [];
        
        // Debugging
        $debugFile = storage_path('logs/pdf_debug.log');
        file_put_contents($debugFile, "Puskesmas Collection: " . json_encode([
            'count' => count($puskesmasCollection),
            'first_item' => !empty($puskesmasCollection) ? array_keys($puskesmasCollection[0]) : 'empty'
        ]) . "\n", FILE_APPEND);
    @endphp

    @if(empty($months))
        <div class="title">Data bulan tidak tersedia</div>
    @else
        @foreach (array_chunk($months, 3, true) as $chunkIndex => $monthChunkInLoop) {{-- Added true to preserve keys --}}
            <table>
                <thead>
                    <tr>
                        <th rowspan="3">NO</th>
                        <th rowspan="3">NAMA PUSKESMAS</th>
                        <th rowspan="3">SASARAN</th>
                        @foreach ($monthChunkInLoop as $monthNumber => $monthName) {{-- Changed to use monthNumber --}}
                            <th colspan="5">{{ strtoupper($monthName) }}</th>
                        @endforeach
                        <th colspan="3" rowspan="2">{{ 'TOTAL STANDAR TRIWULAN ' . $quarterNames[$chunkIndex] }}</th>
                        <th rowspan="3">TARGET PENDERITA DM/HT SETAHUN</th>
                        <th rowspan="3">TOTAL PASIEN</th>
                        <th rowspan="3">% CAPAIAN PELAYANAN SESUAI STANDAR TAHUNAN</th>
                    </tr>
                    <tr>
                        @foreach ($monthChunkInLoop as $monthNumber => $monthName) {{-- Changed to use monthNumber --}}
                            <th colspan="3">S</th>
                            <th rowspan="2">TS</th>
                            <th rowspan="2">%S</th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach ($monthChunkInLoop as $monthNumber => $monthName) {{-- Changed to use monthNumber --}}
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
                            @for ($m_idx = 0; $m_idx < count($monthChunkInLoop); $m_idx++) {{-- Adjusted loop condition --}}
                                @php
                                    $currentDisplayMonthIndex = array_keys($monthChunkInLoop)[$m_idx]; // Get the actual month number from the chunk
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
                                $quarterData = $puskesmas_row['quarterly'][$chunkIndex + 1] ?? [ // Adjusted index for quarterly data
                                    'total' => 0,
                                    'non_standard' => 0,
                                    'percentage' => 0,
                                ];
                            @endphp
                            <td>{{ $quarterData['total'] - $quarterData['non_standard'] }}</td> {{-- Standard patients --}}
                            <td>{{ $quarterData['non_standard'] }}</td> {{-- Non-standard patients --}}
                            <td>{{ $quarterData['total'] }}</td>
                            <td>{{ $puskesmas_row['target'] }}</td>
                            <td>{{ $puskesmas_row['total_pasien'] }}</td>
                            <td>{{ $puskesmas_row['persen_capaian_tahunan'] }}%</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td colspan="2">Total</td>
                        <td>{{ $grandTotalData['target'] }}</td>
                        @php
                            $currentMonthOffset = $chunkIndex * 3;
                        @endphp
                        @for ($m_idx = 0; $m_idx < count($monthChunkInLoop); $m_idx++) {{-- Adjusted loop condition --}}
                            @php
                                $currentDisplayMonthIndex = array_keys($monthChunkInLoop)[$m_idx]; // Get the actual month number from the chunk
                                $totalMonthData = $grandTotalData['monthly'][$currentDisplayMonthIndex] ?? [
                                    'male' => 0,
                                    'female' => 0,
                                    'total' => 0,
                                    'non_standard' => 0,
                                    'percentage' => 0,
                                ];
                            @endphp
                            <td>{{ $totalMonthData['male'] }}</td>
                            <td>{{ $totalMonthData['female'] }}</td>
                            <td>{{ $totalMonthData['total'] }}</td>
                            <td>{{ $totalMonthData['non_standard'] }}</td>
                            <td>{{ $totalMonthData['percentage'] }}%</td>
                        @endfor
                        @php
                            $totalQuarterData = $grandTotalData['quarterly'][$chunkIndex + 1] ?? [ // Adjusted index for quarterly data
                                'total' => 0,
                                'non_standard' => 0,
                                'percentage' => 0,
                            ];
                        @endphp
                        <td>{{ $totalQuarterData['total'] - $totalQuarterData['non_standard'] }}</td> {{-- Standard patients --}}
                        <td>{{ $totalQuarterData['non_standard'] }}</td> {{-- Non-standard patients --}}
                        <td>{{ $totalQuarterData['total'] }}</td>
                        <td>{{ $grandTotalData['target'] }}</td>
                        <td>{{ $grandTotalData['total_patients'] }}</td>
                        <td>{{ $grandTotalData['yearly_achievement_percentage'] }}%</td>
                    </tr>
                </tbody>
            </table>
            @if (!$loop->last)
                <div class="page-break"></div>
            @endif
        @endforeach
    @endif

    @if(empty($puskesmasCollection))
        <div class="title">Tidak ada data puskesmas yang tersedia untuk ditampilkan.</div>
    @endif

    <div class="footer">
        Laporan ini dibuat pada {{ $export_meta['exported_at'] }} oleh {{ $export_meta['exported_by'] }}
    </div>
</body>

</html>
