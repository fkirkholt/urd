var search = {

    oninit: function(vnode) {
        var table = vnode.attrs.table ? vnode.attrs.table : ds.table;
        config.filter = false;
        if (!config.edit_search) {
            table.filters = {};
        }
    },

    toggle_relation: function(field) {

        m.request({
            method: "GET",
            url: "table",
            params: {
                base: field.foreign_key.base,
                table: field.foreign_key.table,
                limit: 0
            }
        }).then(function(result) {
            field.relation = result.data;
            field.relation.alias = field.name;
            // mark the relation so that we don't expand more than one level deep
            field.relation.sublevel = true;

            if (field.expanded) {
                field.expanded = false;
                return;
            } else if (field.foreign_key) {
                field.expanded = true;
            }
        });
    },

    draw_field: function(table, item, label) {

        // If this is a heading
        if (typeof item === 'object') {
            label = item.label ? item.label : label;

            return [
                m('tr', [
                    m('td', {class: 'tc'}, [
                        item.inline && item.expandable === false ? '' : m('i.fa', {
                            class: item.expanded ? 'fa-angle-down' : 'fa-angle-right',
                            onclick: function() {
                                if (item.expandable === false) return;

                                item.expanded = !item.expanded;
                            }
                        })
                    ]),
                    m('td.label', {
                        class: [
                            'f6 nowrap pr2',
                            !item.inline || item.expandable ? 'b' : ''
                        ].join(' '),
                        colspan: item.inline ? 1 : 3,
                        onclick: function() {
                            item.expanded = !item.expanded;
                        }
                    }, label),
                ]),
                !item.expanded ? null : m('tr', [
                    m('td'),
                    m('td', {colspan: 3}, [
                        m('table', [
                            Object.keys(item.items).map(function(label, idx) {
                                var col = item.items[label];
                                return search.draw_field(table, col, label);
                            })
                        ]),
                    ])
                ])
            ]
        }

        // If this is a field
        if (typeof item === 'string' && item.indexOf('relations') == -1 && item.indexOf('actions.') == -1) {
            var field = table.fields[item];

            label = isNaN(parseInt(label)) ? label: field.label;
            var operators = filterpanel.get_operators(field);

            if (table.alias === undefined) table.alias = table.name;
            var filtername = table.alias == ds.table.name ? field.name : (table.alias || table.name) + '.' + field.name;

            if (!ds.table.filters[filtername]) {
                ds.table.filters[filtername] = {
                    field: filtername,
                    operator: field.element == 'textarea' || (
                        field.element == 'input[type=text]' &&
                            ['integer', 'decimal', 'float'].indexOf(field.datatype) == -1
                    ) ? 'LIKE' : '='
                }
            }

            var filter = ds.table.filters[filtername];
            field.value = filter.value;

            return [
                m('tr', [
                    m('td', {class: 'tc v-top'}, [
                        !field.foreign_key || !field.expandable || table.sublevel ? null : m('i.fa', {
                            class: !field.expanded ? 'fa-angle-right' : field.expandable ? 'fa-angle-down' : '',
                            onclick: function() {
                                search.toggle_relation(field)
                            }
                        })
                    ]),
                    // label
                    m('td.label', {
                        class: 'f6 nowrap pr1 v-top max-w5 truncate',
                        title: label
                    }, label),
                    // operator
                    m(select, {
                        class: 'mr2 w5',
                        options: operators,
                        required: true,
                        value: filter.operator,
                        width: '100px',
                        onchange: function(e) {
                            filter.operator = e.target['value'];

                            // This code recreates the value field, to run oncreate again
                            // with attributes for the new field
                            filter.disabled = true;
                            m.redraw();
                            filter.disabled = false;
                        }
                    }),
                    // input
                    m('td', {
                        class: 'max-w7 w-100'
                    }, filterpanel.draw_value_field(field, filter))
                ]),
                !field.expanded ? null : m('tr', [
                    m('td'),
                    m('td', {colspan: 3}, m(search, {table: field.relation}))
                ])
            ]
        }
    },

    view: function(vnode) {

        var table = vnode.attrs.table ? vnode.attrs.table : ds.table;

        return [
            m('div', {class: 'ml3'}, [vnode.attrs.table ? '' : m('div',[
            m('input[type=button]', {
                value: 'Utfør søk',
                onclick: function () {
                    filterpanel.search();
                }
            }),
            m('input[type=button]', {
                value: 'Avbryt',
                onclick: function () {
                    ds.table.search = !ds.table.search;
                    m.redraw();
                }
            }),
            /*
            m('input[type=button]', {
                value: 'Avansert',
                onclick: function() {
                    config.filter = true;
                    ds.table.filters = filterpanel.parse_query(ds.table.query);
                    filterpanel.expanded = true;
                    config.search = false;
                    ds.table.search = false;
                }
            }),
            */
            m('input[type=button]', {
                value: 'Nullstill skjema',
                disabled: Object.keys(ds.table.filters).filter(function (label) {
                    var filter = ds.table.filters[label];
                    return filter.value || ['IS NULL', 'IS NOT NULL'].includes(filter.operator);
                }).length === 0,
                onclick: function () {
                    ds.table.filters = {};
                }
            }),
            m('input[type=checkbox]', {
                class: 'ml2',
                checked: config.edit_search,
                onchange: function () {
                    config.edit_search = !config.edit_search;
                    Cookies.set('edit_search', config.edit_search, { expires: 14 });
                    if (config.edit_search) {
                        ds.table.filters = filterpanel.parse_query(ds.table.query);
                    }
                }
            }), ' Vis aktive søkekriterier']),
            m('form[name="search"]', {
                class: 'flex flex-column',
                style: 'flex: 0 0 550px;'
            }, m('table[name=search]', {
                class: 'pt1 pl1 pr2 flex flex-column',
                style: '-ms-overflow-style:-ms-autohiding-scrollbar'
            }, m('tbody', [
                Object.keys(table.form.items).map(function(label, idx) {
                    var item = table.form.items[label];

                    if (typeof item !== 'object' && item.indexOf('.') === -1 && table.fields[item].defines_relaton) {
                        return;
                    }

                    return search.draw_field(table, item, label);
                })
            ])))])
        ]
    }
}

module.exports = search;

var control = require('./control.js');
var filterpanel = require('./filterpanel.js');
var select = require('./select.js');
var config = require('./config.js');
