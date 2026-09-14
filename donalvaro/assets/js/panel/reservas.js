var reservationCalendar = null;

$(document).ready(function () {
    controlHash();
    controlPage();

});

controlPage = function(){
    
    var url = window.location.toString();
    var partsControl = url.split('/');
    var endControl = partsControl.pop();
    var parts = endControl.split('#');

    if (url.includes('/reservas')) {

        cargarDataTables('dt_reservas');
    }else if (url.includes('/nueva-reserva')) {

        cargarDatosNuevaReserva();;
    }else if(url.includes('/calendario-reservas')){
        
        cargarCalendarioReservas();
    }else if (url.includes('/auditreserva')) {

        cargarDataTables('dt_audit');
    }

   
    
};

cargarDataTables = function (id) {

    const columnConfig = {
        "dt_reservas": { total: 10, noSortable: [8],searching:false, serverside: false, buttons: true },
        "dt_audit": { total: 9, noSortable: [3,4,5,6,7],searching:true, serverside: false, buttons: true }
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

formFilter = function(id){
    
    var form = $('#' + id)[0];
    var formData = new FormData(form);

    formData.append('controller', 'panelController');
    formData.append('function', 'formFilter');
    formData.append('form_id', id);

    sendToServerDoc(formData, function (Data) {

        $('#insertTableReservas').empty();
        $('#insertTableReservas').html(Data.result);
        cargarDataTables('dt_reservas');

    });
    
};

cargarDatosNuevaReserva = function () {

    $('#facility_id').on('change', function () {

        var facility_id = $(this).val();

        $('#reservation_options').hide().empty();
        $('#reservation_availability').hide().empty();
        $('#btn_search_availability').hide();
        $('#reservation_form_error').hide();

        if (facility_id === '') {
            return;
        }

        cargarOpcionesReserva(facility_id);

    });

    $('#form_quick_client').on('input change', 'input, select, textarea', function () {
        $(this).removeClass('is-invalid');
        $('#quick_client_form_error').hide();
    });

    $(document).on('change', '.reservation-payment-option', function () {

        var type = $(this).data('type');
        var code = $(this).data('code');

        $('#pending_payment_info').hide();

        if (type === 'PAYMENT_METHOD' && (code === 'cash' || code === 'bizum')) {
            $('#pending_payment_info').show();
        }

    });

};

cargarOpcionesReserva = function(facility_id){

    var params = {
        controller: 'reservasController',
        function: 'cargarOpcionesReserva',
        facility_id: facility_id
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        if(Data.result){

            $('#reservation_options')
                    .html(Data.result)
                    .show();

            $('#btn_search_availability').show();
        }

    });

};

addQuickClient = function(){

    var valid = true;

    $('#form_quick_client .is-invalid').removeClass('is-invalid');
    $('#quick_client_form_error').hide();

    var first_name = $('#quick_client_first_name').val().trim();
    var last_name = $('#quick_client_last_name').val().trim();
    var email = $('#quick_client_email').val().trim();

    if(first_name === ''){
        $('#quick_client_first_name').addClass('is-invalid');
        valid = false;
    }

    if(last_name === ''){
        $('#quick_client_last_name').addClass('is-invalid');
        valid = false;
    }

    if(email === ''){
        $('#quick_client_email').addClass('is-invalid');
        valid = false;
    }else if(!validateEmail(email)){
        $('#quick_client_email').addClass('is-invalid');
        valid = false;
    }

    if(!valid){

        $('#quick_client_form_error')
                .text('Debe completar correctamente los campos obligatorios.')
                .show();

        return;
    }


    disableBtn('btn_save_quick_client');
    $('#loading_quick_client').css('display', 'block');

    var form = $('#form_quick_client')[0];
    var formData = new FormData(form);

    formData.append('controller', 'reservasController');
    formData.append('function', 'addQuickClient');

    sendToServerDoc(formData, function(Data){

        enableBtn('btn_save_quick_client');
        $('#loading_quick_client').css('display', 'none');

        if(Data.result){

            var client = Data.extra;

            $('#user_id').append(
                    '<option value="' + client.id + '">' +
                    client.name + ' - ' + client.email +
                    '</option>'
                    );

            $('#user_id').val(client.id);

            var modal = bootstrap.Modal.getInstance(
                    document.getElementById('modal_quick_client')
                    );

            modal.hide();

            $('#form_quick_client')[0].reset();

        }else{

            afterReload(Data);
        }

    });

};

searchAvailability = function(){

    var valid = true;

    $('#form_reservation .is-invalid').removeClass('is-invalid');
    $('#reservation_options .is-invalid').removeClass('is-invalid');

    $('#reservation_form_error').hide();
    $('#reservation_availability').hide().empty();

    var facility_id = $('#facility_id').val();
    var user_id = $('#user_id').val();
    var people_count = parseInt($('#people_count').val()) || 0;

    var booking_type = $('#facility_id option:selected').data('booking-type');

    /*
     * Datos generales.
     */
    if(facility_id === ''){
        $('#facility_id').addClass('is-invalid');
        valid = false;
    }

    if(user_id === ''){
        $('#user_id').addClass('is-invalid');
        valid = false;
    }

    if(people_count < 1){
        $('#people_count').addClass('is-invalid');
        valid = false;
    }

    /*
     * Reserva por fechas.
     */
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

            $('#start_at').addClass('is-invalid');
            $('#end_at').addClass('is-invalid');

            valid = false;
        }
    }

    /*
     * Reserva por hora.
     */
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

    /*
     * El formulario principal contiene instalación,
     * cliente y número de personas.
     */
    var form = $('#form_reservation')[0];
    var formData = new FormData(form);

    /*
     * Las fechas/horas están en reservation_options,
     * fuera de form_reservation, así que las añadimos
     * manualmente.
     */
    if(booking_type === 'DATE_RANGE'){

        formData.append('start_at', $('#start_at').val());
        formData.append('end_at', $('#end_at').val());
    }

    if(booking_type === 'SPECIFIC_HOUR'){

        formData.append('reservation_date', $('#reservation_date').val());
        formData.append('reservation_hour', $('#reservation_hour').val());
        formData.append('duration_minutes', $('#reservation_duration').val());
    }

    formData.append('controller', 'reservasController');
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

