var facilityGalleryFiles = [];
var facilityGalleryDeleteIds = [];

$(document).ready(function () {
    controlHash();
    controlPage();

});

controlPage = function(){
    
    var url = window.location.toString();
    var partsControl = url.split('/');
    var endControl = partsControl.pop();
    var parts = endControl.split('#');

    if (url.includes('/instalaciones')) {

        cargarDataTables('dt_instalacion');
        cargarDatosInstalacion();
        
    }else if (url.includes('/auditinstalacion')) {

        cargarDataTables('dt_audit');
    }
    else if(url.includes('/editinstalacion')){        
        cargarDatosEditInstalacion();
    }
    else if(url.includes('/disponibilidad-instalacion')){        
        cargarDisponibilidad();
    }
   
    
};

cargarDataTables = function (id) {

    const columnConfig = {
        "dt_instalacion": { total: 6, noSortable: [4,5],searching:false, serverside: false, buttons: true },
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
        cargarDataTables('dt_instalacion');

    });

};


cargarDatosInstalacion = function () {

    var dropzone = $('#facility_dropzone');
    var input = $('#facility_image');


    /*
     * Seleccionar imagen haciendo clic.
     */
    dropzone.on('click', function () {

        input.trigger('click');

    });


    /*
     * Cambiar imagen desde el preview.
     */
    $('#btn_change_facility_image').on('click', function () {

        input.trigger('click');

    });


    /*
     * Imagen seleccionada desde el explorador.
     */
    input.on('change', function () {

        if (!this.files || this.files.length === 0) {
            return;
        }

        var file = this.files[0];

        if (!validarImagenInstalacion(file)) {

            input.val('');

            return;
        }

        mostrarPreviewInstalacion(file);

    });


    /*
     * Cuando arrastramos un fichero sobre la zona.
     */
    dropzone.on('dragover', function (e) {

        e.preventDefault();
        e.stopPropagation();

        $(this).addClass('border-primary');

    });


    /*
     * Cuando salimos de la zona.
     */
    dropzone.on('dragleave', function (e) {

        e.preventDefault();
        e.stopPropagation();

        $(this).removeClass('border-primary');

    });


    /*
     * Cuando soltamos un fichero.
     */
    dropzone.on('drop', function (e) {

        e.preventDefault();
        e.stopPropagation();

        $(this).removeClass('border-primary');

        var files = e.originalEvent.dataTransfer.files;

        if (!files || files.length === 0) {
            return;
        }

        var file = files[0];

        if (!validarImagenInstalacion(file)) {
            return;
        }

        /*
         * Guardamos el fichero también en el input file
         * para poder enviarlo posteriormente.
         */
        var dataTransfer = new DataTransfer();

        dataTransfer.items.add(file);

        input[0].files = dataTransfer.files;

        mostrarPreviewInstalacion(file);

    });


    /*
     * Quitar imagen.
     */
    $('#btn_remove_facility_image').on('click', function () {

        input.val('');

        $('#facility_image_preview_img')
                .attr('src', '');

        $('#facility_image_preview').hide();

        dropzone.show();

    });


    /*
     * Evitamos valores negativos o decimales
     * en capacidad.
     */
    $('#facility_capacity').on('input', function () {

        var value = $(this).val();

        if (value < 1 && value !== '') {
            $(this).val('');
        }

    });


    $(document).on(
            'input change',
            '#facility_name, #facility_description',
            function () {

                $(this).removeClass('is-invalid');

                if ($('#form_new_facility .is-invalid').length === 0) {
                    $('#facility_form_error').hide();
                }

            }
    );

    $('#booking_enabled').on('change', function () {

        if ($(this).is(':checked')) {

            $('#booking_type_container').show();

        } else {

            $('#booking_type_id')
                    .val('')
                    .removeClass('is-invalid');

            $('#booking_type_container').hide();
        }

    });

    $('#form_new_facility').on('input change', 'input, select, textarea', function () {
        $(this).removeClass('is-invalid');
        $('#facility_form_error').hide();
    });

};


/*
 * Validación de imagen.
 */
validarImagenInstalacion = function (file) {

    var valid_types = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    if (!valid_types.includes(file.type)) {

        $('#facility_form_error')
                .text('El archivo seleccionado debe ser una imagen JPG, PNG o WEBP.')
                .show();

        return false;
    }

    return true;

};


/*
 * Mostrar preview.
 */
