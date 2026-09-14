<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 15 ago 2026
 */
class facilitiesRepository
{
    private facilitiesModel $facilities_model;
    private facilitiesDevicesModel $facilities_devices_model;
    private facilitiesServicesModel $facilities_services_model;
    private facilitiesImagesModel $facilities_images_model;
    private facilitiesPaymentMethodsModel $facilities_payment_methods_model;
    private bonusesFacilitiesModel $facilities_bonuses_model;
    private facilitiesPricesModel $facilities_prices_model;

    public function __construct()
    {
        $this->facilities_model = new facilitiesModel();

        $this->facilities_devices_model =
            new facilitiesDevicesModel();

        $this->facilities_services_model =
            new facilitiesServicesModel();

        $this->facilities_images_model =
            new facilitiesImagesModel();

        $this->facilities_payment_methods_model =
            new facilitiesPaymentMethodsModel();

        $this->facilities_bonuses_model =
            new bonusesFacilitiesModel();

        $this->facilities_prices_model =
            new facilitiesPricesModel();
    }


    /**
     * Devuelve todos los datos necesarios para editar
     * una instalación.
     */
    public function getFacilityEditData(
        int $facility_id
    ): array {

        if (empty($facility_id)) {
            throw new InvalidArgumentException(
                'El identificador de la instalación no es válido.'
            );
        }


        /*
         * Datos principales.
         */
        $facility = $this->facilities_model->findById(
            $facility_id,
            true
        );

        if (empty($facility)) {
            throw new RuntimeException(
                'No se ha encontrado la instalación.'
            );
        }


        /*
         * Dispositivos asociados.
         */
        $devices =
            $this->facilities_devices_model
                ->findByFacilityId(
                    $facility_id,
                    true
                );


        /*
         * Servicios asociados.
         */
        $services =
            $this->facilities_services_model
                ->findByFacilityId(
                    $facility_id,
                    true
                );


        /*
         * Imágenes.
         */
        $images =
            $this->facilities_images_model
                ->findByFacilityId(
                    $facility_id,
                    true
                );


        /*
         * Métodos de pago.
         */
        $payment_methods =
            $this->facilities_payment_methods_model
                ->findByFacilityId(
                    $facility_id,
                    true
                );


        /*
         * Bonos.
         */
        $bonuses =
            $this->facilities_bonuses_model
                ->findByFacilityId(
                    $facility_id,
                    true
                );


        /*
         * Precios.
         */
        $prices =
            $this->facilities_prices_model
                ->findByFacilityId(
                    $facility_id,
                    true
                );


        return [
            'facility' => $facility,
            'devices' => $devices,
            'services' => $services,
            'payment_methods' => $payment_methods,
            'bonuses' => $bonuses,
            'images' => $images,
            'prices' => $prices
        ];
    }
    
    
    /**
     * Busca instalaciones según los filtros recibidos.
     *
     * @param array $filters
     *
     * @return array
     */
    public function searchFacilities(array $filters = []): array {

        $conditions = [];
        $params = [];

        $name = isset($filters['name']) ? trim(strip_tags((string) $filters['name'])) : '';
        $city = isset($filters['city']) ? trim(strip_tags((string) $filters['city'])) : '';
        $status = $filters['status'] ?? '';
        $provinces = isset($filters['provinces']) && is_array($filters['provinces']) ? $filters['provinces'] : [];

        /*
         * No mostramos instalaciones eliminadas.
         */
        $conditions[] = 'f.deleted_at IS NULL';

        /*
         * Buscar por nombre.
         */
        if ($name !== '') {

            $conditions[] = 'f.name LIKE ?';
            $params[] = '%' . $name . '%';
        }

        /*
         * Buscar por localidad.
         */
        if ($city !== '') {

            $conditions[] = 'f.city LIKE ?';
            $params[] = '%' . $city . '%';
        }

        /*
         * Filtrar por estado.
         */
        if ($status !== '') {

            $conditions[] = 'f.status = ?';
            $params[] = (int) $status;
        }

        /*
         * Filtrar por una o varias provincias.
         *
         * Si no hay ninguna seleccionada,
         * no se añade ninguna condición.
         */
        if (!empty($provinces)) {

            $provinces = array_values(
                    array_filter(
                            array_map('intval', $provinces),
                            fn($id) => $id > 0
                    )
            );

            if (!empty($provinces)) {

                $placeholders = implode(
                        ',',
                        array_fill(
                                0,
                                count($provinces),
                                '?'
                        )
                );

                $conditions[] = 'f.province_id IN (' . $placeholders . ')';

                foreach ($provinces as $province_id) {
                    $params[] = $province_id;
                }
            }
        }

        $where = '';

        if (!empty($conditions)) {
            $where = ' WHERE ' . implode(' AND ',$conditions);
        }

        return $this->facilities_model->findByFilters($where,$params,true);
    }
}
