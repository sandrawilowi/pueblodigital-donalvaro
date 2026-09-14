$(document).ready(function () {
    controlHash();
    controlPage();
    
    $(window).on('hashchange', function () {
        controlHash();
    });
    
});

controlPage = function(){
    
    var url = window.location.toString();
    var partsControl = url.split('/');
    var endControl = partsControl.pop();
    var parts = endControl.split('#');

    if (url.includes('/cerraduras')) {

        cargarDataTables('dt_cerraduras');
    }else if (url.includes('/gateways')) {

        cargarDataTables('dt_gateway');
    }
    else if (url.includes('/auditcerradura') || url.includes('/auditgateway')) {

        cargarDataTables('dt_audit');
    }

   
    
};

cargarDataTables = function (id) {

    const columnConfig = {
        "dt_cerraduras": { total: 7, noSortable: [6],searching:false, serverside: false, buttons: true },
        "dt_gateway": { total: 7, noSortable: [6],searching:false, serverside: false, buttons: true },
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

        if (id === 'filterCerraduras') {

            $('#insertTableCerraduras').empty();
            $('#insertTableCerraduras').html(Data.result);
            cargarDataTables('dt_cerraduras');

        } else if (id === 'filterGateway') {

            $('#insertTableGateway').empty();
            $('#insertTableGateway').html(Data.result);
            cargarDataTables('dt_gateway');
        } 

    });
};

sincronizarCerraduras = function () {

    disableBtn('btn_sync_locks');
    $('#loading').css('display', 'block');
    
    var params = {
        controller: 'dispositivosController',
        function: 'sincronizarCerraduras'
    };    

    var valores = JSON.stringify(params);
    sendToServer(valores, function(Data){

        enableBtn('btn_sync_locks');
        if(Data.result){
            Data.result = urlEnvironment + 'cerraduras?r=' + Date.now() + '#success';
            
        }

        afterReload(Data);
        
    });


};

sincronizarGateways = function(){
    
    disableBtn('btn_sync_locks');
    $('#loading').css('display', 'block');
    
    var params = {
        controller: 'dispositivosController',
        function: 'sincronizarGateways'
    };    

    var valores = JSON.stringify(params);
    sendToServer(valores, function(Data){

        enableBtn('btn_sync_locks');
        if(Data.result){
            Data.result = urlEnvironment + 'gateways?r=' + Date.now() + '#success';
            
        }

        afterReload(Data);
        
    });
    
};

actualizarCerradura = function(id){
    
    disableBtn('btn_save');
    $('#loading').css('display', 'block');
    
    var params = {
        controller: 'dispositivosController',
        function: 'actualizarCerradura',
        id: id,
        alias: $('#nombre').val()
    };
    
    var valores = JSON.stringify(params);
    sendToServer(valores, function(Data){

        enableBtn('btn_save');
        afterReload(Data);
        
    });
    
    
};

actualizarGateway = function(id){
    
    disableBtn('btn_save');
    $('#loading').css('display', 'block');
    
    var params = {
        controller: 'dispositivosController',
        function: 'actualizarGateway',
        id: id,
        nombre: $('#nombre').val()
    };
    
    var valores = JSON.stringify(params);
    sendToServer(valores, function(Data){

        enableBtn('btn_save');
        afterReload(Data);
        
    });
    
};

activeDevice = function(id, value, type_device){
    
    var params = {
        controller: 'dispositivosController',
        function: 'activeDevice',
        id: id,
        value: value,
        type_device: type_device
    };
    
    var text = "'cerraduras'";
    
    var valores = JSON.stringify(params);
    sendToServer(valores, function(Data){

        if(Data.result){

            if(value==1){

                $('#btnActiveDevice'+id).removeClass('colorWarning');
                $('#btnActiveDevice'+id).addClass('colorAccent');
                $('#btnActiveDeviceLink'+id).attr('onclick', 'activeDevice('+id+',0,'+text+')');

            }else{

                $('#btnActiveDevice'+id).removeClass('colorAccent');
                $('#btnActiveDevice'+id).addClass('colorWarning');
                $('#btnActiveDeviceLink'+id).attr('onclick', 'activeDevice('+id+',1,'+text+')');
            }


        }

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

cerrarCerradura = function (id) {

    disableBtn('btn_lock_lock');
    disableBtn('btn_unlock_lock');

    var params = new Object();
    params.controller = 'dispositivosController';
    params.function = 'cerrarCerradura';
    params.id = id;

    var valores = JSON.stringify(params);

    sendToServer(valores, function (Data) {

        enableBtn('btn_lock_lock');
        enableBtn('btn_unlock_lock');

        afterReload(Data);

    });
};