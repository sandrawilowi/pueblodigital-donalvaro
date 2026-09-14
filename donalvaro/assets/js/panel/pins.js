$(document).ready(function () {
    controlHash();
    controlPage();

});

controlPage = function(){
    
    var url = window.location.toString();
    var partsControl = url.split('/');
    var endControl = partsControl.pop();
    var parts = endControl.split('#');

    if(url.includes('/codigos-pin')){
        cargarDataTables('dt_pins');
        cargarDatosPin();
    }else if (url.includes('/auditpin')) {

        cargarDataTables('dt_audit');
    }
   
    
};

cargarDataTables = function (id) {

    const columnConfig = {
        "dt_pins": { total: 9, noSortable: [8],searching:false, serverside: false, buttons: true },
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

formFilter = function (id) {

    var form = $('#' + id)[0];
    var formData = new FormData(form);

    formData.append('controller', 'panelController');
    formData.append('function', 'formFilter');
    formData.append('form_id', id);

    sendToServerDoc(formData, function (Data) {

        $('#insertTableCerraduras').empty();
        $('#insertTableCerraduras').html(Data.result);
        cargarDataTables('dt_pins');

    });

};

cargarDatosPin = function () {

    $('#generate_pin').on('change', function(){

        if($(this).is(':checked')){

            $('#pin_code').val('').prop('disabled', true);
            $('#pin_code').next('.form-text').text('El PIN se generará automáticamente.');

        }else{

            $('#pin_code').prop('disabled', false).focus();
            $('#pin_code').next('.form-text').text('Introduzca manualmente el PIN de acceso.');
        }

    });


    $('#is_permanent').on('change', function(){

        if($(this).is(':checked')){

            $('#is_one_time').prop('checked', false);

            $('#valid_until')
                    .val('')
                    .prop('disabled', true)
                    .removeClass('is-invalid');

        }else{

            $('#valid_until').prop('disabled', false);
        }

    });


    $('#is_one_time').on('change', function(){

        if($(this).is(':checked')){

            $('#is_permanent').prop('checked', false);
            $('#valid_until').prop('disabled', false);

        }

    });

    $(document).on('input', '#pin_code', function () {
        this.value = this.value.replace(/\D/g, '');
    });

    $('#form_add_pin').on('input change', 'input, select, textarea', function () {
        $(this).removeClass('is-invalid');
        $('#pin_form_error').hide();
    });
};

addPin = function(){

    var valid = true;

    $('#form_add_pin .is-invalid').removeClass('is-invalid');
    $('#pin_form_error').hide();

    var facility_id = $('#facility_id').val();
    var generate_pin = $('#generate_pin').is(':checked');
    var pin_code = $('#pin_code').val().trim();
    var is_permanent = $('#is_permanent').is(':checked');
    var is_one_time = $('#is_one_time').is(':checked');
    var valid_from = $('#valid_from').val();
    var valid_until = $('#valid_until').val();
    var description = $('#description').val().trim();

    if(facility_id === ''){
        $('#facility_id').addClass('is-invalid');
        valid = false;
    }

    if(!generate_pin){

        var min_length = parseInt($('#pin_code').data('min-length'));
        var max_length = parseInt($('#pin_code').data('max-length'));

        if(pin_code === '' || !/^\d+$/.test(pin_code) || pin_code.length < min_length || pin_code.length > max_length){
            $('#pin_code').addClass('is-invalid');
            valid = false;
        }
    }

    if(valid_from === ''){
        $('#valid_from').addClass('is-invalid');
        valid = false;
    }

    if(!is_permanent && valid_until === ''){
        $('#valid_until').addClass('is-invalid');
        valid = false;
    }

    if(description === ''){
        $('#description').addClass('is-invalid');
        valid = false;
    }

    if(is_permanent && is_one_time){
        $('#pin_form_error').text('El PIN no puede ser permanente y de un solo uso al mismo tiempo.').show();
        return;
    }

    if(!is_permanent && valid_from !== '' && valid_until !== '' && valid_until <= valid_from){
        $('#valid_from, #valid_until').addClass('is-invalid');
        $('#pin_form_error').text('La fecha final debe ser posterior a la fecha inicial.').show();
        return;
    }

    if(!valid){
        $('#pin_form_error').text('Debe completar correctamente los campos obligatorios.').show();
        return;
    }


    disableBtn('btn_add_pin');
    $('#loading').css('display', 'block');

    var form = $('#form_add_pin')[0];
    var formData = new FormData(form);

    formData.append('controller', 'pinsController');
    formData.append('function', 'addPin');

    sendToServerDoc(formData, function(Data){

        enableBtn('btn_add_pin');
        $('#loading').css('display', 'none');
        if(Data.result){
            Data.result = urlEnvironment + 'codigos-pin?r=' + Date.now() + '#success';
        }

        afterReload(Data);
    });

};

revokePin = function(pin_id){

    disableBtn('btn_revoke_pin_' + pin_id);
    $('#loading_revoke_pin_' + pin_id).css('display', 'block');

    var params = {
        controller: 'pinsController',
        function: 'revokePin',
        pin_id: pin_id
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        enableBtn('btn_revoke_pin_' + pin_id);
        $('#loading_revoke_pin_' + pin_id).css('display', 'none');

        if(Data.result){
            Data.result = urlEnvironment + 'codigos-pin?r=' + Date.now() + '#success';
        }

        afterReload(Data);
    });
};
