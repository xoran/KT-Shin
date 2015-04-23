/* ++++++++++++++++++++++++++++++++++++++++++++ */
/* +++ Traitement du message d'alerte Flash +++ */
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

});
/*
$('#myTab a [href="#consultation"]').click(function (e) {
  e.preventDefault()
  $(this).tab('show')
})
$('#myTab a [href="#addSong"]').click(function (e) {
  e.preventDefault()
  $(this).tab('show')
})
$('#myTab a [href="#addRecord"]').click(function (e) {
  e.preventDefault()
  $(this).tab('show')
})
$('#myTab a [href="#delRecord"]').click(function (e) {
  e.preventDefault()
  $(this).tab('show')
})
*/

/*
$('#myTab a[href="#consultation"]').tab('show') // Select tab by name
$('#myTab a[href="#addDossier"]').tab('show') // Select tab by name
$('#myTab a[href="#addRecord"]').tab('show') // Select tab by name
$('#myTab a[href="#delRecord"]').tab('show') // Select tab by name
*/

$(function(){
    var url = document.location.toString();
    if (url.match('#')) {
        $('.nav-tabs a[href=#'+url.split('#')[1]+']').tab('show') ;
    }
    $('.nav-tabs a').on('shown.bs.tab', function (e) {
        window.location.hash = e.target.hash;
    });
});
