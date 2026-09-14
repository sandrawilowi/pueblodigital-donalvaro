<?php

declare(strict_types=1);

class reportsPdfService {

    private const REPORTS_PATH = 'assets/reports/';

    public function generateReservationsReport(array $results, array $filters = []): array {

        $result = [
            'success' => false,
            'message' => '',
            'path' => '',
            'filename' => ''
        ];

        try {

            $reports_path = dirname(__DIR__, 3) . '/' . self::REPORTS_PATH;

            if (!is_dir($reports_path)) {

                if (!mkdir($reports_path, 0775, true) && !is_dir($reports_path)) {
                    throw new RuntimeException('No se ha podido crear el directorio de informes.');
                }
            }

            $filename = 'informe_reservas_' . date('Ymd_His') . '.pdf';
            $file_path = $reports_path . $filename;

            $pdf = new FPDF('L', 'mm', 'A4');

            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->AddPage();

            /*
             * Título.
             */
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, utf8_decode('Informe de reservas'), 0, 1, 'L');

            /*
             * Filtros aplicados.
             */
            $pdf->SetFont('Arial', '', 9);

            $date_from = trim((string) ($filters['date_from'] ?? ''));
            $date_until = trim((string) ($filters['date_until'] ?? ''));

            $period = 'Todos los registros';

            if ($date_from !== '' || $date_until !== '') {

                $from_text = $date_from !== '' ? date('d/m/Y', strtotime($date_from)) : '-';

                $until_text = $date_until !== '' ? date('d/m/Y', strtotime($date_until)) : '-';

                $period = $from_text . ' - ' . $until_text;
            }

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Periodo: ' . $period),
                    0,
                    1
            );

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Generado: ' . date('d/m/Y H:i')),
                    0,
                    1
            );

            $facility_name = trim((string) ($filters['facility_name'] ?? ''));
            $status_name = trim((string) ($filters['status_name'] ?? ''));

            if ($facility_name !== '') {

                $pdf->Cell(
                        0,
                        6,
                        utf8_decode('Instalación: ' . $facility_name),
                        0,
                        1
                );
            }

            if ($status_name !== '') {

                $pdf->Cell(
                        0,
                        6,
                        utf8_decode('Estado: ' . $status_name),
                        0,
                        1
                );
            }

            $pdf->Ln(4);

            /*
             * Cabecera de tabla.
             */
            $pdf->SetFont('Arial', 'B', 8);

            $widths = [
                34, // Referencia
                47, // Cliente
                54, // Instalación
                30, // Entrada
                30, // Salida
                16, // Personas
                27, // Estado
                23  // Importe
            ];

            $headers = [
                'Referencia',
                'Cliente',
                'Instalación',
                'Entrada',
                'Salida',
                'Personas',
                'Estado',
                'Importe'
            ];

            foreach ($headers as $index => $header) {
                $pdf->Cell(
                        $widths[$index],
                        8,
                        utf8_decode($header),
                        1,
                        0,
                        'C'
                );
            }

            $pdf->Ln();

            /*
             * Datos.
             */
            $pdf->SetFont('Arial', '', 7.5);

            foreach ($results as $row) {

                $reference = (string) ($row->reference ?? '');
                $user_name = (string) ($row->user_name ?? '');
                $facility_name = (string) ($row->facility_name ?? '');

                $start_at = !empty($row->start_at) ? date('d/m/Y H:i', strtotime($row->start_at)) : '-';

                $end_at = !empty($row->end_at) ? date('d/m/Y H:i', strtotime($row->end_at)) : '-';

                $people_count = (string) ((int) ($row->people_count ?? 0));
                $status_name = (string) ($row->status_name ?? '');

                $total_amount = number_format(
                                (float) ($row->total_amount ?? 0),
                                2,
                                ',',
                                '.'
                        ) . ' EUR';

                $pdf->Cell(
                        $widths[0],
                        7,
                        utf8_decode($this->limitText($reference, 25)),
                        1
                );

                $pdf->Cell(
                        $widths[1],
                        7,
                        utf8_decode($this->limitText($user_name, 34)),
                        1
                );

                $pdf->Cell(
                        $widths[2],
                        7,
                        utf8_decode($this->limitText($facility_name, 40)),
                        1
                );

                $pdf->Cell($widths[3], 7, $start_at, 1, 0, 'C');
                $pdf->Cell($widths[4], 7, $end_at, 1, 0, 'C');
                $pdf->Cell($widths[5], 7, $people_count, 1, 0, 'C');

                $pdf->Cell(
                        $widths[6],
                        7,
                        utf8_decode($this->limitText($status_name, 18)),
                        1,
                        0,
                        'C'
                );

                $pdf->Cell(
                        $widths[7],
                        7,
                        $total_amount,
                        1,
                        0,
                        'R'
                );

                $pdf->Ln();
            }

            /*
             * Resumen.
             */
            $pdf->Ln(5);

            $pdf->SetFont('Arial', 'B', 9);

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Total de reservas: ' . count($results)),
                    0,
                    1
            );

            $pdf->Output('F', $file_path);

            if (!is_file($file_path)) {
                throw new RuntimeException('No se ha podido generar el archivo PDF.');
            }

            $result['success'] = true;
            $result['message'] = 'Informe generado correctamente.';
            $result['path'] = self::REPORTS_PATH . $filename;
            $result['filename'] = $filename;
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    public function generateIncomeReport(array $results, array $filters = []): array {

        $result = [
            'success' => false,
            'message' => '',
            'path' => '',
            'filename' => ''
        ];

        try {

            $reports_path = dirname(__DIR__, 3) . '/' . self::REPORTS_PATH;

            if (!is_dir($reports_path)) {

                if (!mkdir($reports_path, 0775, true) && !is_dir($reports_path)) {
                    throw new RuntimeException('No se ha podido crear el directorio de informes.');
                }
            }

            $filename = 'informe_ingresos_' . date('Ymd_His') . '.pdf';
            $file_path = $reports_path . $filename;

            $pdf = new FPDF('L', 'mm', 'A4');

            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->AddPage();

            /*
             * Título.
             */
            $pdf->SetFont('Arial', 'B', 16);

            $pdf->Cell(
                    0,
                    10,
                    utf8_decode('Informe de ingresos'),
                    0,
                    1,
                    'L'
            );

            /*
             * Filtros.
             */
            $pdf->SetFont('Arial', '', 9);

            $date_from = trim((string) ($filters['date_from'] ?? ''));
            $date_until = trim((string) ($filters['date_until'] ?? ''));
            $facility_name = trim((string) ($filters['facility_name'] ?? ''));

            $period = 'Todos los registros';

            if ($date_from !== '' || $date_until !== '') {

                $from_text = $date_from !== '' ? date('d/m/Y', strtotime($date_from)) : '-';

                $until_text = $date_until !== '' ? date('d/m/Y', strtotime($date_until)) : '-';

                $period = $from_text . ' - ' . $until_text;
            }

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Periodo: ' . $period),
                    0,
                    1
            );

            if ($facility_name !== '') {

                $pdf->Cell(
                        0,
                        6,
                        utf8_decode('Instalación: ' . $facility_name),
                        0,
                        1
                );
            }

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Generado: ' . date('d/m/Y H:i')),
                    0,
                    1
            );

            $pdf->Ln(4);

            /*
             * Cabecera.
             */
            $pdf->SetFont('Arial', 'B', 8);

            $widths = [
                38, // Referencia
                53, // Cliente
                60, // Instalación
                34, // Entrada
                34, // Salida
                31, // Estado
                27  // Importe
            ];

            $headers = [
                'Referencia',
                'Cliente',
                'Instalación',
                'Entrada',
                'Salida',
                'Estado',
                'Importe'
            ];

            foreach ($headers as $index => $header) {

                $pdf->Cell(
                        $widths[$index],
                        8,
                        utf8_decode($header),
                        1,
                        0,
                        'C'
                );
            }

            $pdf->Ln();

            /*
             * Datos.
             */
            $pdf->SetFont('Arial', '', 7.5);

            $total_income = 0;

            foreach ($results as $row) {

                $reference = (string) ($row->reference ?? '');
                $user_name = (string) ($row->user_name ?? '');
                $facility_name_row = (string) ($row->facility_name ?? '');
                $status_name = (string) ($row->status_name ?? '');

                $start_at = !empty($row->start_at) ? date('d/m/Y H:i', strtotime($row->start_at)) : '-';

                $end_at = !empty($row->end_at) ? date('d/m/Y H:i', strtotime($row->end_at)) : '-';

                $amount = (float) ($row->total_amount ?? 0);

                $total_income += $amount;

                $amount_text = number_format(
                                $amount,
                                2,
                                ',',
                                '.'
                        ) . ' EUR';

                $pdf->Cell(
                        $widths[0],
                        7,
                        utf8_decode($this->limitText($reference, 27)),
                        1
                );

                $pdf->Cell(
                        $widths[1],
                        7,
                        utf8_decode($this->limitText($user_name, 38)),
                        1
                );

                $pdf->Cell(
                        $widths[2],
                        7,
                        utf8_decode($this->limitText($facility_name_row, 42)),
                        1
                );

                $pdf->Cell($widths[3], 7, $start_at, 1, 0, 'C');
                $pdf->Cell($widths[4], 7, $end_at, 1, 0, 'C');

                $pdf->Cell(
                        $widths[5],
                        7,
                        utf8_decode($this->limitText($status_name, 20)),
                        1,
                        0,
                        'C'
                );

                $pdf->Cell(
                        $widths[6],
                        7,
                        utf8_decode($amount_text),
                        1,
                        0,
                        'R'
                );

                $pdf->Ln();
            }

            /*
             * Totales.
             */
            $pdf->Ln(5);

            $pdf->SetFont('Arial', 'B', 9);

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Reservas contabilizadas: ' . count($results)),
                    0,
                    1
            );

            $total_text = number_format(
                            $total_income,
                            2,
                            ',',
                            '.'
                    ) . ' EUR';

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Ingresos totales: ' . $total_text),
                    0,
                    1
            );

            $pdf->Output('F', $file_path);

            if (!is_file($file_path)) {
                throw new RuntimeException('No se ha podido generar el archivo PDF.');
            }

            $result['success'] = true;
            $result['message'] = 'Informe generado correctamente.';
            $result['path'] = self::REPORTS_PATH . $filename;
            $result['filename'] = $filename;
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    public function generateClientsReport(array $results, array $filters = []): array {

        $result = [
            'success' => false,
            'message' => '',
            'path' => '',
            'filename' => ''
        ];

        try {

            $reports_path = dirname(__DIR__, 3) . '/' . self::REPORTS_PATH;

            if (!is_dir($reports_path)) {

                if (!mkdir($reports_path, 0775, true) && !is_dir($reports_path)) {
                    throw new RuntimeException('No se ha podido crear el directorio de informes.');
                }
            }

            $filename = 'informe_clientes_' . date('Ymd_His') . '.pdf';
            $file_path = $reports_path . $filename;

            $pdf = new FPDF('L', 'mm', 'A4');

            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->AddPage();

            /*
             * Título.
             */
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, utf8_decode('Informe de clientes'), 0, 1, 'L');

            /*
             * Filtros aplicados.
             */
            $pdf->SetFont('Arial', '', 9);

            $date_from = trim((string) ($filters['date_from'] ?? ''));
            $date_until = trim((string) ($filters['date_until'] ?? ''));
            $facility_name = trim((string) ($filters['facility_name'] ?? ''));

            $period = 'Todos los registros';

            if ($date_from !== '' || $date_until !== '') {

                $from_text = $date_from !== '' ? date('d/m/Y', strtotime($date_from)) : '-';

                $until_text = $date_until !== '' ? date('d/m/Y', strtotime($date_until)) : '-';

                $period = $from_text . ' - ' . $until_text;
            }

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Periodo: ' . $period),
                    0,
                    1
            );

            if ($facility_name !== '') {

                $pdf->Cell(
                        0,
                        6,
                        utf8_decode('Instalación: ' . $facility_name),
                        0,
                        1
                );
            }

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Generado: ' . date('d/m/Y H:i')),
                    0,
                    1
            );

            $pdf->Ln(4);

            /*
             * Cabecera.
             */
            $pdf->SetFont('Arial', 'B', 8);

            $widths = [
                55, // Cliente
                67, // Email
                42, // Provincia
                42, // País
                27, // Nº reservas
                40  // Importe
            ];

            $headers = [
                'Cliente',
                'Email',
                'Provincia',
                'País',
                'Nº reservas',
                'Importe'
            ];

            foreach ($headers as $index => $header) {

                $pdf->Cell(
                        $widths[$index],
                        8,
                        utf8_decode($header),
                        1,
                        0,
                        'C'
                );
            }

            $pdf->Ln();

            /*
             * Datos.
             */
            $pdf->SetFont('Arial', '', 7.5);

            $total_amount = 0;
            $total_reservations = 0;

            foreach ($results as $row) {

                $user_name = (string) ($row->user_name ?? '');
                $email = (string) ($row->email ?? '');
                $province_name = (string) ($row->province_name ?? '');
                $country_name = (string) ($row->country_name ?? '');

                $reservations_count = (int) ($row->reservations_count ?? 0);
                $amount = (float) ($row->total_amount ?? 0);

                $total_reservations += $reservations_count;
                $total_amount += $amount;

                $amount_text = number_format(
                                $amount,
                                2,
                                ',',
                                '.'
                        ) . ' EUR';

                $pdf->Cell(
                        $widths[0],
                        7,
                        utf8_decode($this->limitText($user_name, 40)),
                        1
                );

                $pdf->Cell(
                        $widths[1],
                        7,
                        utf8_decode($this->limitText($email, 50)),
                        1
                );

                $pdf->Cell(
                        $widths[2],
                        7,
                        utf8_decode($this->limitText($province_name !== '' ? $province_name : '-', 30)),
                        1
                );

                $pdf->Cell(
                        $widths[3],
                        7,
                        utf8_decode($this->limitText($country_name !== '' ? $country_name : '-', 30)),
                        1
                );

                $pdf->Cell(
                        $widths[4],
                        7,
                        (string) $reservations_count,
                        1,
                        0,
                        'C'
                );

                $pdf->Cell(
                        $widths[5],
                        7,
                        utf8_decode($amount_text),
                        1,
                        0,
                        'R'
                );

                $pdf->Ln();
            }

            /*
             * Resumen.
             */
            $pdf->Ln(5);

            $pdf->SetFont('Arial', 'B', 9);

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Clientes: ' . count($results)),
                    0,
                    1
            );

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Reservas realizadas: ' . $total_reservations),
                    0,
                    1
            );

            $total_text = number_format(
                            $total_amount,
                            2,
                            ',',
                            '.'
                    ) . ' EUR';

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Importe total: ' . $total_text),
                    0,
                    1
            );

            $pdf->Output('F', $file_path);

            if (!is_file($file_path)) {
                throw new RuntimeException('No se ha podido generar el archivo PDF.');
            }

            $result['success'] = true;
            $result['message'] = 'Informe generado correctamente.';
            $result['path'] = self::REPORTS_PATH . $filename;
            $result['filename'] = $filename;
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    public function generateOriginReport(array $results, array $filters = []): array {

        $result = [
            'success' => false,
            'message' => '',
            'path' => '',
            'filename' => ''
        ];

        try {

            $reports_path = dirname(__DIR__, 3) . '/' . self::REPORTS_PATH;

            if (!is_dir($reports_path)) {

                if (!mkdir($reports_path, 0775, true) && !is_dir($reports_path)) {
                    throw new RuntimeException('No se ha podido crear el directorio de informes.');
                }
            }

            $filename = 'informe_procedencia_' . date('Ymd_His') . '.pdf';
            $file_path = $reports_path . $filename;

            $pdf = new FPDF('L', 'mm', 'A4');

            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->AddPage();

            /*
             * Título.
             */
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, utf8_decode('Informe de procedencia de clientes'), 0, 1, 'L');

            /*
             * Filtros aplicados.
             */
            $pdf->SetFont('Arial', '', 9);

            $date_from = trim((string) ($filters['date_from'] ?? ''));
            $date_until = trim((string) ($filters['date_until'] ?? ''));
            $facility_name = trim((string) ($filters['facility_name'] ?? ''));

            $period = 'Todos los registros';

            if ($date_from !== '' || $date_until !== '') {

                $from_text = $date_from !== '' ? date('d/m/Y', strtotime($date_from)) : '-';

                $until_text = $date_until !== '' ? date('d/m/Y', strtotime($date_until)) : '-';

                $period = $from_text . ' - ' . $until_text;
            }

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Periodo: ' . $period),
                    0,
                    1
            );

            if ($facility_name !== '') {

                $pdf->Cell(
                        0,
                        6,
                        utf8_decode('Instalación: ' . $facility_name),
                        0,
                        1
                );
            }

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Generado: ' . date('d/m/Y H:i')),
                    0,
                    1
            );

            $pdf->Ln(4);

            /*
             * Cabecera.
             */
            $pdf->SetFont('Arial', 'B', 8);

            $widths = [
                62, // País
                62, // Provincia
                42, // Clientes
                42, // Reservas
                42  // Porcentaje
            ];

            $headers = [
                'País',
                'Provincia',
                'Clientes',
                'Reservas',
                'Porcentaje'
            ];

            foreach ($headers as $index => $header) {

                $pdf->Cell(
                        $widths[$index],
                        8,
                        utf8_decode($header),
                        1,
                        0,
                        'C'
                );
            }

            $pdf->Ln();

            /*
             * Datos.
             */
            $pdf->SetFont('Arial', '', 8);

            $total_reservations = 0;

            foreach ($results as $row) {

                $country_name = (string) ($row->country_name ?? 'Sin especificar');
                $province_name = (string) ($row->province_name ?? 'Sin especificar');
                $clients_count = (int) ($row->clients_count ?? 0);
                $reservations_count = (int) ($row->reservations_count ?? 0);
                $percentage = (float) ($row->percentage ?? 0);

                $total_reservations += $reservations_count;

                $pdf->Cell(
                        $widths[0],
                        7,
                        utf8_decode($this->limitText($country_name, 42)),
                        1
                );

                $pdf->Cell(
                        $widths[1],
                        7,
                        utf8_decode($this->limitText($province_name, 42)),
                        1
                );

                $pdf->Cell(
                        $widths[2],
                        7,
                        (string) $clients_count,
                        1,
                        0,
                        'C'
                );

                $pdf->Cell(
                        $widths[3],
                        7,
                        (string) $reservations_count,
                        1,
                        0,
                        'C'
                );

                $pdf->Cell(
                        $widths[4],
                        7,
                        number_format($percentage, 2, ',', '.') . ' %',
                        1,
                        0,
                        'C'
                );

                $pdf->Ln();
            }

            /*
             * Resumen.
             */
            $pdf->Ln(5);

            $pdf->SetFont('Arial', 'B', 9);

            $total_clients = (int) ($filters['total_clients'] ?? 0);

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Clientes contabilizados: ' . $total_clients),
                    0,
                    1
            );

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Reservas contabilizadas: ' . $total_reservations),
                    0,
                    1
            );

            $pdf->Output('F', $file_path);

            if (!is_file($file_path)) {
                throw new RuntimeException('No se ha podido generar el archivo PDF.');
            }

            $result['success'] = true;
            $result['message'] = 'Informe generado correctamente.';
            $result['path'] = self::REPORTS_PATH . $filename;
            $result['filename'] = $filename;
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    public function generateBonusesReport(array $results, array $filters = []): array {

        $result = [
            'success' => false,
            'message' => '',
            'path' => '',
            'filename' => ''
        ];

        try {

            $reports_path = dirname(__DIR__, 3) . '/' . self::REPORTS_PATH;

            if (!is_dir($reports_path)) {

                if (!mkdir($reports_path, 0775, true) && !is_dir($reports_path)) {
                    throw new RuntimeException('No se ha podido crear el directorio de informes.');
                }
            }

            $filename = 'informe_bonos_' . date('Ymd_His') . '.pdf';
            $file_path = $reports_path . $filename;

            $pdf = new FPDF('L', 'mm', 'A4');

            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->AddPage();

            /*
             * Título.
             */
            $pdf->SetFont('Arial', 'B', 16);

            $pdf->Cell(
                    0,
                    10,
                    utf8_decode('Informe de bonos'),
                    0,
                    1,
                    'L'
            );

            /*
             * Filtros.
             */
            $pdf->SetFont('Arial', '', 9);

            $date_from = trim((string) ($filters['date_from'] ?? ''));
            $date_until = trim((string) ($filters['date_until'] ?? ''));
            $facility_name = trim((string) ($filters['facility_name'] ?? ''));

            $period = 'Todos los registros';

            if ($date_from !== '' || $date_until !== '') {

                $from_text = $date_from !== '' ? date('d/m/Y', strtotime($date_from)) : '-';

                $until_text = $date_until !== '' ? date('d/m/Y', strtotime($date_until)) : '-';

                $period = $from_text . ' - ' . $until_text;
            }

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Periodo: ' . $period),
                    0,
                    1
            );

            if ($facility_name !== '') {

                $pdf->Cell(
                        0,
                        6,
                        utf8_decode('Instalación: ' . $facility_name),
                        0,
                        1
                );
            }

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Generado: ' . date('d/m/Y H:i')),
                    0,
                    1
            );

            $pdf->Ln(4);

            /*
             * Cabecera.
             */
            $pdf->SetFont('Arial', 'B', 7.5);

            $widths = [
                35, // Cliente
                30, // Bono
                17, // Tipo
                40, // Instalaciones
                25, // Adquirido
                35, // Vigencia
                18, // Usos
                27, // Método
                20, // Precio
                20  // Estado
            ];

            $headers = [
                'Cliente',
                'Bono',
                'Tipo',
                'Instalaciones',
                'Adquirido',
                'Vigencia',
                'Usos',
                'Método',
                'Precio',
                'Estado'
            ];

            foreach ($headers as $index => $header) {

                $pdf->Cell(
                        $widths[$index],
                        8,
                        utf8_decode($header),
                        1,
                        0,
                        'C'
                );
            }

            $pdf->Ln();

            /*
             * Datos.
             */
            $pdf->SetFont('Arial', '', 7);

            $total_amount = 0;

            foreach ($results as $row) {

                $user_name = (string) ($row->user_name ?? '');
                $bonus_name = (string) ($row->bonus_name ?? '');
                $bonus_type = (string) ($row->bonus_type ?? '');
                $facilities = (string) ($row->facilities ?? '');
                $payment_method_name = (string) ($row->payment_method_name ?? '');

                $type_text = '-';

                if ($bonus_type === 'USES') {
                    $type_text = 'Por usos';
                } elseif ($bonus_type === 'TIME') {
                    $type_text = 'Por tiempo';
                }

                $purchased_at = !empty($row->purchased_at) ? date('d/m/Y H:i', strtotime($row->purchased_at)) : '-';

                $validity_text = '-';

                if (!empty($row->valid_from)) {

                    $validity_text = date('d/m/Y', strtotime($row->valid_from)) . ' - ';

                    if (!empty($row->expires_at)) {
                        $validity_text .= date('d/m/Y', strtotime($row->expires_at));
                    } else {
                        $validity_text .= 'Sin caducidad';
                    }
                }

                if ($bonus_type === 'TIME') {

                    $uses_text = 'Ilimitado';
                } else {

                    $remaining_uses = (int) ($row->remaining_uses ?? 0);
                    $initial_uses = (int) ($row->initial_uses ?? 0);

                    $uses_text = $remaining_uses . ' / ' . $initial_uses;
                }

                $amount = (float) ($row->purchase_price ?? 0);
                $total_amount += $amount;

                $amount_text = number_format(
                                $amount,
                                2,
                                ',',
                                '.'
                        ) . ' EUR';

                $status = (int) ($row->status ?? -1);

                $status_text = match ($status) {
                    0 => 'Inactivo',
                    1 => 'Activo',
                    2 => 'Bloqueado',
                    3 => 'Eliminado',
                    default => '-'
                };

                $pdf->Cell(
                        $widths[0],
                        7,
                        utf8_decode($this->limitText($user_name, 27)),
                        1
                );

                $pdf->Cell(
                        $widths[1],
                        7,
                        utf8_decode($this->limitText($bonus_name, 24)),
                        1
                );

                $pdf->Cell(
                        $widths[2],
                        7,
                        utf8_decode($type_text),
                        1,
                        0,
                        'C'
                );

                $pdf->Cell(
                        $widths[3],
                        7,
                        utf8_decode($this->limitText($facilities !== '' ? $facilities : '-', 32)),
                        1
                );

                $pdf->Cell(
                        $widths[4],
                        7,
                        $purchased_at,
                        1,
                        0,
                        'C'
                );

                $pdf->Cell(
                        $widths[5],
                        7,
                        utf8_decode($this->limitText($validity_text, 28)),
                        1,
                        0,
                        'C'
                );

                $pdf->Cell(
                        $widths[6],
                        7,
                        utf8_decode($uses_text),
                        1,
                        0,
                        'C'
                );

                $pdf->Cell(
                        $widths[7],
                        7,
                        utf8_decode($this->limitText($payment_method_name !== '' ? $payment_method_name : '-', 22)),
                        1
                );

                $pdf->Cell(
                        $widths[8],
                        7,
                        utf8_decode($amount_text),
                        1,
                        0,
                        'R'
                );

                $pdf->Cell(
                        $widths[9],
                        7,
                        utf8_decode($status_text),
                        1,
                        0,
                        'C'
                );

                $pdf->Ln();
            }

            /*
             * Resumen.
             */
            $pdf->Ln(5);

            $pdf->SetFont('Arial', 'B', 9);

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Bonos contabilizados: ' . count($results)),
                    0,
                    1
            );

            $total_text = number_format(
                            $total_amount,
                            2,
                            ',',
                            '.'
                    ) . ' EUR';

            $pdf->Cell(
                    0,
                    6,
                    utf8_decode('Importe total: ' . $total_text),
                    0,
                    1
            );

            $pdf->Output('F', $file_path);

            if (!is_file($file_path)) {
                throw new RuntimeException('No se ha podido generar el archivo PDF.');
            }

            $result['success'] = true;
            $result['message'] = 'Informe generado correctamente.';
            $result['path'] = self::REPORTS_PATH . $filename;
            $result['filename'] = $filename;
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    private function limitText(string $text, int $length): string {

        $text = trim($text);

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 3) . '...';
    }
}