continueReservation = function(){

    var facility_id = $('#facility_id').val();
    var user_id = $('#user_id').val();
    var people_count = parseInt($('#people_count').val()) || 0;

    var start_at = $('#reservation_confirm_start_at').val();
    var end_at = $('#reservation_confirm_end_at').val();
    var booking_type = $('#reservation_confirm_booking_type').val();
    var requested_units = parseInt($('#reservation_confirm_requested_units').val()) || 0;
    var facility_price_id = $('#reservation_confirm_facility_price_id').val();

    if(
            facility_id === '' ||
            user_id === '' ||
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
        controller: 'reservasController',
        function: 'prepareReservation',
        facility_id: facility_id,
        user_id: user_id,
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

confirmReservation = function () {

    $('#reservation_prepare_error').hide();

    var payment_option = $('input[name="reservation_payment_option"]:checked').val();

    if (!payment_option) {

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
        controller: 'reservasController',
        function: 'confirmReservation',
        facility_id: $('#confirm_facility_id').val(),
        user_id: $('#confirm_user_id').val(),
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

    sendToServer(valores, function (Data) {

        enableBtn('btn_confirm_reservation');
        $('#loading_reservation').css('display', 'none');

        if (Data.result) {

            /*
             * Más adelante, si requiere pasarela online,
             * aquí podremos redirigir a la URL devuelta.
             */
            if (Data.extra && Data.extra.payment_url) {
                window.location.href = Data.extra.payment_url;
                return;
            }

            Data.result = urlEnvironment + 'reservas?r=' + Date.now() + '#success';
        }

        afterReload(Data);

    });

};

changeReservationStatus = function(reservation_id){

    var status_id = $('#reservation_status_' + reservation_id).val();

    if(status_id === ''){
        return;
    }

    disableBtn('btn_status_reservation_' + reservation_id);
    $('#loading_status_reservation_' + reservation_id).css('display', 'block');
    

    var params = {
        controller: 'reservasController',
        function: 'changeReservationStatus',
        reservation_id: reservation_id,
        status_id: status_id,
        cancellation_reason: $('#cancellation_reason_' + reservation_id).val()
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        enableBtn('btn_status_reservation_' + reservation_id);
        $('#loading_status_reservation_' + reservation_id).css('display', 'none');

        if(Data.result){
            Data.result = urlEnvironment + 'reservas?r=' + Date.now() + '#success';
        }

        afterReload(Data);
    });
};

changeReservationPayment = function(reservation_id){

    var payment_method_id = $('#reservation_payment_' + reservation_id).val();

    $('#payment_reservation_error_' + reservation_id).hide();

    if(payment_method_id === ''){
        $('#payment_reservation_error_' + reservation_id)
                .text('Debe seleccionar un método de pago.')
                .show();

        return;
    }

    disableBtn('btn_payment_reservation_' + reservation_id);
    $('#loading_payment_reservation_' + reservation_id).css('display', 'block');

    var params = {
        controller: 'reservasController',
        function: 'changeReservationPayment',
        reservation_id: reservation_id,
        payment_method_id: payment_method_id
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        enableBtn('btn_payment_reservation_' + reservation_id);
        $('#loading_payment_reservation_' + reservation_id).css('display', 'none');

        if(Data.result){
            Data.result = urlEnvironment + 'reservas?r=' + Date.now() + '#success';
        }

        afterReload(Data);
    });
};


changeReservationStatusReason = function(reservation_id){

    var status_code = $('#reservation_status_' + reservation_id + ' option:selected').data('code');

    if(status_code === 'cancelled'){
        $('#cancellation_reason_container_' + reservation_id).show();
    }else{
        $('#cancellation_reason_container_' + reservation_id).hide();
        $('#cancellation_reason_' + reservation_id).val('');
    }
};

editReservation = function(reservation_id){

    disableBtn('btn_edit_reservation');
    $('#loading_edit_reservation').css('display', 'block');
    $('#reservation_edit_error').hide();

    var params = {
        controller: 'reservasController',
        function: 'editReservation',
        reservation_id: reservation_id,
        notes: $('#notes').val()
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        enableBtn('btn_edit_reservation');
        $('#loading_edit_reservation').css('display', 'none');

        if(Data.result){
            Data.result = urlEnvironment + 'editreserva/' + reservation_id + '?r=' + Date.now() + '#success';
        }

        afterReload(Data);
    });
};

