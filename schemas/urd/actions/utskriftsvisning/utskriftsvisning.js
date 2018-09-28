$(document).ready(function() {

    $('#print-view-close').on('click', function() {
        $('div#print-view').hide();
        $('#header').show();
        $('#page-container').show();
        // trigger resize to align thead in grid
        $(window).trigger('resize');
    });

    list = ds.table;
    console.log(list);
    var html = '<tr>'
    $.each(list.grid.columns, function(i, felt) {
        console.log('i', i);
        console.log('felt', felt);
        if ($.inArray(list.fields[felt].datatype, ['integer', 'float', 'boolean']) > -1) {
            align = 'right';
        }
        else {
            align = 'left';
        }
        html+= '<th class="ba" align="' + align + '">' + list.fields[felt].label + '</th>';
    });
    html += '</tr>';
    $('#thead').html(html);

    html = '';
    $.each(list.records, function(i, rec) {
        html+= '<tr>';
        $.each(list.grid.columns, function(i, felt) {
            console.log(rec);
            var value = rec.columns[felt] != null ? rec.columns[felt] : '';
            // value = control.display_value(field, value);

            if ($.inArray(list.fields[felt].datatype, ['integer', 'float', 'boolean']) > -1) {
                align = 'right';
            }
            else {
                align = 'left';
            }
            html+= '<td class="ba" align="'+align+'">'+value+'</td>';
        });
        html+= '</tr>';
    });
    $('#tbody').html(html);

    /*
    html = '';
    footer = $("#jqgrid_0").jqGrid('footerData', 'get');
    if (count(footer) > 0) {
        html+= '<tr>';
        $.each(list.kolonne, function(i, felt) {
            html+= '<td>'+footer[felt]+'</td>';
        });
        html+= '</tr>';
    }
    $('#tfoot').html(html);

    console.log(footer);
    */
});