mostrarPreviewInstalacion = function (file) {

    var reader = new FileReader();

    reader.onload = function (e) {

        $('#facility_image_preview_img')
                .attr('src', e.target.result);

        $('#facility_dropzone').hide();

        $('#facility_image_preview').show();

    };

    reader.readAsDataURL(file);

};

addFacility = function () {

    var name = $('#facility_name').val().trim();
    var description = $('#facility_description').val().trim();
    var booking_enabled = $('#booking_enabled').is(':checked');
    var booking_type_id = $('#booking_type_id').val();

    var valid = true;

    $('#form_new_facility .is-invalid').removeClass('is-invalid');
    $('#facility_form_error').hide();

    if(name === ''){
        $('#facility_name').addClass('is-invalid');
        valid = false;
    }

    if(description === ''){
        $('#facility_description').addClass('is-invalid');
        valid = false;
    }

    if(booking_enabled && booking_type_id === ''){
        $('#booking_type_id').addClass('is-invalid');
        valid = false;
    }

    if(!valid){

        $('#facility_form_error')
                .text('Debe completar los campos obligatorios.')
                .show();

        return;
    }

    disableBtn('btn_guardar_facility');
    $('#loading').css('display', 'block');

    var form = $('#form_new_facility')[0];
    var formData = new FormData(form);

    formData.append('controller', 'facilitiesController');
    formData.append('function', 'addFacility');

    sendToServerDoc(formData, function (Data) {

        enableBtn('btn_guardar_facility');
        $('#loading').css('display', 'none');

        if(Data.result){
            Data.result = urlEnvironment + 'instalaciones?r=' + Date.now() + '#success';
        }

        afterReload(Data);

    });

};

activeFacility = function(id, value){
    
    var params = {
        controller: 'facilitiesController',
        function: 'activeFacility',
        id: id,
        value: value
    };
        
    var valores = JSON.stringify(params);
    sendToServer(valores, function(Data){

        if(Data.result){

            if(value==1){

                $('#btnActiveDevice'+id).removeClass('colorWarning');
                $('#btnActiveDevice'+id).addClass('colorAccent');
                $('#btnActiveDeviceLink'+id).attr('onclick', 'activeFacility('+id+',0)');

            }else{

                $('#btnActiveDevice'+id).removeClass('colorAccent');
                $('#btnActiveDevice'+id).addClass('colorWarning');
                $('#btnActiveDeviceLink'+id).attr('onclick', 'activeFacility('+id+',1)');
            }


        }

    });
    
};

activeFacilityPrice = function(id, price_id, value){

    var params = {
        controller: 'facilitiesController',
        function: 'activeFacilityPrice',
        id: id,
        price_id: price_id,
        value: value
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        if(Data.result){

            if(value == 1){

                $('#btnActivePrice'+price_id).removeClass('colorWarning').addClass('colorAccent');
                $('#btnActivePriceLink'+price_id).attr('onclick', 'activeFacilityPrice('+id+','+price_id+',0)');
                $('#badgeInactivePrice'+price_id).hide();

            }else{

                $('#btnActivePrice'+price_id).removeClass('colorAccent').addClass('colorWarning');
                $('#btnActivePriceLink'+price_id).attr('onclick', 'activeFacilityPrice('+id+','+price_id+',1)');
                $('#badgeInactivePrice'+price_id).show();

            }

        }

    });

};



actualizarInstalacion = function(id){

    disableBtn('btn_save');
    $('#loading').css('display', 'block');

    var name = $('#name').val().trim();
    var description = $('#description').val().trim();
    var booking_enabled = $('#booking_enabled').is(':checked');
    var booking_type_id = $('#booking_type_id').val();
    var booking_type_code = $('#booking_type_id option:selected').data('code');
    var check_in_time = $('#check_in_time').val();
    var check_out_time = $('#check_out_time').val();

    var valid = true;

    $('#form_data .is-invalid').removeClass('is-invalid');
    $('#facility_form_error').hide();

    if(name === ''){
        $('#name').addClass('is-invalid');
        valid = false;
    }

    if(description === ''){
        $('#description').addClass('is-invalid');
        valid = false;
    }

    if(booking_enabled && booking_type_id === ''){
        $('#booking_type_id').addClass('is-invalid');
        valid = false;
    }

    if(booking_enabled && booking_type_code === 'DATE_RANGE'){

        if(check_in_time === ''){
            $('#check_in_time').addClass('is-invalid');
            valid = false;
        }

        if(check_out_time === ''){
            $('#check_out_time').addClass('is-invalid');
            valid = false;
        }
    }

    if(!valid){

        $('#facility_form_error')
                .text('Debe completar los campos obligatorios.')
                .show();

        enableBtn('btn_save');
        $('#loading').css('display', 'none');

        return;
    }

    var form = $('#form_data')[0];
    var formData = new FormData(form);

    formData.append('controller', 'facilitiesController');
    formData.append('function', 'actualizarInstalacion');
    formData.append('id', id);

    sendToServerDoc(formData, function(Data){

        enableBtn('btn_save');
        $('#loading').css('display', 'none');

        if(Data.result){
            Data.result = urlEnvironment + 'editinstalacion/' + id + '?r=' + Date.now() + '#success';
        }

        afterReload(Data);
    });
};

