var m = require('mithril');
var $ = require('jquery');
var _assign = require('lodash/assign');
var _get = require('lodash/get')
var _isArray = require('lodash/isArray');
var ds = require('./datastore.js');
var Stream = require('mithril/stream');
var config = require('./config.js');

contents = {

    oninit: function(vnode) {
        $('#right_content').hide();

        $('#progress [value="OK"]').on('click', function() {
            $(this).hide();
            $('#progress [name="message"]').text('');
            $('#progress').hide();
            $('.curtain').hide();
        });
    },

    check_display: function(item) {
        if (typeof item == 'object') {
            Object.keys(item.subitems).map(function(label) {
                var subitem = item.subitems[label];
                if (typeof subitem == 'object') {
                    subitem.display = Stream('none');
                    if (item.display() == 'none') {
                        item.display = subitem.display
                    }
                } else {
                    var object = _get(ds.base, subitem, ds.base.tables[subitem]);
                    if (object === undefined) return;
                    if (object.type != 'reference' || config.admin == true) {
                        item.display('block');
                    }
                }
                return contents.check_display(subitem);
            })
        }
    },

    display_header: function(node, display) {
        display = display || 'none'
        Object.keys(node.subitems).map(function(label) {
            var subitem = node.subitems[label]
            if (typeof(subitem) == 'object') {
                display = contents.display_header(subitem, display)
            } else {
                var object = _get(ds.base, subitem, ds.base.tables[subitem])
                if (object.hidden != true || config.admin) {
                    display = 'block'
                }
            }
        })

        return display
    },


    draw_node: function(label, node, level) {
        if (typeof node == 'object' && !node.item) {
            var display = node.expanded ? 'block' : 'none';
            var display_header = contents.display_header(node)
            return m('.module', {
                class: node.class_module,
                style: 'display:' + display_header
            }, [
                m('i', {
                    class: [
                        node.expanded ? 'fa fa-angle-down': 'fa fa-angle-right',
                        node.class_label,
                        'w1 tc',
                        'light-silver'
                    ].join(' '),
                    onclick: function(e) {
                        node.expanded = !node.expanded;
                    }
                }),
                m('.label', {
                    class: [
                        node.class_label,
                        'di b'
                    ].join(' '),
                    onclick: function(e) {
                        node.expanded = !node.expanded;
                    },
                    oncontextmenu: function(event) {
                        contents.context_module = label;
                        $('ul#context-module').css({top: event.clientY, left: event.clientX}).toggle();
                        return false;
                    }
                }, label),
                !node.count || !config.admin ? '' : m('span', {
                    class: 'ml2 light-silver'
                }, '(' + node.count + ')'),
                m('.content', {
                    class: node.class_content,
                    style: 'display: ' + display
                    }, [
                    Object.keys(node.subitems).map(function(label) {
                        var subitem = node.subitems[label];
                        if (_isArray(node.subitems)) {
                            var obj = _get(ds.base, subitem);
                            if (obj === undefined) return;
                            label = obj.label;
                        }
                        return contents.draw_node(label, subitem, level+1);
                    })
                ])
            ]);
        } else {
            var subitems;
            if (typeof node == 'object') {
                subitems = node.subitems;
                item = node.item;
                var display_chevron = contents.display_header(node)
            } else {
                subitems = false;
                item = node;
            }
            var object = _get(ds.base, item, ds.base.tables[item]);
            var grid_idx_name = object.name + '_grid_idx'
            var grid_defined = false
            if (object.indexes && object.indexes[grid_idx_name]) {
                grid_defined = true
            }
            if (item.indexOf('.') == -1) item = 'tables.' + item;
            if (
                ((object.hidden || object.type == 'list') &&
                 !config.admin) || object.type == 'xref'
            ) {
                return
            };
            var icon = object.type && (object.type == 'list') ? 'fa-list'
                : 'fa-table';
            var icon_color = object.hidden ? 'moon-gray' : 'silver';
            var title = object.type && (object.type.indexOf('reference') !== -1)
                ? 'Referansetabell'
                : null
            var display = object.type && (object.type.indexOf('reference') !== -1) &&
                          !config.admin && !grid_defined
                ? 'none'
                : 'inline';

            return m('div', {
                class: ds.table && ds.table.name == object.name ? 'bg-light-gray nowrap' : 'nowrap',
                oncontextmenu: function(event) {
                    if (!config.admin) return false;
                    contents.context_table = object;

                    var hidden_txt = object.hidden ? 'Vis tabell' : 'Skjul tabell';
                    $('ul#context-table li.hide').html(hidden_txt);

                    /*
                    var type_txt = object.type == 'reference'
                        ? 'Sett til datatabell'
                        : 'Sett til referansetabell!!!';
                    $('ul#context-table li.type').html(type_txt);
                    */

                    $('ul#context-table').css({top: event.clientY, left: event.clientX}).toggle();
                    return false;
                }
            }, [
                typeof node != 'object' ? '' : m('i', {
                    class: [
                        node.expanded ? 'fa fa-angle-down' : 'fa fa-angle-right',
                        'w1 tc',
                        'light-silver'
                    ].join(' '),
                    style: (display == 'none' || display_chevron == 'none') ? 'display: none' : '',
                    onclick: function (e) {
                        node.expanded = !node.expanded;
                    }
                }),
                m('i', {
                    class: [
                        icon_color + ' mr1 fa ' + icon,
                        (typeof node == 'object' && display_chevron == 'block') ? '' : 'ml3'
                    ].join(' '),
                    style: 'display:' + display,
                    title: title
                }),
                m('a', {
                    class: [
                        'black underline-hover nowrap',
                        object.description ? 'dot' : 'link',
                        object.type == 'view' ? 'i' : ''
                    ].join(' '),
                    title: object.description ? object.description : '',
                    style: 'display:' + display,
                    href: '#/' + ds.base.name + '/' + object.name
                }, label),
                !object.rowcount || !config.admin ? '' : m('span', {
                    class: 'ml2 light-silver',
                    style: 'display:' + display
                }, '(' + object.rowcount + ')'),
                !subitems || !node.expanded ? '' : m('.content', {
                    style: 'margin-left:' + 18 + 'px',
                }, [
                    Object.keys(subitems).map(function(label) {
                        var subitem = subitems[label];
                        return contents.draw_node(label, subitem, level + 1);
                    })
                ])
            ]);
        }
    },

    draw_foreign_keys: function(node, def) {
        var item = node.item ? node.item : node
        var object = _get(ds.base, item, ds.base.tables[item])
        diagram.draw_foreign_keys(object, def, ds.base.contents[module])

        if (!node.subitems) {
            return
        }

        Object.values(node.subitems).map(function(subnode) {
            contents.draw_foreign_keys(subnode, def)
        })
    },

    view: function() {
        if (!ds.base.contents && !ds.base.tables) return;

        if (!ds && !ds.base.contents) return;

        return [m('.contents', {class: "flex"}, [
            m('ul#context-module', {
                class: 'absolute left-0 bg-white list pa1 shadow-5 dn pointer z-999'
            }, [
                m('li', {
                    class: 'hover-blue',
                    onclick: function() {
                        var module = contents.context_module;
                        var def = ['erDiagram'];

                        Object.values(ds.base.contents[module].subitems).map(function(node) {
                            contents.draw_foreign_keys(node, def)
                        });

                        diagram.def = def.join("\n");
                        $('ul#context-module').hide();
                    }
                }, 'Vis diagram')
            ]),
            m('ul#context-table', {
                class: 'absolute left-0 bg-white list pa1 shadow-5 dn pointer z-999'
            }, [
                config.show_table ? '' : m('li', {
                    class: 'hover-blue',
                    onclick: function() {
                        diagram.add_path(contents.context_table);
                        $('ul#context-table').hide();
                    }
                }, 'Vis koblinger til denne tabellen'),
                m('li.hide', {
                    class: 'hover-blue',
                    onclick: function() {
                        var tbl = contents.context_table;
                        tbl.hidden = !tbl.hidden;
                        $('ul#context-table').hide();

                        ds.set_cfg_value(tbl, 'hidden', tbl.hidden);
                    }
                }, 'Skjul tabell'),
                /*
                m('li.type', {
                    class: 'hover-blue',
                    onclick: function() {
                        console.log('valgte contextmenyitem')
                        var tbl = contents.context_table;
                        tbl.type = tbl.type == 'data'
                            ? 'reference'
                            : 'data';
                        $('ul#context-table').hide();

                        contents.set_cfg_value(tbl, 'type', tbl.type)
                    }
                }, 'Sett til referansetabell???')
                */
            ]),
            m('.list', {class: "flex flex-column overflow-auto min-w5"}, [
                !ds.base.schemata || ds.base.schemata.length < 2 ? '' : m('select', {
                    class: 'mb2',
                    onchange: function() {
                        var schema = $(this).val()
                        db_name = ds.base.name.split('.')[0]
                        m.route.set('/' + db_name + '.' + schema)
                    }
                }, [
                    ds.base.schemata.map(function(schema, idx) {
                        var selected = (schema == ds.base.schema)
                        return m('option', {
                            value: schema,
                            selected: selected
                        }, schema);
                    })
                ]),
                ds.base.contents && Object.keys(ds.base.contents).length
                    ? Object.keys(ds.base.contents).sort().map(function(label) {
                        var item = ds.base.contents[label];
                        item.display = Stream('none');
                        contents.check_display(item);
                        var retur = contents.draw_node(label, item, 3);
                        return retur;
                    })
                    : Object.keys(ds.base.tables).map(function(name) {
                        var table = ds.base.tables[name];
                        return contents.draw_node(table.label, 'tables.'+name, 3);
                    }),
                m('div', [
                    !((ds.type == 'contents' || !config.show_table) && config.admin)
                        ? '' : m('ul', { target: '_blank', class: 'f6 list pa1' }, [
                            m('li', { class: 'dib' }, [
                                m('i', {
                                    class: [
                                        'fa ml1 mr3 pointer dim',
                                        config.show_table ? 'fa-project-diagram' : 'fa-table'
                                    ].join(' '),
                                    title: config.show_table ? 'Vis ER-diagram' : 'Vis tabell',
                                    onclick: function () {
                                        config.show_table = !config.show_table;

                                        if (config.show_table) {
                                            m.redraw();
                                            grid.align_thead();
                                        }
                                    }
                                })
                            ]),
                            m('li', { class: 'dib' }, [
                                m('i', {
                                    class: 'fa fa-edit',
                                    title: 'Oppdater skjema fra database',
                                    onclick: function () {
                                        $('#action-dialog').load('urd/dialog_schema?version=1');
                                        $('div.curtain').show();
                                        $('#action-dialog').show();
                                    }
                                })
                            ])
                         ]),
                ]),
            ]),
        ]), ds.table || !ds.base.description ? '' : m('div', {class: 'pl5'}, [
            m('b', 'Beskrivelse'),
            m('br'),
            m('p', ds.base.description)
        ])]

    }
}

module.exports = contents;
