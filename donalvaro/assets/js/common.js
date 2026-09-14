/*
 * Developed by wilowi
 */

var hash = "";

// --- Enviar al servidor
sendToServer = function (parametros, funcion_callback) {
    var asincrono = true;


    var request = $.ajax(
            {
                url: urlAjax,
                scriptCharset: "utf8",
                type: "POST",
                contentType: "application/json; charset=utf-8",
                dataType: "json",
                headers: {
                    "Access-Control-Allow-Origin": "*",
                    "Cache-Control": "no-store, no-cache, must-revalidate"
                },
                async: asincrono,
                crossDomain: true,
                data: parametros,
                cache: false
            });
    request.done(funcion_callback);
    request.fail(function (jqXHR, textStatus)
    {
        console.log(jqXHR);
        console.log(textStatus);
        //alert("Sorry, there is a problem with the request. Please contact support with all steps and a screenshot.");

        return false;
    });

    return true;
};

sendToServerDoc = function (formData, funcion_callback) {

    var asincrono = true;
    var request = $.ajax(
            {
                url: urlAjax,
                type: 'POST',
                data: formData,
                async: asincrono,
                crossDomain: true,
                contentType: false,
                processData: false,
                cache: false
            });
    request.done(funcion_callback);
    request.fail(function (jqXHR, textStatus)
    {
        console.log(jqXHR);
        console.log(textStatus);

        return false;
    });

    return true;

};

cookiesInfo = function () {

    const cookiesInfoDiv = document.getElementById('cookies-info');
    const cookiesClose = document.getElementById('cookies-close');

    if (!cookiesInfoDiv || !cookiesClose) {
        return;
    }

    if (localStorage.getItem('cookies_info_closed')) {
        cookiesInfoDiv.classList.add('hidden');
    }

    cookiesClose.addEventListener('click', () => {

        localStorage.setItem('cookies_info_closed', '1');

        cookiesInfoDiv.classList.add('hidden');

    });

};

// --- ngenter
ngEnter = function () {
	return function (scope, element, attrs) {
		element.bind("keydown keypress", function (event) {
			if (event.which === 13) {
				scope.$apply(function () {
					scope.$eval(attrs.ngEnter);
				});

				event.preventDefault();
			}
		});
	};
};

ngFilter = function(e,btn){

    if (e.keyCode === 13 && !e.shiftKey) {
        //alert("entro");
        e.preventDefault();
        var boton = document.getElementById(btn);
        if (boton) {
            boton.click();
        }
    }

};


validateEmail = function (sEmail) {

    var filter = /^[\w.\-+]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

    return filter.test(sEmail);
};


afterDo = function (Data) {

    disableModals();

    if (Data.extra == 'SESSION_FALSE') {

        window.location.href = Data.result;

    } else {

        scrollSmooth();
        resetAlerts();

        if(Data.update_container){
            $('#container_update_action').empty();
            $('#container_update_action').html(Data.result);
        }else{
            showAlert(Data);
        }

        modalsResult(Data);
        controlesPeticiones(Data);


    }

};

afterReload = function (Data) {

    disableModals();
    if (Data.control_request == 2) { //Muestra mensaje si actualizamos
        
        showAlert(Data);
        //Hacer scroll hacia arriba
        scrollSmooth();
        modalsResult(Data);
    } else { //Refresca pagina si creamos
        window.location.href = Data.result;
    }
};


scrollSmooth = function () {

    //$window.location.hash = '#wrapper';
    window.scroll({
        top: 0,
        left: 0,
        behavior: 'smooth'
    });
    history.replaceState("", document.title, window.location.pathname
            + window.location.search);
};

/*disableModals = function () {
    
    $(document.activeElement).blur();

    if ($('#modal_delete').hasClass('show')) {
        $('#modal_delete').modal('hide');
    }

    if ($('#modal_add').hasClass('show')) {
        $('#modal_add').modal('hide');
    }

    // También cierra el modal_loading si se está usando
    if ($('#modal_loading').hasClass('show')) {
        $('#modal_loading').modal('hide');
    }

    $('#modal_loading').modal('hide');
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open');
    $('body').css('overflow', '');
    $('body').css('padding-right', '');

    $('#loading').css('display', 'none');
    $('.loading-div').css('display', 'none');

    $('.btnSendAjax').prop('disabled', false);

};*/

