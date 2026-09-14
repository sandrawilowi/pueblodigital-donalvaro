$(document).ready(function () {
    controlHash();
    controlPage();

});

controlPage = function(){
    
    var url = window.location.toString();
    var partsControl = url.split('/');
    var endControl = partsControl.pop();
    var parts = endControl.split('#');

    if(url.includes('ver-instalacion')){
        cargarVerInstalacion();
    }else if(url.includes('reservas-cliente')){
        cargarDataTables('dt_reservations_client');
    }
   
    
};

cargarDataTables = function (id) {

    const columnConfig = {
        "dt_reservations_client": { total: 8, noSortable: [7], searching:true, serverside: false, buttons: false }
    };

    if (!columnConfig[id]) {
        console.warn(` No hay configuración definida para: ${id}`);
        return;
    }

    const { total, noSortable } = columnConfig[id];

    let columns = Array.from({ length: total }, (_, index) => ({
        "sortable": !noSortable.includes(index) // Si la columna está en noSortable, no es ordenable
    }));


    if (!columnConfig[id].serverside && columnConfig[id].buttons) {

        var options_table = {
            dom:
                    '<"datatable-top mb-3 d-flex justify-content-between align-items-center"<"dt-left"B><"dt-center"f><"dt-right"p>>' +
    'rt' +
    '<"datatable-bottom mt-3"<"datatable-bottom-right"ip>>',
            "stateSave": false,
            "searching": columnConfig[id].searching,
            "order": [],
            "lengthChange": true,
            "aLengthMenu": [25, 50],
            "pageLength": 25,
            "columns": columns,
            "ordering": true,
            "responsive": true,
            language: {
                decimal: ",",
                thousands: ".",
                emptyTable: "No hay datos disponibles",
                info: "Mostrando del _START_ al _END_ de _TOTAL_ registros",
                infoEmpty: "Mostrando 0 registros",
                infoFiltered: "(filtrado de _MAX_ registros totales)",
                lengthMenu: "Mostrar _MENU_ registros",
                loadingRecords: "Cargando...",
                processing: "Procesando...",
                search: "Buscar:",
                zeroRecords: "No se encontraron resultados",
                paginate: {
                    first: "Primera",
                    last: "Última",
                    next: "Siguiente",
                    previous: "Anterior"
                },
                aria: {
                    sortAscending: ": activar para ordenar de forma ascendente",
                    sortDescending: ": activar para ordenar de forma descendente"
                }
            },
            "buttons": [
                {
                    "extend": "excelHtml5",
                    "autoFilter": true,
                    "text": "<i class='fas fa-file-excel'></i> Descargar Excel",
                    "className": "btn backgroundMuted"
                }
            ]
        };

    } else if (!columnConfig[id].serverside && !columnConfig[id].buttons) {

        var options_table = {
            dom:
                    '<"datatable-top mb-3 d-flex justify-content-between align-items-center"<"dt-left"B><"dt-center"f><"dt-right"p>>' +
    'rt' +
    '<"datatable-bottom mt-3"<"datatable-bottom-right"ip>>',
            "stateSave": false,
            "searching": columnConfig[id].searching,
            "order": [],
            "lengthChange": true,
            "aLengthMenu": [25, 50],
            "pageLength": 25,
            "columns": columns,
            "ordering": true,
            "responsive": true,
            language: {
                decimal: ",",
                thousands: ".",
                emptyTable: "No hay datos disponibles",
                info: "Mostrando del _START_ al _END_ de _TOTAL_ registros",
                infoEmpty: "Mostrando 0 registros",
                infoFiltered: "(filtrado de _MAX_ registros totales)",
                lengthMenu: "Mostrar _MENU_ registros",
                loadingRecords: "Cargando...",
                processing: "Procesando...",
                search: "Buscar:",
                zeroRecords: "No se encontraron resultados",
                paginate: {
                    first: "Primera",
                    last: "Última",
                    next: "Siguiente",
                    previous: "Anterior"
                },
                aria: {
                    sortAscending: ": activar para ordenar de forma ascendente",
                    sortDescending: ": activar para ordenar de forma descendente"
                }
            },
            "buttons": [
            ]
        };
    } else {
        /*var options_table = {
            "dom": 'Bfrtip',
            "stateSave": false,
            "searching": columnConfig[id].searching,
            "order": [],
            "lengthChange": true,
            "aLengthMenu": [25, 50],
            "pageLength": 25,
            "columns": columns,
            "ordering": true,
            "responsive": true,
            "serverSide": true,
            "ajax": {
                "url": urlEnvironment + 'app/dataTable/studentsDataTable.php', // 👈 URL que devolverá los datos paginados
                "type": "POST"
            },
            "buttons": [
                {
                    "extend": "excelHtml5",
                    "autoFilter": true,
                    "text": "<i class='fas fa-file-excel'></i> Download Excel",
                    "className": "btn_base_1"
                }
            ]
        };

        options_table.createdRow = function (row, data, dataIndex) {
            $(row).addClass('text-center');
        };*/
    }

    $('#' + id).DataTable(options_table);
};



