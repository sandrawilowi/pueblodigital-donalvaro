$(document).ready(function () {
    controlHash();
    controlPage();

});

controlPage = function(){
    
    var url = window.location.toString();
    var partsControl = url.split('/');
    var endControl = partsControl.pop();
    var parts = endControl.split('#');

    if (url.includes('/bonos')) {
        cargarDatosBonos();
        cargarDataTables('dt_bono');
        
    }else if (url.includes('/auditbono')) {

        cargarDataTables('dt_audit');
    }
    else if(url.includes('/editbono')){        
        cargarDatosBonos();
    }
   
    
};

cargarDataTables = function (id) {

    const columnConfig = {
        "dt_bono": { total: 6, noSortable: [4,5],searching:false, serverside: false, buttons: true },
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
        cargarDataTables('dt_bono');

    });

};



addBonus = function(){

    var valid = true;

    $('#form_add_bonus .is-invalid').removeClass('is-invalid');
    $('#bonus_form_error').hide();
    $('#bonus_facilities').closest('.bootstrap-select').removeClass('is-invalid');

    var name = $('#bonus_name').val().trim();
    var bonus_type = $('#bonus_type').val();
    var total_uses = $('#bonus_total_uses').val();
    var price = $('#bonus_price').val();
    var validity_mode = $('#bonus_validity_mode').val();
    var validity_days = $('#bonus_validity_days').val();
    var facility_ids = $('#bonus_facilities').val();

    if (name === '') {
        $('#bonus_name').addClass('is-invalid');
        valid = false;
    }

    if (bonus_type === 'USES') {
        if (total_uses === '' || parseInt(total_uses) < 1) {
            $('#bonus_total_uses').addClass('is-invalid');
            valid = false;
        }
    }

    if (price === '' || parseFloat(price) < 0) {
        $('#bonus_price').addClass('is-invalid');
        valid = false;
    }

    if (validity_mode === 'DAYS') {

        if (bonus_type === 'TIME') {

            if (validity_days === '' || parseInt(validity_days) < 1) {
                $('#bonus_validity_days').addClass('is-invalid');
                valid = false;
            }

        } else if (validity_days !== '' && parseInt(validity_days) < 1) {

            $('#bonus_validity_days').addClass('is-invalid');
            valid = false;
        }
    }

    if (!facility_ids || facility_ids.length === 0) {
        $('#bonus_facilities').closest('.bootstrap-select').addClass('is-invalid');
        valid = false;
    }

    if (!valid) {
        $('#bonus_form_error').text('Debe completar correctamente los campos obligatorios.').show();
        return;
    }

    if (bonus_type === 'TIME') {
        $('#bonus_total_uses').val(0);
    }

    if (validity_mode !== 'DAYS') {
        $('#bonus_validity_days').val('');
    }

    disableBtn('btn_add_bonus');
    $('#loading-modal-add-bono').css('display', 'block');

    var form = $('#form_add_bonus')[0];
    var formData = new FormData(form);

    formData.append('controller', 'bonusController');
    formData.append('function', 'addBonus');

    sendToServerDoc(formData, function(Data){

        enableBtn('btn_add_bonus');
        $('#loading-modal-add-bono').css('display', 'none');

        if(Data.result){
            Data.result = urlEnvironment + 'bonos?r=' + Date.now() + '#success';
        }

        disableSelectedModal('modal_add_bonus');
        afterReload(Data);
    });
};

editBonus = function(bonus_id){

    var valid = true;

    $('#form_edit_bonus .is-invalid').removeClass('is-invalid');
    $('#bonus_form_error').hide();
    $('#bonus_facilities').closest('.bootstrap-select').removeClass('is-invalid');
    

    var name = $('#bonus_name').val().trim();
    var bonus_type = $('#bonus_type').val();
    var total_uses = $('#bonus_total_uses').val();
    var price = $('#bonus_price').val();
    var validity_mode = $('#bonus_validity_mode').val();
    var validity_days = $('#bonus_validity_days').val();
    var facility_ids = $('#bonus_facilities').val();

    if (name === '') {
        $('#bonus_name').addClass('is-invalid');
        valid = false;
    }

    if (bonus_type === 'USES') {
        if (total_uses === '' || parseInt(total_uses) < 1) {
            $('#bonus_total_uses').addClass('is-invalid');
            valid = false;
        }
    }

    if (price === '' || parseFloat(price) < 0) {
        $('#bonus_price').addClass('is-invalid');
        valid = false;
    }

    if (validity_mode === 'DAYS') {

        if (bonus_type === 'TIME') {

            if (validity_days === '' || parseInt(validity_days) < 1) {
                $('#bonus_validity_days').addClass('is-invalid');
                valid = false;
            }

        } else if (validity_days !== '' && parseInt(validity_days) < 1) {

            $('#bonus_validity_days').addClass('is-invalid');
            valid = false;
        }
    }

    if (!facility_ids || facility_ids.length === 0) {
        $('#bonus_facilities').closest('.bootstrap-select').addClass('is-invalid');
        valid = false;
    }

    if (!valid) {
        $('#bonus_form_error').text('Debe completar correctamente los campos obligatorios.').show();
        return;
    }

    if (bonus_type === 'TIME') {
        $('#bonus_total_uses').val(0);
    }

    if (validity_mode !== 'DAYS') {
        $('#bonus_validity_days').val('');
    }

    disableBtn('btn_save');

    var form = $('#form_edit_bonus')[0];
    var formData = new FormData(form);

    formData.append('controller', 'bonusController');
    formData.append('function', 'editBonus');
    formData.append('bonus_id', bonus_id);
    

    sendToServerDoc(formData, function(Data){

        enableBtn('btn_save');

        if(Data.result){
            Data.result = urlEnvironment + 'editbono/' + bonus_id + '?r=' + Date.now() + '#success';
        }

        afterReload(Data);
    });
};