disableModals = function () {

    $(document.activeElement).blur();

    if ($('#modal_delete').hasClass('show')) {
        $('#modal_delete').modal('hide');
    }

    if ($('#modal_add').hasClass('show')) {
        $('#modal_add').modal('hide');
    }

    if ($('#modal_loading').hasClass('show')) {
        $('#modal_loading').modal('hide');
    }

    $('#loading').css('display', 'none');
    $('.loading-div').css('display', 'none');

    $('.btnSendAjax').prop('disabled', false);

};

/*disableSelectedModal = function (id) {

    $(document.activeElement).blur();
    $('#'+id).modal('hide');
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open');
    $('body').css('overflow', '');
    $('body').css('padding-right', '');

    $('#loading').css('display', 'none');
    $('.loading-div').css('display', 'none');

};*/

disableSelectedModal = function (id) {

    $(document.activeElement).blur();

    $('#' + id).modal('hide');

    $('#loading').css('display', 'none');
    $('.loading-div').css('display', 'none');

};


controlHash = function(){

    var url = window.location.toString();
    var parts = url.split('#');
    var hash = '';
    if (parts.length > 1) {
        hash = parts.pop();
    }

    if (hash === '') {
        return;
    }

    history.replaceState(
            "",
            document.title,
            window.location.pathname
            );

    var params = new Object();

    if (hash === 'success') {
        params.type_msg = 'INFO';
        params.msg = 'Operación realizada correctamente';

    }else if(hash === 'error'){
        params.type_msg = 'ERROR';
        params.msg = 'Error al crear registro';

    }else if(hash === 'warning'){
        params.type_msg = 'WARNING';
        params.msg = 'No se ha guardado correctamente el registro.';

    }
    else if(hash === 'exist'){
        params.type_msg = 'WARNING';
        params.msg = 'El registro ya existe en la base de datos.';

    }

    modalsResult(params);

};


showAlert = function (Data) {

    if (Data.result === 'OK') {

        $('#alert-success').removeClass('display_none');
        $('#alert-warning').addClass('display_none');
        $('#alert-danger').addClass('display_none');
        $('#alert-success-msg').html(Data.msg);
    } else if (Data.result === 'KO') {

        $('#alert-danger').removeClass('display_none');
        $('#alert-warning').addClass('display_none');
        $('#alert-success').addClass('display_none');
        $('#alert-danger-msg').html(Data.msg);
    } else if (Data.result === 'WARNING') {
        $('#alert-danger').addClass('display_none');
        $('#alert-success').addClass('display_none');
        $('#alert-warning').removeClass('display_none');
        $('#alert-warning-msg').html(Data.msg);
    }
    else {

        $('#alert-success').addClass('display_none');
        $('#alert-danger').addClass('display_none');
        $('#alert-warning').addClass('display_none');
    }
};

requiredFields = function () {

    $('[required]').each(function () {
        const $campo = $(this);
        const tipo = $campo.prop('tagName').toLowerCase();

        let valorInvalido = false;

        if (tipo === 'select') {
            valorInvalido = $campo.val() === "0";
        } else {
            valorInvalido = !$campo.val();
        }

        if (valorInvalido) {
            $campo.addClass('input-error');
        } else {
            $campo.removeClass('input-error');
        }
    });
};

resetAlerts = function(){

    $('#alert-success, #alert-warning, #alert-danger').addClass('display_none');
    $('#alert-success-msg, #alert-warning-msg, #alert-danger-msg').text('');

};


function validatePassword(password) {
  const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@#$%&+!=?]).{6,}$/;

  return regex.test(password);
}

modalsResult = function (Data) {

    if (!Data.msg) {
        return;
    }

    const $modalContent = $('#modal_result .result-modal');
    const $title = $('#modalResultTitle');
    const $message = $('#modalResultMessage');

    $modalContent.removeClass(
        'modal-info modal-warning modal-error'
    );

    switch (Data.type_msg) {

        case 'INFO':
            $modalContent.addClass('modal-info');
            $title.text('Información');

            break;

        case 'WARNING':
            $modalContent.addClass('modal-warning');
            $title.text('Aviso');

            break;

        case 'ERROR':
            $modalContent.addClass('modal-error');
            $title.text('Se ha producido un error');
            break;

        default:
            $modalContent.addClass('modal-info');
            $title.text('Información');

            break;
    }

    $message.text(Data.msg);
    const modalElement = document.getElementById('modal_result');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

    modal.show();
};