addFacilityPrice = function(id){
    
    var valid = true;

    $('#form_add_price .is-invalid').removeClass('is-invalid');
    $('#price_form_error').hide();

    var name = $('#price_name').val().trim();
    var price = $('#price_value').val();
    var billing_unit_type_id = $('#billing_unit_type_id').val();
    var billing_period_id = $('#billing_period_id').val();
    var valid_from = $('#price_valid_from').val();
    var valid_until = $('#price_valid_until').val();

    if (name === '') {
        $('#price_name').addClass('is-invalid');
        valid = false;
    }

    if (price === '' || parseFloat(price) < 0) {
        $('#price_value').addClass('is-invalid');
        valid = false;
    }

    if (billing_unit_type_id === '') {
        $('#billing_unit_type_id').addClass('is-invalid');
        valid = false;
    }

    if (billing_period_id === '') {
        $('#billing_period_id').addClass('is-invalid');
        valid = false;
    }

    if (valid_from !== '' && valid_until !== '' && valid_until < valid_from) {
        $('#price_valid_from, #price_valid_until').addClass('is-invalid');
        $('#price_form_error').text('La fecha final no puede ser anterior a la fecha inicial.').show();
        return;
    }

    if (!valid) {
        $('#price_form_error').text('Debe completar los campos obligatorios.').show();
        return;
    }

    disableBtn('btn_add_price');
    $('#loading-modal-add-price').css('display', 'block');

    var form = $('#form_add_price')[0];
    var formData = new FormData(form);

    formData.append('controller', 'facilitiesController');
    formData.append('function', 'addFacilityPrice');
    formData.append('id', id);

    sendToServerDoc(formData, function(Data){
        enableBtn('btn_add_price');
        $('#loading-modal-add-price').css('display', 'none');
        if(Data.result){
            Data.result = urlEnvironment + 'editinstalacion/'+id+'?r=' + Date.now() + '#success';
            
        }
        afterReload(Data);
    });
};

editFacilityPrice = function(id, price_id){
    
    var valid = true;

    $('#form_edit_price'+price_id+' .is-invalid').removeClass('is-invalid');
    $('#price_edit_form_error_'+price_id).hide();

    var name = $('#price_name_'+price_id).val().trim();
    var price = $('#price_value_'+price_id).val();
    var billing_unit_type_id = $('#billing_unit_type_id_'+price_id).val();
    var billing_period_id = $('#billing_period_id_'+price_id).val();
    var valid_from = $('#price_valid_from_'+price_id).val();
    var valid_until = $('#price_valid_until_'+price_id).val();

    if (name === '') {
        $('#price_name_'+price_id).addClass('is-invalid');
        valid = false;
    }

    if (price === '' || parseFloat(price) < 0) {
        $('#price_value_'+price_id).addClass('is-invalid');
        valid = false;
    }

    if (billing_unit_type_id === '') {
        $('#billing_unit_type_id_'+price_id).addClass('is-invalid');
        valid = false;
    }

    if (billing_period_id === '') {
        $('#billing_period_id_'+price_id).addClass('is-invalid');
        valid = false;
    }

    if (valid_from !== '' && valid_until !== '' && valid_until < valid_from) {
        $('#price_valid_from_'+price_id+', #price_valid_until_'+price_id).addClass('is-invalid');
        $('#price_edit_form_error_'+price_id).text('La fecha final no puede ser anterior a la fecha inicial.').show();
        return;
    }

    if (!valid) {
        $('#price_edit_form_error_'+price_id).text('Debe completar los campos obligatorios.').show();
        return;
    }

    disableBtn('btn_edit_price_'+price_id);
    $('#loading-modal-edit-price').css('display', 'block');

    var form = $('#form_edit_price'+price_id)[0];
    var formData = new FormData(form);

    formData.append('controller', 'facilitiesController');
    formData.append('function', 'editFacilityPrice');
    formData.append('id', id);
    formData.append('price_id', price_id);

    sendToServerDoc(formData, function(Data){
        enableBtn('btn_edit_price_'+price_id);
        $('#loading-modal-edit-price').css('display', 'none');
        if(Data.result){
            Data.result = urlEnvironment + 'editinstalacion/'+id+'?r=' + Date.now() + '#success';
            
        }
        afterReload(Data);
    });
    
};