controlesPeticiones = function(Data){

    if (Data.control_request == 'recover') {

        if (Data.result == 'OK') {
            window.location.href = Data.extra;
        }
    }


};


cargarVerInstalacion = function () {

    $(document).on('click', '.facility-gallery-image', function () {
        $('#facilityImageModalImg').attr('src', $(this).data('image'));
    });

    $(document).on('click', '.facility-gallery-prev', function () {
        $('.facility-gallery').get(0).scrollBy({
            left: -400,
            behavior: 'smooth'
        });
    });

    $(document).on('click', '.facility-gallery-next', function () {
        $('.facility-gallery').get(0).scrollBy({
            left: 400,
            behavior: 'smooth'
        });
    });
    
    
};


buyBonus = function(bonus_id){

    var payment_method_id = $('#buy_bonus_payment_method_' + bonus_id).val();

    $('#buy_bonus_payment_method_' + bonus_id).removeClass('is-invalid');

    if(payment_method_id === ''){

        $('#buy_bonus_payment_method_' + bonus_id).addClass('is-invalid');
        return;
    }

    disableBtn('btn_buy_bonus_' + bonus_id);

    var params = {
        controller: 'clientController',
        function: 'buyBonus',
        bonus_id: bonus_id,
        payment_method_id: payment_method_id
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        enableBtn('btn_buy_bonus_' + bonus_id);

        if(Data.result){

            disableSelectedModal('modalBuyBonus' + bonus_id);

            Data.result = urlEnvironment + 'bonos-cliente?r=' + Date.now() + '#success';
        }

        afterReload(Data);

    });

};

