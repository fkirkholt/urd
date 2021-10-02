
module.exports = {

    get_items: function() {
        var items = [];
        var param = m.route.param();

        items.push({
            icon: "icon-crosshairs",
            text: "URD",
            addr: '',
            branch: ds.branch
        })

        if (param.base) {
            items.push({
                icon: "fa-database",
                text: ds.base.label,
                addr: ds.base.name
            });
        }

        if (param.table && ds.table) {
            items.push({
                icon: "fa-table",
                text: ds.table.label,
                addr: ds.base.name + '/' + ds.table.name
            })
        } else if (param.report) {
            // TODO: ds.base.reports sometimes undefined - unknown why
            if (ds.base.reports) {
                var report_name = m.route.param('report');
                var label = ds.base.reports[report_name].label;
                items.push({
                    icon: "fa-file-text-o",
                    text: label,
                    addr: ds.base.name + '/reports/' + ds.report.name
                })
            }
        }

        return items;

    },

    view: function(vnode) {
        var param = m.route.param();
        if (!param) return;
        var sti = this.get_items();

        return m('div', {class: 'fl'}, [
            sti.map(function(item, idx) {
                return [
                    m('a', {
                        href: "#/" + item.addr,
                        class: 'fw3 white no-underline underline-hover f4'
                    }, [m('i', {
                        class: [
                            'relative fa ' + item.icon,
                            idx === 0 ? 'f4 white' : 'f6 mr2 white',
                        ].join(' '),
                        style: item.icon !== 'table' ? 'bottom: 2px;' : ''
                    }), item.text]),
                    !item.branch || item.branch == 'master' ? '' : m('span', {class: 'light-silver'}, [
                        m('i', {class: 'fa fa-code-fork ml2'}),
                        ds.branch,
                    ]),
                    idx == sti.length - 1 ? '' : m('i', {class: 'fa fa-angle-right f3 fw3 ml2 mr2'})
                ];
            }),
        ]);
    }
}

var m = require('mithril');
var ds = require('./datastore.js');
var config = require('./config.js');
var filterpanel = require('./filterpanel.js');
