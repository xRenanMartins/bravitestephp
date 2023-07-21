<?php

namespace App\Rules\Customer;

use Carbon\Carbon;
use Packk\Core\Models\Customer;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class ExportCustomer implements FromCollection, ShouldAutoSize, WithHeadings, WithEvents
{
    use Exportable;

    private $payload;

    public function __construct($payload)
    {
        $this->payload = $payload;
    }

    public function collection()
    {
        $customers = Customer::select(['clientes.id as cliente_id', 'clientes.user_id', 'users.borned_at', 'users.cpf', 'users.nome', 'users.sobrenome', 'users.telefone', 'users.email'])
            ->join('users', 'clientes.user_id', 'users.id')
            ->where('tipo', 'C')
            ->groupBy('clientes.id')
            ->orderByDesc('users.created_at')
            ->get();

        return $this->formatData($customers);
    }

    public function headings(): array
    {
        return [
            "ID Usuário",
            "Nome Completo",
            "CPF",
            "Data de Nascimento",
            "Telefone",
            "E-mail"
        ];
    }

    public function registerEvents(): array
    {
        return [];
    }

    private function formatData($customers)
    {
        $formatted = collect([]);

        foreach ($customers as $customer) {
            $formatted->push([
                "user_id" => $customer->user_id ?? 'não informado',
                "name" => "{$customer->nome} {$customer->sobrenome}",
                "cpf" => $customer->cpf ?? 'não informado',
                "borned_at" => $customer->borned_at ? Carbon::parse($customer->borned_at)->format('d/m/Y') : 'não informado',
                "phone" => $customer->telefone ?? 'não informado',
                "email" => $customer->email ?? 'não informado'
            ]);
        }

        return $formatted;
    }

    private function stylePdf()
    {
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ];
        $backgroundHeader = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => 'e2e2e2'],
            ],
        ];
        return [
            AfterSheet::class => function (AfterSheet $event) use ($styleArray, $backgroundHeader) {
                $event->sheet->styleCells(
                    'A1:G1',
                    $backgroundHeader
                );
                $event->sheet->getDelegate()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $event->sheet->getDelegate()->getStyle($event->sheet->calculateWorksheetDimension())
                    ->applyFromArray($styleArray);
            },
        ];
    }
}
