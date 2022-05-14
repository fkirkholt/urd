var config = require('./config.js');
var datapanel = require('./datapanel.js');
var grid = require('./grid.js');

var home = {

    load_databases: function() {

        grid.url = '';

        m.request({
            method: 'get',
            url: 'dblist'
        }).then(function(result) {
            ds.table = result.data;
            // TODO Burde være unødvendig
            ds.table.filters = [];
            // TODO Burde være unødvendig
            ds.table.sort_fields = {};
        }).catch(function(e) {
            if (e.code === 401) {
                $('div.curtain').show();
                $('#login').show();
                $('#brukernavn').focus();
            } else {
                alert(e.response.detail);
            }
        });
    },

    oninit: function() {
        ds.type = 'dblist';
        ds.table = null;

        if (!ds.table) {
            home.load_databases();
        }
    },

    view: function(vnode) {
        if (!ds.table) return;

        if (config.admin) return m(datapanel);

        ds.type = 'dblist';

        return m('div', [
            m('h2', 'Databaser'),
            m('ul', [
                ds.table.records.map(function(post, i) {
                    return m('li', [
                        m('h4.mt1.mb1', [
                            m('a', {
                                href: '#/' + (post.columns.alias ? post.columns.alias : post.columns.name)
                            }, post.columns.label),
                        ]),
                        m('p.mt1.mb1', post.columns.description)
                    ]);
                })
            ])
        ]);
    }
}

module.exports = home;