searchClientAvailability = function(){

    var valid = true;

    $('#form_client_reservation .is-invalid').removeClass('is-invalid');
    $('#reservation_options .is-invalid').removeClass('is-invalid');

    $('#reservation_form_error').hide();
    $('#reservation_availability').hide().empty();

    var facility_id = $('#facility_id').val();
    var people_count = parseInt($('#people_count').val()) || 0;
    var booking_type = $('#booking_type').val();

    if(people_count < 1){
        $('#people_count').addClass('is-invalid');
        valid = false;
    }

    if(booking_type === 'DATE_RANGE'){

        var start_at = $('#start_at').val();
        var end_at = $('#end_at').val();

        if(start_at === ''){
            $('#start_at').addClass('is-invalid');
            valid = false;
        }

        if(end_at === ''){
            $('#end_at').addClass('is-invalid');
            valid = false;
        }

        if(start_at !== '' && end_at !== '' && end_at < start_at){
            $('#start_at, #end_at').addClass('is-invalid');
            valid = false;
        }
    }

    if(booking_type === 'SPECIFIC_HOUR'){

        var reservation_date = $('#reservation_date').val();
        var reservation_hour = $('#reservation_hour').val();
        var reservation_duration = parseInt($('#reservation_duration').val()) || 0;

        if(reservation_date === ''){
            $('#reservation_date').addClass('is-invalid');
            valid = false;
        }

        if(reservation_hour === ''){
            $('#reservation_hour').addClass('is-invalid');
            valid = false;
        }

        if(reservation_duration <= 0){
            $('#reservation_duration').addClass('is-invalid');
            valid = false;
        }
    }

    if(!valid){

        $('#reservation_form_error')
                .text('Debe completar correctamente los datos de la reserva.')
                .show();

        return;
    }

    disableBtn('btn_search_availability');
    $('#loading_reservation').css('display', 'block');

    var form = $('#form_client_reservation')[0];
    var formData = new FormData(form);

    if(booking_type === 'DATE_RANGE'){
        formData.append('start_at', $('#start_at').val());
        formData.append('end_at', $('#end_at').val());
    }

    if(booking_type === 'SPECIFIC_HOUR'){
        formData.append('reservation_date', $('#reservation_date').val());
        formData.append('reservation_hour', $('#reservation_hour').val());
        formData.append('duration_minutes', $('#reservation_duration').val());
    }

    formData.append('controller', 'clientController');
    formData.append('function', 'searchAvailability');

    sendToServerDoc(formData, function(Data){

        enableBtn('btn_search_availability');
        $('#loading_reservation').css('display', 'none');

        if(Data.result){

            $('#reservation_availability')
                    .html(Data.extra)
                    .show();

        }else{

            afterReload(Data);
        }

    });
};

continueClientReservation = function(){

    var facility_id = $('#facility_id').val();
    var people_count = parseInt($('#people_count').val()) || 0;

    var start_at = $('#reservation_confirm_start_at').val();
    var end_at = $('#reservation_confirm_end_at').val();
    var booking_type = $('#reservation_confirm_booking_type').val();
    var requested_units = parseInt($('#reservation_confirm_requested_units').val()) || 0;
    var facility_price_id = $('#reservation_confirm_facility_price_id').val();

    if(
            facility_id === '' ||
            people_count <= 0 ||
            start_at === '' ||
            end_at === '' ||
            requested_units <= 0
    ){
        return;
    }

    disableBtn('btn_continue_reservation');
    $('#loading_reservation').css('display', 'block');

    var params = {
        controller: 'clientController',
        function: 'prepareReservation',
        facility_id: facility_id,
        people_count: people_count,
        start_at: start_at,
        end_at: end_at,
        booking_type: booking_type,
        requested_units: requested_units,
        facility_price_id: facility_price_id
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        enableBtn('btn_continue_reservation');
        $('#loading_reservation').css('display', 'none');

        if(Data.result){

            $('#reservation_availability')
                    .html(Data.extra)
                    .show();

        }else{

            afterReload(Data);
        }

    });
};

confirmClientReservation = function(){

    $('#reservation_prepare_error').hide();

    var payment_option = $('input[name="reservation_payment_option"]:checked').val();

    if(!payment_option){

        $('#reservation_prepare_error')
                .text('Debe seleccionar una forma de pago.')
                .show();

        return;
    }
    
    if ($('#accept_booking_conditions').length && !$('#accept_booking_conditions').is(':checked')) {

        $('#reservation_prepare_error')
                .text('Debe leer y aceptar las condiciones de reserva.')
                .show();

        return;
    }

    disableBtn('btn_confirm_reservation');
    $('#loading_reservation').css('display', 'block');

    var params = {
        controller: 'clientController',
        function: 'confirmReservation',
        facility_id: $('#confirm_facility_id').val(),
        start_at: $('#confirm_start_at').val(),
        end_at: $('#confirm_end_at').val(),
        people_count: $('#confirm_people_count').val(),
        requested_units: $('#confirm_requested_units').val(),
        facility_price_id: $('#confirm_facility_price_id').val(),
        payment_option: payment_option,
        booking_conditions_accepted: $('#accept_booking_conditions').length
            ? $('#accept_booking_conditions').is(':checked')
            : true
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        enableBtn('btn_confirm_reservation');
        $('#loading_reservation').css('display', 'none');

        if(Data.result){

            if(Data.extra && Data.extra.payment_url){
                window.location.href = Data.extra.payment_url;
                return;
            }

            Data.result = urlEnvironment + 'reservas-cliente?r=' + Date.now() + '#success';
        }

        afterReload(Data);

    });
};

