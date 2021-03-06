
var grid = {

    url: '',

    align_thead: function() {
        var $table = $('#urdgrid');
        var $headCells = $table.find('thead tr').children();
        var $bodyCells = $table.find('tbody tr:first').children();
        var $footCells = $table.find('tfoot tr').children();
        var colWidth;


        // Remove existing width attributes
        $headCells.each(function(i, v) {
            $(v).css('width', '');
        })
        $bodyCells.each(function(i, v) {
            $(v).css('width', '');
        })
        $footCells.each(function(i, v) {
            $(v).css('width', '');
        });

        // Get column widths

        colWidthHead = $headCells.map(function() {
            return $(this).width() + 1;
        });

        colWidthBody = $bodyCells.map(function() {
            return $(this).width();
        }).get();

        colWidthFoot = $footCells.map(function() {
            return $(this).width();
        }).get();

        if (colWidthFoot.length > 0 && colWidthBody.length > 0) {
            $.each(colWidthFoot, function(idx, width) {
                var n;
                if (width > colWidthBody[idx]) {
                    $table.find('tbody tr:first td:nth-child(' + (idx+1) + ')').width(width);
                }
            });
        }

        if (!config.compressed) {
            $.each(colWidthHead, function(idx, width) {
                var n;
                if (width > colWidthBody[idx]) {
                    $table.find('tbody tr:first td:nth-child(' + (idx+1) + ')').width(width);
                }
            })
        }

        colWidthBody = $bodyCells.map(function() {
            return $(this).width();
        });

        if (colWidthBody.length === 0) {
            colWidth = colWidthHead;
        } else {
            colWidth = colWidthBody;
        }

        // Set the width of thead columns
        $table.find('thead tr').children().each(function(i, v) {
            $(v).width(colWidth[i]);
        });


        // Set the width of tfoot columns
        $table.find('tfoot tr').children().each(function(i, v) {
            $(v).width(colWidth[i]);
        })
    },

    oncreate: function() {
        // Adjust the width of thead cells when window resizes
        $(window).resize(grid.align_thead);
    },

    onupdate: function() {
        grid.align_thead();

        // Ensure scrolling to bottom for new records
        if ((ds.table.selection + 1) == ds.table.records.length) {
            var height = $('#urdgrid tbody')[0].scrollHeight;
            $('#urdgrid tbody').scrollTop(height);
        }
    },

    column: {
        order: function(col) {
            return ds.table.sort_fields[col]
                ? ds.table.sort_fields[col]['order'].toLowerCase()
                : '';
        }
    },

    row: {
        to: function() {
            var til_post;
            if (ds.table.count_records < (parseInt(ds.table.offset) + parseInt(ds.table.limit))) {
                til_post = ds.table.count_records;
            } else {
                til_post = parseInt(ds.table.offset) + parseInt(ds.table.limit);
            }
            return til_post;
        }
    },

    sort: function(col) {
        var list = ds.table;
        var sort = [];
        var order;
        if (list.sort_fields[col] && list.sort_fields[col]['idx'] == 0) {
            order = list.sort_fields[col]['order'] == 'ASC' ? 'DESC' : 'ASC';
        } else {
            order = 'ASC';
        }
        sort.push(col + ' ' + order);
        var data = {
            base: ds.base.name,
            table: list.name,
            filter: m.route.param('query') ? decodeURI(m.route.param('query')) : null,
            condition: m.route.param('where') ? decodeURI(m.route.param('where')) : null,
            sort: JSON.stringify(sort),
            offset: list.offset
        }
        this.get(data);
    },

    /**
     * Get table data from server
     *
     * @param {object} data      ajax data: base, table, condition, limit, offset, sort, filter, prim_key
     * @param {string}  selection selected record
     *
     */
    get: function(data) {

        m.request({
            method: "get",
            url: "table",
            data: data
        }).then(function(result) {
            ds.table = result.data;
            ds.table.dirty = false;

            // Parses sort data
            // TODO: Virker rart å måtte gjøre dette
            var sort_fields = {};
            $.each(ds.table.grid.sort_columns, function(i, value) {
                // splits the value into field and sort order
                var sort_order;
                var value_parts = value !== null ? value.split(' ') : [];
                var key = value_parts[0];
                if (value_parts[1] !== undefined) {
                    sort_order = value_parts[1];
                } else {
                    sort_order = 'ASC';
                }
                sort_fields[key] = {};
                sort_fields[key]['order'] = sort_order;
                // idx angir her hvilken prioritet dette feltet har i sorteringen
                sort_fields[key]['idx'] = i;
            });
            ds.table.sort_fields = sort_fields;
            ds.type = ds.table.type == 'database' ? 'content' : ds.table.type;

            ds.table.query = data.filter;

            ds.table.filters = filterpanel.parse_query(data.filter);

            if (ds.table.selection === null) ds.table.selection = 0;

            // Remount filterpanel to recreate value fields
            var $filterpanel = $('#filterpanel');
            m.mount($filterpanel[0], null);
            m.mount($filterpanel[0], filterpanel);

            m.redraw();

            // Show first record
            entry.select(ds.table, ds.table.selection, true);
            $('#urdgrid tr.focus').focus();

        }).catch(function(e) {
            // xhr errors have name 'Error', js errors don't
            if (e.name == 'Error') {
                alert(e.message);
            } else {
                console.error(e);
            }
        });
    },

    /**
     * Reloads table after save
     *
     * @param {object} list - The list that is shown
     * @param {object} data - Data returned from ajax save
     */
    update: function(list, data) {
        var p = {};
        var idx = list.selection;
        var post = list.records[idx];

        // Updates primary key for selected record from save response
        if (data.selected) {
            post.primary_key = data.selected;
        }

        p.base = ds.base.name;
        p.table = list.name;
        p.filter = m.route.param('query') ? decodeURI(m.route.param('query')) : null;
        p.condition = m.route.param('where') ? decodeURI(m.route.param('where')) : null;
        p.sort = JSON.stringify(list.grid.sort_columns);
        p.limit = list.limit;
        p.offset = list.offset;
        if (!post.delete) {
            p.prim_key = post.primary_key;
        } else {
            p.prim_key = null;
        }
        grid.get(p);
    },

    save: function() {
        var data = {
            base_name: ds.base.name,
            table_name: ds.table.name,
            records: []
        }

        var invalid = false;

        $.each(ds.table.records, function(idx, rec) {
            if (!rec.dirty && !rec.new) return;

            entry.validate(rec, true);

            if (rec.invalid) {
                invalid = true;
                return;
            }

            var changes = entry.get_changes(rec, true);
            if (idx == ds.table.selection) changes.selected = true;
            data.records.push(changes);
        });

        if (invalid) {
            alert('Rett feil før lagring');
            return;
        }

        m.request({
            method: 'put',
            url: 'table',
            data: data
        }).then(function(result) {
            $('#message').show().html('Lagring vellykket').delay(2000).fadeOut('slow');

            grid.update(ds.table, result.data);
        });

        return true;
    },

    check_dirty: function() {
        var txt = "Er du sikker på at du vil oppdatere innhold på siden?\n\n";
        var r; // Skal holde returverdi
        if (ds.table.dirty) {
            txt += "Du har foretatt endringer i listen. ";
            txt += "Endringene vil ikke bli lagret hvis du fortsetter.\n\n";
            txt += "Trykk OK for å fortsette, eller Avbryt for å bli stående.";
            r = confirm(txt);
            return r;
        }
        else return true;
    },

    load: function() {
        var params = m.route.param();
        var base_name = params['base'] ? params['base'] : ds.urd_base;
        var table_name = params['table'] ? params['table'] : 'database_';

        ds.load_database(base_name, function(data) {
            var search = params['query'] ? params['query'] : null;
            var condition = params['where'] ? params['where'] : null;

            filterpanel.advanced = condition ? true : false;

            grid.get({base: base_name, table: table_name, filter: search, condition: condition});
        });

        $('div[name="vis"]').removeClass('inactive');
        $('div[name="sok"]').addClass('inactive');
    },

    onremove: function(vnode) {
        // Make table load again after navigation
        // grid.url is used in datapanel.view to check if table should be loaded
        if (m.route.get() !== grid.url) {
            grid.url = '';
        }
    },

    draw_row: function(record, idx, indent) {

        return m('tr', {
            tabindex: 0,
            onclick: function(e) {
                e.redraw = false;
                entry.select(ds.table, idx);
            },
            onkeydown: function(e) {
                e.redraw = false;
                if (e.keyCode == 38) { // arrow up
                    $(this).prev('tr').focus();
                    e.preventDefault();
                } else if (e.keyCode == 40) { // arrow down
                    $(this).next('tr').focus();
                    e.preventDefault();
                } else if (e.keyCode == 13) { // enter
                    e.redraw = false;
                    $(this).trigger('click');
                    $('#main form:first').find(':input:enabled:not([readonly]):first').focus();
                } else if (e.keyCode == 32) { // space
                    $(this).trigger('click');
                    e.preventDefault();
                } else if (e.shiftKey && e.keyCode == 9) { // shift tab
                    $(this).prev('tr').trigger('click');
                } else if (e.keyCode == 9) { // tab
                    $(this).next('tr').trigger('click');
                }
            },
            class: [
                (ds.table.selection == idx) ? 'bg-light-blue focus' : '',
                'bb b--light-gray lh-copy cursor-default',
                record.class ? record.class : '',
            ].join(' ')
        }, [
            m('td', {
                align: 'right',
                class: [
                    'linjenr w1',
                    idx < ds.table.records.length - 1 ? 'bb b--light-gray' : '',
                ].join(' ')
            }, [
                config.autosave ? m.trust('&nbsp;') : m('i', {
                    class: [
                        record.delete  ? 'fa fa-trash'       :
                            record.invalid ? 'fa fa-warning red' :
                            record.new     ? 'fa fa-plus-circle' :
                            record.dirty   ? 'fa fa-pencil light-gray' : ''
                    ]
                })
            ]),
            Object.keys(ds.table.grid.columns).map(function(label, colidx) {
                var col = ds.table.grid.columns[label];

                // Check if this is an action
                if (col.indexOf('actions.') > -1) {
                    var parts = col.split('.');
                    var action_name = parts[1];
                    var action = ds.table.actions[action_name];
                    action.alias = action_name;

                    return control.draw_action_button(record, action);
                }

                return control.draw_cell(ds.table, idx, col, {compressed: config.compressed, border: true});
            })
        ]);
    },

    view: function(vnode) {

        if (ds.table.search) return;

        return [m('table#urdgrid.tbl', {class: 'max-w10 ba b--moon-gray flex flex-column overflow-auto', style: 'border-spacing: 0;'}, [
            m('thead', {class: 'db'}, [
                m('tr', {class: 'cursor-default'}, [
                    m('th', {class: 'tl bb b--moon-gray bg-light-gray normal f6 pb0 w1'}, ''),
                    Object.keys(ds.table.grid.columns).map(function(label, idx) {
                        var col = ds.table.grid.columns[label];

                        var field = ds.table.fields[col];

                        // If this is for instance an action
                        if (field === undefined) {
                            return m('th', '');
                        }

                        var label = isNaN(parseInt(label)) ? label
                            : ds.table.fields[col].label ? ds.table.fields[col].label
                            : col;
                        return m('th', {
                            class: 'tl bl bb b--moon-gray bg-light-gray f6 pa1 pb0 nowrap truncate dib',
                            onclick: grid.sort.bind(grid, col)
                        }, m('div', {class: 'flex'}, [ m('span', {class: "flex-auto truncate", title: label}, label), [
                            !grid.column.order(col) ? '' : m('i.fa', {
                                class: 'pl1 di fa-angle-' + (grid.column.order(col) === 'asc' ? 'down' : 'up')
                            })
                        ]]));
                    })
                ])
            ]),
            m('tbody', {class: 'db overflow-y-auto overflow-x-hidden'}, [
                ds.table.records.map(function(record, idx) {
                    record.base_name = ds.base.name;
                    record.table_name = ds.table.name;
                    if (record.dirty) entry.validate(record);

                    return record.hidden ? '' : grid.draw_row(record, idx, 0);
                })
            ]),
            (!ds.table.grid.sums) ? null : m('tfoot', [
                m('tr', {class: 'bg--light-gray'}, [
                    m('td', {class: 'tl bt b--moon-gray pb0 bg-light-gray'}, m.trust('')),
                    Object.keys(ds.table.grid.columns).map(function(label, idx) {
                        var col = ds.table.grid.columns[label];
                        return m('td', {
                            class: 'tr bl bt b--moon-gray bg-light-gray f6 pa1 pb0 nowrap dib'
                        }, (col in ds.table.grid.sums) ? m.trust(numeral(ds.table.grid.sums[col]).format()) : m.trust('&nbsp'));
                    })
                ])
            ])
        ])];
    }
};

module.exports = grid;

// Place here modules which requires grid (circular reference)
var filterpanel = require('./filterpanel.js');



var m = require('mithril');
var $ = require('jquery');
var moment = require('moment');
var numeral = require('numeral');
require('numeral/locales/no');
var entry = require('./entry.js');
var control = require('./control.js');
var config = require('./config.js');
var ds = require('./datastore.js');
