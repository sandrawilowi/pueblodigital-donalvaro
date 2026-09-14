<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 29 ago 2026
 */

class statisticsRepository {

    private reservationsModel $reservations_model;
    private ttlockAccessLogsModel $access_logs_model;
    private usersBonusesModel $users_bonuses_model;

    public function __construct() {

        $this->reservations_model = new reservationsModel();
        $this->access_logs_model = new ttlockAccessLogsModel();
        $this->users_bonuses_model = new usersBonusesModel();
    }

    public function getStatistics(int $year, int $month = 0, int $facility_id = 0): array {

        $group_by_day = $month > 0;
        $bonuses_enabled = defined('_BONUSES_ENABLED') && _BONUSES_ENABLED;

        /*
         * Filtros para reservas.
         */
        $reservation_conditions = [
            'YEAR(r.start_at) = ?'
        ];

        $reservation_params = [
            $year
        ];

        /*
         * Filtros para accesos.
         */
        $access_conditions = [
            'YEAR(al.event_at) = ?'
        ];

        $access_params = [
            $year
        ];

        if ($month > 0) {

            $reservation_conditions[] = 'MONTH(r.start_at) = ?';
            $reservation_params[] = $month;

            $access_conditions[] = 'MONTH(al.event_at) = ?';
            $access_params[] = $month;
        }

        if ($facility_id > 0) {

            $reservation_conditions[] = 'r.facility_id = ?';
            $reservation_params[] = $facility_id;

            $access_conditions[] = 'al.facility_id = ?';
            $access_params[] = $facility_id;
        }

        $reservation_where = 'WHERE ' . implode(' AND ', $reservation_conditions);
        $access_where = 'WHERE ' . implode(' AND ', $access_conditions);

        /*
         * KPIs.
         */
        $reservation_kpis = $this->reservations_model->getStatisticsKpis(
                $reservation_where,
                $reservation_params
        );

        $access_kpis = $this->access_logs_model->getStatisticsKpis(
                $access_where,
                $access_params
        );

        /*
         * Gráficas.
         */
        $reservations_raw = $this->reservations_model->getStatisticsTrend(
                $reservation_where,
                $reservation_params,
                $group_by_day
        );

        $income_raw = $this->reservations_model->getStatisticsIncomeTrend(
                $reservation_where,
                $reservation_params,
                $group_by_day
        );

        $statuses_raw = $this->reservations_model->getStatisticsStatuses(
                $reservation_where,
                $reservation_params
        );

        $accesses_raw = $this->access_logs_model->getStatisticsTrend(
                $access_where,
                $access_params,
                $group_by_day
        );

        $access_results_raw = $this->access_logs_model->getStatisticsResults(
                $access_where,
                $access_params
        );

        /*
         * Valores por defecto de bonos.
         */
        $bonus_kpis = [
            'bonuses' => 0,
            'income' => 0
        ];

        $bonus_raw = [];
        $bonus_income_raw = [];

        /*
         * Solo cargamos datos de bonos si el módulo está habilitado.
         */
        if ($bonuses_enabled) {

            $bonus_conditions = [
                'YEAR(ub.purchased_at) = ?',
                'ub.status = 1'
            ];

            $bonus_params = [
                $year
            ];

            if ($month > 0) {
                $bonus_conditions[] = 'MONTH(ub.purchased_at) = ?';
                $bonus_params[] = $month;
            }

            if ($facility_id > 0) {

                $bonus_conditions[] = '
                EXISTS (
                    SELECT 1
                    FROM wi_bonuses_facilities bf
                    WHERE bf.bonus_id = ub.bonus_id
                      AND bf.facility_id = ?
                )';

                $bonus_params[] = $facility_id;
            }

            $bonus_where = 'WHERE ' . implode(' AND ', $bonus_conditions);

            $bonus_kpis = $this->users_bonuses_model->getStatisticsKpis(
                    $bonus_where,
                    $bonus_params
            );

            $bonus_raw = $this->users_bonuses_model->getStatisticsTrend(
                    $bonus_where,
                    $bonus_params,
                    $group_by_day
            );

            $bonus_income_raw = $this->users_bonuses_model->getStatisticsIncomeTrend(
                    $bonus_where,
                    $bonus_params,
                    $group_by_day
            );
        }

        $reservation_income = $this->prepareTrend(
                $income_raw,
                $year,
                $month
        );

        $bonus_income = $this->prepareTrend(
                $bonus_income_raw,
                $year,
                $month
        );

        return [
            'kpis' => [
                'reservations' => (int) ($reservation_kpis['reservations'] ?? 0),
                'income' => (float) ($reservation_kpis['income'] ?? 0) + (float) ($bonus_kpis['income'] ?? 0),
                'clients' => (int) ($reservation_kpis['clients'] ?? 0),
                'accesses' => (int) ($access_kpis['accesses'] ?? 0),
                'bonuses' => (int) ($bonus_kpis['bonuses'] ?? 0)
            ],
            'reservations' => $this->prepareTrend($reservations_raw, $year, $month),
            'income' => $this->sumTrends($reservation_income, $bonus_income),
            'statuses' => $this->prepareDistribution($statuses_raw),
            'accesses' => $this->prepareTrend($accesses_raw, $year, $month),
            'access_results' => $this->prepareAccessResults($access_results_raw),
            'bonuses' => $this->prepareTrend($bonus_raw, $year, $month)
        ];
    }

    private function prepareTrend(array $rows, int $year, int $month): array {

        $values_by_period = [];

        foreach ($rows as $row) {
            $values_by_period[(int) $row['period']] = (float) $row['total'];
        }

        $labels = [];
        $values = [];

        /*
         * Si hay mes seleccionado, mostramos los días.
         */
        if ($month > 0) {

            $days = cal_days_in_month(CAL_GREGORIAN, $month, $year);

            for ($day = 1; $day <= $days; $day++) {
                $labels[] = (string) $day;
                $values[] = $values_by_period[$day] ?? 0;
            }

        /*
         * Si no, mostramos los 12 meses.
         */
        } else {

            $month_names = [
                1 => 'Ene',
                2 => 'Feb',
                3 => 'Mar',
                4 => 'Abr',
                5 => 'May',
                6 => 'Jun',
                7 => 'Jul',
                8 => 'Ago',
                9 => 'Sep',
                10 => 'Oct',
                11 => 'Nov',
                12 => 'Dic'
            ];

            for ($month_number = 1; $month_number <= 12; $month_number++) {
                $labels[] = $month_names[$month_number];
                $values[] = $values_by_period[$month_number] ?? 0;
            }
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    private function prepareDistribution(array $rows): array {

        $labels = [];
        $values = [];

        foreach ($rows as $row) {
            $labels[] = $row['name'];
            $values[] = (int) $row['total'];
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    private function prepareAccessResults(array $rows): array {

        $allowed = 0;
        $denied = 0;

        foreach ($rows as $row) {

            if ((int) $row['access_granted'] === 1) {
                $allowed = (int) $row['total'];
            } else {
                $denied = (int) $row['total'];
            }
        }

        return [
            'labels' => [
                'Permitidos',
                'Denegados'
            ],
            'values' => [
                $allowed,
                $denied
            ]
        ];
    }
    
    private function sumTrends(array $first, array $second): array {

        $values = [];

        foreach ($first['values'] as $index => $value) {
            $values[] = (float) $value + (float) ($second['values'][$index] ?? 0);
        }

        return [
            'labels' => $first['labels'],
            'values' => $values
        ];
    }
}
