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
    },

    check_display: function(item) {
        if (typeof item == 'object') {
            Object.keys(item.items).map(function(label) {
                var subitem = item.items[label];
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


    draw_item: function(label, item, level) {
        if (typeof item == 'object' && !item.item) {
            var display = item.hidden ? 'none' : 'block';
            return m('.module', {
                class: item.class_module,
                style: 'display:' + item.display
            }, [
                m('i', {
                    class: [
                        item.hidden ? 'fa fa-angle-right': 'fa fa-angle-down',
                        item.class_label || 'f'+level,
                        'mr1',
                        'light-silver'
                    ].join(' '),
                    onclick: function(e) {
                        item.hidden = !item.hidden;
                    }
                }),
                m('.label', {
                    class: item.class_label || ' f'+level,
                    style: 'display: inline',
                    onclick: function(e) {
                        item.hidden = !item.hidden;
                    },
                    oncontextmenu: function(event) {
                        contents.context_module = label;
                        $('ul#context-module').css({top: event.clientY, left: event.clientX}).toggle();
                        return false;
                    }
                }, label),
                !item.count || !config.admin ? '' : m('span', {
                    class: 'ml2 light-silver'
                }, '(' + item.count + ')'),
                m('.content', {
                    class: item.class_content,
                    style: 'display: ' + display
                    }, [
                    Object.keys(item.items).map(function(label) {
                        var subitem = item.items[label];
                        if (_isArray(item.items)) {
                            var obj = _get(ds.base, subitem);
                            if (obj === undefined) return;
                            label = obj.label;
                        }
                        return contents.draw_item(label, subitem, level+1);
                    })
                ])
            ]);
        } else {
            if (typeof item == 'object') {
                var subitems = item.items;
                item = item.item;
            } else subitems = false
            var object = _get(ds.base, item, ds.base.tables[item]);
            if (item.indexOf('.') == -1) item = 'tables.' + item;
            if (object.hidden && !config.admin) return;
            var icon = object.type && (object.type.indexOf('reference') !== -1)
                ? 'fa-list'
                : 'fa-table';
            var icon_color = object.hidden ? 'light-gray' : 'light-silver';
            var title = object.type && (object.type.indexOf('reference') !== -1)
                ? 'Referansetabell'
                : 'Datatabell'
            var display = object.type && (object.type.indexOf('reference') !== -1) && !config.admin
                    ? 'none'
                    : 'inline';
            return m('div', {
                class: ds.table && ds.table.name == object.name ? 'bg-light-gray' : '',
                oncontextmenu: function(event) {
                    if (!config.admin) return false;
                    contents.context_table = object;

                    var hidden_txt = object.hidden ? 'Vis tabell' : 'Skjul tabell';
                    $('ul#context-table li.hide').html(hidden_txt);

                    var type_txt = object.type == 'reference'
                        ? 'Sett til datatabell'
                        : 'Sett til referansetabell';
                    $('ul#context-table li.type').html(type_txt);

                    $('ul#context-table').css({top: event.clientY, left: event.clientX}).toggle();
                    return false;
                }
            }, [
                m('i', {
                    class: icon_color + ' mr1 fa ' + icon,
                    style: 'display:' + display,
                    title: title
                }),
                m('a', {
                    class: [
                        'black underline-hover',
                        object.description ? 'dot' : 'link'
                    ].join(' '),
                    title: object.description ? object.description : '',
                    style: 'display:' + display,
                    href: '#/' + ds.base.name + '/' + item.replace('.', '/')
                }, label),
                !object.count_rows || !config.admin ? '' : m('span', {
                    class: 'ml2 light-silver',
                    style: 'display:' + display
                }, '(' + object.count_rows + ')'),
                !subitems ? '' : m('.content', {
                    style: 'margin-left:' + 18 + 'px',
                }, [
                    Object.keys(subitems).map(function(label) {
                        var subitem = subitems[label];
                        return contents.draw_item(label, subitem, level + 1);
                    })
                ])
            ]);
        }
    },

    set_dirty_attr: function(tbl, attr, value) {
        if (ds.schema.config.dirty === undefined) {
            ds.schema.config.dirty = {};
        }

        if (ds.schema.config.dirty[tbl.name] === undefined) {
            ds.schema.config.dirty[tbl.name] = {};
        }

        ds.schema.config.dirty[tbl.name][attr] = value;
    },

    view: function() {
        if ((!ds.base.contents && !ds.base.tables) || ds.base.name !== m.route.param('base')) return;

        if (!ds && !ds.base.contents) return;

        return m('.contents', {class: "flex"}, [
            m('ul#context-module', {
                class: 'absolute left-0 bg-white list pa1 shadow-5 dn pointer z-999'
            }, [
                m('li', {
                    class: 'hover-blue',
                    onclick: function() {
                        var module = contents.context_module;
                        var def = ['classDiagram'];

                        Object.values(ds.base.contents[module].items).map(function(item) {
                            var object = _get(ds.base, item, ds.base.tables[item]);
                            diagram.draw_foreign_keys(object, def, ds.base.contents[module]);
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

                        contents.set_dirty_attr(tbl, 'hidden', tbl.hidden);
                    }
                }, 'Skjul tabell'),
                m('li.type', {
                    class: 'hover-blue',
                    onclick: function() {
                        var tbl = contents.context_table;
                        tbl.type = tbl.type == 'data'
                            ? 'reference'
                            : 'data';
                        $('ul#context-table').hide();

                        contents.set_dirty_attr(tbl, 'type', tbl.type)
                    }
                }, 'Sett til referansetabell')
            ]),
            m('.list', {class: "flex flex-column overflow-auto min-w5"}, [
                ds.base.contents && Object.keys(ds.base.contents).length
                    ? Object.keys(ds.base.contents).map(function(label) {
                        var item = ds.base.contents[label];
                        item.display = Stream('none');
                        contents.check_display(item);
                        var retur = contents.draw_item(label, item, 3);
                        return retur;
                    })
                    : Object.keys(ds.base.tables).map(function(name) {
                        var table = ds.base.tables[name];
                        return contents.draw_item(table.label, 'tables.'+name, 3);
                    }),
            ]),
        ]);

    }
}

module.exports = contents;
