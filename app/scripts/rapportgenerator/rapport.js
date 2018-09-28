$(document).ready(function() {
    var schema = $('input[name="schema"]').val();
    var p = {};
    p.rapportid = $('input[name="id"]').val();
    console.log(p.schema);
    console.log(p.rapportid);
    $.getJSON('get_report_meta.php', p, function(data) {
        console.log(data);
        p.base = schema;
        p.table = data.tabell;
        p.fields = data.felter;
        p.conditions = data.betingelser;
        $.getJSON('run_report.php', p, function(data) {
            var table = '<thead><tr>';
            var rec = data[0];
            $.each(rec, function(field, value) {
                table += '<th>'+field+'</th>';
            });
            table += '</tr></thead><tbody>';
            $.each(data, function(i, rec) {
                table += '<tr>';
                $.each(rec, function(field, value) {
                    table += '<td>'+value+'</td>';
                });
                table += '</tr>';
            });
            table += '</tbody>';
            $('#report').html(table);
        });
    });

    $('#csv').click(function() {
        console.log('csv');
        console.log(p);
        p.csv = true;
        $.download('run_report.php', p);
    });

});
