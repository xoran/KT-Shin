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

$(".expand").click(function () {

    $header = $(this);
    //getting the next element
    $content = $header.next();
    //open up the content needed - toggle the slide- if visible, slide up, if not slidedown.
    $content.slideToggle(500, function () {
        //execute this after slideToggle is done
        //change text of header based on visibility of content div
        $header.text(function () {
            //change text based on condition
            return $content.is(":visible") ? "Fermer" : "Lire mon commentaire";
        });
    });

});
