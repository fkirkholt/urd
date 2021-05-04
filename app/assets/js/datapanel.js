
var datapanel = {
    view: function(vnode) {
        if (m.route.get() != grid.url && config.show_table) {
            grid.load();
            grid.url = m.route.get();
        }

        if (!ds.table) return;

        ds.type = 'table';

        if (!config.show_table) {
            var table_name = m.route.param('table');
            if (ds.table && ds.table.name !== table_name) {
                ds.table = ds.base.tables[table_name];
            }

            if (diagram.main_table !== table_name) {
                diagram.draw(ds.base.tables[table_name]);
            }
        } else {
            var selected_idx = ds.table.selection !== null ? ds.table.selection : 0;
        }

        ds.table.invalid = false;
        $('.right.content').hide();
        if (ds.table.modus == 'search') return;
        $('.left.nav').show();
        $('.right.content').show();

        return config.show_table && !ds.table.records ? m('div', 'laster ...') : [
            m(contents),
            config.show_table ? '' : m(diagram),
            !config.show_table || ds.table.search || ds.table.edit
            ? ''
            : m('div#gridpanel', {
                class: 'flex flex-column ml2',
                style: [
                    'background: #f9f9f9',
                    'border: 1px solid lightgray',
                    ds.table.hide ? 'display: none' : ''
                ].join(';')
            }, [
                m(toolbar),
                m(grid),
                m(pagination, {
                    from: Number(ds.table.offset) + 1,
                    to: grid.row.to(),
                    count: ds.table.count_records,
                    table: ds.table
                })
            ]),

            !config.show_table || !ds.table.records ? '' : ds.table.search ? m(search) : m(entry, {
                record: ds.table.records[selected_idx]
            }),
            !config.show_table ? '' : m('div', {style: 'flex: 1'}),
        ];
    }
}

module.exports = datapanel;

var pagination = require('./pagination.js');
var toolbar = require('./toolbar.js');
var contents = require('./contents.js');
var grid = require('./grid.js');
var entry = require('./entry.js');
var search = require('./search.js');
var config = require('./config.js');
var diagram = require('./diagram.js');
