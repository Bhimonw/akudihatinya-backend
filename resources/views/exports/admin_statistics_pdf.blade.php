<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Test PDF' }}</title>
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
    <div class="title">{{ $title }}</div>
    <div class="subtitle">Pelayanan Kesehatan Pada Penderita
        {{ $type === 'ht' ? 'Hipertensi (HT)' : 'Diabetes Mellitus (DM)' }} Tahun {{ $year }}</div>

    @php
        $quarterNames = ['I', 'II', 'III', 'IV'];
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];
    @endphp

    @foreach (array_chunk($months, 3, true) as $chunkIndex => $monthChunk)
        <table>
            <thead>
                <tr>
                    <th rowspan="3">NO</th>
                    <th rowspan="3">NAMA PUSKESMAS</th>
                    <th rowspan="3">SASARAN</th>
                    @foreach ($monthChunk as $monthNumber => $monthName)
                        <th colspan="5">{{ strtoupper($monthName) }}</th>
                    @endforeach
                    <th colspan="3" rowspan="2">{{ 'TOTAL STANDAR TRIWULAN ' . $quarterNames[$chunkIndex] }}</th>
                    <th rowspan="3">TARGET PENDERITA {{ $type === 'ht' ? 'HT' : 'DM' }} SETAHUN</th>
                    <th rowspan="3">TOTAL PASIEN</th>
                    <th rowspan="3">% CAPAIAN PELAYANAN SESUAI STANDAR TAHUNAN</th>
                </tr>
                <tr>
                    @foreach ($monthChunk as $monthNumber => $monthName)
                        <th colspan="3">S</th>
                        <th rowspan="2">TS</th>
                        <th rowspan="2">%S</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($monthChunk as $monthNumber => $monthName)
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
                @php
                    $totalTarget = 0;
                    $totalMonthlyData = array_fill(1, 12, [
                        'male' => 0,
                        'female' => 0,
                        'standard' => 0,
                        'non_standard' => 0,
                        'total' => 0,
                        'percentage' => 0,
                    ]);
                    $totalQuarterlyData = array_fill(1, 4, [
                        'male' => 0,
                        'female' => 0,
                        'standard' => 0,
                        'non_standard' => 0,
                        'total' => 0,
                        'percentage' => 0,
                    ]);
                    $totalYearlyStandard = 0;
                    $totalYearlyPatients = 0;
                @endphp

                @foreach ($statistics as $index => $puskesmas)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $puskesmas['puskesmas_name'] }}</td>
                        <td>{{ $puskesmas['target'] }}</td>

                        @php
                            $totalTarget += $puskesmas['target'];
                            $currentQuarter = $chunkIndex + 1;
                            $startMonth = ($currentQuarter - 1) * 3 + 1;
                            $endMonth = $currentQuarter * 3;

                            // Get quarterly data using QuarterHelper logic
                            $quarterData = \App\Helpers\QuarterHelper::getQuarterSummary(
                                $puskesmas['monthly_data'],
                                $currentQuarter,
                            );

                            // Update total quarterly data
                            $totalQuarterlyData[$currentQuarter]['male'] += $quarterData['male'];
                            $totalQuarterlyData[$currentQuarter]['female'] += $quarterData['female'];
                            $totalQuarterlyData[$currentQuarter]['standard'] += $quarterData['standard'];
                            $totalQuarterlyData[$currentQuarter]['non_standard'] += $quarterData['non_standard'];
                            $totalQuarterlyData[$currentQuarter]['total'] += $quarterData['total'];

                            // Get yearly data from December or last available month
                            $yearlyData =
                                isset($puskesmas['monthly_data'][12]) && $puskesmas['monthly_data'][12]['total'] > 0
                                    ? $puskesmas['monthly_data'][12]
                                    : \App\Helpers\QuarterHelper::getQuarterSummary($puskesmas['monthly_data'], 4);

                            $totalYearlyStandard += $yearlyData['standard'];
                            $totalYearlyPatients += $yearlyData['total'];
                        @endphp

                        @for ($m = $startMonth; $m <= $endMonth; $m++)
                            @php
                                $monthData = $puskesmas['monthly_data'][$m] ?? [
                                    'male' => 0,
                                    'female' => 0,
                                    'standard' => 0,
                                    'non_standard' => 0,
                                    'total' => 0,
                                    'percentage' => 0,
                                ];

                                // Update total monthly data
                                $totalMonthlyData[$m]['male'] += $monthData['male'];
                                $totalMonthlyData[$m]['female'] += $monthData['female'];
                                $totalMonthlyData[$m]['standard'] += $monthData['standard'];
                                $totalMonthlyData[$m]['non_standard'] += $monthData['non_standard'];
                                $totalMonthlyData[$m]['total'] += $monthData['total'];
                            @endphp
                            <td>{{ $monthData['male'] }}</td>
                            <td>{{ $monthData['female'] }}</td>
                            <td>{{ $monthData['standard'] }}</td>
                            <td>{{ $monthData['non_standard'] }}</td>
                            <td>{{ $monthData['percentage'] }}%</td>
                        @endfor

                        <td>{{ $quarterData['male'] }}</td>
                        <td>{{ $quarterData['female'] }}</td>
                        <td>{{ $quarterData['standard'] }}</td>
                        <td>{{ $puskesmas['target'] }}</td>
                        <td>{{ $yearlyData['total'] }}</td>
                        <td>{{ $yearlyData['percentage'] }}%</td>
                    </tr>
                @endforeach

                <tr class="total">
                    <td colspan="2">Total</td>
                    <td>{{ $totalTarget }}</td>

                    @for ($m = $startMonth; $m <= $endMonth; $m++)
                        @php
                            $totalMonthlyData[$m]['percentage'] =
                                $totalTarget > 0
                                    ? round(($totalMonthlyData[$m]['standard'] / $totalTarget) * 100, 2)
                                    : 0;
                        @endphp
                        <td>{{ $totalMonthlyData[$m]['male'] }}</td>
                        <td>{{ $totalMonthlyData[$m]['female'] }}</td>
                        <td>{{ $totalMonthlyData[$m]['standard'] }}</td>
                        <td>{{ $totalMonthlyData[$m]['non_standard'] }}</td>
                        <td>{{ $totalMonthlyData[$m]['percentage'] }}%</td>
                    @endfor

                    @php
                        $totalQuarterlyData[$currentQuarter]['percentage'] =
                            $totalTarget > 0
                                ? round(($totalQuarterlyData[$currentQuarter]['standard'] / $totalTarget) * 100, 2)
                                : 0;
                        $totalYearlyPercentage =
                            $totalTarget > 0 ? round(($totalYearlyStandard / $totalTarget) * 100, 2) : 0;
                    @endphp

                    <td>{{ $totalQuarterlyData[$currentQuarter]['male'] }}</td>
                    <td>{{ $totalQuarterlyData[$currentQuarter]['female'] }}</td>
                    <td>{{ $totalQuarterlyData[$currentQuarter]['standard'] }}</td>
                    <td>{{ $totalTarget }}</td>
                    <td>{{ $totalYearlyPatients }}</td>
                    <td>{{ $totalYearlyPercentage }}%</td>
                </tr>
            </tbody>
        </table>

        @if (!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach

    <div class="footer">
        Laporan ini dibuat pada {{ $generated_at }} oleh {{ $generated_by }} ({{ $user_role }})
    </div>
</body>

</html>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Test PDF' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }
    </style>
</head>

<body>
    <h1>{{ $title ?? 'Test PDF' }}</h1>

    @if (isset($statistics) && is_array($statistics))
        <p>Jumlah data: {{ count($statistics) }}</p>

        @if (count($statistics) > 0)
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Puskesmas</th>
                        <th>Target</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($statistics as $index => $stat)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $stat['puskesmas_name'] ?? 'N/A' }}</td>
                            <td>{{ $stat['target'] ?? 0 }}</td>
                        </tr>
                        @if ($index >= 4)
                            @break
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endif
    @else
        <p>Tidak ada data statistik</p>
    @endif

    <p>Generated at: {{ date('Y-m-d H:i:s') }}</p>
</body>

</html>