deleteFacilityPrice = function(id, price_id){
    
    disableBtn('btn_delete_price_'+price_id);
    $('#loading-modal-delete-price').css('display', 'block');
    
    var params = {
        controller: 'facilitiesController',
        function: 'deleteFacilityPrice',
        id: id,
        price_id: price_id
    };

    var valores = JSON.stringify(params);
    sendToServer(valores, function(Data){

        enableBtn('btn_delete_price_'+price_id);
        $('#loading-modal-delete-price').css('display', 'none');
        if(Data.result){
            Data.result = urlEnvironment + 'editinstalacion/'+id+'?r=' + Date.now() + '#success';
            
        }

        afterReload(Data);
        
    });
    
};

addFacilityDevice = function(id){
    
    var device_id = $('#ttlock_device_id').val();

    $('#form_add_device .is-invalid').removeClass('is-invalid');
    $('#device_form_error').hide();

    if (device_id === '') {
        $('#ttlock_device_id').addClass('is-invalid');
        $('#device_form_error').text('Debe seleccionar una cerradura.').show();
        return;
    }
    
    disableBtn('btn_guardar_facility');
    $('#loading-modal-add-device').css('display', 'block');
    
    var form = $('#form_add_device')[0];
    var formData = new FormData(form);
    formData.append('controller', 'facilitiesController');
    formData.append('function', 'addFacilityDevice');
    formData.append('id', id);

    sendToServerDoc(formData, function(Data){
        enableBtn('btn_guardar_facility');
        $('#loading-modal-add-device').css('display', 'none');
        if(Data.result){
            Data.result = urlEnvironment + 'editinstalacion/'+id+'?r=' + Date.now() + '#success';
            
        }
        afterReload(Data);
    });
    
};

removeFacilityDevice = function(id, device_id){
    
    disableBtn('btn_remove_device_'+device_id);
    
    var params = {
        controller: 'facilitiesController',
        function: 'removeFacilityDevice',
        id: id,
        device_id: device_id
    };

    var valores = JSON.stringify(params);
    sendToServer(valores, function(Data){

        enableBtn('btn_remove_device_'+device_id);
        if(Data.result){
            Data.result = urlEnvironment + 'editinstalacion/'+id+'?r=' + Date.now() + '#success';
            
        }

        afterReload(Data);
        
    });
    
};

addFacilityService = function(id){
    
    var service_ids = $('#service_ids').val() || [];

    $('#form_add_service .is-invalid').removeClass('is-invalid');
    $('#service_form_error').hide();

    if (service_ids.length === 0) {
        $('#service_ids').addClass('is-invalid');
        $('#service_form_error').text('Debe seleccionar al menos un servicio.').show();
        return;
    }
    
    disableBtn('btn_add_service');
    $('#loading-modal-add-service').css('display', 'block');
    
    var form = $('#form_add_service')[0];
    var formData = new FormData(form);
    formData.append('controller', 'facilitiesController');
    formData.append('function', 'addFacilityService');
    formData.append('id', id);

    sendToServerDoc(formData, function(Data){
        enableBtn('btn_add_service');
        $('#loading-modal-add-service').css('display', 'none');
        if(Data.result){
            Data.result = urlEnvironment + 'editinstalacion/'+id+'?r=' + Date.now() + '#success';
            
        }
        afterReload(Data);
    });
    
};

removeFacilityService = function(id, service_id){
    
    disableBtn('btn_remove_service_'+service_id);
    
    var params = {
        controller: 'facilitiesController',
        function: 'removeFacilityService',
        id: id,
        service_id: service_id
    };

    var valores = JSON.stringify(params);
    sendToServer(valores, function(Data){

        enableBtn('btn_remove_service_'+service_id);
        if(Data.result){
            Data.result = urlEnvironment + 'editinstalacion/'+id+'?r=' + Date.now() + '#success';
            
        }

        afterReload(Data);
        
    });
    
};