filterReservasCliente = function(){

    disableBtn('btn_filter_reservations_client');
    $('#loading_reservations_client').css('display', 'block');

    var form = $('#form_filter_reservations_client')[0];
    var formData = new FormData(form);

    formData.append('controller', 'clientController');
    formData.append('function', 'filterReservasCliente');

    sendToServerDoc(formData, function(Data){

        enableBtn('btn_filter_reservations_client');
        $('#loading_reservations_client').css('display', 'none');

        if (Data.result) {

            $('#reservations_client_results').html(Data.result);

            cargarDataTables('dt_reservations_client');

        } else {

            afterReload(Data);
        }

    });

};


clearFilterReservasCliente = function(){

    $('#form_filter_reservations_client')[0].reset();

    filterReservasCliente();

};

addClientReservationGuest = function(reservation_id){

    var full_name = $('#guest_full_name').val().trim();
    var document_type_id = $('#guest_document_type_id').val();
    var document_number = $('#guest_document_number').val().trim();
    var is_holder = $('#guest_is_holder').is(':checked') ? 1 : 0;

    $('#add_guest_error').hide().html('');

    if(full_name === ''){
        $('#add_guest_error').html('Debe indicar el nombre completo del huésped.').show();
        return;
    }

    disableBtn('btn_add_guest');
    $('#loading_add_guest').css('display', 'block');

    var params = {
        controller: 'clientController',
        function: 'addReservationGuest',
        reservation_id: reservation_id,
        full_name: full_name,
        document_type_id: document_type_id,
        document_number: document_number,
        is_holder: is_holder
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        enableBtn('btn_add_guest');
        $('#loading_add_guest').css('display', 'none');

        if(Data.result){
            Data.result = urlEnvironment + 'ver-reserva-cliente/' + reservation_id + '?r=' + Date.now() + '#huespedes';
        }

        afterReload(Data);

    });

};

editClientReservationGuest = function(reservation_id, guest_id){

    var full_name = $('#guest_full_name_' + guest_id).val().trim();
    var document_type_id = $('#guest_document_type_id_' + guest_id).val();
    var document_number = $('#guest_document_number_' + guest_id).val().trim();
    var is_holder = $('#guest_is_holder_' + guest_id).is(':checked') ? 1 : 0;

    $('#edit_guest_error_' + guest_id).hide().html('');

    if(full_name === ''){
        $('#edit_guest_error_' + guest_id).html('Debe indicar el nombre completo del huésped.').show();
        return;
    }

    disableBtn('btn_edit_guest_' + guest_id);
    $('#loading_edit_guest_' + guest_id).css('display', 'block');

    var params = {
        controller: 'clientController',
        function: 'editReservationGuest',
        reservation_id: reservation_id,
        guest_id: guest_id,
        full_name: full_name,
        document_type_id: document_type_id,
        document_number: document_number,
        is_holder: is_holder
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        enableBtn('btn_edit_guest_' + guest_id);
        $('#loading_edit_guest_' + guest_id).css('display', 'none');

        if(Data.result){
            Data.result = urlEnvironment + 'ver-reserva-cliente/' + reservation_id + '?r=' + Date.now() + '#huespedes';
        }

        afterReload(Data);

    });

};