var chartStatisticsReservations = null;
var chartStatisticsIncome = null;
var chartStatisticsStatus = null;
var chartStatisticsAccesses = null;
var chartStatisticsAccessResults = null;
var chartStatisticsBonuses;

$(document).ready(function () {
    controlHash();
    controlPage();

});

controlPage = function(){
    
    var url = window.location.toString();
    var partsControl = url.split('/');
    var endControl = partsControl.pop();
    var parts = endControl.split('#');

    if(url.includes('/estadisticas')){
        
        cargarDatosEstadisticas();

    }else if (url.includes('/informes')) {

        cargarDatosInformes();
    }
   
    
};

cargarDataTables = function (id) {

    const columnConfig = {
        "dt_report_reservations": { total: 8, noSortable: [],searching:false, serverside: false, buttons: true },
        "dt_report_income": { total: 7, noSortable: [], searching: false, serverside: false, buttons: true },
        "dt_report_accesses": { total: 7, noSortable: [], searching: false, serverside: false, buttons: true },
        "dt_report_clients": { total: 6, noSortable: [], searching: false, serverside: false, buttons: true },
        "dt_report_origin": { total: 5, noSortable: [], searching: false, serverside: false, buttons: true },
        "dt_report_bonuses": { total: 10, noSortable: [], searching: false, serverside: false, buttons: true }
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
                },
                {
                    "text": "<i class='fa-regular fa-file-pdf'></i> Descargar PDF",
                    "className": "btn backgroundMuted ms-2",
                    "action": function () {
                        generateReportPdf();
                    }
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

    

};

cargarDatosEstadisticas = function(){

    $('#statistics_year, #statistics_month, #statistics_facility').on('change', function(){
        loadStatistics();
    });

    loadStatistics();
};


loadStatistics = function(){

    $('#statistics_error').hide();
    $('#statistics_loading').css('display', 'block');

    var params = {
        controller: 'analyticsController',
        function: 'loadStatistics',
        year: $('#statistics_year').val(),
        month: $('#statistics_month').val(),
        facility_id: $('#statistics_facility').val()
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        $('#statistics_loading').css('display', 'none');

        if(Data.result){

            renderStatistics(Data.extra);

        }else{

            afterReload(Data);
        }

    });
};


renderStatistics = function(data){

    $('#statistics_reservations').text(
            parseInt(data.kpis.reservations) || 0
    );

    $('#statistics_income').text(
            formatStatisticsAmount(data.kpis.income)
    );

    $('#statistics_accesses').text(
            parseInt(data.kpis.accesses) || 0
    );

    $('#statistics_clients').text(
            parseInt(data.kpis.clients) || 0
            );

    if ($('#statistics_bonuses').length) {
        $('#statistics_bonuses').text(
                parseInt(data.kpis.bonuses) || 0
                );
    }

    renderReservationsChart(data.reservations);
    renderIncomeChart(data.income);
    renderReservationStatusChart(data.statuses);
    renderAccessesChart(data.accesses);
    renderAccessResultsChart(data.access_results);

    if ($('#chart_statistics_bonuses').length) {
        renderBonusesChart(data.bonuses);
    }
};


formatStatisticsAmount = function(amount){

    amount = parseFloat(amount) || 0;

    return amount.toLocaleString('es-ES', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }) + ' €';
};


renderReservationsChart = function(data){

    if(chartStatisticsReservations){
        chartStatisticsReservations.destroy();
    }

    var ctx = document.getElementById('chart_statistics_reservations');

    chartStatisticsReservations = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Reservas',
                data: data.values,
                tension: 0.3,
                fill: false
            }]
        },
        options: getStatisticsChartOptions(false)
    });
};


renderIncomeChart = function(data){

    if(chartStatisticsIncome){
        chartStatisticsIncome.destroy();
    }

    var ctx = document.getElementById('chart_statistics_income');

    chartStatisticsIncome = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Ingresos',
                data: data.values
            }]
        },
        options: getStatisticsChartOptions(true)
    });
};


renderReservationStatusChart = function(data){

    if(chartStatisticsStatus){
        chartStatisticsStatus.destroy();
    }

    var ctx = document.getElementById('chart_statistics_status');

    chartStatisticsStatus = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
};