addFacilityPaymentMethod = function(id){    
    
    var payment_method_ids = $('#payment_method_ids').val() || [];

    $('#form_add_payment_method .is-invalid').removeClass('is-invalid');
    $('#payment_method_form_error').hide();

    if (payment_method_ids.length === 0) {
        $('#payment_method_ids').addClass('is-invalid');
        $('#payment_method_form_error').text('Debe seleccionar al menos un método de pago.').show();
        return;
    }

    disableBtn('btn_add_payment_method');
    $('#loading-modal-add-payment').css('display', 'block');
    
    var form = $('#form_add_payment_method')[0];
    var formData = new FormData(form);
    formData.append('controller', 'facilitiesController');
    formData.append('function', 'addFacilityPaymentMethod');
    formData.append('id', id);

    sendToServerDoc(formData, function(Data){
        enableBtn('btn_add_payment_method');
        $('#loading-modal-add-payment').css('display', 'none');
        if(Data.result){
            Data.result = urlEnvironment + 'editinstalacion/'+id+'?r=' + Date.now() + '#success';
            
        }
        afterReload(Data);
    });
    
};

removeFacilityPaymentMethod = function(id, method_id){
    
    disableBtn('btn_remove_payment_method_'+method_id);
    
    var params = {
        controller: 'facilitiesController',
        function: 'removeFacilityPaymentMethod',
        id: id,
        method_id: method_id
    };

    var valores = JSON.stringify(params);
    sendToServer(valores, function(Data){

        enableBtn('btn_remove_payment_method_'+method_id);
        if(Data.result){
            Data.result = urlEnvironment + 'editinstalacion/'+id+'?r=' + Date.now() + '#success';
            
        }

        afterReload(Data);
        
    });
};


deleteFacility = function(id){
    
    disableBtn('btn_delete');
    $('#loading-delete').css('display', 'block');
    
    var params = {
        controller: 'facilitiesController',
        function: 'deleteFacility',
        id: id
    };

    var valores = JSON.stringify(params);
    sendToServer(valores, function(Data){

        enableBtn('btn_delete');
        $('#loading-delete').css('display', 'none');
        if(Data.result){
            Data.result = urlEnvironment + 'instalaciones?r=' + Date.now() + '#success';
            
        }

        afterReload(Data);
        
    });
    
};

cargarDatosEditInstalacion = function(){
    
    $('#facility_cover_dropzone').on('click', function(){
        document.getElementById('facility_cover_image').click();
    });

    $('#facility_cover_image').on('change', function(){

        if (!this.files || this.files.length === 0) {
            return;
        }

        var file = this.files[0];

        if (!validarImagenInstalacion(file)) {
            $(this).val('');
            return;
        }

        mostrarPreviewPortada(file);
    });


    $('#facility_cover_dropzone').on('dragover', function(e){
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('border-primary');
    });

    $('#facility_cover_dropzone').on('dragleave', function(e){
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('border-primary');
    });

    $('#facility_cover_dropzone').on('drop', function(e){

        e.preventDefault();
        e.stopPropagation();

        $(this).removeClass('border-primary');

        var files = e.originalEvent.dataTransfer.files;

        if (!files || files.length === 0) {
            return;
        }

        var file = files[0];

        if (!validarImagenInstalacion(file)) {
            return;
        }

        var dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);

        $('#facility_cover_image')[0].files = dataTransfer.files;

        mostrarPreviewPortada(file);
    });


    $('#btn_remove_cover_preview').on('click', function(){

        $('#facility_cover_image').val('');
        $('#facility_cover_preview_img').attr('src', '');
        $('#facility_cover_preview').hide();

    });


    cargarGaleriaInstalacion();

    $('#booking_enabled').on('change', function () {

        if ($(this).is(':checked')) {

            $('#booking_type_container').show();

        } else {

            $('#booking_type_id')
                    .val('')
                    .removeClass('is-invalid');

            $('#booking_type_container').hide();
        }

    });


    $('#booking_enabled').on('change', function () {
        toggleBookingHourConfig();
    });

    $('#booking_type_id').on('change', function () {
        toggleBookingHourConfig();
    });

    toggleBookingHourConfig();

};

