var m = require('mithril');
var $ = require('jquery');
var mermaid = require('mermaid');
var _union = require('lodash/union');
var _repeat = require('lodash/repeat');

diagram = {
    def: "",
    main_table: "",

    oninit: function(vnode) {
        $('body').on('click', 'svg g.classGroup', function() {
            var table_name = $(this).find('text tspan.title').html();
            var table = ds.base.tables[table_name];

            diagram.draw(table);

            $('#mermaid').html(diagram.def).removeAttr('data-processed');
            mermaid.init(undefined, $("#mermaid"));
            $('#mermaid svg g.classGroup').addClass('pointer');
        });
    },

    onupdate: function(vnode) {

        if (this.def !== "") {
            mermaid.mermaidAPI.initialize({
                securityLevel: 'loose',
                themeCSS: 'g.classGroup text{font-family: Consolas, monaco, monospace;}'
            });
            $('#mermaid').html(this.def).removeAttr('data-processed');
            mermaid.init(undefined, $("#mermaid"));

            $('#mermaid svg g.classGroup').addClass('pointer');
        }
    },

    draw: function(table) {
        var def = ["classDiagram"];
        def.push("class " + table.name);

        diagram.main_table = table.name;

        if (table.count_rows) {
            def.push(table.name + ' : ' + 'count(' + table.count_rows + ')');
        }

        Object.keys(table.fields).map(function(alias) {
            var field = table.fields[alias];
            var sign = field.nullable ? '-' : '+';
            // number of invicible spaces to align column names
            var count = 6 - field.datatype.length;
            def.push(table.name + ' : ' + sign + field.datatype + _repeat('\u2000', count) + ' ' + field.name);
        });

        Object.keys(table.foreign_keys).map(function(alias) {
            var fk = table.foreign_keys[alias];
            var field = table.fields[alias];
            var label = field.label ? field.label : alias;
            def.push(table.name + (field.hidden ? ' ..> ' : ' --> ') + fk.table + ' : ' + label);

            var fk_table = ds.base.tables[fk.table];
            def.push(fk.table + ' : pk(' + fk_table.primary_key.join(', ') + ')');
            if (fk_table.count_rows && fk.table != table.name) {
                def.push(fk.table + ' : count(' + fk_table.count_rows + ')');
            }
        });

        Object.keys(table.relations).map(function(alias) {
            var rel = table.relations[alias];
            if (rel.table == table.name) return;
            def.push(rel.table + (rel.hidden ? " ..> " : " --> ") + table.name);

            var rel_table = ds.base.tables[rel.table];
            if (rel_table.count_rows) {
                def.push(rel.table + ' : count(' + rel_table.count_rows + ')');
            }
        });

        this.def = def.join("\n");
    },

    add_path: function(table) {
        var path = []
        var level = 0;
        path = diagram.get_path(table, path);

        if (path) {
            path = path.filter(function(line) {
                if (diagram.def.indexOf(line) !== -1) return false;
                // Check reversed relation
                if (diagram.def.indexOf(line.replace('<--', '-->').split(" ").reverse().join(" ")) !== -1) return false;

                return true
            });

            this.def += "\n" + path.join("\n");
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

            if (rel.table == diagram.main_table) {
                found_path = _union(found_path, new_path);

                return;
            } else {
                new_path = diagram.get_path(rel_table, new_path);
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

            if (fk.table == diagram.main_table) {
                found_path = found_path.concat(new_path);
                return new_path;
            } else {
                if (fk_table.type == 'reference') return;

                new_path = diagram.get_path(fk_table, new_path);
                if (new_path) {
                    found_path = _union(found_path, new_path);

                    return new_path;
                }
            }
        });

        return (found_path.length) ? found_path : false;
    },

    view: function() {
        return m('div.mermaid', {
            id: "mermaid",
            class: "flex flex-grow flex-column center overflow-auto"
        }, this.def);
    }
}

module.exports = diagram;
