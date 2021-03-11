
var control = {

    align: function(list, colname) {
        var col = list.fields[colname];
        if (((col.datatype == 'integer' || col.datatype == 'float') && !col.foreign_key) ||
            (col.element == 'input[type=date]' && col.datatype == 'string') && !col.element === 'input[type=checkbox]') {
                return 'right';
            } else {
                return 'left';
            }
    },

    validate: function(value, field) {
        field.invalid = false;
        field.errormsg = '';
        if (field.editable == false) return;
        if ((field.nullable || field.extra) && (value == '' || value == null)) {
            field.invalid = false;
        } else if (!field.nullable && (value === '' || value === null) && !field.source) {
            field.errormsg = 'Feltet kan ikke stå tomt';
            field.invalid = true;
        } else if (field.datatype == 'integer' && field.format === 'byte') {
            field.errormsg = 'Må ha enhet (B, kB, MB, GB) til slutt';
            field.invalid = false;
        } else if (field.datatype == 'integer') {
            var pattern = new RegExp("^-?[0-9]*$");
            if (!pattern.test(value)) {
                field.errormsg = 'Feltet skal ha heltall som verdi';
                field.invalid = true;
            }
        } else if (field.datatype == 'date') {
            if (moment(value, 'YYYY-MM-DD').isValid() == false) {
                field.errormsg = 'Feil datoformat';
                field.invalid = true;
            }
        } else if (field.datatype == 'string' && field.element == 'input[type=date]') {
            if (
                moment(value, 'YYYY-MM-DD', true).isValid() == false &&
                moment(value, 'YYYY-MM', true).isValid() == false &&
                moment(value, 'YYYY', true).isValid() == false
            ) {
                field.errormsg = 'Feil datoformat';
                field.invalid = true;
            }
        }
        if (field.invalid) {
            field.errormsg += '. Verdien er nå ' + value;
        }
    },

    /**
     * Get conditions to collect data from a foreign key field
     *
     * param {Object} rec - record
     * param {Object} field - foreign key field
     */
    get_condition: function(rec, field) {

        var kandidatbetingelser = [];
        var filter;

        if (field.foreign_key) {

            if (field.foreign_key.filter) {
                filter = field.foreign_key.filter;
                $.each(rec.fields, function(name, field2) {
                    var re = new RegExp('\\b'+field2.table+'\\.'+field2.name+'\\b', 'g');
                    filter = filter.replace(re, "'"+field2.value+"'");
                    re = new RegExp('\!= null\\b', 'g');
                    filter = filter.replace(re, 'is not null');
                    re = new RegExp('\= null\\b', 'g');
                    filter = filter.replace(re, 'is null');
                });
                kandidatbetingelser.push(filter);
            }

            if (field.foreign_key.local && field.foreign_key.local.length > 1) {
                $.each(field.foreign_key.local, function(i, column) {
                    if (column === field.name) return;
                    // Just fields that is editable on their own participates in the condition
                    if (rec.fields[column].value != null && column in rec.fields) {
                        var condition = field.name + '.' + field.foreign_key.foreign[i] + ' = ' + "'" + rec.fields[column].value + "'";
                        kandidatbetingelser.push(condition);
                    }
                });
            }
        }

        return kandidatbetingelser.join(' AND ');
    },

    edit_field: function(rec, fieldname, placeholder) {
        var field = rec.fields[fieldname];
        var value;
        var readOnly = !field.editable;

        placeholder = placeholder || field.placeholder;

        if (field.element == 'select' && (field.options || field.optgroups)) {
            var filtered_optgroups;

            if (field.optgroups && field.optgroup_field && rec.fields[field.optgroup_field].value) {
                filtered_optgroups = field.optgroups.filter(function(optgroup) {
                    return optgroup.label == rec.fields[field.optgroup_field].value;
                })
            } else {
                filtered_optgroups = field.optgroups;
            }

            var option = _find(field.options, {value: field.value});

            maxlength = field.options && field.options.length ? field.options.map(function(el) {
                return el.label ? el.label.length : 0;
            }).reduce(function(max, cur) {
                return Math.max(max, cur);
            }) : 0;


            return readOnly ? m('input', {disabled: true, value: option ? option.label : field.value}) : m(select, {
                name: field.name,
                style: field.expandable ? 'width: calc(100% - 30px)' : '',
                class: [
                    'max-w7',
                    maxlength >= 30 ? 'w-100' : '',
                ].join(' '),
                options: field.options,
                optgroups: filtered_optgroups,
                required: !field.nullable,
                value: field.value,
                text: field.text,
                label: field.label,
                clear: true,
                placeholder: placeholder,
                disabled: readOnly,
                onchange: function(event) {
                    if (field.optgroup_field) {
                        var optgroup = $(':selected', event.target).closest('optgroup').data('value');
                        rec.fields[field.optgroup_field].value = optgroup;
                    }
                    if (event.target.value == field.value) {
                        return;
                    }
                    control.validate(event.target.value, field);
                    var text = event.target.options[event.target.selectedIndex].text;
                    var coltext = event.target.options[event.target.selectedIndex].dataset.coltext;
                    field.text = text
                    field.coltext = coltext;
                    entry.update_field(event.target.value, field.name, rec);
                }
            });
        } else if (field.element === 'select') {

            // Handles self referencing fields
            // TODO: Do we need both checks?
            if (!field.foreign_key) field.text = field.value;
            if (field.foreign_key && field.foreign_key.table == field.table && field.foreign_key.foreign[0] == field.name) field.text = field.value;

            return m(autocomplete, {
                name: field.name,
                style: field.expandable ? 'width: calc(100% - 30px)' : 'width: 100%',
                required: !field.nullable,
                class: 'max-w7 border-box',
                item: field,
                value: field.text,
                placeholder: 'Velg',
                disabled: readOnly,
                ajax: {
                    url: "select",
                    data: {
                        limit: 1000,
                        schema: field.foreign_key ? field.foreign_key.schema : '',
                        base: field.foreign_key ? field.foreign_key.base : rec.base_name,
                        table: field.foreign_key ? field.foreign_key.table : rec.table_name,
                        alias: field.name,
                        view: field.view,
                        column_view: field.column_view,
                        key: field.foreign_key ? field.foreign_key.foreign : [field.name],
                        condition: control.get_condition(rec, field)
                    }
                },
                onchange: function(event) {
                    var value = $(event.target).data('value');

                    // handle self referencing fields
                    if (value === undefined) value = event.target.value;

                    field.text = event.target.value;
                    field.coltext = $(event.target).data('coltext');

                    if (field.value === value) {
                        // return;
                    }

                    control.validate(value, field);
                    entry.update_field(value, field.name, rec);
                },
                onclick: function(event) {
                    if (event.target.value === '') {
                        $(event.target).autocomplete('search', '');
                    }
                }

            });
        } else if (field.element == 'textarea' && field.expanded === true) {
            var converter = new showdown.Converter();
            var text = field.format == 'markdown'
                ? converter.makeHtml(field.value)
                : field.value;

            return readOnly ? m.trust(text) : m('textarea', {
                name: field.name,
                class: [
                    'ba b--light-grey w-100 max-w7',
                    field.format == 'markdown' ? 'code' : ''
                ].join(' '),
                required: !field.nullable,
                value: field.value,
                disabled: readOnly,
                oncreate: function(vnode) {
                    $(vnode.dom).outerHeight(38).outerHeight($(vnode.dom).scrollHeight);
                    $(vnode.dom).on('input', function() {
                        $(this).outerHeight(38).outerHeight(this.scrollHeight);
                    });
                },
                onchange: function(event) {
                    control.validate(event.target.value, field);
                    entry.update_field(event.target.value, field.name, rec);
                }
            });

        } else if (
            (field.element == 'input' && field.attr.type == 'checkbox') ||
            field.element == 'input[type=checkbox]'
        ) {
            return m('input[type=checkbox][name=' + field.name +']', {
                disabled: readOnly,
                onchange: function(event) {
                    var value = event.target.checked ? 1 : 0;
                    control.validate(value, field);
                    entry.update_field(value, field.name, rec);
                },
                checked: +field.value
            });
        } else if (field.element == 'input[type=date]' && field.datatype == 'date') {
            var value = typeof field.value === 'object' && field.value !== null
                ? field.value.date
                : field.value;
            return m(datepicker, {
                name: field.name,
                // required: !field.nullable,
                disabled: readOnly,
                dateFormat: 'yy-mm-dd',
                value: typeof field.value === 'object' && field.value !== null
                    ? field.value.date
                    : field.value,
                onchange: function(event) {
                    var value = event.target.value;
                    if (field.value && value === field.value.substr(0, 10)) {
                        event.redraw = false;
                        return;
                    }
                    control.validate(value, field);
                    entry.update_field(value, field.name, rec);
                }
            });
        } else if (
            (
                (field.element == 'input' && field.attr.type == 'date') ||
                field.element == 'input[type=date]'
            ) && field.datatype == 'string'
        ) {
            var separator = _get(rec.table, 'date_as_string.separator', '-');
            if (field.value !== null) {
                var date_items = separator == ''
                    ? [field.value.substr(6,2), field.value.substr(4,2), field.value.substr(0,4)]
                    : field.value.split(separator);
                value = $.grep(date_items, Boolean).join('-');
            } else {
                value = null;
            }

            return m('input', {
                name: field.name,
                placeholder: 'yyyy(-mm(-dd))',
                maxlength: 10,
                // required: !field.nullable,
                class: [
                    !field.nullable && field.value === '' ? 'invalid' : '',
                    'max-w7',
                ].join(' '),
                disabled: readOnly,
                value: value,
                onchange: function(event) {
                    control.validate(event.target.value, field);
                    var value = event.target.value.split('.').reverse().join(separator);
                    entry.update_field(event.target.value, field.name, rec);
                }
            })
        } else {
            var width = (field.size === null || field.size > 20)
                ? '100%'
                : field.size + 'em';

            value = typeof field.value === 'string'
                ? field.value.replace(/\n/g, '\u21a9')
                : field.value;

            if (field.format === 'byte') {
                numeral.locale('no');
                value = numeral(value).format('0.00b');
            }

            return m('input', {
                name: field.name,
                maxlength: field.size,
                // required: !field.nullable && field.extra !== 'auto_increment',
                class: [
                    !field.nullable && field.value === '' ? 'invalid' : '',
                    field.size >= 30 ? 'w-100' : '',
                    'max-w7 border-box',
                ].join(' '),
                style: [
                    'width: ' + width,
                    'text-overflow: ellipsis',
                ].join(';'),
                disabled: readOnly,
                value: value,
                placeholder: placeholder,
                onchange: function(event) {
                    value = field.format === 'byte'
                        ? numeral(event.target.value).value()
                        : event.target.value.replace(/\u21a9/g, "\n");
                    control.validate(value, field);
                    entry.update_field(value, field.name, rec);
                }
            })
        }
    },

    display_value: function(field, value) {
        if (value === '') return '';
        if (typeof value === 'undefined') value = field.value;
        // Different types of fields
        var is_date_as_string = value &&
            field.element == 'input[type=date]' &&
            field.datatype == 'string';
        var is_timestamp = field.element == 'input' &&
            field.attr.class == 'timestamp';
        var is_date = field.element == 'input[type=date]';
        var is_integer = field.datatype == 'integer' && !field.options && !field.foreign_key;
        var is_checkbox = field.element == 'input[type=checkbox]';
        var date_items;

        if (field.text) {
            value = field.text;
        } else if (field.element == 'select' && field.options && field.value) {
            var option = _find(field.options, value);
            value = option ? option.label : value;
        } else if (is_date_as_string) {
            if (field.size === 8) {
                date_items = [
                    value.substr(0,4),
                    value.substr(4,2),
                    value.substr(6,2)
                ];
                value = $.grep(date_items, Boolean).join('-');
            }
        } else if (is_timestamp) {
            // var parts = value.split(' ');
            // value = moment(value, 'YYYY-MM-DD').format('DD.MM.YYYY') + ' ' + parts[1];
        } else if (is_date) {
            // value = value ? moment(value, 'YYYY-MM-DD').format('DD.MM.YYYY') : '';
        } else if (is_checkbox) {
            var icon = value == 0 ? 'fa-square-o' : 'fa-check-square-o';
            value = m('i', {class: 'fa ' + icon});
        } else if (is_integer && field.size > 5) {
            numeral.locale('no');
            value = value === null ? null : numeral(value).format();
        }

        return value;
    },

    draw_cell: function(list, rowidx, col, options) {
        var rec = list.records[rowidx];
        var field = list.fields[col];
        if (field.hidden) return;
        var value = rec.columns[col] != null ? rec.columns[col] : '';
        value = control.display_value(field, value);
        var expansion = col === list.expansion_column && options.grid;
        var is_checkbox = field.element == 'input[type=checkbox]';

        var icon = m('i', {
                class: [
                    expansion ? 'fa fa-fw' : '',
                    expansion && rec.expanded ? 'fa-angle-down' : expansion ? 'fa-angle-right' : '',
                    expansion && rec.count_children ? 'black' : 'moon-gray',
                ].join(' '),
                style: col === list.expansion_column ? 'margin-left: ' + (rec.indent * 15) + 'px;' : '',
                onclick: function(e) {
                    if (!rec.count_children) return;
                    if (rec.expanded === undefined) {
                        m.request({
                            method: 'get',
                            url: 'children',
                            data: {
                                base: ds.base.name,
                                table: ds.table.name,
                                primary_key: JSON.stringify(rec.primary_key)
                            }
                        }).then(function(result) {
                            var indent = rec.indent ? rec.indent + 1 : 1;
                            records = result.data.map(function(record, idx) {
                                record.indent = indent;
                                record.path = rec.path ? rec.path + '.' + idx : rowidx + '.' + idx;
                                record.parent = rec.primary_key;
                                return record;
                            })
                            list.records.splice.apply(list.records, [rowidx+1, 0].concat(records));
                        });
                    } else if (rec.expanded === false) {
                        list.records = list.records.map(function(record) {
                            if (_isEqual(record.parent, rec.primary_key)) record.hidden = false;

                            return record;
                        });
                    } else {
                        var path = rec.path ? rec.path : rowidx;

                        list.records = list.records.map(function(record) {

                            // Check if record.path starts with path
                            if(record.path && record.path.lastIndexOf(path, 0) === 0 && record.path !== path) {
                                record.hidden = true;
                                if (record.expanded) record.expanded = false;
                            }

                            return record;
                        });
                    }

                    rec.expanded = !rec.expanded;
                }
            });

        return m('td', {
            class: [
                control.align(list, col) === 'right' ? 'tr' : 'tl',
                options.compressed || (field.datatype !== 'string' && field.datatype !== 'binary' && field.element != 'select') || (value.length < 30) ? 'nowrap' : '',
                options.compressed && value.length > 30 ? 'pt0 pb0' : '',
                options.border ? 'bl b--light-gray' : '',
                ds.table.sort_fields[col] ? 'min-w3' : 'min-w2',
                'f6 pl1 pr1',
                rowidx < ds.table.records.length - 1 ? 'bb b--light-gray' : '',
            ].join(' '),
            title: options.compressed && value.length > 30 ? value : ''
        }, [

            !(value.length > 30 && options.compressed) ? [icon, value]
                : m('table', {
                    class: 'w-100',
                    style: 'table-layout:fixed; border-spacing:0px'
                }, [
                    m('tr', m('td.pa0', {class: options.compressed ? 'truncate': 'overflow-wrap'}, [icon, value]))
                ]),
        ]);
    },

    draw_action_button: function(rec, action) {

        // If disabled status for the action is based on an expression
        // then we get the status from a column with same name as alias of action
        if (action.alias && rec.columns[action.alias] !== undefined) {
            action.disabled = rec.columns[action.alias];
        }

        return action.disabled ? '' : m('td', [
            m('i', {
                class: 'fa fa-' + action.icon,
                onclick: function(e) {
                    var data = {};
                    if (action.communication === 'download') {
                        // Don't break schema 'budsjett'
                        if (ds.base.schema === 'budsjett') {
                            data = rec.primary_key;
                        } else {
                            data.base = rec.base_name;
                            data.table = rec.table_name;
                            data.primary_key = JSON.stringify(rec.primary_key);
                        }
                        var address = (action.url[0] === '/') ? action.url.substr(1) : ds.base.schema + '/' + action.url;
                        $.download(address, data, '_blank');
                    }
                    e.stopPropagation();
                }
            })
        ]);
    },

    draw_field: function(rec, colname, label) {

        if (typeof colname === 'object') {
            label = colname.label ? colname.label : label;
            if (!colname.inline && colname.expanded === undefined && config.expand_headings) colname.expanded = true;

            // Finds number of registered fields under the heading
            var count_fields = 0;
            var count_field_values = 0;
            var count_empty_relations = 0;
            Object.keys(colname.items).map(function(label, idx) {
                count_fields++;
                var col = colname.items[label];
                if (rec.fields[col] && rec.fields[col].value !== null) {
                    count_field_values++;
                } else if (typeof col === 'string' && col.indexOf('relations') > -1) {
                    var key = col.replace('relations.', '');
                    var rel = rec.relations && rec.relations[key] ? rec.relations[key] : {};
                    if ($.isEmptyObject(rel)) count_empty_relations++;
                    if (rel.count_records) count_field_values++;
                }
            });

            // if (count_empty_relations === count_fields) return;

            return [
                m('tr', [
                    m('td', {class: 'tc'}, [
                        colname.inline && colname.expandable === false ? '' : m('i.fa', {
                            class: [
                                colname.expanded ? 'fa-angle-down' : 'fa-angle-right',
                                colname.invalid ? 'invalid' : colname.dirty ? 'dirty' : ''
                            ].join(' '),
                            onclick: function() {
                                if (colname.expandable === false) return;

                                colname.expanded = !colname.expanded;
                            }
                        })
                    ]),
                    m('td.label', {
                        class: [
                            'f6 nowrap pr2',
                            !colname.inline || colname.expandable ? 'b' : ''
                        ].join(' '),
                        colspan: colname.inline ? 1 : 3,
                        onclick: function() {
                            colname.expanded = !colname.expanded;
                        }
                    }, [
                        label,
                        colname.inline ? '' : m('span', {class: 'normal ml1 moon-gray f7'}, count_field_values + '/' + count_fields),
                    ]),
                    m('td', [
                        colname.expanded ? '' :
                        colname.invalid ? m('i', {class: 'fa fa-warning ml1 red'})     :
                        colname.dirty ? m('i', {class: 'fa fa-pencil ml1 light-gray'}) : '',
                    ]),
                    !colname.expanded ? entry.draw_inline_fields(rec, colname) : null
                ]),
                !colname.expanded ? null : m('tr', [
                    m('td'),
                    m('td', {colspan: 3}, [
                        m('table', [
                            Object.keys(colname.items).map(function(label, idx) {
                                var col = colname.items[label];
                                return control.draw_field(rec, col, label);
                            })
                        ])
                    ])
                ])
            ];
        } else {
            var item = _get(rec, colname, rec.fields[colname]);
        }

        if (typeof colname === "string" && colname.indexOf('relations') > -1) {
            var key = colname.replace('relations.', '');
            var relation = rec.table.relations[key];
            var rel = rec.relations && rec.relations[key] ? rec.relations[key] : {};

            if (relation === undefined) return '';

            label = relation.label ? relation.label : label;

            return [
                m('tr.heading', {
                    onclick: function() {
                        entry.toggle_heading(rel)
                        if (!rel.records) {
                            entry.get_relations(rec, key);
                        }
                    }
                }, [
                    m('td.fa.tc', {
                        class: [
                            rel.expanded === true ? 'fa-angle-down' : 'fa-angle-right',
                            rel.invalid ? 'invalid' : rel.dirty ? 'dirty' : ''
                        ].join(' ')
                    }),
                    m('td.label', {
                        class: "f6 nowrap pr2 b",
                        colspan: 3
                    }, [
                        label,
                        rel.count_records !== undefined ? m('span', {class: 'ml1 pr1 normal moon-gray f7'}, rel.count_records) : '',
                        // show target icon for relations
                        !rel.name ? '' :
                        m('i', {
                            class: [
                                'icon-crosshairs light-blue hover-blue pointer mr1',
                            ].join(' '),
                            onclick: function(event) {
                                var url = '/' + rel.base_name + '/tables/' + rel.name;
                                url += '?query=' + rel.conditions.join(' AND ').replace(/=/g, '%3D');
                                m.route.set(url);
                                event.stopPropagation();
                            }
                        }),
                        rel.dirty ? m('i', {class: 'fa fa-pencil ml1 light-gray'}) : '',
                    ]),
                ]),
                rel.expanded && rel.records ? entry.draw_relation_table(rel, rec) : null
            ];
        } else if (typeof colname === "string" && colname.indexOf('actions.') > -1) {
            m('tr', [
                m('td'),
                m('td', [
                    m('input', {
                        type: 'button',
                        value: 'test'
                    })
                ])
            ]);
        } else {
            var field = rec.fields[colname];

            if (field.hidden) return;

            // TODO: Hva gjør jeg med rights her?
            var mandatory = !field.nullable && !field.extra && field.editable && !field.source == true;
            label = isNaN(parseInt(label)) ? label: field.label;

            return [
                // TODO: sto i utgangspunktet list.betingelse. Finn ut hva jeg skal erstatte med.
                m('tr', [
                    m('td', {class: 'tc v-top'}, [
                        !field.foreign_key || !field.expandable || rec.fields[colname].value === null ? null : m('i.fa', {
                            class: !field.expanded ? 'fa-angle-right' : field.expandable ? 'fa-angle-down' : '',
                            onclick: entry.toggle_relation.bind(this, rec, colname)
                        }),
                        field.element != 'textarea' ? null : m('i.fa', {
                            class: field.expanded ? 'fa-angle-down' : 'fa-angle-right',
                            onclick: function() {
                                field.expanded = !field.expanded;
                            }
                        })
                    ]),
                    m('td.label', {
                        class: [
                            'f6 nowrap pr1 v-top',
                            field.invalid ? 'invalid' : field.dirty ? 'dirty' : '',
                            'max-w5 truncate'
                        ].join(' '),
                        title: label,
                        onclick: function() {
                            if (field.foreign_key && field.expandable && rec.fields[colname].value) {
                                entry.toggle_relation(rec, colname);
                            } else if (field.element == 'textarea') {
                                field.expanded = !field.expanded;
                            }
                        }
                    }, [
                        field.description
                            ? m('abbr', {title: field.description}, label)
                            : label,
                        ':'
                    ]),
                    m('td', {
                        class: 'v-top'
                    }, [
                        field.invalid && field.value == null ? m('i', {class: 'fa fa-asterisk red'}) :
                        field.invalid ? m('i', {class: 'fa fa-warning ml1 red', title: field.errormsg}) :
                        field.dirty ? m('i', {class: 'fa fa-pencil ml1 light-gray'}) :
                        mandatory && config.edit_mode ? m('i', {class: 'fa fa-asterisk f7 light-gray'}) : ''
                    ]),
                    m('td', {
                        class: [
                            'max-w7 w-100',
                            field.element == 'textarea' && !field.expanded ? 'nowrap truncate' : '',
                            field.invalid ? 'invalid' : field.dirty ? 'dirty' : ''
                        ].join(' ')
                    }, [
                        rec.table.permission.edit == 0 || rec.readonly || !config.edit_mode ? control.display_value(field) : control.edit_field(rec, colname),
                        !field.expandable || field.value === null || 
                        (rec.table.type === "xref" && config.edit_mode) ? '' : m('i', {
                            class: 'icon-crosshairs light-blue hover-blue pointer',
                            onclick: function() {
                                var url = '/' + field.foreign_key.base + '/tables/' + field.foreign_key.table + '?query=';
                                $.each(field.foreign_key.foreign, function(i, colname) {
                                    var fk_field = field.foreign_key.local[i];
                                    url += colname + ' %3D ' + rec.fields[fk_field].value;
                                    if (i !== field.foreign_key.foreign.length - 1 ) url += ' AND '
                                })
                                m.route.set(url);
                            }
                        }),

                        // Show trash bin for field from cross reference table
                        rec.table.type != 'xref' || !config.edit_mode ? '' : m('i', {
                            class: 'fa fa-trash-o light-blue pl1 hover-blue pointer',
                            onclick: entry.delete.bind(this, rec)
                        }),

                        !field.attr || !field.attr.href ? '' : m('a', {
                            href: sprintf(field.attr.href, field.value)
                        }, m('i', {class: 'icon-crosshairs light-blue hover-blue pointer'})),
                        ds.base.schema != 'urd' || rec.table.name != 'database_' || field.name != 'name' ? '' : m('a', {
                            href: '#/' + (rec.columns.alias ? rec.columns.alias : rec.columns.name)
                        }, m('i', {class: 'icon-crosshairs light-blue hover-blue pointer'}))
                    ])
                ]),
                !field.foreign_key || !field.expanded ? null : m('tr', [
                    m('td'),
                    m('td', {
                        colspan: 3
                    }, [
                        m(entry, {record: field.relation})
                    ])
                ])
            ];
        }
    }
};

module.exports = control;

var m = require('mithril');
var $ = require('jquery');
var select = require('./select.js');
var datepicker = require('./datepicker.js');
var autocomplete = require('./autocomplete.js');
var ds = require('./datastore.js');
var showdown = require('showdown');
var moment = require('moment');
var numeral = require('numeral'); require('numeral/locales/no');
var _get = require('lodash/get');
var _find = require('lodash/find');
var _isEqual = require('lodash/isEqual');
var config = require('./config.js');
var sprintf = require("sprintf-js").sprintf

// TODO: Dette fører til sirkulær avhengighet, som gjør at entry blir tomt objekt.
var entry = require('./entry.js');