toggleBookingHourConfig = function(){

    var booking_enabled = $('#booking_enabled').is(':checked');
    var booking_type_code = $('#booking_type_id option:selected').data('code');

    if(!booking_enabled || !booking_type_code){

        $('#booking_hour_config').hide();
        $('#booking_date_range_config').hide();

        return;
    }

    if(booking_type_code === 'SPECIFIC_HOUR'){

        $('#booking_hour_config').show();
        $('#booking_date_range_config').hide();

    }else if(booking_type_code === 'DATE_RANGE'){

        $('#booking_hour_config').hide();
        $('#booking_date_range_config').show();

    }else{

        $('#booking_hour_config').hide();
        $('#booking_date_range_config').hide();
    }
};


mostrarPreviewPortada = function(file){

    var reader = new FileReader();

    reader.onload = function(e){
        $('#facility_cover_preview_img').attr('src', e.target.result);
        $('#facility_cover_preview').show();
    };

    reader.readAsDataURL(file);
};

cargarGaleriaInstalacion = function () {

    $('#facility_gallery_dropzone').on('click', function () {
        document.getElementById('facility_gallery_images').click();
    });

    $('#facility_gallery_images').on('change', function () {

        if (!this.files || this.files.length === 0) {
            return;
        }

        addGalleryFiles(this.files);
    });


    $('#facility_gallery_dropzone').on('dragover', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('border-primary');
    });

    $('#facility_gallery_dropzone').on('dragleave', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('border-primary');
    });

    $('#facility_gallery_dropzone').on('drop', function (e) {

        e.preventDefault();
        e.stopPropagation();

        $(this).removeClass('border-primary');

        var files = e.originalEvent.dataTransfer.files;

        if (!files || files.length === 0) {
            return;
        }

        addGalleryFiles(files);
    });

};

addGalleryFiles = function(files){

    for (var i = 0; i < files.length; i++) {

        if (!validarImagenInstalacion(files[i])) {
            continue;
        }

        facilityGalleryFiles.push(files[i]);
    }

    actualizarInputGaleria();
    mostrarPreviewGaleria();
};

actualizarInputGaleria = function(){

    var dataTransfer = new DataTransfer();

    facilityGalleryFiles.forEach(function(file){
        dataTransfer.items.add(file);
    });

    $('#facility_gallery_images')[0].files = dataTransfer.files;
};

mostrarPreviewGaleria = function(){

    $('#facility_gallery_preview').html('');

    facilityGalleryFiles.forEach(function(file, index){

        var reader = new FileReader();

        reader.onload = function(e){

            var html = `
                <div class="col-md-4 mb-3">
                    <div class="position-relative">

                        <img src="${e.target.result}"
                             class="img-fluid rounded w-100"
                             style="height:160px; object-fit:cover;">

                        <i class="fa-solid fa-xmark
                                  position-absolute top-0 end-0
                                  m-2 bg-white colorError
                                  rounded p-2 cursor_pointer"
                           role="button"
                           aria-label="Quitar imagen"
                           onclick="removeGalleryImage(${index});">
                        </i>

                    </div>
                </div>
            `;

            $('#facility_gallery_preview').append(html);
        };

        reader.readAsDataURL(file);
    });
};

removeGalleryImage = function(index){

    facilityGalleryFiles.splice(index, 1);

    actualizarInputGaleria();
    mostrarPreviewGaleria();
};

mostrarPreviewImagenGaleria = function(file){

    var reader = new FileReader();

    reader.onload = function(e){

        var html = `
            <div class="col-md-4 mb-3">
                <img src="${e.target.result}"
                     class="img-fluid rounded w-100"
                     style="height:160px; object-fit:cover;">
            </div>
        `;

        $('#facility_gallery_preview').append(html);
    };

    reader.readAsDataURL(file);
};

saveFacilityImages = function(facility_id){

    $('#facility_images_error').hide();    

    var formData = new FormData();

    formData.append('controller', 'facilitiesController');
    formData.append('function', 'saveFacilityImages');
    formData.append('facility_id', facility_id);
    formData.append('delete_gallery_ids', JSON.stringify(facilityGalleryDeleteIds));

    var cover = $('#facility_cover_image')[0].files;

    if (cover && cover.length > 0) {
        formData.append('facility_cover_image', cover[0]);
    }

    facilityGalleryFiles.forEach(function(file){
        formData.append('facility_gallery_images[]', file);
    });

    if (
            (!cover || cover.length === 0)
            && facilityGalleryFiles.length === 0
            && facilityGalleryDeleteIds.length === 0
            ) {
        $('#facility_images_error')
                .text('No hay ningún cambio en las imágenes.')
                .show();

        return;
    }

    disableBtn('btn_save_facility_images');
    $('#loading-images').css('display', 'block');

    sendToServerDoc(formData, function(Data){

        enableBtn('btn_save_facility_images');
        $('#loading-images').css('display', 'none');

        if(Data.result){
            Data.result = urlEnvironment + 'editinstalacion/' + facility_id + '?r=' + Date.now() + '#success';
        }

        afterReload(Data);
    });
};


