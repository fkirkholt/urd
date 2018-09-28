
var datapanel = {
    view: function(vnode) {
        if (m.route.get() != grid.url) {
            grid.load();
            grid.url = m.route.get();
        }

        if (!ds.table) return;

        var selected_idx = ds.table.selection !== null ? ds.table.selection : 0;
        var selected_record = ds.table.records[selected_idx];

        ds.table.invalid = false;
        $('.right.content').hide();
        if (ds.table.modus == 'search') return;
        $('.left.nav').show();
        $('.right.content').show();
        return !ds.table.records ? m('div', 'laster ...') : [
            !config.show_table || ds.table.search || ds.table.edit ? '' : m('div#gridpanel', {class: 'flex flex-column'}, [
                m(grid),
                m(pagination, {
                    from: Number(ds.table.offset) + 1,
                    to: grid.row.to(),
                    count: ds.table.count_records,
                    table: ds.table
                })
            ]),

            ds.table.search ? m(search) : m(entry, {
                record: selected_record
            }),
            m('div', {style: 'flex: 1'}),
        ];
    }
}

module.exports = datapanel;

var pagination = require('./pagination.js');
var toolbar = require('./toolbar.js');
var grid = require('./grid.js');
var entry = require('./entry.js');
var search = require('./search.js');
var config = require('./config.js');
