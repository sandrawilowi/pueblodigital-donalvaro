
modalView = function () {
    $('#modal_view').modal('show');
};

modalViewOne = function (id) {
    $('#modal_view' + id).modal('show');
};

modalViewMore = function (id,name) {
    $('#'+ name + id).modal('show');
};

