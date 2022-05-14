
var control = {

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
        } else if (field.datatype == 'string' && field.placeholder == 'yyyy(-mm(-dd))') {
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

        var keys = [];
        Object.keys(rec.table.foreign_keys).map(function(label) {
            key = rec.table.foreign_keys[label];

            if (key.foreign.indexOf(field.name) > 0) {
                last_fk_col = key.foreign.slice(-1)
                if (last_fk_col != field.name && rec.fields[last_fk_col].nullable == true) {
                    return
                }
                key.foreign_idx = $.inArray(field.name, key.foreign);
                keys.push(key);
            }
        });

        $.each(keys, function(idx, key) {

            if (key.filter) {
                filter = key.filter;
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

            if (key.foreign && key.foreign.length > 1) {
                $.each(key.foreign, function(i, column) {
                    if (column === field.name) return;
                    var condition
                    if (rec.fields[column].value != null && column in rec.fields) {
                        var col = field.foreign_key.primary.slice(-1)[0];

                        if (key.table == field.foreign_key.table) {
                            condition = key.primary[i] + " = '" + rec.fields[column].value + "'"
                        } else {
                            condition = col + ' in (select ' + key.primary[key.foreign_idx];
                            condition += ' from ' + key.table + ' where ' + key.foreign[i];
                            condition += " = '" + rec.fields[column].value + "')";
                        }
                        kandidatbetingelser.push(condition);
                    }
                });
            }
        });

        return kandidatbetingelser.join(' AND ');
    },

    input: function(rec, fieldname, placeholder) {
        var field = rec.fields[fieldname];
        var value;
        var readOnly = !field.editable;

        placeholder = placeholder || field.placeholder;

        if (!placeholder && field.extra == 'auto_increment') {
            placeholder = 'autoincr.';
        }

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
                // style: field.expandable ? 'width: calc(100% - 30px)' : '',
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

            if (!field.text) field.text = field.value

            key_json = JSON.stringify(field.foreign_key ? field.foreign_key.primary: [field.name]);

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
                        key: key_json,
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
                        return;
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
        } else if (field.datatype == 'json') {
            return m(jsoned, {
                name: field.name,
                field: field,
                rec: rec,
                style: "width: 350px; height: 400px;",
                value: JSON.parse(field.value),
                onchange: function(value) {
                    entry.update_field(value, field.name, rec);
                }
            })
        } else if (field.element == 'textarea' && field.expanded === true) {
            var converter = new showdown.Converter();
            text = converter.makeHtml(field.value)

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
        } else if (field.element == 'input[type=date]') {
            var value = typeof field.value === 'object' && field.value !== null
                ? field.value.date
                : field.value;
            return m(datepicker, {
                name: field.name,
                class: 'w4',
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
        } else {
            var size = field.datatype == 'float' || field.datatype == 'decimal'
                ? field.size + 1
                : field.size
            var width = size ? Math.round(size * 0.6) + 'em' : '';

            value = typeof field.value === 'string'
                ? field.value.replace(/\n/g, '\u21a9')
                : field.value;

            if (field.format === 'byte') {
                numeral.locale('no');
                value = numeral(value).format('0.00b');
            }

            return m('input', {
                name: field.name,
                maxlength: size ? size : '',
                // required: !field.nullable && field.extra !== 'auto_increment',
                class: [
                    !field.nullable && field.value === '' ? 'invalid' : '',
                    field.size >= 30 ? 'w-100' : '',
                    'min-w3 max-w7 border-box',
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
            field.placeholder == 'yyyy(-mm(-dd))' &&
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
        } else if (field.datatype == 'json' && field.value) {
            console.log('field.value', field.value)
            value = m(jsoned, {
                name: field.name,
                mode: 'view',
                field: field,
                rec: rec,
                style: "width: 350px; height: 400px;",
                value: JSON.parse(field.value)
            })
        } else if (field.element == "textarea" && field.expanded) {
            var converter = new showdown.Converter();
            value = m.trust(converter.makeHtml(field.value))
        }

        return value;
    },

    draw: function(rec, colname, label) {

        if (typeof colname === 'object') {
            label = colname.label ? colname.label : label;
            if (!colname.inline && colname.expanded === undefined && config.expand_headings) colname.expanded = true;

            // Find number of registered fields under the heading
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
                                return control.draw(rec, col, label);
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
            var rel = rec.relations && rec.relations[key] ? rec.relations[key] : {};

            if (rel.show_if) {
                hidden = false
                Object.keys(rel.show_if).map(function(key) {
                    value = rel.show_if[key]
                    if(rec.fields[key].value != value) {
                        hidden = true
                    }
                })
                if(hidden) return ''
            }

            var base_path
            if (ds.base.system == 'postgres' &&
                rel.schema_name &&
                rel.schema_name != rel.base_name && rel.schema_name != 'public')
            {
                base_path = rel.base_name + '.' + rel.schema_name
            } else {
                base_path = rel.base_name || rel.schema_name
            }
            var url = '#/' + base_path + '/' + rel.name + '?';

            conditions = []
            $.each(rel.conds, function(col, val) {
                conditions.push(col + "=" + val)
            })

            if (conditions.length == 0) conditions = rel.conditions

            if (conditions) {
                url += conditions.join('&')
            }

            return [
                m('tr.heading', {
                    onclick: function() {
                        entry.toggle_heading(rel)
                        if (!rel.records) {
                            entry.get_relations(rec, key);
                        }
                    }
                }, [
                    m('td.fa.tc.w1', {
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
                        m('a', {
                            class: [
                                'icon-crosshairs light-blue hover-blue pointer mr1 link',
                            ].join(' '),
                            href: url
                        }),
                        rel.dirty ? m('i', {class: 'fa fa-pencil ml1 light-gray'}) : '',
                    ]),
                ]),
                rel.expanded && rel.records 
                    ? ['1:M', 'M:M'].includes(rel.relationship)
                        ? entry.draw_relation_table(rel, rec)
                        : m('tr', [
                            m('td'),
                            m('td', {colspan: 3}, [
                                m(entry, {record: rel.records[0]})
                            ])
                        ])
                    : null
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
            var field = $.extend({}, rec.fields[colname])

            if (field.virtual) {
                field.text = rec.columns[colname]
            }

            // Show hidden fields only in edit mode
            if (rec.table.fields[colname].hidden && !config.edit_mode) return

            // TODO: Hva gjør jeg med rights her?
            var mandatory = !field.nullable && !field.extra && field.editable && !field.source == true;
            label = isNaN(parseInt(label)) ? label: field.label;

            if (field.foreign_key) {
                if (
                    ds.base.system == 'postgres' &&
                    field.foreign_key.schema &&
                    field.foreign_key.schema != field.foreign_key.base &&
                    field.foreign_key.schema != 'public'
                ) {
                    base = field.foreign_key.base + '.' + field.foreign_key.schema
                } else if (ds.base.system == 'sqlite3') {
                    base = ds.base.name
                } else {
                    base = field.foreign_key.base || field.foreign_key.schema
                }
                var url = '#/' + base + '/' + field.foreign_key.table + '?'
                $.each(field.foreign_key.primary, function(i, colname) {
                    var fk_field = field.foreign_key.foreign[i];
                    url += colname + '=' + rec.fields[fk_field].value;
                    if (i !== field.foreign_key.primary.length - 1 ) url += '&'
                })
            }

            return [
                // TODO: sto i utgangspunktet list.betingelse. Finn ut hva jeg skal erstatte med.
                (!config.edit_mode && config.hide_empty && rec.fields[colname].value === null) ? '' : m('tr', [
                    m('td', {class: 'tc v-top'}, [
                        !field.foreign_key || !field.expandable || rec.fields[colname].value === null ? null : m('i.fa.w1', {
                            class: !field.expanded ? 'fa-angle-right' : field.expandable ? 'fa-angle-down' : '',
                            onclick: entry.toggle_relation.bind(this, rec, colname)
                        }),
                        field.element != 'textarea' ? null : m('i.fa', {
                            class: field.expanded ? 'fa-angle-down' : 'fa-angle-right',
                            onclick: function() {
                                field = rec.fields[colname]
                                field.expanded = !field.expanded;
                            }
                        })
                    ]),
                    m('td.label', {
                        class: [
                            'f6 pr1 v-top',
                            field.invalid ? 'invalid' : field.dirty ? 'dirty' : '',
                            'max-w5 w1 truncate'
                        ].join(' '),
                        title: label,
                        onclick: function() {
                            if (field.foreign_key && field.expandable && rec.fields[colname].value) {
                                entry.toggle_relation(rec, colname);
                            } else if (field.element == 'textarea') {
                                field = rec.fields[colname]
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
                            field.element == 'textarea' && !field.expanded && !config.edit_mode ? 'nowrap truncate' : '',
                            config.edit_mode ? 'nowrap' : '',
                            field.invalid ? 'invalid' : field.dirty ? 'dirty' : '',
                            rec.inherited ? 'gray' : '',
                        ].join(' ')
                    }, [
                        rec.table.privilege.update == 0 || rec.readonly || !config.edit_mode ? control.display_value(field) : control.input(rec, colname),
                        !field.expandable || field.value === null ? '' : m('a', {
                            class: 'icon-crosshairs light-blue hover-blue pointer link',
                            href: url
                        }),

                        // Show trash bin for field from cross reference table
                        rec.table.relationship != 'M:M' || !config.edit_mode ? '' : m('i', {
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
var jsoned = require('./jsoned.js');
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

