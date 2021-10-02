var pagination = {

    navigate: function(name) {
        var list = ds.table;
        var offset = parseInt(list.limit)+parseInt(list.offset);
        var sort = list.grid.sort_columns;
        var selection = 0;
        switch (name) {
        case 'next':
            offset = parseInt(list.offset) + parseInt(list.limit);
            break;
        case 'previous':
            offset = parseInt(list.offset) - list.limit;
            selection = config.limit - 1;
            break;
        case 'last':
            offset = Math.floor((list.count_records-1)/list.limit)*list.limit;
            selection = list.count_records - offset - 1;
            break;
        case 'first':
            offset = 0;
            break;
        }
        var data = {
            base: ds.base.name,
            table: list.name,
            filter: m.route.param('query') ? decodeURI(m.route.param('query')) : null,
            condition: m.route.param('where') ? decodeURI(m.route.param('where')) : null,
            sort: JSON.stringify(sort),
            limit: config.limit,
            offset: offset
        };

        m.request({
            method: "get",
            url: "table",
            params: data
        }).then(function(result) {
            ds.table.records = result.data.records;
            ds.table.offset = result.data.offset;
            m.redraw(true);
            ds.table.selection = selection;
            entry.select(ds.table, 0, true);
        });

    },

    disabled: function(name, table) {
        if (name == 'first' || name == 'previous') {
            return table.offset == 0 ? true : false;
        } else {
            return (parseInt(table.count_records) - parseInt(table.offset) <= parseInt(table.limit))
                ? true
                : false;
        }
    },

    view: function(vnode) {
        var args = vnode.attrs;
        var table = vnode.attrs.table;
        var param = m.route.param();
        return m('div', [
            m('div[name="statuslinje"]', {
                class: 'f6 fl mb1 mt1 ml1'
            }, [args.count ? args.from + '-' + args.to + ' av ' + args.count : args.count]),
            m('div[name="navigation"]', {
                class: 'fr ml2 mb1 mt1 mr1',
                onclick: function(e) {
                    pagination.navigate(e.target.name);
                }
            }, [
                m('button[name="first"]', {
                    class: [
                        'icon fa fa-angle-double-left ba b--light-silver br0 bg-white',
                        pagination.disabled('first', table) ? 'moon-gray' : ''
                    ].join(' '),
                    disabled: pagination.disabled('first', table)
                }),
                m('button[name=previous]', {
                    class: [
                        'icon fa fa-angle-left bt br bl-0 bb b--light-silver br0 bg-white',
                        pagination.disabled('previous', table) ? 'moon-gray' : '',
                    ].join(' '),
                    disabled: pagination.disabled('previous', table)
                }),
                m('button[name=next]', {
                    class: [
                        'icon fa fa-angle-right bt br bb bl-0 b--light-silver br0 bg-white',
                        pagination.disabled('next', table) ? 'moon-gray' : '',
                    ].join(' '),
                    disabled: pagination.disabled('next', table)
                }),
                m('button[name=last]', {
                    class: [
                        'icon fa fa-angle-double-right bt br bb bl-0 b--light-silver br0 bg-white',
                        pagination.disabled('last', table) ? 'moon-gray' : '',
                    ].join(' '),
                    disabled: pagination.disabled('last', table)
                })
            ]),
        ]);
    }


};

module.exports = pagination;

var m = require('mithril');
var ds = require('./datastore.js');
var grid = require('./grid.js');
var config = require('./config.js');
var entry = require('./entry.js');
