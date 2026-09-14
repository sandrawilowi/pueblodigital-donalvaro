$(document).ready(function () {

    controlPage();
    cookiesInfo();

    $("#email").on('paste', function (e) {
        e.preventDefault();
        //alert('Esta acción está prohibida');
    });

    $("#email").on('copy', function (e) {
        e.preventDefault();
        //alert('Esta acción está prohibida');
    });

    $("#emailRepeat").on('paste', function (e) {
        e.preventDefault();
        //alert('Esta acción está prohibida');
    });

    $("#emailRepeat").on('copy', function (e) {
        e.preventDefault();
        //alert('Esta acción está prohibida');
    });

});

controlPage = function(){
    
    var url = window.location.toString();
    var partsControl = url.split('/');
    var endControl = partsControl.pop();
    var parts = endControl.split('#');

    if(url.includes('/sitemap')){

        $(function () {
            function adjustSitemap() {
                const $primaryNav = $(".primaryNav");
                const $home = $("#home");
                const $navItems = $primaryNav.find("ul > li");
                const navWidth = $primaryNav.width();
                const itemWidth = parseInt($navItems.first().css("width"));

                if (Math.floor(navWidth / itemWidth) < $navItems.length - 1) {
                    $home.removeClass("long-cell");
                    $("#longcoll").remove();

                    const windowWidth = $(window).width();
                    const heightAdjustment = windowWidth <= 480 ? -40 : 1.5;
                    const height = $primaryNav.height() - $navItems.last().height() + heightAdjustment;

                    $("<style>")
                            .attr("id", "longcoll")
                            .text(`.primaryNav #home:before { height: ${height}px; }`)
                            .appendTo("head");

                    $home.addClass("long-cell");
                    $primaryNav.find("ul").addClass("showSiteMapLines");
                } else {
                    $home.removeClass("long-cell");
                }
            }

            $(window).on("load resize", adjustSitemap);
        });

    }

};


doLogin = function () {

// deshabilitar botón login con javascript

    disableBtn('btnLogin');
    var form = $('#login-form')[0];
    var formData = new FormData(form);
    formData.append('controller', 'webController');
    formData.append('function', 'login');

    sendToServerDoc(formData, afterLogin);
};

afterLogin = function (Data) {

    //buttonsDisabled = false;

    if (Data.result == 'loginTrue') {
        window.location.href = 'dashboard';

    } else {

        enableBtn('btnLogin');
        $('#resultLogin').html(Data.result);
        $('#resultLogin').addClass("tiemblaDiv");
        $('#loading').css('display', 'none');
    }

};

doForgotEmail = function () {

    buttonsDisabled = true;

    var params = new Object();
    params.controller = 'webController';
    params.function = 'forgotEmail';
    params.email = $('#email-forgot').val();

    if (validarFormulario(params)) {


        var valores = JSON.stringify(params);
        sendToServer(valores, afterForgot);

    } else {
        buttonsDisabled = false;
        alert('Please, enter a valid email');
    }

};

contactForm = function () {

    buttonsDisabled = true;
    var params = new Object();
    var form = $('form')[0];
    var formData = new FormData(form);
    formData.append('controller', 'webController');
    formData.append('function', 'contactForm');
    formData.append('kindContact', $('#changeNameContact').attr('value'));
    params.name = $('#name').val();
    params.message = $('#message').val();
    params.email = $('#email').val();
    params.issuetype = $('#issuetype').val();

    if (params.message !== '' && params.name != '' && params.email != '' && validarEmail(params.email) && params.issuetype > 0) {

        $.ajax({
            url: rutaAjax,
            type: 'POST',
            data: formData,
            success: function (Data) {
                buttonsDisabled = false;
                if (Data.resultado == 'KO') {
                    alert("You are not a human");
                } else {
                    alert("Thank you for your email. We will shortly contact you.");
                    $window.location.href = 'contact';
                }

            },
            error: function (Data) {
                buttonsDisabled = false;
                alert("Error");
            },
            async: true,
            cache: false,
            contentType: false,
            processData: false
        });


    } else {
        buttonsDisabled = false;
        alert("You need to write your name, valid email, issue type and message");
    }


};

doRegister = function () {

// deshabilitar botón login con javascript

    disableBtn('btnRegister');
    var form = $('#register-form')[0];
    var formData = new FormData(form);
    formData.append('controller', 'webController');
    formData.append('function', 'register');

    sendToServerDoc(formData, afterRegister);
};

afterRegister = function (Data) {

    enableBtn('btnRegister');

    if (Data.result) {
        $("#register-form")[0].reset();
        modalsResult(Data);

    } else {
        $('#resultLogin').html(Data.msg);
        $('#resultLogin').addClass("tiemblaDiv");
        $('#loading').css('display', 'none');
    }

};

togglePassword = function(inputId,iconId){

    let password = $("#" + inputId);
    let icon = $("#" + iconId);


    if(password.attr("type") === "password"){

        password.attr("type","text");

        icon.removeClass("fa-eye");
        icon.addClass("fa-eye-slash");

    }else{

        password.attr("type","password");

        icon.removeClass("fa-eye-slash");
        icon.addClass("fa-eye");

    }

};

setPassword = function(){

    var password = $('#password').val();
    var password_confirm = $('#password-confirm').val();

    $('#password, #password-confirm').removeClass('is-invalid');
    $('#set_password_error').empty();

    if(password === ''){
        $('#password').addClass('is-invalid');
        $('#set_password_error').text('Debe introducir una contraseña.');
        return;
    }

    if(password_confirm === ''){
        $('#password-confirm').addClass('is-invalid');
        $('#set_password_error').text('Debe repetir la contraseña.');
        return;
    }

    if(password !== password_confirm){
        $('#password, #password-confirm').addClass('is-invalid');
        $('#set_password_error').text('Las contraseñas no coinciden.');
        return;
    }

    disableBtn('btn_set_password');

    var form = $('#form_set_password')[0];
    var formData = new FormData(form);

    formData.append('controller', 'usuariosController');
    formData.append('function', 'setPassword');

    sendToServerDoc(formData, function(Data){

        enableBtn('btn_set_password');

        if(Data.result){
            Data.result = urlEnvironment + 'login?r=' + Date.now() + '#success';
        }

        afterReload(Data);

    });

};