markExistingGalleryImageForDelete = function(image_id){

    if (!facilityGalleryDeleteIds.includes(image_id)) {
        facilityGalleryDeleteIds.push(image_id);
    }

    $('#facility_gallery_image_' + image_id + ' img').css('opacity', '0.35');

    $('#delete_badge_gallery_image_' + image_id).show();

    $('#btn_delete_gallery_image_' + image_id).hide();
    $('#btn_restore_gallery_image_' + image_id).show();
};

restoreExistingGalleryImage = function(image_id){

    facilityGalleryDeleteIds = facilityGalleryDeleteIds.filter(function(id){
        return id !== image_id;
    });

    $('#facility_gallery_image_' + image_id + ' img').css('opacity', '1');

    $('#delete_badge_gallery_image_' + image_id).hide();

    $('#btn_restore_gallery_image_' + image_id).hide();
    $('#btn_delete_gallery_image_' + image_id).show();
};

cargarDisponibilidad = function(){
    
    var calendarEl = document.getElementById('availability_calendar');

    if(!calendarEl){
        return;
    }

    var booking_type = calendarEl.dataset.bookingType;

    if(booking_type === 'SPECIFIC_HOUR'){
        $('#availability_hours').show();
        cargarDisponibilidadHoras(calendarEl);
    }

    if (booking_type === 'DATE_RANGE') {
        $('#availability_hours').hide();
        cargarDisponibilidadFechas(calendarEl);
    }

    $('#form_add_availability').on('input change', 'input, select, textarea', function () {
        $(this).removeClass('is-invalid');
        $('#availability_form_error').hide();
    });
    
};

cargarDisponibilidadHoras = function(calendarEl){

    var facility_id = calendarEl.dataset.facilityId;

    var calendar = new FullCalendar.Calendar(calendarEl, {

        locale: 'es',
        firstDay: 1,
        initialView: 'timeGridWeek',

        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'timeGridWeek'
        },

        allDaySlot: false,
        selectable: true,
        editable: false,
        height: 500,

        events: function(info, successCallback, failureCallback){

            cargarEventosDisponibilidad(
                    info,
                    successCallback,
                    failureCallback,
                    facility_id
            );

        },
        eventClick: function (info) {

            var event = info.event;
            var occupied_units = parseInt(event.extendedProps.occupied_units || 0);

            if (occupied_units === 0) {

                abrirModalEliminarDisponibilidad(
                        event.extendedProps.availability_id,
                        event.extendedProps.segment_start,
                        event.extendedProps.segment_end
                        );

            } else {

                cargarReservasDisponibilidad(
                        facility_id,
                        event.extendedProps.segment_start,
                        event.extendedProps.segment_end
                        );
            }
        }

    });

    calendar.render();
};

cargarDisponibilidadFechas = function (calendarEl) {

    var facility_id = calendarEl.dataset.facilityId;

    var calendar = new FullCalendar.Calendar(calendarEl, {

        locale: 'es',
        firstDay: 1,
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth'
        },

        selectable: true,
        editable: false,
        height: 500,
        events: function (info, successCallback, failureCallback) {

            cargarEventosDisponibilidad(
                    info,
                    successCallback,
                    failureCallback,
                    facility_id
                    );

        },
        eventClick: function (info) {

            var event = info.event;
            var occupied_units = parseInt(event.extendedProps.occupied_units || 0);

            if (occupied_units === 0) {

                abrirModalEliminarDisponibilidad(
                        event.extendedProps.availability_id,
                        event.startStr,
                        event.endStr
                        );

            } else {

                cargarReservasDisponibilidad(
                        facility_id,
                        event.startStr,
                        event.endStr
                        );
            }
        }

    });

    calendar.render();

};

