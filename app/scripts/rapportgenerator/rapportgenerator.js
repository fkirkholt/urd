$(document).ready(function() {



    function hent_kolonner(base, table, tpl) {
        var p = {};
        p.base = base;
        p.tabell = table;
        $.getJSON("skript/rapportgenerator/hent_kolonner.php", p, function(data) {
            var colrefs = [];
            $.each(path, function(i, field) {
                colrefs.push(field.name);
            });
            var colref = colrefs.join('.');
            var table = '<table>';
            cols = data;
            $.each(cols, function(i, col) {
                var fk = false;
                if (col.ref_table) {
                    fk = true;
                }
                table += '<tr name="'+col.kolonne+'">';
                // Checks if field is chosen
                var checked = '';
                $.each(fields, function(i, field) {
                    if (field == colref+'.'+col.kolonne) {
                        checked = 'checked';
                        return;
                    }
                });
                table += '<td><input type="checkbox" '+checked+'></td>';
                if (fk) {
                    table += '<td class="link">'+col.kolonne+'</td>';
                } else {
                    table += '<td>'+col.kolonne+'</td>';
                }
                table += '</tr>';
            });
            table += '</table>';
            $('#column_list').html(table);
        });
    }

    function draw_path() {
        console.log(path);
        var html = '';
        $.each(path, function(i, field) {
            if (i > 0) {
                html += ' > ';
            }
            if (i < path.length - 1) {
                html += '<span data-nr="'+i+'">'+field.name+'</span>';
            } else {
                html += field.name;
            }
        });
        $('#path').html(html);
    }

    var base = ds.liste.base_navn;
    var table = ds.liste.tabell;
    var cols = [];
    var path = [{'base':base, 'table':table, 'tpl':'', 'name':table}];
    var fields = [];
    var tablerefs = [];
    var cond_cols = [];
    draw_path();

    hent_kolonner(base, table, '');

    $('#path').on('click', 'span', function() {
        var idx = Number($(this).attr('data-nr'));
        var field = path[idx];
        path.length = idx+1;
        hent_kolonner(field.base, field.table, field.tpl);
        draw_path();
    });

    $('#column_list').on('click', 'table td.link', function() {
        // TODO: gjentar kode her med kalling av skriptet hent_kolonner.php
        var idx = $(this).parent().attr('name');
        var col = cols[idx];
        console.log(col);
        // TODO shall base and table really be global variables?
        base = col.ref_base;
        var tpl = col.kandidatmal;
        table = col.ref_table;
        console.log(table);
        path.push({'base':base, 'table':table, 'tpl':tpl, 'name':idx});
        draw_path();
        hent_kolonner(base, table, tpl);
    });


    $('#column_list').on('dblclick', 'table tr', function() {
        var idx = $(this).attr('name');
        var col = cols[idx];
        $('#field_list tbody').append('<tr><td>'+col.kolonne+'</td></tr>');
    });

    $('#column_list').on('click', 'input', function() {
        var idx = $(this).parents('tr').attr('name');
        var col = cols[idx];
        var colrefs = [];
        $.each(path, function(i, field) {
            colrefs.push(field.name);
        });
        var colref = colrefs.join('.');
        if ($.inArray(colref, tablerefs) == -1) {
            tablerefs.push(colref);
            var options = '<option value="">-- Tabell --</option>';
            console.log(tablerefs);
            $.each(tablerefs, function(i, ref) {
                options += '<option>'+ref+'</option>';
            });
            console.log(options);
            $('select.cond_table').html(options);
        }

        colref += '.'+col.kolonne;
        if ($(this).is(':checked')) {
            var html = '<tr title="'+colref+'"><td>'+colref+'</td></tr>';
            $('#field_list tbody').append(html);
            fields.push(colref);
        } else {
            console.log('fjerner felt '+colref);
            $('#field_list tbody tr[title="'+colref+'"]').remove();
            var fields_index = fields.indexOf(colref);
            fields.splice(fields_index, 1);
            console.log(fields);
        }
    });

    $('#button_column input[value=">"]').click(function() {
        $('#column_list input:checked').each(function() {
            var idx = $(this).parents('tr').attr('name');
            var col = cols[idx];
            var colref = path.join('.');
            if ($.inArray(colref, tablerefs) == -1) {
                tablerefs.push(colref);
                var options = '<option value="">-- Tabell --</option>';
                console.log(tablerefs);
                $.each(tablerefs, function(i, ref) {
                    options += '<option>'+ref+'</option>';
                });
                console.log(options);
                $('select.cond_table').html(options);
            }

            colref += '.'+col.kolonne;
            $('#field_list tbody').append('<tr><td>'+colref+'</td></tr>');
            fields.push(colref);
        });
    });

    $('#run_report').click(function() {
        var p = {};
        p.base = ds.liste.base_navn;
        p.table = ds.liste.tabell;
        p.fields = fields;
        p.conditions = get_conditions();
        $.ajax({
            type: "POST",
            url: 'skript/rapportgenerator/run_report.php',
            data: JSON.stringify(p),
            contentType: "application/json: charset=utf-8",
            dataType: "json",
            success: function(response) {
                var recs = response.data.recs;
                var html = '';
                if (recs.length) {
                    html += '<thead><tr>';
                    var rec = recs[0];
                    $.each(rec, function(field, value) {
                        html += '<th>'+field+'</th>';
                    });
                    html += '</tr></thead><tbody>';
                    $.each(recs, function(i, rec) {
                        html += '<tr>';
                        $.each(rec, function(field, value) {
                            if (value == null) {
                                value = '';
                            }
                            html += '<td>'+value+'</td>';
                        });
                        html += '</tr>';
                    });
                    html += '</tbody>';
                }
                $('#report').html(html);
            }
        });
    });

    function get_conditions() {
        var conditions = [];
        $('#conditions div.condition').each(function() {
            var table_ref = $(this).children('select.cond_table').val();
            var field = $(this).children('select.cond_field').val();
            var operator = $(this).children('select.cond_operator').val();
            if (table_ref === '' || field === '' || operator === '') {
                return;
            }
            var value;
            if ($(this).children('select.cond_values').is(':visible')) {
                value = $(this).children('select.cond_values').val();
            } else {
                value = $(this).children('input.cond_value').val();
            }
            conditions.push({'table_ref':table_ref, 'field':field,
                             'operator':operator, 'value':value});
            console.log([table_ref, field, operator, value]);
        });
        return conditions;
    }

    $('#save_report').click(function() {
        $('#save_report_dialog').show();
    });

    $('#save_report_dialog input[value="OK"]').click(function() {
        console.log('lagrer rapport');
        var p = {};
        p.name = $(this).siblings('input[name="name"]').val();
        p.table = ds.liste.tabell;
        p.tpl = ds.liste.databasemal;
        p.fields = JSON.stringify(fields);
        var conditions = get_conditions();
        p.conditions = JSON.stringify(conditions);
        $('#save_report_dialog').hide();
        $.post('skript/rapportgenerator/save_report.php', p, function(data) {
        }, 'json');
    });
    $('#save_report_dialog input[value="Avbryt"]').click(function() {
        console.log('avbryter lagring');
        $('#save_report_dialog').hide();
    });

    $('select.cond_table').change(function() {
        console.log($(this).val());
        var p = {};
        p.base = ds.liste.base_navn;
        p.tabell = $(this).val();
        $.getJSON("skript/rapportgenerator/hent_kolonner.php", p, function(data) {
            cond_cols = data;
            var options = '<option value="">-- Felt --</option>';
            $.each(data, function(name, field) {
                options += '<option value="'+name+'">'+name+'</option>';
            });
            $('select.cond_field').html(options);
        });
    });

    $('select.cond_field').change(function() {
        var field = cond_cols[$(this).val()];
        var felttype = field.felttype;
        var options = '<option value="">-- Operator --</option>';
        var operators;
        if (felttype == 'dropdownlist') {
            operators = {'!=':'≠', '=':'=', 'IS NULL':'har ingen verdi',
                         'IS NOT NULL':'har verdi'};
            var p = {};
            if (!field.kandidatmal) {
                // TODO: Må nok sjekke tabellen isteden
                field.kandidatmal = ds.liste.databasemal;
            }
            p.kandidatmal = field.kandidatmal;
            p.kandidatbase = field.ref_base;
            p.kandidattabell = field.ref_table;
            if (field.kandidatalias) {
                p.kandidatalias = field.kandidatalias;
            } else {
                p.kandidatalias = field.ref_table;
            }
            // TODO: Tillat flere kandidatnøkler
            p.kandidatnokkel = [field.ref_key];
            p.kandidatvisning = field.kandidatvisning;
            p.q = '';
            var options2 = '<option value="">-- Verdi --</option>';
            $.post("skript/hent_nedtrekksmeny.php", p, function(data) {
                $.each(data.verdier, function(i, rec) {
                    options2 += '<option value="'+rec.id+'">'+rec.text+'</option>';
                });
                console.log(options2);
                $('select.cond_values').html(options2).show();
                $('input.cond_value').hide();
            }, 'json');
        } else if (felttype == 'dropdownlazy') {
            operators = {'!=':'≠', '=':'=', 'IS NULL':'har ingen verdi',
                         'IS NOT NULL':'har verdi'};
            $('select.cond_values').hide();
            $('input.cond_value').show();
        } else if (felttype == 'datefield') {
            operators = {'=':'=', '!=':'≠', '>':'&gt;', '<':'&lt;',
                         'IS NULL':'har ingen verdi','IS NOT NULL':'har verdi'
                        };
            $('select.cond_values').hide();
            $('input.cond_value').show();
        } else {
            operators = { 'LIKE':'inneholder',
                          '=':'=', '!=':'≠', '>':'&gt;', '<':'&lt;',
                          'IS NULL':'har ingen verdi','IS NOT NULL':'har verdi'
                        };
            $('select.cond_values').hide();
            $('input.cond_value').show();
        }
        $.each(operators, function(value, operator) {
            options += '<option value="'+value+'">'+operator+'</option>';
        });
        $('select.cond_operator').html(options);
    });


    $('input[value="+"]').click(function() {
        var html = '';
        html += '<div class="condition">';
        html += '<select class="cond_table">';
        html += '  <option value="">-- Tabell --</option>';
        html += '</select> ';
        html += '<select class="cond_field">';
        html += '  <option value="">-- Felt --</option>';
        html += '</select> ';
        html += '<select class="cond_operator">';
        html += '  <option value="">-- Operator --</option>';
        html += '</select> ';
        html += '<select class="cond_values" style="display:none"></select>';
        html += '<input type="text" class="cond_value">';
        html += '</div>';
        $('#conditions').append(html);
        var options = $('select.cond_table:first').html();
        $('select.cond_table:last').html(options);
    });

});