changeEditReservationStatus = function(reservation_id){

    var status_id = parseInt($('#reservation_status_id').val());
    var status_code = $('#reservation_status_id option:selected').data('code');
    var cancellation_reason = $('#cancellation_reason').val().trim();

    $('#edit_reservation_status_error').hide().html('');

    if(status_code === 'cancelled' && cancellation_reason === ''){
        $('#edit_reservation_status_error').html('Debe indicar el motivo de cancelación.').show();
        return;
    }

    disableBtn('btn_edit_reservation_status');
    $('#loading_edit_reservation_status').css('display', 'block');

    var params = {
        controller: 'reservasController',
        function: 'changeReservationStatus',
        reservation_id: reservation_id,
        status_id: status_id,
        cancellation_reason: cancellation_reason
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        enableBtn('btn_edit_reservation_status');
        $('#loading_edit_reservation_status').css('display', 'none');

        if(Data.result){
            Data.result = urlEnvironment + 'editreserva/' + reservation_id + '?r=' + Date.now() + '#success';
        }

        afterReload(Data);
    });
};

changeEditReservationPayment = function(reservation_id){

    var payment_method_id = parseInt($('#payment_method_id').val());

    $('#edit_reservation_payment_error').hide().html('');

    if(!payment_method_id){
        $('#edit_reservation_payment_error').html('Debe seleccionar un método de pago.').show();
        return;
    }

    disableBtn('btn_edit_reservation_payment');
    $('#loading_edit_reservation_payment').css('display', 'block');

    var params = {
        controller: 'reservasController',
        function: 'changeReservationPayment',
        reservation_id: reservation_id,
        payment_method_id: payment_method_id
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        enableBtn('btn_edit_reservation_payment');
        $('#loading_edit_reservation_payment').css('display', 'none');

        if(Data.result){
            Data.result = urlEnvironment + 'editreserva/' + reservation_id + '?r=' + Date.now() + '#success';
        }

        afterReload(Data);
    });
};