addAvailability = function(facility_id){

    var valid = true;

    $('#form_add_availability .is-invalid').removeClass('is-invalid');
    $('#availability_form_error').hide();

    var date_from = $('#availability_date_from').val();
    var date_until = $('#availability_date_until').val();
    var booking_type = $('#availability_booking_type').val();
    var days_selected = $('.availability-day:checked').length;

    if(date_from === ''){
        $('#availability_date_from').addClass('is-invalid');
        valid = false;
    }

    if(date_until === ''){
        $('#availability_date_until').addClass('is-invalid');
        valid = false;
    }

    if(date_from !== '' && date_until !== '' && date_until < date_from){
        $('#availability_date_from, #availability_date_until').addClass('is-invalid');
        valid = false;
    }

    if(days_selected === 0){
        valid = false;
    }

    if(booking_type === 'SPECIFIC_HOUR'){

        var start_time = $('#availability_start_time').val();
        var end_time = $('#availability_end_time').val();

        if(start_time === ''){
            $('#availability_start_time').addClass('is-invalid');
            valid = false;
        }

        if(end_time === ''){
            $('#availability_end_time').addClass('is-invalid');
            valid = false;
        }

        if(start_time !== '' && end_time !== '' && end_time <= start_time){
            $('#availability_start_time, #availability_end_time').addClass('is-invalid');
            valid = false;
        }
    }

    if(!valid){

        $('#availability_form_error')
                .text('Debe completar correctamente los campos obligatorios.')
                .show();

        return;
    }

    disableBtn('btn_save_availability');
    $('#loading_availability').css('display', 'block');

    var form = $('#form_add_availability')[0];
    var formData = new FormData(form);

    formData.append('controller', 'facilitiesController');
    formData.append('function', 'addAvailability');
    formData.append('facility_id', facility_id);

    sendToServerDoc(formData, function(Data){

        enableBtn('btn_save_availability');
        $('#loading_availability').css('display', 'none');
        disableSelectedModal('modal_add_availability');

        if(Data.result){
            Data.result = urlEnvironment + 'disponibilidad-instalacion/'+facility_id+'?r=' + Date.now() + '#success';
        }

        afterReload(Data);
    });

};

cargarEventosDisponibilidad = function(info, successCallback, failureCallback, facility_id){

    var params = {
        controller: 'facilitiesController',
        function: 'getAvailabilityCalendar',
        facility_id: facility_id,
        start: info.startStr,
        end: info.endStr
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        if(Data.result){
            successCallback(Data.extra);
        }else{
            failureCallback();
        }

    });

};

cargarReservasDisponibilidad = function(facility_id, start_at, end_at){

    var params = {
        controller: 'facilitiesController',
        function: 'getAvailabilityReservations',
        facility_id: facility_id,
        start_at: start_at,
        end_at: end_at
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        if(Data.result){

            $('#availability_reservations_content').html(Data.extra);

            var modal = new bootstrap.Modal(
                    document.getElementById('modal_availability_reservations')
                    );

            modal.show();
        }

    });
};

abrirModalEliminarDisponibilidad = function(availability_id, start_at, end_at){

    $('#delete_availability_id').val(availability_id);
    $('#delete_availability_start').val(start_at);
    $('#delete_availability_end').val(end_at);

    $('#delete_availability_error').hide().html('');

    var start = new Date(start_at);

    var texto = start.toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });

    if(start_at.includes('T')){

        var end = new Date(end_at);

        texto += ' de ' +
                start.toLocaleTimeString('es-ES', {
                    hour: '2-digit',
                    minute: '2-digit'
                }) +
                ' a ' +
                end.toLocaleTimeString('es-ES', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
    }

    $('#delete_availability_period').text(texto);

    var modal = new bootstrap.Modal(
            document.getElementById('modal_delete_availability')
            );

    modal.show();
};

deleteAvailability = function(facility_id){

    var availability_id = $('#delete_availability_id').val();
    var start_at = $('#delete_availability_start').val();
    var end_at = $('#delete_availability_end').val();

    if(availability_id === '' || start_at === '' || end_at === ''){
        return;
    }

    disableBtn('btn_delete_availability');

    var params = {
        controller: 'facilitiesController',
        function: 'deleteAvailability',
        facility_id: facility_id,
        availability_id: availability_id,
        start_at: start_at,
        end_at: end_at
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        enableBtn('btn_delete_availability');

        if(Data.result){
            Data.result = urlEnvironment + 'disponibilidad-instalacion/' + facility_id + '?r=' + Date.now() + '#success';
        }

        afterReload(Data);

    });
};

