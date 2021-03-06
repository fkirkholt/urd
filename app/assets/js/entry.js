
var entry = {

    select: function(table, idx, root) {
        table.selection = idx;

        if (table.records.length == 0) return;

        // Don't load if already loaded
        if (table.records[idx].fields) {
            m.redraw();
            return;
        }

        m.request({
            method: "GET",
            url: 'record',
            data: {
                base: m.route.param('base'),
                table: table.name,
                primary_key: JSON.stringify(table.records[idx].primary_key)
            }
        }).then(function(result) {
            rec = $.extend(table.records[idx], result.data);
            rec.table = table;
            rec.root = root;

            rec.columns = table.records[idx].columns;
            entry.get_relations_count(rec);
        });
    },

    get_relations_count: function(rec) {
        m.request({
            method: "get",
            url: "relations",
            data: {
                base: rec.base_name,
                table: rec.table.name,
                primary_key: JSON.stringify(rec.primary_key),
                count: true
            }
        }).then(function(result) {
            rec.relations = result.data;
        });
    },

    get_relations: function(rec, alias) {
        $('.icon-crosshairs').addClass('fast-spin');
        m.request({
            method: "GET",
            url: "relations",
            data: {
                base: rec.base_name,
                table: rec.table.name,
                primary_key: JSON.stringify(rec.primary_key),
                count: false,
                alias: alias
            }
        }).then(function(result) {
            $('.icon-crosshairs').removeClass('fast-spin');
            _merge(rec.relations, result.data);
        });
    },

    create: function(list, relation) {

        if (list.actions.new) {
            toolbar.run_action(list.actions.new);
            return;
        }

        relation = relation ? relation : null;

        // all columns defaults to null
        var columns = {};
        $.each(list.grid.columns, function(i, col) {
            columns[col] = null;
        });

        // Adds record to end of table
        var idx = list.records.length;

        // Create new record with column specifications
        // after selected record
        list.records.splice(idx, 0, {
            primary_key: {},
            columns: columns,
            new: true
        })

        list.selection = idx;
        list.dirty = true;

        // create new record
        var rec = {
            base_name: ds.base.name,
            table_name: list.name,
            fields: $.extend(true, {}, list.fields),
            primary_key: {},
            groups: [] // TODO: This should be removed
        };

        // set standard value of field, and sets editable from list
        $.each(rec.fields, function(name, field) {
            field.name = name;
            var conditions = [];

            if (field.default) {
                field.value = field.default;
                field.dirty = true;
            } else {
                // Sets the value to filtered value if such filter exists
                if (!relation) {
                    $.each(list.filters, function(idx, filter) {
                        var parts = filter.field.split('.');
                        var table_name = parts[0];
                        var field_name = parts[1];

                        if (table_name === rec.table_name && field_name === field.name && filter.operator === '=') {
                            conditions.push(filter);
                        }
                    })
                }

                if (conditions.length === 1) {
                    field.value = conditions[0].value;
                    field.dirty = true;
                } else {
                    field.value = null;
                }
            }

            field.editable = field.editable === false ? field.editable : list.permission.edit;
            rec.fields[name] = field;

            // if (field.value) entry.update_field(field.value, field.name, rec);
        });

        rec.new = true;
        rec.dirty = true;
        rec.loaded = true;

        rec.columns = list.records[idx].columns;
        rec = $.extend(list.records[idx], rec);
        rec.table = list;

        if (!relation) {
            rec.root = true;
        } else {
            rec.fk = [];
            rec.open = true;
        }

        entry.get_relations_count(rec);

        $('#main form:first').find(':input:enabled:not([readonly]):first').focus();

        return rec;
    },

    copy: function() {
        var selected = ds.table.selection;
        var active_rec = ds.table.records[selected];
        var clone = {};
        clone.fields = $.extend(true, {}, active_rec.fields);
        $.each(clone.fields, function(name, field) {
            if (field.value) {
                field.dirty = true;
            }
        });
        clone.columns = $.extend(true, {}, active_rec.columns);
        clone.table = ds.table;
        clone.new = true;

        var idx = ds.table.selection + 1;
        ds.table.records.splice(idx, 0, clone);
        ds.table.selection = idx;
        ds.table.dirty = true;

        // Handles auto fields
        $.each(ds.table.fields, function(name, field) {
            if (field.extra) {
                clone.fields[name].value = field.default;
                clone.columns[name] = field.default;
                clone.fields[name].dirty = true;
                if (field.options) {
                    if (field.default) {
                        clone.text = field.options.find(function(d) {
                            return d.value === field.default
                        }).text;
                    } else {
                        clone.text = null;
                    }
                }
            }
        });

        clone.primary_key = {};
    },

    toggle_heading: function(object) {
        object.expanded
            ? object.expanded = false
            : object.expanded = true;
    },

    delete: function(rec) {
        rec.delete = rec.delete ? false : true;
        rec.dirty = true;
        ds.table.dirty = true;

        if (config.autosave) entry.save(rec);
    },


    /**
     * Expands or collapses foreign key relations
     * @param {} list
     * @param {} field
     */
    toggle_relation: function(rec, fieldname) {
        var field = rec.fields[fieldname];
        var list = rec.table;
        var idx = list.selection;
        if (field.expanded) {
            field.expanded = false;
            return;
        } else if (field.foreign_key) {
            field.expanded = true;
        }
        var filters = [];

        $.each(field.foreign_key.foreign, function(i, ref_field) {
            var fk_field = field.foreign_key.local[i];
            var value = rec.fields[fk_field].value;
            filters.push(field.foreign_key.table + '.' + ref_field + " = '" + value + "'");
        });

        m.request({
            method: "GET",
            url: "table",
            data: {
                base: field.foreign_key.base,
                table: field.foreign_key.table,
                filter: filters.join(' AND ')
            }
        }).then(function(result) {
            var table = result.data;
            if (table.count_records == 0) {
                alert('Fant ikke posten i databasen');
            }
            var pk = table.records[0].primary_key;
            m.request({
                method: "GET",
                url: "record",
                data: {
                    base: field.foreign_key.base,
                    table: field.foreign_key.table,
                    // betingelse: betingelse,
                    primary_key: JSON.stringify(pk)
                }
            }).then(function(result) {
                var record = result.data;
                record.readonly = true;
                record.table = table;
                table.records[0] = Object.assign(table.records[0], record);
                rec.fields[fieldname].relation = record;
                entry.get_relations_count(record);
            });
        });
    },

    toggle_record: function(rec, tbl) {

        if (config.relation_view === 'column') {
            tbl.records.map(function(record) {
                record.open = false;
            })
        }

        if (rec.open) {
            rec.open = false;
        } else {
            rec.open = true;
        }

        // Don't load record if it's already loaded
        if (rec.loaded) {
            return;
        }

        m.request({
            method: "GET",
            url: "record",
            data: {
                base: ds.base.name,
                table: tbl.name,
                primary_key: JSON.stringify(rec.primary_key)
            }
        }).then(function(result) {
            var rel = $.extend(rec, result.data);
            rel.table = tbl;
            rel.list = tbl;
            rec.loaded = true;
            entry.get_relations_count(rel);
            setTimeout(function() {
                $('#main').scrollLeft(420);
            }, 50);
        });
    },

    save: function(rec) {
        var changes = entry.get_changes(rec, false);

        var data = {
            base_name: rec.base_name,
            table_name: rec.table.name,
            primary_key: changes.prim_key,
            values: changes.values
        }

        m.request({
            method: changes.method,
            data: data,
            url: 'record'
        }).then(function(data) {
            $.each(changes.values, function(fieldname, value) {
                rec.fields[fieldname].dirty = false;
            });
            rec.new = false;
            $.each(data.values, function(fieldname, value) {
                rec.fields[fieldname].value = value;

                // Update value in grid cell
                if (rec.columns && fieldname in rec.columns) {
                    rec.columns[fieldname] = value;
                }

            });
            if (rec.delete){
                var idx = rec.table.selection;
                rec.table.records.splice(idx, 1);
                rec.table.selection = 0;
            }
        });
    },

    /**
     * Validates record
     *
     * @param {object} rec record
     * @param {boolean} revalidate
     */
    validate: function(rec, revalidate) {
        rec.invalid = false;
        rec.dirty = rec.delete ? true : false;

        var items = rec.table.form.items || [];

        $.each(items, function(i, item) {
            var status = entry.validate_item(rec, item, revalidate);

            if (status.dirty || status.invalid) {
                item.dirty = status.dirty;
                item.invalid = status.invalid;
            }

        });
    },

    /**
     * Validate field or heading in form
     *
     * @param {object} rec record
     * @param {object|string} item what we shall validate
     * @param {boolean} revalidate
     */
    validate_item: function(rec, item, revalidate) {
        item.dirty = false;
        item.invalid = false;
        var parts;
        var type = typeof item === 'object'        ? 'heading'
                 : item.indexOf('relations.') > -1 ? 'relation'
                 : item.indexOf('actions.') > -1   ? 'action'
                 : 'field';

        switch (type) {
            case 'heading':
                // For headings we validate each subitem
                Object.keys(item.items).map(function(label, idx) {
                    var subitem = item.items[label];

                    var status = entry.validate_item(rec, subitem);

                    if (status.dirty) item.dirty = true;
                    if (status.invalid) item.invalid = true;

                });

                return {dirty: item.dirty, invalid: item.invalid};
            case 'relation':
                // If relations isn't loaded yet
                if (!rec.relations) return {dirty: false, invalid: false}

                parts = item.split('.');
                var rel_key = parts[1];
                var rel = rec.relations[rel_key];
                if (!rel) return {dirty: false, invalid: false}
                $.each(rel.records, function(i, rec) {
                    if (!rec.loaded) return;
                    entry.validate(rec);
                    if (rec.invalid) {
                        rel.invalid = true;
                    }
                    if (rec.dirty) {
                        rel.dirty = true;
                    }
                });
                if (rel.invalid) rec.invalid = false;
                if (rel.dirty) rec.dirty = true;

                return {dirty: rel.dirty, invalid: rel.invalid};
            case 'field':
                // If record of relation isn't loaded from server yet
                if (!rec.fields) return {dirty: false, invalid: false}

                parts = item.split('.');
                var field_name = parts.pop();

                var field = rec.fields[field_name];

                if (revalidate) {
                    control.validate(field.value, field);
                }
                var status = {
                    dirty: field.dirty,
                    invalid: field.invalid
                }

                if (field.dirty) rec.dirty = true;
                if (field.invalid) rec.invalid = true;

                return status;
            case 'action':
                return {};
        }
    },

    get_changes: function(rec, traverse) {

        traverse = traverse ? traverse : false;

        var changes = {};
        changes.prim_key = rec.primary_key;
        changes.relations = {};

        var values = {};
        $.each(rec.fields, function(name, field) {
            if (field.dirty == true) {
                values[name] = field.value;
            }
        });

        if (Object.keys(values).length) {
            changes.values = values;
        }

        changes.method = rec.delete ? 'delete' :
                         rec.new    ? 'post'   : 'put';

        if (changes.action == 'delete' || !traverse) return changes;


        $.each(rec.relations, function(alias, rel) {
            if (!rel.dirty) return;
            var changed_rel = {
                base_name: ds.base.name,
                table_name: rel.name,
                condition: rel.conditions.join(' AND '),
                records: []
            }
            $.each(rel.records, function(i, subrec) {
                if (!subrec.dirty) return;
                subrec_changes = entry.get_changes(subrec, true);
                changed_rel.records.push(subrec_changes);
            });
            changes.relations[alias] = changed_rel;
        });

        return changes;
    },

    update_field: function(value, fieldalias, rec) {

        var field = rec.fields[fieldalias];

        field.dirty = true;
        rec.dirty = true;
        ds.table.dirty = true;

        // Update value in grid cell
        if (rec.columns && field.name in rec.columns) {
            rec.columns[field.name] =
                field.coltext ? field.coltext :
                field.text    ? field.text    : value.substring(0, 256);
        }

        rec.fields[field.name].value = value;


        // For each select that depends on the changed field, we must set the
        // value to empty and load new options
        $.each(rec.table.fields, function(name, other_field) {
            if (name == field.name || !other_field.foreign_key) return;

            if (other_field.element == 'select' && other_field.foreign_key.local.length > 1) {
                // If the field is part of the dropdowns foreign keys
                if ($.inArray(field.name, other_field.foreign_key.local) != -1) {
                    if (rec.fields[name].value !== null) {
                        rec.fields[name].value = null;
                        rec.fields[name].dirty = true;
                        rec.columns[name] = null;
                    }
                    // Get new options for select
                    m.request({
                        method: 'GET',
                        url: 'select',
                        data: {
                            q: '',
                            limit: 1000,
                            schema: other_field.foreign_key.schema,
                            base: other_field.foreign_key.base,
                            table: other_field.foreign_key.table,
                            alias: other_field.name,
                            view: other_field.view,
                            column_view: other_field.column_view,
                            key: other_field.foreign_key.foreign,
                            condition: control.get_condition(rec, other_field)
                        }
                    }).then(function(data) {
                        rec.fields[name].options = data;
                    });
                }
            }
        });


        // Updates conditions for relations
        if (rec.relations) {
            $.each(rec.relations, function(i, relation) {
                $.each(relation.betingelse, function(relation_field, relation_value) {
                    if (relation_field == field.name) {
                        $.each(relation.records, function(i, post) {
                            post.fields[relation_field] = value;
                            post.dirty = true;
                            relasjon.dirty = true;
                        });
                    }
                });
            });
        }



        // if (!ds.table.invalid && config.autosave) table.save();

        // return;

        if (config.autosave == false) {
            return;
        }

        rec.invalid = false;
        rec.dirty = false;
        ds.table.dirty = false;
        $.each(rec.fields, function(name, field) {
            if (field.invalid || (field.nullable === false && field.value === null && field.extra !== 'auto_increment')) {
                rec.invalid = true;
            }
            if (field.dirty) {
                rec.dirty = true;
            }
        });

        if (rec.invalid) return;

        entry.save(rec);
    },

    draw_field_relation: function(rec, colname) {
        var field = rec.fields[colname];
        return [
            field.name in ds.table.betingelse ? null : m('tr', [
                m('td'),
                m('td.label', field.label),
                m('td', control.edit_field(rec, colname))
            ])
        ]
    },

    draw_relation_list: function(rel, record) {
        var count_columns = 0;
        var group = rel.gruppe;
        rel.base = ds.base;

        return m('tr', [
            m('td', {

            }),
            m('td', {colspan:3}, [
                m('table', {class: 'w-100 collapse'}, [
                    // draw header cells
                    m('tr', [
                        m('td'),
                        config.relation_view === 'column' ? '' : m('td', {class: 'w0 gray'}),
                        Object.keys(rel.grid.columns).map(function(label, idx) {
                            var field_name = rel.grid.columns[label];

                            var field = rel.fields[field_name];

                            // If this is for instance an action
                            if (field === undefined) {
                                return m('td', '');
                            }

                            if (!(field.defines_relation)) {
                                count_columns++;
                            }
                            var label = label && !$.isArray(rel.grid.columns) ? label
                                : field.label_column ? field.label_column
                                : field.label;
                            return field.defines_relation
                                ? ''
                                : m('td', {
                                    style: 'text-align: left',
                                    class: 'gray f6 pa1 pb0'
                                }, label);
                        }),
                        m('td'),
                        config.relation_view !== 'column' ? '' : m('td'),
                    ]),
                    // draw records
                    rel.records.map(function(rec, rowidx) {
                        rec.base_name = rel.base.name;
                        rec.table_name = rel.name;
                        return [
                            m('tr', {
                                class: config.relation_view === 'column' && _isEqual(rec, record.active_relation) ? 'bg-blue white' : '',
                                onclick: function() {
                                    if (record.readonly) return;
                                    record.active_relation = rec;
                                    entry.toggle_record(rec, rel);
                                }
                            }, [
                                m('td', {
                                    align: 'center',
                                    class: 'bg-white'
                                }, [
                                    m('i', {
                                        class: [
                                            rec.delete ? 'fa fa-trash light-gray mr1' :
                                            rec.invalid ? 'fa fa-warning red mr1' :
                                            rec.dirty ? 'fa fa-pencil light-gray mr1' : '',
                                        ].join(' ')
                                    })
                                ]),
                                config.relation_view === 'column' || record.readonly ? '' : m('td.fa', {
                                    class: [
                                        rec.open ? 'fa-angle-down' : 'fa-angle-right',
                                        rec.invalid ? 'invalid' : rec.dirty ? 'dirty' : '',
                                    ].join(' ')
                                }),
                                Object.keys(rel.grid.columns).map(function(label, colidx) {
                                    var field_name = rel.grid.columns[label];

                                    // Check if this is an action
                                    if (field_name.indexOf('actions.') > -1) {
                                        var parts = field_name.split('.');
                                        var action_name = parts[1];
                                        var action = rel.actions[action_name];
                                        action.alias = action_name;

                                        return control.draw_action_button(rec, action);
                                    }

                                    var field = rel.fields[field_name];

                                    return field.defines_relation
                                        ? ''
                                        : control.draw_cell(rel, rowidx, field_name, {compressed: true});
                                }),
                                m('td', {class: 'bb b--light-gray'}, [
                                    !rec.open || record.readonly ? '' : m('i', {
                                        class: [
                                            rel.permission.delete && config.edit_mode ? 'fa fa-trash-o light-blue pl1' : '',
                                            config.relation_view === 'column' ? 'hover-white' : 'hover-blue',
                                        ].join(' '),
                                        style: 'cursor: pointer',
                                        onclick: entry.delete.bind(this, rec),
                                        title: 'Slett'
                                    })
                                ]),
                                config.relation_view !== 'column' || record.readonly ? '' : m('td', {class: 'bb b--light-gray'}, [
                                    m('i', {
                                        class: 'fa fa-angle-right'
                                    })
                                ]),
                            ]),
                            !rec.open || config.relation_view === 'column' ? null : m('tr', [
                                m('td'),
                                m('td'),
                                m('td', {
                                    colspan: count_columns+1,
                                    class: 'bl b--moon-gray'
                                }, [
                                    m(entry, {record: rec})
                                ])
                            ])
                        ];
                    }),
                    record.readonly || !config.edit_mode ? '' : m('tr', [
                        m('td'),
                        config.relation_view !== 'expansion' ? '' : m('td'),
                        m('td', [
                            !rel.permission.add ? '' : m('a', {
                                onclick: function(e) {
                                    e.stopPropagation();
                                    var rec = entry.create(rel, true);
                                    if (!rec) return;

                                    // Tilordner verdier til felter som definerer relasjon:
                                    $.each(rel.conditions, function(i, condition) {
                                        var filter = filterpanel.parse_query(condition)[0];
                                        var parts = filter.field.split('.');
                                        var fieldname = parts.pop();
                                        rec.fields[fieldname].value = filter.value;
                                        rec.fields[fieldname].dirty = true;
                                    });

                                    rel.modus = 'edit';
                                    record.active_relation = rec;
                                }
                            }, m('i', {class: 'fa fa-plus light-blue hover-blue pointer ml1'}))
                        ])
                    ]),
                ])
            ])
        ])
    },

    draw_inline_fields: function(rec, fieldset) {
        if (!fieldset.inline) {
            return;
        }

        return m('td.nowrap', [
            Object.keys(fieldset.items).map(function(label, idx) {
                var fieldname = fieldset.items[label];
                var type = fieldname.indexOf('actions.') > -1 ? 'action' : 'field';

                switch (type) {
                    case 'field':
                        var field = rec.fields[fieldname];
                        var separator;
                        if (idx > 0 && field.value) {
                            separator = field.separator ? field.separator : ', ';
                        } else {
                            separator = null;
                        }

                        // determine if field should be displayd or edited
                        var display = rec.table.permission.edit == 0 || rec.readonly || !config.edit_mode;

                        return m('span', {class: display ? '' : 'mr2'}, display ? [separator, control.display_value(field)].join('') : control.edit_field(rec, fieldname, field.label));
                    case 'action':
                        var action = _get(ds.table, fieldname);
                        return m('span', {class: 'mr2'}, [
                            m('input', {
                                type: 'button',
                                value: action.label,
                                onclick: function() {
                                    toolbar.run_action(action, rec)
                                }
                            })
                        ]);
                }
            })
        ])
    },

    view: function(vnode) {
        var rec = vnode.attrs.record;

        if (!rec || !rec.table) {
            return m('form[name="record"]', {
                class: 'flex flex-column',
                style: 'flex: 0 0 550px;'
            });
        }

        entry.validate(rec);

        rec.dirty = rec.dirty == undefined ? false : rec.dirty;
        return [m('form[name="record"]', {
            class: 'flex flex-column',
            style: 'flex: 0 0 550px;'
        }, [
            m('table[name=view]', {
                class: [
                    'pt1 pl1 pr2 flex flex-column',
                    config.theme === 'material' ? 'md' : '',
                    'overflow-auto',
                ].join(' '),
                style: '-ms-overflow-style:-ms-autohiding-scrollbar'
            }, [
                m('tbody', [
                    Object.keys(rec.table.form.items).map(function(label, idx) {
                        var item = rec.table.form.items[label];

                        if (typeof item !== 'object' && item.indexOf('.') === -1 && rec.table.fields[item].defines_relation) {
                            return;
                        }

                        return control.draw_field(rec, item, label);
                    })
                ])
            ])
        ]),
            config.relation_view === 'expansion' || (rec.active_relation && rec.active_relation.table && !rec.active_relation.table.expanded) ? '' : m(entry, {
                record: rec.active_relation
            })
        ]
    }
};

module.exports = entry;

var m = require('mithril');
var $ = require('jquery');
var moment = require('moment');
var config = require('./config.js');
var ds = require('./datastore.js');
var _merge = require('lodash/merge');
var _isEqual = require('lodash/isEqual');
var _get = require('lodash/get');

var control = require('./control.js');
var filterpanel = require('./filterpanel.js');
var toolbar = require('./toolbar.js');
