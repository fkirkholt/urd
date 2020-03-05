var m = require('mithril');
var $ = require('jquery');
var _assign = require('lodash/assign');
var _get = require('lodash/get')
var _isArray = require('lodash/isArray');
var _union = require('lodash/union');
var ds = require('./datastore.js');
var Stream = require('mithril/stream');
var config = require('./config.js');
var mermaid = require('mermaid');

contents = {

    diagram: "",
    diagram_table: "",

    oninit: function(vnode) {

        $('#right_content').hide();

        var report = m.route.param('report');
        var base_name = m.route.param('base');

        ds.table = null;
        ds.type = 'contents';

        ds.load_database(base_name);

        $('body').on('click', 'svg g.classGroup', function() {
            var table_name = $(this).find('text tspan.title').html();
            var table = ds.base.tables[table_name];

            contents.draw_table_diagram(table);
            contents.diagram_table = table_name;

            $('#mermaid').html(contents.diagram).removeAttr('data-processed');
            mermaid.init(undefined, $("#mermaid"));
            $('#mermaid svg g.classGroup').addClass('pointer');
        });
    },

    onupdate: function(vnode) {

        var value = "classDiagram\nclass Test";

        if (this.diagram !== "") {
            mermaid.mermaidAPI.initialize({
                securityLevel: 'loose'
            });
            $('#mermaid').html(this.diagram).removeAttr('data-processed');
            mermaid.init(undefined, $("#mermaid"));

            $('#mermaid svg g.classGroup').addClass('pointer');
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

    draw_table_diagram: function(table) {
        var diagram = ["classDiagram"];
        diagram.push("class " + table.name);
        if (table.count_rows) {
            diagram.push(table.name + ' : ' + 'count(' + table.count_rows + ')');
        }

        Object.keys(table.fields).map(function(alias) {
            var field = table.fields[alias];
            diagram.push(table.name + ' : ' + field.datatype + ' ' + field.name);
        });

        Object.keys(table.foreign_keys).map(function(alias) {
            var fk = table.foreign_keys[alias];
            var field = table.fields[alias];
            if (field.hidden) return;
            var label = field.label ? field.label : alias;
            diagram.push(table.name + " --> " + fk.table + ' : ' + label);

            var fk_table = ds.base.tables[fk.table];
            diagram.push(fk.table + ' : pk(' + fk_table.primary_key.join(', ') + ')');
        });

        Object.keys(table.relations).map(function(alias) {
            var rel = table.relations[alias];
            if (rel.hidden || rel.table == table.name) return;
            diagram.push(rel.table + " --> " + table.name);
        });

        this.diagram = diagram.join("\n");
    },

    add_table_diagram: function(table) {
        var path = []
        var level = 0;
        path = contents.get_path(table, path);

        if (path) {
            path = path.filter(function(line) {
                if (contents.diagram.indexOf(line) !== -1) return false;
                // Check reversed relation
                if (contents.diagram.indexOf(line.replace('<--', '-->').split(" ").reverse().join(" ")) !== -1) return false;

                return true
            });

            this.diagram += "\n" + path.join("\n");
        }
    },

    get_path: function(table, path) {

        // TODO: Bør kanskje finne annen måte enn å hardkode dette
        if (path.length > 3) {
            return false;
        }

        var new_path;
        var found_path = [];

        Object.keys(table.relations).map(function(label) {

            new_path = path.slice();

            var rel = table.relations[label];

            if ($.inArray(rel.table + ' --> ' + table.name, path) !== -1) return;

            var rel_table = ds.base.tables[rel.table];
            if (rel_table.hidden) return;

            new_path.push(table.name + ' <-- ' + rel.table);

            if (rel.table == contents.diagram_table) {
                found_path = _union(found_path, new_path);

                return;
            } else {
                new_path = contents.get_path(rel_table, new_path);
                if (new_path) {
                    found_path = _union(found_path, new_path);

                    return new_path;
                }
            }
        });

        Object.keys(table.foreign_keys).map(function(label) {
            new_path = path.slice();
            var fk = table.foreign_keys[label];

            if ($.inArray(fk.table + ' <-- ' + table.name, path) !== -1) return;

            var fk_table = ds.base.tables[fk.table];

            if (fk_table.hidden) return;

            new_path.push(table.name + ' --> ' + fk.table);

            if (fk.table == contents.diagram_table) {
                found_path = found_path.concat(new_path);
                return new_path;
            } else {
                if (fk_table.type == 'reference') return;

                new_path = contents.get_path(fk_table, new_path);
                if (new_path) {
                    found_path = _union(found_path, new_path);

                    return new_path;
                }
            }
        });

        return (found_path.length) ? found_path : false;
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
            var object = _get(ds.base, item, ds.base.tables[item]);
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
            return m('div', [
                m('i', {
                    class: icon_color + ' mr1 fa ' + icon,
                    style: 'display:' + display,
                    title: title,
                    onclick: function(event) {
                        contents.draw_table_diagram(object);
                        contents.diagram_table = object.name;
                    },
                    oncontextmenu: function(event){
                        if (!config.admin) return false;
                        contents.context_table = object;

                        var hidden_txt = object.hidden ? 'Vis tabell' : 'Skjul tabell';
                        $('ul#context li.hide').html(hidden_txt);

                        var type_txt = object.type == 'reference'
                            ? 'Sett til datatabell'
                            : 'Sett til referansetabell';
                        $('ul#context li.type').html(type_txt);

                        $('ul#context').css({top: event.clientY, left: event.clientX}).toggle();
                        return false;
                    }
                }),
                m('a', {
                    class: object.description ? 'dot' : 'link',
                    title: object.description ? object.description : '',
                    style: 'display:' + display,
                    href: '#/' + ds.base.name + '/' + item.replace('.', '/')
                }, label),
                !object.count_rows || !config.admin ? '' : m('span', {
                    class: 'ml2 light-silver',
                    style: 'display:' + display
                }, '(' + object.count_rows + ')'),
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

        return m('.contents', {class: "flex w-100"}, [
            m('ul#context', {
                class: 'absolute left-0 bg-white list pa1 shadow-5 dn pointer z-999'
            }, [
                m('li', {
                    class: 'hover-blue',
                    onclick: function() {
                        contents.add_table_diagram(contents.context_table);
                        $('ul#context').hide();
                    }
                }, 'Vis koblinger til denne tabellen'),
                m('li.hide', {
                    class: 'hover-blue',
                    onclick: function() {
                        var tbl = contents.context_table;
                        tbl.hidden = !tbl.hidden;
                        $('ul#context').hide();

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
                        $('ul#context').hide();

                        contents.set_dirty_attr(tbl, 'type', tbl.type)
                    }
                }, 'Sett til referansetabell')
            ]),
            m('.list', {class: "flex flex-column overflow-auto min-w6"}, [
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
