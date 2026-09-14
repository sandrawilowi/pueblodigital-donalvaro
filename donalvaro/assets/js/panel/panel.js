$(document).ready(function () {
    controlHash();
    controlPage();

});

controlPage = function(){
    
    var url = window.location.toString();
    var partsControl = url.split('/');
    var endControl = partsControl.pop();
    var parts = endControl.split('#');

    if (url.includes('/dashboard')) {


    } else if (url.includes('/perfil')) {
        cargarDatosPerfil();
        cargarFotoPerfil();
    } else if (url.includes('/auditoria')) {
        cargarDatosAuditoria();
    }
    else if (url.includes('/historial-accesos')) {
        cargarDataTables('dt_access_history');
    }

    
};

cargarDataTables = function (id) {

    const columnConfig = {
        "dt_audit": { total: 9, noSortable: [3,4,5,6,7],searching:true, serverside: false, buttons: true },
        "dt_audit_all": { total: 10, noSortable: [4,5,6,7,8],searching:true, serverside: false, buttons: true },
        "dt_access_history": { total: 9, noSortable: [],searching:true, serverside: false, buttons: true }
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

        $('#insertTableHistorial').empty();
        $('#insertTableHistorial').html(Data.result);
        cargarDataTables('dt_access_history');

    });
};

abrirCerradura = function (id) {

    disableBtn('btn_unlock_lock');
    disableBtn('btn_lock_lock');

    var params = new Object();
    params.controller = 'dispositivosController';
    params.function = 'abrirCerradura';
    params.id = id;

    var valores = JSON.stringify(params);

    sendToServer(valores, function (Data) {

        enableBtn('btn_unlock_lock');
        enableBtn('btn_lock_lock');

        afterReload(Data);

    });
};

confirmPendingReservation = function(reservation_id){
    
    disableBtn('btn_confirm_pending_reservation_'+reservation_id);
    $('#loading_confirm_reservation_'+reservation_id).css('display', 'block');

    var params = {
        controller: 'panelController',
        function: 'confirmPendingReservation',
        reservation_id: reservation_id
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        enableBtn('btn_confirm_pending_reservation_'+reservation_id);
        $('#loading_confirm_reservation_'+reservation_id).css('display', 'none');

        if(Data.result){
            Data.result = urlEnvironment + 'dashboard?r=' + Date.now() + '#success';
        }

        afterReload(Data);

    });
};

cargarDatosPerfil = function () {

    $('#form_profile').on('input change', 'input, select, textarea', function () {
        $(this).removeClass('is-invalid');
        $('#profile_form_error').hide();
    });

    $('#user_country_id').on('change', function () {

        var country_id = $(this).val();

        $('#user_province_id')
                .empty()
                .append('<option value="">Seleccione primero un país</option>')
                .prop('disabled', true);

        if (country_id === '') {
            return;
        }

        cargarProvinciasPais(country_id, '#user_province_id');
    });

};

cargarFotoPerfil = function () {

    var dropzone = $('#profile_dropzone');
    var input = $('#profile_image');

    dropzone.on('click', function () {
        input.click();
    });

    $('#btn_change_profile_image').on('click', function () {
        input.click();
    });

    input.on('change', function () {

        if(this.files && this.files[0]){

            $('#remove_profile_image').val('0');
            previewFotoPerfil(this.files[0]);
        }

    });

    dropzone.on('dragover', function (e) {

        e.preventDefault();
        e.stopPropagation();

    });

    dropzone.on('drop', function (e) {

        e.preventDefault();
        e.stopPropagation();

        var files = e.originalEvent.dataTransfer.files;

        if(files.length > 0){

            input[0].files = files;
            $('#remove_profile_image').val('0');

            previewFotoPerfil(files[0]);
        }

    });

    $('#btn_remove_profile_image').on('click', function () {

        input.val('');
        $('#remove_profile_image').val('1');

        $('#profile_image_preview_img').attr('src', '');
        $('#profile_image_preview').hide();
        $('#profile_dropzone').show();

    });

};

previewFotoPerfil = function(file){

    $('#profile_image_form_error').hide();

    var allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    if(!allowedTypes.includes(file.type)){

        $('#profile_image_form_error')
                .text('El formato de imagen no es válido. Solo se permiten JPG, PNG y WEBP.')
                .show();

        $('#profile_image').val('');

        return;
    }

    var reader = new FileReader();

    reader.onload = function(e){

        $('#profile_image_preview_img').attr('src', e.target.result);
        $('#profile_dropzone').hide();
        $('#profile_image_preview').show();

    };

    reader.readAsDataURL(file);
};

