
var filterpanel = {

    expanded: false,

    advanced: false,

    tables: [],

    oncreate: function() {
        config.filter = true;
    },

    search: function() {
        // var url = '#/' + ds.base.name + '/tables/' + ds.table.name;
        var query = {};

        if (filterpanel.advanced) {
            var condition = $('#advanced_filter').val().replace(/=/g, '%3D');
            query.key = 'where';
            query.value = encodeURI(condition);
        } else {
            var search_criterias = filterpanel.parse_search();
            query.key = 'query';
            query.value = search_criterias.join(' AND ');
        }

        // url += '?' + query.key + '=' + query.value;

        m.route.set('/' + ds.base.name + '/' + ds.table.name + '?' + query.key + '=' + query.value);

        /*
        history.pushState({}, "Filter", url);

        var data = {
            base: ds.base.name,
            table: ds.table.name,
            filter: query.key === 'query' ? query.value : null,
            condition: query.key === 'where' ? query.value : null,
        };

        m.request({
            method: "get",
            url: "table",
            params: data
        }).then(function(result) {
            ds.table.records = result.data.records
            ds.table.count_records = result.data.count_records;
            ds.table.grid = result.data.grid;
            m.redraw();
            entry.select(ds.table, 0, true);
        });
        */
    },

    parse_search: function() {
        var search_criterias = [];

        Object.keys(ds.table.filters).map(function(label, idx) {
            var filter = ds.table.filters[label];
            if (filter.value || ['IS NULL', 'IS NOT NULL'].includes(filter.operator)) {
                var value = filter.value;
                var operator = filter.operator
                 if (filter.operator === 'LIKE' || filter.operator === 'NOT LIKE') {
                    value = '*' + value + '*';
                } else if (filter.operator === 'start') {
                    value = value + '*';
                    operator = 'LIKE';
                } else if (filter.operator === 'slutt') {
                    value = '*' + value;
                    operator = 'LIKE';
                } else if (filter.operator.search('=') !== -1) {
                    operator = filter.operator.replace('=', '%3D')
                }
                search_criterias.push((filter.field + ' ' + operator + ' ' + (value || '')).trim());
            }

        });

        return search_criterias;
    },

    save_filter: function (list) {
        var label = prompt("Lagre søk med betegnelse:");
        var save = true;
        var id = null;
        var index = list.saved_filters.length;

        // Check if this label already exists
        $.each(list.saved_filters, function(i, filter) {
            if (filter.user_defined && filter.label == label) {
                if (confirm("Et lagret søk med denne betegnelsen finnes fra før. Vil du overskrive?")) {
                    id = filter.id;
                    index = i;
                } else {
                    save = false;
                }

                return false;
            }
        })

        if (!save) return

        var filter = filterpanel.advanced
            ? $('#advanced_filter').val().replace(/=/g, '%3D')
            : filterpanel.parse_search().join(' AND ').replace(/%3D/g, '=');

        if (label) {
            var param = {
                schema: ds.base.schema,
                base:   ds.base.name,
                table:  list.name,
                filter: filter,
                id:     id,
                label:  label,
                advanced: filterpanel.advanced ? 1 : 0
            };
            $.post("filter", param, function(result) {
                list.saved_filters[index] = {
                    "id": result.data.id,
                    "expression": filter,
                    "label": label,
                };
                $('[name="btn_saved_searches"] option[value="custom"]').hide();
                $('[name="btn_save_search"]').val("Slett søk");
                m.redraw();
            }, "json");
        }
    },

    delete_filter: function() {

        var id;

        var param = m.route.param();

        ds.table.saved_filters.map(function(filter, idx) {
            var selected = (
                param.query && param.query === filter.expression ||
                m.route.param('where') == filter.expression
            )
            if (selected) {
                id = filter.id;
                return;
            }

        });

        m.request({
            method: 'DELETE',
            params: {id: id},
            url: 'filter',
        }).then(function() {
            ds.table.saved_filters = ds.table.saved_filters.filter(function(search) {
                return search.id != id;
            });
            ds.table.delete_search = false;
        });
    },

    /**
     * Returns an object with field, operator and value from where expression
     */
    parse_query: function(expr) {
        var conditions = expr !== null ? expr.split(' AND ') : [];
        var search = config.filter ? [] : {};
        $.each(conditions, function(i, cond) {
            var parts = cond.split(/\s*([=<>]|!=|IN|LIKE|NOT LIKE|IS NULL|IS NOT NULL)\s*/);
            if (parts.length > 1) {
                var val = parts[2].replace(/(^'\*)|(^')|(\*'$)|('$)/g, '');
                var operator = parts[1];

                if (val.charAt(0) === '*' && val.slice(-1) === '*') {
                    val = val.substr(1, val.length-2);
                } else if (val.charAt(0) === '*') {
                    val = val.substr(1, val.length);
                    operator = 'slutt';
                } else if (val.slice(-1) === '*') {
                    val = val.substr(0, val.length-1);
                    operator = 'start';
                }

                if (operator === 'IN') {
                    val = val.replace(', ', ',').split(',');
                    val = val.map(function(value) {

                        return value == parseInt(value) ? parseInt(value) : value;
                    });
                }

                var field = parts[0];
                var item = {
                    field: field,
                    operator: operator,
                    value: val
                };

                config.filter ? search.push(item) : search[field] = item;
            }
        });

        return search;
    },

    object_options: function() {
        var fields = ds.table.fields;
        // var options = _mapValues(ds.table.fields, function(field) {
        var options = Object.keys(ds.table.fields).filter(function(fieldname) {
            var field = ds.table.fields[fieldname];
            return field.foreign_key ? field.foreign_key.table : null;
        }).map(function(field) {
            var field = ds.table.fields[field];
            var option = {};
            if (field.foreign_key) {
                option.label = field.label;
                option.value = field.name;
            }
            return option;
        });

        return options;
    },

    get_operators: function(field) {
        var operators = [
            {value: 'IN', label: 'blant'},
            {value: 'LIKE', label: 'inneholder'},
            {value: 'NOT LIKE', label: 'inneholder ikke'},
            {value: 'start', label: 'starter på'},
            {value: 'slutt', label: 'slutter på'},
            {value: '=', label: '='},
            {value: '!=', label: '!='},
            {value: '>', label: '>'},
            {value: '<', label: '<'},
            {value: 'IS NULL', label: 'er tom'},
            {value: 'IS NOT NULL', label: 'er ikke tom'},
        ];
        operators = operators.filter(function(operator) {

            if (((field.element == 'select' && (field.foreign_key && field.foreign_key.table !== field.table)) ||
                (field.element == 'input' && field.attr.type == 'radio')) &&
                    ['LIKE', 'NOT LIKE', 'start', 'slutt', '>', '<'].includes(operator.value)
            ) {
                return false;
            } else if (
                field.datatype == 'boolean' &&
                ['IN', 'LIKE', 'NOT LIKE', 'start', 'slutt', '>', '<'].includes(operator.value)
            ) {
                return false;
            } else if (
                (field.element == 'input[type=date]' ||
                (field.element == 'input[type=text]' && field.datatype == 'integer') ||
                (field.element == 'input' && (
                    field.attr.type == 'date' ||
                        field.datatype == 'integer'
                ))) && ['LIKE', 'NOT LIKE', 'start', 'slutt'].includes(operator.value)
            ) {
                return false;
            } else if (
                (field.datatype == 'string' &&
                 (field.element == 'textarea' || field.element == 'input[type=text]' ||
                  (field.element == 'input' && field.attr.type == 'text'))) &&
                    ['IN'].includes(operator.value)
            ) {
                return false;
            } else {
                return true;
            }
        });

        return operators;
    },

    view: function(vnode) {
        if (!ds.table || ds.type != 'table' || !filterpanel.expanded) return;
        if (!filterpanel.tables[ds.table.name]) filterpanel.tables[ds.table.name] = ds.table;
        var table = ds.table;

        filterpanel.table = ds.table.name;
        filterpanel.field_options = Object.keys(filterpanel.tables[filterpanel.table].fields).map(function(key) {
            var field = filterpanel.tables[filterpanel.table].fields[key];
            return {value: field.name, label: field.label || field.name};
        });

        filterpanel.field_options = filterpanel.field_options.sort(function(a, b) {
            if (a.label < b.label) return -1;
            if (a.label > b.label) return 1;
            return 0;
        });

        if (table.filters.length == 0) {
            table.filters = [{
                field: ds.table.name + '.' + filterpanel.field_options[0].value,
                operator: '=',
                value: ''
            }];
        }

        filterpanel.advanced_filter = filterpanel.advanced_filter ? filterpanel.advanced_filter
            : m.route.param('where') ? m.route.param('where').replace('%3D', '=')
            : null;

        var param = m.route.param();
        var parsed = filterpanel.parse_search().join(' AND ').replace(/%3D/g, '=');

        return m('div.filter', {class: 'fl w-100'}, [
            !filterpanel.expanded ? null : m('div', {
                style: [
                    'margin-top: 10px',
                    'margin-bottom: 5px',
                    'background-color: #F5F5F5',
                    'padding: 7px',
                    'border: 1px solid lightgray'].join(';')
            }, [
                filterpanel.advanced ? '' : table.filters.map(function(filter, idx) {
                    var parts = filter.field.split('.');
                    var field_name;

                    if (parts.length === 2) {
                        filter.table = parts[0];
                        field_name = parts[1];
                    } else {
                        filter.table = ds.table.name;
                        field_name = parts[0];
                    }

                    if (filterpanel.tables[filter.table] === undefined) {
                        var ref_field = ds.table.fields[filter.table];
                        m.request({
                            method: 'get',
                            url: 'table',
                            params: {
                                base: ref_field.foreign_key.base,
                                table: ref_field.foreign_key.table,
                                limit: 0
                            }
                        }).then(function(response) {
                            filterpanel.tables[ref_field.name] = response.data;
                        });

                        return;
                    }

                    var field = filterpanel.tables[filter.table].fields[field_name];

                    var field_options = Object.keys(filterpanel.tables[filter.table].fields).map(function(key) {
                        var field = filterpanel.tables[filter.table].fields[key];
                        return {value: field.name, label: field.label || field.name};
                    });

                    field_options = field_options.sort(function(a, b) {
                        if (a.label < b.label) return -1;
                        if (a.label > b.label) return 1;
                        return 0;
                    })

                    var operators = filterpanel.get_operators(field);
                    filter.operator = filter.operator || operators[0].value;
                    var object_options = filterpanel.object_options();
                    return m('div', {style: 'display:flex;'}, [
                        m('select', {
                            class: 'mr2',
                            disabled: object_options.length === 0,
                            title: 'Søk innenfor valgt objekt',
                            onchange: function(e) {
                                if (e.target.selectedIndex === 0) {
                                    filter.field = ds.table.name + '.' + Object.keys(ds.table.fields)[0];
                                } else {
                                    var fieldname = e.target['value'];
                                    var field = ds.table.fields[fieldname];
                                    var ref_table = field.foreign_key ? field.foreign_key.table : null;
                                    if (filterpanel.tables[ref_table]) {
                                        filter.field = ref_table + '.' + Object.keys(filterpanel.tables[ref_table].fields)[0];
                                    } else {
                                        m.request({
                                            method: 'get',
                                            url: 'table',
                                            params: {
                                                base: field.foreign_key.base,
                                                table: field.foreign_key.table,
                                                limit: 0
                                            }
                                        }).then(function(response) {
                                            filterpanel.tables[field.name] = response.data;
                                            filter.field = field.name + '.' + Object.keys(response.data.fields)[0];
                                        });
                                    }
                                }
                            }
                        }, [
                            m('option', {value: ds.table.name}, ds.table.label),
                            !object_options.length ? '' : m('optgroup', {
                                label: "Relasjoner"
                            }, [
                                object_options.map(function(option, idx) {
                                    return m('option', {
                                        value: option.value,
                                        selected: option.value === filter.table
                                    }, option.label)
                                })
                            ])
                        ]),
                        m(select, {
                            class: 'mr2',
                            options: field_options,
                            value: field.name,
                            required: true,
                            width: '150px',
                            onchange: function(e) {
                                filter.field = filter.table + '.' + e.target['value'];
                                filter.operator = '';
                                filter.value = '';
                                filter.text = '';
                                // This code recreates the value field, to run oncreate again
                                // with attributes for the new field
                                filter.disabled = true;
                                m.redraw();
                                filter.disabled = false;
                            }
                        }),
                        m(select, {
                            class: 'mr2',
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
                        filterpanel.draw_value_field(field, filter),
                        m('span', m.trust('&nbsp;')),
                        m('i[name="remove"]', {
                            class: 'icon fa fa-trash-o',
                            title: 'Fjern betingelse',
                            style: 'cursor: pointer;',
                            onclick: function(e) {
                                table.filters.splice(idx, 1);
                            }
                        }),
                    ]);
                }),
                !filterpanel.advanced ? '' : m('textarea#advanced_filter', {
                    class: 'w-100 ba b--grey pa1',
                    onchange: function(e) {
                        filterpanel.advanced_filter = $('#advanced_filter').val();
                    }
                }, filterpanel.advanced_filter),
                m('div', {class: 'mt2 overflow-hidden'}, [
                    filterpanel.advanced ? '' : m('i', {
                        class: 'fa fa-plus',
                        onclick: function() {
                            table.filters.push({
                                field: Object.keys(table.fields)[0],
                                operator: '=',
                                value: ''
                            });
                            return false;
                        }
                    }),
                    m('label', {class: 'ml2'}, [
                        m('input[type="checkbox"]', {
                            checked: filterpanel.advanced,
                            onclick: function() {
                                filterpanel.advanced = !filterpanel.advanced;
                            }
                        })
                    ], ' Avansert'),
                    !filterpanel.advanced ? '' : m('select', {
                        class: 'ml2',
                        title: 'Tabeller',
                        value: filterpanel.table,
                        onchange: function(e) {
                            filterpanel.table = e.target['value'];
                        }
                    }, [
                        m('option', {value: ds.table.name}, ds.table.label),
                        m('optgroup', {
                            label: "Relasjoner"
                        }, [
                            filterpanel.object_options().map(function(option, idx) {
                                return m('option', {
                                    value: option.value
                                }, option.label)
                            })
                        ]),
                    ]),
                    !filterpanel.advanced ? '' : m(select, {
                        class: 'ml2',
                        options: filterpanel.field_options,
                        value: null,
                        placeholder: '-- Velg felt: --',
                        width: '150px',
                        onchange: function(e) {
                            filterpanel.advanced_filter = $('#advanced_filter').val() + filterpanel.table + '.' + e.target['value'];
                        }

                    }),
                    m('button[name="search"]', {
                        style: 'float: right',
                        onclick: function(e) {
                            // if (grid.check_dirty()) {
                                filterpanel.search();
                            // }
                        }
                    }, 'Filtrer'),
                ])
            ])
        ]);
    },

    /** Draws a value field for the search parameter
     *
     * @param {object} field - The field to search on
     * @param {object} filter - The filter to apply
     *
     */
    draw_value_field: function(field, filter) {
        if (filter.disabled) {
            return null;
        } else if (['IS NULL', 'IS NOT NULL'].includes(filter.operator)) {
            return null;
            /*
            return m(select, {
                name: field.name,
                options: [
                    {value: 'NULL', label: 'NULL'},
                    {value: 'FALSE', label: 'FALSE'},
                    {value: 'TRUE', label: 'TRUE'}
                ],
                value: filter.value,
                label: filter.label,
                onchange: function(e) {
                    filter.value = e.target['value'];
                }
            })
            */
        } else if (
            ((field.element == 'select' && field.options && filter.operator !== 'IN') ||
            (field.element == 'input' && field.attr.type == 'checkbox')) && filter.operator !== ''
        ) {
            // TODO: field.relation tror jeg ikke finnes mer
            var key = field.relation;

            return m(select, {
                name: filter.field,
                options: field.options,
                valueField: 'value',
                value: filter.value,
                label: filter.label,
                style: 'flex: 2;',
                ajax: field.options ? null : {
                    type: 'GET',
                    url: 'select',
                    dataType: 'json',
                    data: {
                        limit: 500,
                        schema: key.ref_schema,
                        base: key.ref_base,
                        table: key.ref_table,
                        alias: key.alias,
                        view: field.view,
                        key: key.ref_key,
                        condition: null
                    }
                },
                onchange: function(e) {
                    filter.value = e.target['value'];
                    filter.label = e.target['textContent'];
                }
            });
        } else if (field.element === 'select' && ['', 'LIKE', 'start', 'slutt', '>', '<'].includes(filter.operator) == false) {

            var key_json = JSON.stringify(field.foreign_key ? field.foreign_key.primary : [field.name]);

            return m(autocomplete, {
                name: filter.field,
                item: filter,
                placeholder: 'Velg',
                multiple: filter.operator === 'IN' ? true : false,
                options: field.options ? field.options : null,
                value: filter.text,
                class: 'w-100',
                style: 'flex: 2;',
                ajax: field.options ? null : {
                    url: 'select',
                    data: {
                        limit: 550,
                        schema: field.foreign_key ? field.foreign_key.schema : '',
                        base: field.foreign_key ? field.foreign_key.base : ds.base.name,
                        table: field.foreign_key ? field.foreign_key.table : field.table,
                        alias: field.name,
                        view: field.view,
                        column_view: field.column_view,
                        key: key_json,
                        condition: null
                    }
                },
                onchange: function(e) {
                    filter.value = $(e.target).data('value');
                    filter.text = e.target['value'];
                },
                onclick: function(e) {
                    if (e.target.value === '') {
                        $(e.target).autocomplete('search', '');
                    }
                }
            });
        } else if (field.element == 'input' && field.attr.type == 'radio') {
            return [
                field.options.map(function(filter, idx) {
                    return [m('input[type="radio"]', {
                        value: filter.value
                    }), filter.label];
                })
            ];
        } else if (field.element == 'input' && field.attr.type == 'date') {
            return m(datepicker, {
                name: filter.field,
                value: filter.value,
                style: 'flex: 2;',
                dateFormat: 'yy-mm-dd',
                onchange: function() {
                    filter.value = e.target['value'];
                }
            });
        } else {
            var width = (field.size === null || field.size > 20)
                ? '100%'
                : field.size + 'em';
            return m('input', {
                name: filter.field,
                value: filter.value !== undefined ? filter.value : '',
                style: config.filter ? 'flex:2;' : 'width: ' + width,
                disabled: filter.operator === '' ? true : false,
                onchange: function(e) {
                    value = field.attr && field.attr.class === 'byte'
                        ? numeral(e.target['value']).value()
                        : e.target['value'];
                    filter.value = value;
                },
                onkeydown: function(e) {
                    if (e.keyCode == 13) {
                        filter.value = e.target.value;
                        filterpanel.search(ds.table.filter);
                        e.preventDefault();
                    }
                    e.redraw = false;
                }
            });
        }

    }
}

module.exports = filterpanel;

var m = require('mithril');
var $ = require('jquery');
var _last = require('lodash/last');
var _find = require('lodash/find');
var datepicker = require('./datepicker.js');
var select = require('./select.js');
var autocomplete = require('./autocomplete.js');
var numeral = require('numeral');
var ds = require('./datastore.js');
var entry = require('./entry.js');
var config = require('./config.js');