deleteBonus = function(bonus_id){

    disableBtn('btn_delete_bonus_'+bonus_id);

    var params = {
        controller: 'bonusController',
        function: 'deleteBonus',
        bonus_id: bonus_id
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        enableBtn('btn_delete_bonus_'+bonus_id);

        if(Data.result){
            Data.result = urlEnvironment + 'bonos/' + bonus_id + '?r=' + Date.now() + '#success';
        }

        afterReload(Data);

    });

};

activeBonus = function(bonus_id, value){

    var params = {
        controller: 'bonusController',
        function: 'activeBonus',
        bonus_id: bonus_id,
        value: value
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        if(Data.result){

            if(value == 1){

                $('#btnActiveBonus'+bonus_id).removeClass('colorWarning').addClass('colorAccent');
                $('#btnActiveBonusLink'+bonus_id).attr('onclick', 'activeBonus('+bonus_id+',0)');
                $('#btnActiveBonusLink'+bonus_id).attr('aria-label', 'Desactivar bono');

            }else{

                $('#btnActiveBonus'+bonus_id).removeClass('colorAccent').addClass('colorWarning');
                $('#btnActiveBonusLink'+bonus_id).attr('onclick', 'activeBonus('+bonus_id+',1)');
                $('#btnActiveBonusLink'+bonus_id).attr('aria-label', 'Activar bono');
            }
        }
    });
};


cargarDatosBonos = function(){
    
    $(document).on('change', '#bonus_type, #bonus_validity_mode', function () {
        controlBonusFields();
    });

 
};


controlBonusFields = function () {

    const bonus_type = $('#bonus_type').val();
    const validity_mode = $('#bonus_validity_mode').val();

    if (bonus_type === 'USES') {
        $('#bonus_total_uses_block').show();
    } else {
        $('#bonus_total_uses_block').hide();
        $('#bonus_total_uses').val(0);
    }

    if (validity_mode === 'DAYS') {

        $('#bonus_validity_days_block').show();

        if (bonus_type === 'TIME') {
            $('#bonus_validity_days_required').show();
            $('#bonus_validity_help').hide();
        } else {
            $('#bonus_validity_days_required').hide();
            $('#bonus_validity_help').show();
        }

    } else {

        $('#bonus_validity_days_block').hide();
        $('#bonus_validity_days').val('');
        $('#bonus_validity_days_required').hide();
        $('#bonus_validity_help').hide();
    }
};

addBonusPaymentMethod = function(id){    
    
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
    formData.append('controller', 'bonusController');
    formData.append('function', 'addBonusPaymentMethod');
    formData.append('id', id);

    sendToServerDoc(formData, function(Data){
        enableBtn('btn_add_payment_method');
        $('#loading-modal-add-payment').css('display', 'none');
        if(Data.result){
            Data.result = urlEnvironment + 'editbono/'+id+'?r=' + Date.now() + '#success';
            
        }
        afterReload(Data);
    });
    
};

removeBonusPaymentMethod = function(id, method_id){
    
    disableBtn('btn_remove_payment_method_'+method_id);
    
    var params = {
        controller: 'bonusController',
        function: 'removeBonusPaymentMethod',
        id: id,
        method_id: method_id
    };

    var valores = JSON.stringify(params);
    sendToServer(valores, function(Data){

        enableBtn('btn_remove_payment_method_'+method_id);
        if(Data.result){
            Data.result = urlEnvironment + 'editbono/'+id+'?r=' + Date.now() + '#success';
            
        }

        afterReload(Data);
        
    });
};