generatePasswordUser = function () {

    var params = new Object();
    params.controller = 'panelController';
    params.function = 'generatePasswordUser';
    var valores = JSON.stringify(params);
    sendToServer(valores, function(Data){

        $('#password').val(Data.result);
        $('#password-confirm').val(Data.result);

    });

};

toggleRegisterPasswords = function(){

    let password = $("#password");
    let passwordConfirm = $("#password-confirm");
    let icon = $("#password-icon");


    if(password.attr("type") === "password"){

        password.attr("type","text");
        passwordConfirm.attr("type","text");

        icon.removeClass("fa-eye");
        icon.addClass("fa-eye-slash");

    }else{

        password.attr("type","password");
        passwordConfirm.attr("type","password");

        icon.removeClass("fa-eye-slash");
        icon.addClass("fa-eye");

    }

};


disableBtn = function(id){

    $('#'+id).prop('disabled', true);
};

enableBtn = function(id){

    $('#'+id).prop('disabled', false);
};



function downloadURI(uri, name) {
  var link = document.createElement("a");
  link.download = name;
  link.href = uri;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  delete link;
}

function downloadURIPdf(uri, name) {
    
  var link = document.createElement("a");
  link.download = name;
  link.href = uri;
  link.target = '_blank';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  delete link;
}


function CopyToClipboard(containerid) {

    var textToCopy = $('#' + containerid).val();
    var tempTextarea = $('<textarea>');
    $('body').append(tempTextarea);
    tempTextarea.val(textToCopy).select();
    document.execCommand('copy');
    tempTextarea.remove();
}

function redirectWithPost(url, params,modal) {
    
    if (modal != '') {
        window.onbeforeunload = function () {
            document.getElementById(modal).style.display = 'block';
        };
    }

    // Crear un formulario oculto
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;

    // Añadir los datos como campos ocultos
    for (const key in params) {
        if (params.hasOwnProperty(key)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = params[key];
            form.appendChild(input);
        }
    }

    // Añadir el formulario al body y enviarlo
    document.body.appendChild(form);
    form.submit();
}

function clearForm(formId) {
    const form = document.getElementById(formId);

    if (!form) {
        console.error(`No se ha encontrado el formulario: ${formId}`);
        return;
    }

    const fields = form.querySelectorAll(
        'input, select, textarea'
    );

    fields.forEach(function (field) {

        switch (field.type) {

            case 'text':
            case 'search':
            case 'email':
            case 'tel':
            case 'number':
            case 'date':
            case 'datetime-local':
            case 'time':
            case 'password':
            case 'hidden':
                field.value = '';
                break;

            case 'checkbox':
            case 'radio':
                field.checked = false;
                break;

            case 'select-one':
            case 'select-multiple':
                Array.from(field.options).forEach(function (option) {
                    option.selected = false;
                });

                /*
                 * Si es un selectpicker actualizamos
                 * también la parte visual.
                 */
                if ($(field).hasClass('selectpicker')) {
                    $(field).selectpicker('refresh');
                }
                break;

            default:
                field.value = '';
                break;
        }

        field.dispatchEvent(new Event('change', {
            bubbles: true
        }));
    });
};

function toggleFilter(id, icon) {

    $("#" + id).collapse("toggle");

    $(icon).toggleClass("fa-square-plus fa-square-minus");

}

cargarProvinciasPais = function(country_id, province_selector){

    var params = {
        controller: 'usuariosController',
        function: 'cargarProvinciasPais',
        country_id: country_id
    };

    var valores = JSON.stringify(params);

    sendToServer(valores, function(Data){

        $(province_selector).html(Data.result);

        if($(province_selector + ' option[data-province]').length > 0){
            $(province_selector).prop('disabled', false);
        }else{
            $(province_selector).prop('disabled', true);
        }

    });

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
            if(page === 'perfil'){
                Data.result = urlEnvironment + 'perfil?r=' + Date.now() + '#success';
            }else{
                Data.result = urlEnvironment + page + '/' + id + '?r=' + Date.now() + '#success';
            }
            
        }
        afterReload(Data);

    });
    
};

$(document).on('hidden.bs.modal', '.modal', function () {

    if ($('.modal.show').length === 0) {
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
        $('body').css('overflow', '');
        $('body').css('padding-right', '');
    }

});