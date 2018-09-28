var config = require('./config.js');
var datapanel = require('./datapanel.js');
var grid = require('./grid.js');

var home = {

    load_databases: function() {

        grid.url = '';

        m.request({
            method: 'get',
            url: 'table',
            data: {
                base: ds.urd_base,
                table: 'database_'
            }
        }).then(function(result) {
            ds.table = result.data;
            // TODO Burde være unødvendig
            ds.table.filters = [];
            // TODO Burde være unødvendig
            ds.table.sort_fields = {};
        }).catch(function(e) {
            if (e.message !== 'login') {
                alert(e.message);
            }
        });
    },

    oninit: function() {
        ds.type = 'dblist';
        ds.table = null;
        ds.load_database(ds.urd_base);

        if (!ds.table) {
            home.load_databases();
        }
    },

    view: function(vnode) {
        if (!ds.table) return;

        if (config.admin) return m(datapanel);

        return m('div', [
            m('h2', 'Databaser'),
            m('ul', [
                ds.table.records.map(function(post, i) {
                    return m('li', [
                        m('h3', [
                            m('a', {
                                href: '#/' + (post.columns.alias ? post.columns.alias : post.columns.name)
                            }, post.columns.label),
                        ]),
                    ]);
                })
            ])
        ]);
    }
}

module.exports = home;
