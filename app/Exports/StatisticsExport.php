<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StatisticsExport implements FromArray, WithHeadings, WithMapping, WithStyles
{
    protected $statistics;
    protected $year;
    protected $month;
    protected $diseaseType;

    public function __construct(array $statistics, int $year, ?int $month, string $diseaseType)
    {
        $this->statistics = $statistics;
        $this->year = $year;
        $this->month = $month;
        $this->diseaseType = $diseaseType;
    }

    public function array(): array
    {
        return $this->statistics;
    }

    public function headings(): array
    {
        $headings = [
            'Ranking',
            'Puskesmas',
        ];

        if ($this->diseaseType === 'all' || $this->diseaseType === 'ht') {
            $headings = array_merge($headings, [
                'Target HT',
                'Total Pasien HT',
                'Pasien Standar HT',
                'Pasien Non-Standar HT',
                'Pasien Laki-laki HT',
                'Pasien Perempuan HT',
                'Persentase Pencapaian HT',
            ]);
        }

        if ($this->diseaseType === 'all' || $this->diseaseType === 'dm') {
            $headings = array_merge($headings, [
                'Target DM',
                'Total Pasien DM',
                'Pasien Standar DM',
                'Pasien Non-Standar DM',
                'Pasien Laki-laki DM',
                'Pasien Perempuan DM',
                'Persentase Pencapaian DM',
            ]);
        }

        return $headings;
    }

    public function map($row): array
    {
        $data = [
            $row['ranking'],
            $row['puskesmas_name'],
        ];

        if ($this->diseaseType === 'all' || $this->diseaseType === 'ht') {
            $data = array_merge($data, [
                $row['ht']['target'],
                $row['ht']['total_patients'],
                $row['ht']['standard_patients'],
                $row['ht']['non_standard_patients'],
                $row['ht']['male_patients'],
                $row['ht']['female_patients'],
                $row['ht']['achievement_percentage'] . '%',
            ]);
        }

        if ($this->diseaseType === 'all' || $this->diseaseType === 'dm') {
            $data = array_merge($data, [
                $row['dm']['target'],
                $row['dm']['total_patients'],
                $row['dm']['standard_patients'],
                $row['dm']['non_standard_patients'],
                $row['dm']['male_patients'],
                $row['dm']['female_patients'],
                $row['dm']['achievement_percentage'] . '%',
            ]);
        }

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