actualizarPerfil = function(){

    var first_name = $('#first_name').val().trim();
    var last_name = $('#last_name').val().trim();
    var email = $('#email').val().trim();

    var valid = true;

    $('#form_profile .is-invalid').removeClass('is-invalid');
    $('#profile_form_error').hide();

    if(first_name === ''){

        $('#first_name').addClass('is-invalid');
        valid = false;
    }

    if(last_name === ''){

        $('#last_name').addClass('is-invalid');
        valid = false;
    }

    if(email === '' || !validateEmail(email)){

        $('#email').addClass('is-invalid');
        valid = false;
    }

    if(!valid){

        $('#profile_form_error')
                .text('Debe completar correctamente los campos obligatorios.')
                .show();

        return;
    }

    disableBtn('btn_save_profile');
    $('#loading_profile').css('display', 'block');

    var form = $('#form_profile')[0];
    var formData = new FormData(form);

    formData.append('controller', 'usuariosController');
    formData.append('function', 'actualizarPerfil');

    sendToServerDoc(formData, function(Data){

        enableBtn('btn_save_profile');
        $('#loading_profile').css('display', 'none');

        if(Data.result){
            Data.result = urlEnvironment + 'perfil?r=' + Date.now() + '#success';
        }

        afterReload(Data);

    });

};

actualizarPasswordPerfil = function(){

    var current_password = $('#current_password').val();
    var new_password = $('#new_password').val();
    var new_password_confirm = $('#new_password_confirm').val();

    var valid = true;

    $('#form_profile_password .is-invalid').removeClass('is-invalid');
    $('#profile_password_form_error').hide();

    if(current_password === ''){
        $('#current_password').addClass('is-invalid');
        valid = false;
    }

    if(new_password === ''){
        $('#new_password').addClass('is-invalid');
        valid = false;
    }

    if(new_password_confirm === ''){
        $('#new_password_confirm').addClass('is-invalid');
        valid = false;
    }

    if(new_password !== '' && new_password_confirm !== '' && new_password !== new_password_confirm){

        $('#new_password, #new_password_confirm').addClass('is-invalid');

        $('#profile_password_form_error')
                .text('Las nuevas contraseñas no coinciden.')
                .show();

        return;
    }

    if(!valid){

        $('#profile_password_form_error')
                .text('Debe completar los campos obligatorios.')
                .show();

        return;
    }

    disableBtn('btn_save_profile_password');
    $('#loading_profile_password').css('display', 'block');

    var form = $('#form_profile_password')[0];
    var formData = new FormData(form);

    formData.append('controller', 'usuariosController');
    formData.append('function', 'actualizarPasswordPerfil');

    sendToServerDoc(formData, function(Data){

        enableBtn('btn_save_profile_password');
        $('#loading_profile_password').css('display', 'none');

        if(Data.result){

            $('#form_profile_password')[0].reset();

            Data.result = urlEnvironment + 'perfil?r=' + Date.now() + '#success';
        }

        afterReload(Data);

    });

};

cargarDatosAuditoria = function(){

    $('#audit_type').on('change', function(){

        var audit_type = $(this).val();

        if(audit_type === ''){

            $('#audit_results').html(
                    '<div class="card">' +
                        '<div class="card-body colorMuted">' +
                            '<i class="fa-solid fa-circle-info me-1"></i>' +
                            'Seleccione un tipo de auditoría para consultar los registros.' +
                        '</div>' +
                    '</div>'
            );

            return;
        }

        $('#loading_audit').css('display', 'block');
        $('#audit_results').empty();

        var params = {
            controller: 'panelController',
            function: 'cargarDatosAuditoria',
            audit_type: audit_type
        };

        var valores = JSON.stringify(params);

        sendToServer(valores, function(Data){

            $('#loading_audit').css('display', 'none');

            if(Data.result){

                $('#audit_results').html(Data.extra);

                cargarDataTables('dt_audit_all');

            }else{

                afterReload(Data);
            }

        });

    });

};


openDocumentationImage = function(src){

    $('#documentation_image_full').attr('src', src);

    $('#modal_documentation_image').modal('show');
};

confirmDashboardBonus = function (id) {

    disableBtn('btn_confirm_dashboard_bonus_' + id);
    $('#loading_confirm_dashboard_bonus' + id).css('display', 'block');

    var params = {
        controller: 'usuariosController',
        function: 'confirmBonus',
        id: id
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function (Data) {

        enableBtn('btn_confirm_dashboard_bonus_' + id);
        $('#loading_confirm_dashboard_bonus' + id).css('display', 'none');
        disableSelectedModal('modal_confirm_dashboard_bonus' + id);

        if (Data.result) {
            Data.result = urlEnvironment + 'dashboard?r=' + Date.now() + '#success';
        }

        afterReload(Data);

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

            Data.result = urlEnvironment + 'dashboard?r=' + Date.now() + '#success';
        }

        afterReload(Data);

    });

};

actualizarFotoPerfilUsuario = function(id,page){
    
    disableBtn('btn_save_photo');
    $('#loading_photo').css('display', 'block');
    
    var form = $('#form_profile_image')[0];
    var formData = new FormData(form);

    formData.append('controller', 'usuariosController');
    formData.append('function', 'actualizarFotoPerfil');
    formData.append('id', id);
    sendToServerDoc(formData, function (Data) {

        enableBtn('btn_save_photo');
        $('#loading_photo').css('display', 'none');
        if(Data.result){
            Data.result = urlEnvironment + page+'?r=' + Date.now() + '#success';
            
        }
        afterReload(Data);

    });
    
};