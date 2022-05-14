var m = require('mithril');
var jQuery = require('jquery');


/*
 * --------------------------------------------------------------------
 * jQuery-Plugin - $.download - allows for simple get/post requests for files
 * by Scott Jehl, scott@filamentgroup.com
 * http://www.filamentgroup.com
 * reference article: http://www.filamentgroup.com/lab/jquery_plugin_for_requesting_ajax_like_file_downloads/
 * Copyright (c) 2008 Filament Group, Inc
 * Dual licensed under the MIT (filamentgroup.com/examples/mit-license.txt) and GPL (filamentgroup.com/examples/gpl-license.txt) licenses.
 * --------------------------------------------------------------------
 */
jQuery.download = function(url, data, target){
    //url and data options required
    if( url && data ){
        //data can be string of parameters or array/object
        data = typeof data == 'string' ? data : jQuery.param(data);
        //split params into form inputs
        var inputs = '';
        jQuery.each(data.split('&'), function() {
            var pair = this.split('=');
            pair[1] = pair[1].replace('"', '&quot;');
            inputs+='<input type="hidden" name="'+ pair[0] +'" value="'+ pair[1] +'" />';
        });
        //send request
        jQuery('<form action="'+ url +'" method="get" target="' + (target||'_self') + '">'+inputs+'</form>')
            .appendTo('body').submit().remove();
    };
};


var export_dialog = {

    type: 'csv',

    export_csv: function() {
        var param = {};
        param.base = ds.base.name;
        param.table = ds.table.name;
        param.filter = m.route.param('query');

        var fields = [];
        $('input:checkbox[name=field]:checked').each(function() {
            fields.push($(this).val());
        });
        param.fields = JSON.stringify(fields);

        param.csv = true;
        $.download('table', param);
    },

    export_sql: function(dialect) {
        var param = {};
        param.dialect = dialect;
        param.base = ds.base.name;
        param.table = ds.table.name;
        $.download('table_sql', param);
    },

    view: function() {
        if (!ds.table || !ds.table.fields) return;

        return m('div', [
            m('div', [
                m('select', {
                    onchange: function(event) {
                        this.type = event.target.value;
                    }.bind(this)
                }, [
                    m('option', {value: 'csv'}, 'csv'),
                    m('option', {value: 'sql'}, 'sql'),
                ]),
            ]),
            this.type !== 'csv' ? '' : m('div[name=valg]', {class: 'mt2 max-h5 overflow-y-auto'}, [
                'Velg felter:',
                m('ul', {class: 'list'}, [
                    m('li', {class: 'mb2'}, [
                        m('input[type=checkbox]', {
                            onchange: function(e) {
                                var checked = $(this).prop('checked');
                                $('input[type=checkbox][name=field]').prop('checked', checked);
                                e.redraw = false;
                            }
                        }), ' (alle)',
                    ]),
                    Object.keys(ds.table.fields).map(function(fieldname, idx) {
                        var field = ds.table.fields[fieldname];
                        return m('li', {}, [
                            m('input[type=checkbox]', {
                                name: 'field',
                                value: field.name
                            }), ' ', field.label
                        ])
                    })
                ])
            ]),
            this.type !== 'sql' ? '' : m('div[name=valg]', {class: "mt2"}, [
                m('input[type=radio]', {name: 'dialect', value: 'mysql'}), ' MySQL', m('br'),
                m('input[type=radio]', {name: 'dialect', value: 'oracle'}), ' Oracle', m('br'),
                m('input[type=radio]', {name: 'dialect', value: 'postgres'}), ' PostgreSQL', m('br'),
                m('input[type=radio]', {name: 'dialect', value: 'sqlite3'}), ' SQLite', m('br'),
            ]),
            m('div[name=buttons]', {class: "bottom-0 max-w8 mt2"}, [
                m('input[type=button]', {
                    value: 'OK',
                    class: 'fr',
                    onclick: function() {
                        if (this.type === 'csv') {
                            this.export_csv();
                        } else {
                            var dialect = $('#export-dialog input[name="dialect"]:checked').val();
                            export_dialog.export_sql(dialect);
                        }
                        $('div.curtain').hide();
                        $('#export-dialog').hide();
                    }.bind(this)
                }),
                m('input[type=button]', {
                    value: 'Avbryt',
                    class: 'fr',
                    onclick: function() {
                        $('div.curtain').hide();
                        $('#export-dialog').hide();
                    }
                }),
            ])
        ])
    }
}

module.exports = export_dialog;
