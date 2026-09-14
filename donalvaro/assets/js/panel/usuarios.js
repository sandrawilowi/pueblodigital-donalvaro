$(document).ready(function () {
    controlHash();
    controlPage();

});

controlPage = function(){
    
    var url = window.location.toString();
    var partsControl = url.split('/');
    var endControl = partsControl.pop();
    var parts = endControl.split('#');

    if (url.includes('/usuarios') || url.includes('/clientes')) {

        cargarDataTables('dt_usuarios');
        cargarDatosUsuario();
    }else if (url.includes('/auditcliente') || url.includes('/auditusuario')) {

        cargarDataTables('dt_audit');
    }else if(url.includes('/editcliente') || url.includes('/editusuario')){
        cargarFotoPerfil();
        cargarDatosEdit();
    }

   
    
};

cargarDataTables = function (id) {

    const columnConfig = {
        "dt_usuarios": { total: 6, noSortable: [5],searching:false, serverside: false, buttons: true },
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

formFilter = function (id) {

    var form = $('#' + id)[0];
    var formData = new FormData(form);

    formData.append('controller', 'panelController');
    formData.append('function', 'formFilter');
    formData.append('form_id', id);

    sendToServerDoc(formData, function (Data) {

        $('#insertTableUsuarios').empty();
        $('#insertTableUsuarios').html(Data.result);
        cargarDataTables('dt_usuarios');

    });

};

cargarDatosUsuario = function () {

    $('#form_new_user').on('input change', 'input, select, textarea', function () {
        $(this).removeClass('is-invalid');
        $('#user_form_error').hide();
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


cargarDatosEdit = function () {


    $('#bonus_facility_id').on('change', function () {
        $(this).removeClass('is-invalid');
        $('#bonus_form_error').hide();
    });

    $(document).on('change', 'input[name="bonus_id"]', function () {
        $('#bonus_form_error').hide();
    });
    
    $('#country_id').on('change', function () {

        var country_id = $(this).val();

        $('#province_id')
                .empty()
                .append('<option value="">Seleccione primero un país</option>')
                .prop('disabled', true);

        if (country_id === '') {
            return;
        }

        cargarProvinciasPais(country_id, '#province_id');

    });

};


addUser = function(role){

    var valid = true;

    $('#form_new_user .is-invalid').removeClass('is-invalid');
    $('#user_form_error').hide();

    var first_name = $('#user_first_name').val().trim();
    var last_name = $('#user_last_name').val().trim();
    var email = $('#user_email').val().trim();

    if(first_name === ''){
        $('#user_first_name').addClass('is-invalid');
        valid = false;
    }

    if(last_name === ''){
        $('#user_last_name').addClass('is-invalid');
        valid = false;
    }

    if(email === ''){
        $('#user_email').addClass('is-invalid');
        valid = false;
    }else if(!validateEmail(email)){
        $('#user_email').addClass('is-invalid');
        valid = false;
    }

    if(!valid){
        $('#user_form_error').text('Debe completar correctamente los campos obligatorios.').show();
        return;
    }


    disableBtn('btn_guardar_user');
    $('#loading_user').css('display', 'block');

    var form = $('#form_new_user')[0];
    var formData = new FormData(form);

    formData.append('controller', 'usuariosController');
    formData.append('function', 'addUser');
    formData.append('role', role);

    sendToServerDoc(formData, function(Data){

        enableBtn('btn_guardar_user');
        $('#loading_user').css('display', 'none');

        if(Data.result){

            if(role === 3){
                Data.result = urlEnvironment + 'clientes?r=' + Date.now() + '#success';
            }else{
                Data.result = urlEnvironment + 'usuarios?r=' + Date.now() + '#success';
            }
        }

        afterReload(Data);
    });

};

blockUser = function(id, value){

    var params = {
        controller: 'usuariosController',
        function: 'blockUser',
        id: id,
        value: value
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        if(Data.result){

            if(value == 1){

                $('#btnBlockUserLink'+id)
                        .attr('title', 'Desbloquear usuario')
                        .attr('onclick', 'blockUser('+id+',0)')
                        .html('<i id="btnBlockUser'+id+'" class="fa-solid fa-lock colorWarning"></i>');

            }else{

                $('#btnBlockUserLink'+id)
                        .attr('title', 'Bloquear usuario')
                        .attr('onclick', 'blockUser('+id+',1)')
                        .html('<i id="btnBlockUser'+id+'" class="fa-solid fa-lock-open colorMuted"></i>');
            }
        }

    });

};

activeUser = function(id, value){
    
    var params = {
        controller: 'usuariosController',
        function: 'activeUser',
        id: id,
        value: value
    };
        
    var valores = JSON.stringify(params);
    sendToServer(valores, function(Data){

        if(Data.result){

            if(value==1){

                $('#btnActiveDevice'+id).removeClass('colorWarning');
                $('#btnActiveDevice'+id).addClass('colorAccent');
                $('#btnActiveDeviceLink'+id).attr('onclick', 'activeUser('+id+',0)');

            }else{

                $('#btnActiveDevice'+id).removeClass('colorAccent');
                $('#btnActiveDevice'+id).addClass('colorWarning');
                $('#btnActiveDeviceLink'+id).attr('onclick', 'activeUser('+id+',1)');
            }


        }

    });
    
};


actualizarUsuario = function(id,page){
    
    var name = $('#name').val().trim();
    var email = $('#email').val().trim();

    var valid = true;

    $('#form_data .is-invalid').removeClass('is-invalid');
    $('#user_form_error').hide();

    if(name === ''){

        $('#name').addClass('is-invalid');
        valid = false;
    }

    if(email === '' || !validateEmail(email)){

        $('#email').addClass('is-invalid');
        valid = false;
    }

    if(!valid){

        $('#user_form_error')
                .text('Debe completar correctamente los campos obligatorios.')
                .show();

        return;
    }


    disableBtn('btn_save');
    $('#loading').css('display', 'block');
    
    var form = $('#form_data')[0];
    var formData = new FormData(form);

    formData.append('controller', 'usuariosController');
    formData.append('function', 'actualizarUsuario');
    formData.append('id', id);
    sendToServerDoc(formData, function (Data) {

        enableBtn('btn_save');
        $('#loading').css('display', 'none');
        if(Data.result){
            Data.result = urlEnvironment + page+'/'+id+'?r=' + Date.now() + '#success';
            
        }
        afterReload(Data);

    });
    
};

actualizarPassword = function(id){
    
    var password = $('#password').val();
    var passwordConfirm = $('#password-confirm').val();

    var valid = true;

    $('#form_password .is-invalid').removeClass('is-invalid');
    $('#password_form_error').hide();

    if(password === ''){
        $('#password').addClass('is-invalid');
        valid = false;
    }

    if(passwordConfirm === ''){
        $('#password-confirm').addClass('is-invalid');
        valid = false;
    }

    if(password !== '' && passwordConfirm !== '' && password !== passwordConfirm){
        $('#password, #password-confirm').addClass('is-invalid');
        $('#password_form_error').text('Las contraseñas no coinciden.').show();
        return;
    }

    if(!valid){
        $('#password_form_error').text('Debe completar los campos obligatorios.').show();
        return;
    }
    
    disableBtn('btn_save_password');
    $('#loading_password').css('display', 'block');
    
    var form = $('#form_password')[0];
    var formData = new FormData(form);

    formData.append('controller', 'usuariosController');
    formData.append('function', 'actualizarPassword');
    formData.append('id', id);
    sendToServerDoc(formData, function (Data) {

        enableBtn('btn_save_password');
        $('#loading_password').css('display', 'none');
        afterReload(Data);

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

actualizarFotoPerfil = function(id,page){
    
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
            Data.result = urlEnvironment + page+'/'+id+'?r=' + Date.now() + '#success';
            
        }
        afterReload(Data);

    });
    
};

actualizarDisplayName = function(id,page){
    
    disableBtn('btn_save_display_name');
    $('#loading_display_name').css('display', 'block');
    
    var form = $('#form_display_name')[0];
    var formData = new FormData(form);

    formData.append('controller', 'usuariosController');
    formData.append('function', 'actualizarDisplayName');
    formData.append('id', id);
    sendToServerDoc(formData, function (Data) {

        enableBtn('btn_save_display_name');
        $('#loading_display_name').css('display', 'none');
        if(Data.result){
            Data.result = urlEnvironment + page+'/'+id+'?r=' + Date.now() + '#success';
            
        }
        afterReload(Data);

    });
    
};


addBonus = function(id){

    var bonus_id = $('#bonus_id').val();
    var valid_from = $('#bonus_valid_from').val();

    $('#bonus_id').removeClass('is-invalid');
    $('#bonus_valid_from').removeClass('is-invalid');
    $('#bonus_form_error').hide();

    if (!bonus_id) {

        $('#bonus_id').addClass('is-invalid');

        $('#bonus_form_error')
                .text('Debe seleccionar un bono.')
                .show();

        return;
    }

    if (valid_from === '') {

        $('#bonus_valid_from').addClass('is-invalid');

        $('#bonus_form_error')
                .text('Debe indicar la fecha de inicio del bono.')
                .show();

        return;
    }

    disableBtn('btn_add_bono');
    $('#loading_bonus').css('display', 'block');

    var form = $('#form_add_bono')[0];
    var formData = new FormData(form);

    formData.append('controller', 'usuariosController');
    formData.append('function', 'addBonus');
    formData.append('id', id);

    sendToServerDoc(formData, function(Data){

        enableBtn('btn_add_bono');
        $('#loading_bonus').css('display', 'none');
        disableSelectedModal('modal_add_bono');

        if (Data.result) {
            Data.result = urlEnvironment + 'editcliente/' + id + '?r=' + Date.now() + '#success';
        }

        afterReload(Data);

    });

};

deleteBonus = function (id, user_id) {

    disableBtn('btn_delete_bonus');
    $('#loading_delete_bonus'+id).css('display', 'block');
    var params = {
        controller: 'usuariosController',
        function: 'deleteBonus',
        id: id
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function (Data) {

        enableBtn('btn_delete_bonus');
        $('#loading_delete_bonus'+id).css('display', 'none');
        disableSelectedModal('modal_delete_bonus'+id);
        if (Data.result) {
            Data.result = urlEnvironment + 'editcliente/' + user_id + '?r=' + Date.now() + '#success';
        }

        afterReload(Data);

    });

};

deleteUser = function(id){
    
    disableBtn('btn_delete');
    $('#loading-delete').css('display', 'block');
    
    var params = {
        controller: 'usuariosController',
        function: 'deleteUser',
        id: id
    };

    var valores = JSON.stringify(params);
    sendToServer(valores, function(Data){

        enableBtn('btn_delete');
        $('#loading-delete').css('display', 'none');
        if(Data.result){
            Data.result = urlEnvironment + 'usuarios?r=' + Date.now() + '#success';
            
        }

        afterReload(Data);
        
    });
};

deleteClient = function(id){
    
    disableBtn('btn_delete');
    $('#loading-delete').css('display', 'block');
    
    var params = {
        controller: 'usuariosController',
        function: 'deleteClient',
        id: id
    };

    var valores = JSON.stringify(params);
    sendToServer(valores, function(Data){

        enableBtn('btn_delete');
        $('#loading-delete').css('display', 'none');
        if(Data.result){
            Data.result = urlEnvironment + 'clientes?r=' + Date.now() + '#success';
            
        }

        afterReload(Data);
        
    });
};

confirmBonus = function (id, user_id) {

    disableBtn('btn_confirm_bonus_' + id);
    $('#loading_confirm_bonus' + id).css('display', 'block');

    var params = {
        controller: 'usuariosController',
        function: 'confirmBonus',
        id: id
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function (Data) {

        enableBtn('btn_confirm_bonus_' + id);
        $('#loading_confirm_bonus' + id).css('display', 'none');
        disableSelectedModal('modal_confirm_bonus' + id);

        if (Data.result) {
            Data.result = urlEnvironment + 'editcliente/' + user_id + '?r=' + Date.now() + '#success';
        }

        afterReload(Data);

    });

};