addReservationGuest = function(reservation_id){

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
        controller: 'reservasController',
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
            Data.result = urlEnvironment + 'editreserva/' + reservation_id + '?r=' + Date.now() + '#success';
        }

        afterReload(Data);
    });
};

editReservationGuest = function(guest_id){

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
        controller: 'reservasController',
        function: 'editReservationGuest',
        reservation_id: $('#reservation_id').val(),
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
            Data.result = urlEnvironment + 'editreserva/' + $('#reservation_id').val() + '?r=' + Date.now() + '#success';
        }

        afterReload(Data);
    });
};

deleteReservationGuest = function(guest_id){

    disableBtn('btn_delete_guest_' + guest_id);
    $('#loading_delete_guest_' + guest_id).css('display', 'block');

    var reservation_id = $('#reservation_id').val();

    var params = {
        controller: 'reservasController',
        function: 'deleteReservationGuest',
        reservation_id: reservation_id,
        guest_id: guest_id
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        enableBtn('btn_delete_guest_' + guest_id);
        $('#loading_delete_guest_' + guest_id).css('display', 'none');

        if(Data.result){
            Data.result = urlEnvironment + 'editreserva/' + reservation_id + '?r=' + Date.now() + '#success';
        }

        afterReload(Data);
    });
};

cargarCalendarioReservas = function(){

    var calendarEl = document.getElementById('reservation_calendar');

    if(!calendarEl){
        return;
    }

    reservationCalendar = new FullCalendar.Calendar(calendarEl, {

        initialView: 'dayGridMonth',
        locale: 'es',
        firstDay: 1,
        height: 620,
        contentHeight: 540,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },

        buttonText: {
            today: 'Hoy',
            month: 'Mes',
            week: 'Semana',
            day: 'Día'
        },

        events: function(info, successCallback, failureCallback){

            $('#calendar_loading').css('display', 'block');

            var params = {
                controller: 'reservasController',
                function: 'loadCalendarReservations',
                start: info.startStr,
                end: info.endStr,
                facility_id: $('#calendar_facility_id').val()
            };

            var valores = JSON.stringify(params);

            sendToServer(valores, function(Data){

                $('#calendar_loading').css('display', 'none');

                if(Data.result){

                    successCallback(Data.extra);

                }else{

                    failureCallback();
                    afterReload(Data);
                }
            });
        },
        
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        },

        eventClick: function (info) {

            var event = info.event;
            var data = event.extendedProps;

            $('#calendar_reservation_user')
                    .text(data.user_name || '-')
                    .attr('href', 'editcliente/' + data.user_id);

            $('#calendar_reservation_facility')
                    .text(data.facility_name || '-')
                    .attr('href', 'editinstalacion/' + data.facility_id);

            $('#calendar_reservation_reference').text(data.reference || '-');
            $('#calendar_reservation_status').text(data.status_name || '-');

            $('#calendar_reservation_start').text(
                    formatCalendarDate(event.start)
                    );

            $('#calendar_reservation_end').text(
                    event.end ? formatCalendarDate(event.end) : '-'
                    );

            $('#calendar_reservation_link').attr(
                    'href',
                    'editreserva/' + data.reservation_id
                    );

            $('#modal_calendar_reservation').modal('show');
        }
    });

    reservationCalendar.render();

    $('#calendar_facility_id').on('change', function(){
        reservationCalendar.refetchEvents();
    });
};

formatCalendarDate = function(date){

    if(!date){
        return '-';
    }

    return date.toLocaleString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};