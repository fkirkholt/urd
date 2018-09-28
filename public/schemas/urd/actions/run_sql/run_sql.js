$(document).ready(function() {

    var $ = jQuery;

    $('#knapp_run_sql').click(function() {
        console.log('test');
        sql = $('#sql').val();
        base = $('#base_navn').val();
        /*
        $.get('/run_sql', {sql:sql, base:base}, function(data) {
            $('#resultat').html(data);
        });*/
        $.ajax({
            url: '/run_sql',
            data: {
                sql: sql,
                base: base
            },
            method: 'PUT'
        }).done(function(data) {
            $('#resultat').html(data);
        })
    });

});
