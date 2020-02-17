var m = require('mithril');
var $ = require('jquery');
var _assign = require('lodash/assign');
var _get = require('lodash/get')
var _isArray = require('lodash/isArray');
var ds = require('./datastore.js');
var Stream = require('mithril/stream');
var config = require('./config.js');
var mermaid = require('mermaid');

window.callback = function(id) {
    var tablename = $('#'+id+' .title').html();
    var table = ds.base.tables[tablename];

    contents.draw_table_diagram(table);

    $('#mermaid').html(contents.diagram).removeAttr('data-processed');
    mermaid.init(undefined, $("#mermaid"));
}

contents = {

    diagram: "",

    oninit: function(vnode) {

        $('#right_content').hide();

        var report = m.route.param('report');
        var base_name = m.route.param('base');

        ds.table = null;
        ds.type = 'contents';

        ds.load_database(base_name);
    },

    onupdate: function(vnode) {

        var value = "classDiagram\nclass Test";

        if (this.diagram !== "") {
            mermaid.mermaidAPI.initialize({
                securityLevel: 'loose'
            });
            $('#mermaid').html(this.diagram).removeAttr('data-processed');
            mermaid.init(undefined, $("#mermaid"));
        }
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
                    var object = _get(ds.base, subitem);
                    if (object === undefined) return;
                    if (object.type != 'reference' || config.admin == true) {
                        item.display('block');
                    }
                }
                return contents.check_display(subitem);
            })
        }
    },

    draw_table_diagram: function(table) {
        var diagram = "classDiagram\n"
        diagram += "class " + table.name + "\n";

        Object.keys(table.foreign_keys).map(function(label) {
            var fk = table.foreign_keys[label];
            diagram += table.name + " --> " + fk.table + "\n";
            diagram += "callback " + fk.table + ' "callback" "Fokuser"' + "\n";
        });

        Object.keys(table.relations).map(function(label) {
            var rel = table.relations[label];
            diagram += rel.table + " --> " + table.name + "\n";
            diagram += "callback " + rel.table + ' "callback" "Fokuser"' + "\n";
        });

        this.diagram = diagram;
    },

    draw_item: function(label, item, level) {
        if (typeof item == 'object') {
            var display = item.hidden ? 'none' : 'block';
            return m('.module', {
                class: item.class_module,
                style: 'display:' + item.display
            }, [
                m('i', {
                    class: [
                        item.hidden ? 'fa fa-angle-right': 'fa fa-angle-down',
                        'f'+level,
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
                    }
                }, label),
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
            var object = _get(ds.base, item);
            var icon = object.type && (object.type.indexOf('reference') !== -1)
                ? 'fa-list'
                : 'fa-table';
            var title = object.type && (object.type.indexOf('reference') !== -1)
                ? 'Referansetabell'
                : 'Datatabell'
            var display = object.type && (object.type.indexOf('reference') !== -1) && !config.admin
                    ? 'none'
                    : 'inline';
            return m('div', [
                m('i', {
                    class: 'light-silver mr1 fa ' + icon,
                    style: 'display:' + display,
                    title: title,
                    onclick: function() {
                        contents.draw_table_diagram(object)
                    }}),
                m('a', {
                    style: 'display:' + display,
                    href: '#/' + ds.base.name + '/' + item.replace('.', '/')
                }, label)
            ]);
        }
    },

    view: function() {
        if ((!ds.base.contents && !ds.base.tables) || ds.base.name !== m.route.param('base')) return;

        if (!ds && !ds.base.contents) return;

        return m('.contents', {class: "flex w-100"}, [
            m('.list', {class: "flex flex-column overflow-auto"}, [
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
            m('div.mermaid', {
                    id: "mermaid",
                    class: "flex flex-grow flex-column center overflow-auto"
                }, this.diagram)
        ]);

    }
}

module.exports = contents;
