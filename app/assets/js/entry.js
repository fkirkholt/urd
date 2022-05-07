
var entry = {

    select: function(table, idx, root) {
        table.selection = idx;

        if (table.records.length == 0) return;

        // Don't load if already loaded
        if (table.records[idx].fields) {
            m.redraw();
            return;
        }

        pk = ('primary_key' in table.records[idx])
            ? JSON.stringify(table.records[idx].primary_key)
            : JSON.stringify(table.records[idx].columns);

        m.request({
            method: "GET",
            url: 'record',
            params: {
                base: m.route.param('base'),
                table: table.name,
                primary_key: pk
            }
        }).then(function(result) {
            rec = $.extend(table.records[idx], result.data);
            rec.table = table;
            rec.root = root;
            rec.fields = $.extend({}, table.fields, rec.fields)

            rec.columns = table.records[idx].columns;
            entry.get_relations_count(rec);
        }).catch(function(e) {
            if (e.code === 401) {
                $('div.curtain').show();
                $('#login').show();
                $('#brukernavn').focus();
            }
        });
    },

    get_relations_count: function(rec) {
        // If there is a select named "type_" get it's values
        // Used for showing relations based on the type_ value
        types = []
        if (rec.fields.type_) {
            $.each(rec.fields.type_.options, function(idx, option) {
                if (typeof option.value === "string") {
                    types.push(option.value)
                }
            })
        }
        m.request({
            method: "get",
            url: "relations",
            params: {
                base: rec.base_name,
                table: rec.table_name || rec.table.name,
                primary_key: JSON.stringify(rec.primary_key),
                types: JSON.stringify(types),
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
            params: {
                base: rec.base_name,
                table: rec.table.name,
                primary_key: JSON.stringify(rec.primary_key),
                count: false,
                alias: alias
            }
        }).then(function(result) {
            if (result.data[alias].relationship == '1:1') {
                record = result.data[alias].records[0]
                record.table = result.data[alias]
                entry.get_relations_count(record)
            }
            $('.icon-crosshairs').removeClass('fast-spin');
            Object.assign(rec.relations[alias], result.data[alias])
        }) ;
    },

    create: function(list, relation) {

        if (list.actions.new) {
            toolbar.run_action(list.actions.new);
            return;
        }

        relation = relation ? relation : null;

        // all columns and values defaults to null
        var columns = {}
        var values = {}
        $.each(list.grid.columns, function(i, col) {
            columns[col] = null
            values[col] = null
        });

        // Adds record to end of table
        var idx = list.records.length;

        // Create new record with column specifications
        // after selected record
        list.records.splice(idx, 0, {
            primary_key: {},
            columns: columns,
            values: values,
            new: true
        })

        list.selection = idx;
        list.dirty = true;

        // create new record
        var rec = {
            base_name: ds.base.name,
            table_name: list.name,
            table: list,
            columns: list.records[idx].columns,
            values: list.records[idx].values,
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
                        var table_name
                        var field_name
                        if (parts.length == 2) {
                            table_name = parts[0];
                            field_name = parts[1];
                        } else {
                            table_name = rec.table_name
                            field_name = parts[0]
                        }

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

            field.editable = field.editable === false ? field.editable : list.privilege.update;
            rec.fields[name] = field;

            if (field.value) {
                entry.update_field(field.value, field.name, rec);
            }
        });

        rec.new = true;
        rec.dirty = true;
        rec.loaded = true;

        rec = $.extend(list.records[idx], rec);

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
        if (rec.deletable === false) return
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

        $.each(field.foreign_key.primary, function(i, ref_field) {
            var fk_field = field.foreign_key.foreign[i];
            var value = rec.fields[fk_field].value;
            filters.push(field.foreign_key.table + '.' + ref_field + " = " + value);
        });

        m.request({
            method: "GET",
            url: "table",
            params: {
                base: field.foreign_key.base || ds.base.name,
                schema: field.foreign_key.schema,
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
                params: {
                    base: field.foreign_key.base || ds.base.name,
                    schema: field.foreign_key.schema,
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
            params: {
                base: rec.base_name ? rec.base_name : ds.base.name,
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
            params: data,
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
                fkey: rec.table.relations[alias].foreign_key,
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
                field.text    ? field.text    :
                typeof value == "string" ? value.substring(0, 256) : value;
            rec.values[field.name] = value
        }

        rec.fields[field.name].value = value;

        // For each select that depends on the changed field, we must set the
        // value to empty and load new options
        $.each(rec.table.fields, function(name, other_field) {
            if (name == field.name || !other_field.foreign_key) return;
            if (other_field.defines_relation) return;

            if (other_field.element == 'select' && other_field.foreign_key.foreign.length > 1) {
                // If the field is part of the dropdowns foreign keys
                if ($.inArray(field.name, other_field.foreign_key.foreign) != -1) {
                    if (rec.fields[name].value !== null) {
                        rec.fields[name].value = null;
                        rec.fields[name].dirty = true;
                        rec.columns[name] = null;
                    }
                    // Get new options for select
                    m.request({
                        method: 'GET',
                        url: 'select',
                        params: {
                            q: '',
                            limit: 1000,
                            schema: other_field.foreign_key.schema,
                            base: other_field.foreign_key.base,
                            table: other_field.foreign_key.table,
                            alias: other_field.name,
                            view: other_field.view,
                            column_view: other_field.column_view,
                            key: JSON.stringify(other_field.foreign_key.primary),
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
                m('td', control.input(rec, colname))
            ])
        ]
    },

    draw_relation_list: function(rel, record) {
        return m('tr', [
            m('td', {}),
            m('td', {colspan:3}, [
                m('table', {class: 'w-100 collapse'}, [
                    rel.records.map(function(rec, rowidx) {
                        // Make editable only relations attached directly to record
                        // and not to parent records
                        rec.readonly = !rec.new && !_isMatch(rec.values, rel.conds)
                        if (rec.readonly) rec.inherited = true

                        if (rec.delete) return;
                        if (rec.fields === undefined) {
                            rec.fields = JSON.parse(JSON.stringify(rel.fields));
                        }
                        rec.table = rel;
                        rec.loaded = true;

                        Object.keys(rel.fields).map(function (key) {
                            var field = rec.fields[key];
                            if (field.value === undefined) {
                                field.value = rec.values
                                    ? rec.values[key]
                                    : null;
                                field.text = rec.columns[key];
                                field.editable = rel.privilege.update;
                            }
                        });

                        return [    
                            Object.keys(rel.form.items).map(function (label, idx) {
                                var item = rel.form.items[label];

                                if (typeof item !== 'object' && item.indexOf('.') === -1 && rel.fields[item].defines_relation) {
                                    return;
                                }
                                return control.draw(rec, item, label);
                            })
                        ];
                    }),
                    record.readonly || !config.edit_mode || rel.relationship == "1:1" ? '' : m('tr', [
                        m('td'),
                        config.relation_view !== 'expansion' ? '' : m('td'),
                        m('td', [
                            !rel.privilege.insert ? '' : m('a', {
                                onclick: function(e) {
                                    e.stopPropagation();
                                    var rec = entry.create(rel, true);
                                    if (!rec) return;

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

    draw_relation_table: function(rel, record) {
        var count_columns = 0;
        var group = rel.gruppe;

        // count columns that should be shown
        $.each(rel.grid.columns, function(idx, field_name) {
            var field = rel.fields[field_name];
            if (!(field.defines_relation)) {
                count_columns++;
            }
        });

        // Make list instead of table of relations if only one column shown
        if (count_columns == 1 && Object.keys(rel.relations).length == 0) {
            rel.relationship = 'M:M'
            return entry.draw_relation_list(rel, record);
        }

        return m('tr', [
            m('td', {}),
            m('td', {colspan:3}, [
                m('table', {class: 'w-100 collapse'}, [
                    // draw header cells
                    m('tr', [
                        m('td'),
                        config.relation_view === 'column' || rel.primary_key.length == 0 ? '' : m('td', {class: 'w0 gray'}),
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
                        rec.table_name = rel.name;

                        // Make editable only relations attached directly to record
                        // and not to parent records
                        rec.readonly = !rec.new && !_isMatch(rec.values, rel.conds) &&
                            // all keys of rel.conds should be in rec.values
                            Object.keys(rel.conds).every(function(val) {
                                return Object.keys(rec.values).indexOf(val) >= 0;
                            })

                        rec.deletable = rec.relations ? true : false

                        $.each(rec.relations, function(idx, rel) {
                            var count_local = rel.count_records - rel.count_inherited
                            if (count_local && rel.delete_rule != "cascade") {
                                rec.deletable = false
                            }
                        })

                        return [
                            m('tr', {
                                class: [
                                    config.relation_view === 'column' && _isEqual(rec, record.active_relation) ? 'bg-blue white' : '',
                                    rec.readonly ? 'gray' : 'black'
                                ].join(' '),
                                onclick: function() {
                                    if (rec.primary_key == null) return;
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
                                config.relation_view === 'column' || rec.primary_key == null ? '' : m('td.fa', {
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

                                        return grid.cell.button(rec, action);
                                    }

                                    var field = rel.fields[field_name];

                                    return field.defines_relation
                                        ? ''
                                        : grid.cell.draw(rel, rowidx, field_name, {compressed: true});
                                }),
                                m('td', {class: 'bb b--light-gray'}, [
                                    !rec.open || record.readonly ? '' : m('i', {
                                        class: [
                                            rel.privilege.delete && config.edit_mode ? 'fa fa-trash-o pl1' : '',
                                            rec.deletable ? 'light-blue' : 'moon-gray',
                                            rec.deletable ? (config.relation_view === 'column' ? 'hover-white' : 'hover-blue') : '',
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
                            !rel.privilege.insert ? '' : m('a', {
                                onclick: function(e) {
                                    e.stopPropagation();
                                    var rec = entry.create(rel, true);
                                    if (!rec) return;

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
                        var display = rec.table.privilege.update == 0 || rec.readonly || !config.edit_mode;

                        return m('span', {class: display ? '' : 'mr2'}, display ? [separator, control.display_value(field)].join('') : control.input(rec, fieldname, field.label));
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

        // Clone record so the registration can be cancelled easily
        if (ds.table.edit && !ds.rec) {
            rec = _cloneDeep(rec)
            ds.rec = rec
        } else if (ds.table.edit) {
            rec = ds.rec
        }

        if (!rec || !rec.table) {
            return m('form[name="record"]', {
                class: 'flex flex-column',
            });
        }

        entry.validate(rec);

        rec.dirty = rec.dirty == undefined ? false : rec.dirty;
        return [m('form[name="record"]', {
            class: 'flex flex-column',
        }, [
            !ds.table.edit && !ds.table.hide
                ? '' 
                : m('div', [
                    m('input[type=button]', {
                        value: 'Lagre og lukk',
                        onclick: function() {
                            var saved = true;
                            if (ds.table.dirty) {
                                vnode.attrs.record = _merge(vnode.attrs.record, rec)
                                delete ds.rec
                                saved = grid.save();
                            }
                            if (saved) {
                                ds.table.edit = false
                                config.edit_mode = false;
                            }
                        }
                    }),
                    m('input[type=button]', {
                        value: 'Avbryt',
                        onclick: function() {
                            ds.table.edit = false;
                            config.edit_mode = false;
                            delete ds.rec
                            if (rec.new) {
                                var idx = ds.table.selection
                                ds.table.records.splice(idx, 1);
                                entry.select(ds.table, 0, true);
                            }
                        }
                    })
                ]),
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
                        return control.draw(rec, item, label);
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
var _isMatch = require('lodash/isMatch');
var _cloneDeep = require('lodash/cloneDeep')
var grid = require('./grid.js');
var control = require('./control.js');
var filterpanel = require('./filterpanel.js');
var toolbar = require('./toolbar.js');
