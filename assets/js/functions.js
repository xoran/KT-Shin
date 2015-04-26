/* ++++++++++++++++++++++++++++++++++++++++++++ */
/* +++          Fonctions KT-Shin           +++ */
/* ++++++++++++++++++++++++++++++++++++++++++++ */
$(function() {

    var alert = $('#alert');
    if(alert.length > 0){
        alert.hide().slideDown(500).delay(3000).slideUp();
        alert.find('.close').click(function(e){
            e.preventDefault();
            alert.slideUp();
        })
    }

});$('#aboutModal').on('shown.bs.modal', function () {
  $('.about_link').click()
})