renderAccessesChart = function(data){

    if(chartStatisticsAccesses){
        chartStatisticsAccesses.destroy();
    }

    var ctx = document.getElementById('chart_statistics_accesses');

    chartStatisticsAccesses = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Accesos',
                data: data.values,
                tension: 0.3,
                fill: false
            }]
        },
        options: getStatisticsChartOptions(false)
    });
};


renderAccessResultsChart = function(data){

    if(chartStatisticsAccessResults){
        chartStatisticsAccessResults.destroy();
    }

    var ctx = document.getElementById('chart_statistics_access_results');

    chartStatisticsAccessResults = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
};

renderBonusesChart = function(data){

    if(chartStatisticsBonuses){
        chartStatisticsBonuses.destroy();
    }

    var ctx = document.getElementById('chart_statistics_bonuses');

    if(!ctx){
        return;
    }

    chartStatisticsBonuses = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Bonos',
                data: data.values,
                tension: 0.3,
                fill: false
            }]
        },
        options: getStatisticsChartOptions(false)
    });
};


getStatisticsChartOptions = function(currency){

    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context){

                        var value = context.parsed.y ?? 0;

                        if(currency){
                            return context.dataset.label + ': ' + formatStatisticsAmount(value);
                        }

                        return context.dataset.label + ': ' + value;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value){

                        if(currency){
                            return value + ' €';
                        }

                        return value;
                    }
                }
            }
        }
    };
};


cargarDatosInformes = function(){

    $('#report_type').on('change', function(){
        controlReportFilters();
    });

    controlReportFilters();
};


controlReportFilters = function () {

    var report_type = $('#report_type').val();

    if (report_type === 'RESERVATIONS') {
        $('#report_reservation_filters').show();
    } else {
        $('#report_reservation_filters').hide();
        $('#report_status_id').val('');
    }

    
};

clearReportFilters = function(){

    $('#filterReports')[0].reset();

    $('#report_error').hide();
    $('#report_results').html('');

    controlReportFilters();
};

generateReport = function(){

    $('#report_error').hide();
    $('#report_results').html('');

    var report_type = $('#report_type').val();
    var date_from = $('#report_date_from').val();
    var date_until = $('#report_date_until').val();

    if(date_from !== '' && date_until !== '' && date_until < date_from){

        $('#report_error')
                .text('La fecha hasta no puede ser anterior a la fecha desde.')
                .show();

        return;
    }

    disableBtn('btnGenerateReport');
    $('#loading_report').css('display', 'block');

    var params = {
        controller: 'analyticsController',
        function: 'generateReport',
        report_type: report_type,
        date_from: date_from,
        date_until: date_until,
        facility_id: $('#report_facility_id').val(),
        status_id: $('#report_status_id').val()
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        enableBtn('btnGenerateReport');
        $('#loading_report').css('display', 'none');

        if(Data.result){

            $('#report_results')
                    .html(Data.extra)
                    .show();

            if (report_type === 'RESERVATIONS') {
                cargarDataTables('dt_report_reservations');
            }

            if (report_type === 'INCOME') {
                cargarDataTables('dt_report_income');
            }

            if (report_type === 'ACCESSES') {
                cargarDataTables('dt_report_accesses');
            }

            if (report_type === 'CLIENTS') {
                cargarDataTables('dt_report_clients');
            }

            if (report_type === 'ORIGIN') {
                cargarDataTables('dt_report_origin');
            }

            if (report_type === 'BONUSES') {
                cargarDataTables('dt_report_bonuses');
            }

        } else {

            afterReload(Data);
        }

    });
};

generateReportPdf = function(){

    var params = {
        controller: 'analyticsController',
        function: 'generateReportPdf',
        report_type: $('#report_type').val(),
        date_from: $('#report_date_from').val(),
        date_until: $('#report_date_until').val(),
        facility_id: $('#report_facility_id').val(),
        status_id: $('#report_status_id').val()
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        if(Data.result){

            downloadURIPdf(
                    Data.extra.path,
                    Data.extra.filename
            );

        }else{

            afterReload(Data);
        }

    });
};

