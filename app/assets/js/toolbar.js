var toolbar = {

    oninit: function() {
        mousetrap(document.body).bind('mod+s', function(e) {
            grid.save();
            return false;
        });
        mousetrap(document.body).bind('mod+n', function(e) {
            entry.create(ds.table);
            return false;
        });
        mousetrap(document.body).bind('mod+f', function(e) {
            filterpanel.expanded = !filterpanel.expanded;
            m.redraw();
            return false;
        });
        mousetrap(document.body).bind('esc', function(e) {
            $('#urdgrid tr.focus').focus();
        });
    },

    onremove: function() {
        mousetrap.reset();
    },

    run_action: function(action) {
        var rec_idx = ds.table.selection;
        var prim_key = ds.table.records[rec_idx].primary_key;
        var prim_nokler_json = JSON.stringify(prim_key);
        var address = ds.base.schema + (action.url[0] === '/' ? '' : '/') + action.url;
        var kommunikasjon = action.communication;

        var $form = $('form#action');
        $form.find(':input[name="primary_key"]').val(prim_nokler_json);

        if (ds.table.dirty) {
            alert("Du må lagre før du kan utføre handling");
            return;
        }

        if (kommunikasjon == 'submit') {
            $form.attr('action', address).attr('method', 'post').submit();
        } else if (kommunikasjon == 'ajax') {
            $.ajax({
                url: address,
                method: action.method ? action.method : 'get',
                dataType: 'json',
                contentType: "application/json",
                data: JSON.stringify({
                    base: ds.base.name,
                    table: ds.table.name,
                    primary_key: prim_nokler_json
                })
            }).done(function(data) {
                if (action.update_field) {
                    var field = ds.table.records[rec_idx].fields[action.update_field];
                    entry.update_field(data.value, field.name, ds.table.records[rec_idx]);
                    m.redraw();
                }
            });
        } else if (kommunikasjon == 'dialog') {
            $('#action-dialog').load(address + '?version=1');
            $('div.curtain').show();
            $('#action-dialog').show();
        }
    },

    button: {
        disabled: function(name) {
            if (name == 'first' || name == 'previous') {
                return ds.table.offset == 0 && ds.table.selection == 0 ? true : false;
            } else {
                return ds.table.count_records - ds.table.offset <= ds.table.limit && ds.table.selection == ds.table.count_records - ds.table.offset - 1
                    ? true
                    : false;
            }
        }
    },

    delete_record: function() {
        if (ds.table.permission.delete != true) return;

        var r = true;
        if (config.autosave || !config.edit_mode) {
            var r = confirm('Er du sikker på at du vil slette posten?');
        }

        if (r === true) {
            idx = ds.table.selection;
            if (ds.table.records[idx].new) {
                ds.table.records.splice(idx, 1);
            } else {
                entry.delete(ds.table.records[idx]);
            }

            if (!config.edit_mode) {
                grid.save();
            }
        }
    },

    view: function(vnode) {

        if (!ds.table || ds.table.type === 'database' && !config.admin) return;

        var param = m.route.param();

        if (ds.table.search) {
            return [
                m('input[type=button]', {
                    value: 'Utfør søk',
                    onclick: function() {
                        filterpanel.search();
                    }
                }),
                m('input[type=button]', {
                    value: 'Avbryt',
                    onclick: function() {
                        ds.table.search = !ds.table.search;
                        m.redraw();
                    }
                }),
                /*
                m('input[type=button]', {
                    value: 'Avansert',
                    onclick: function() {
                        config.filter = true;
                        ds.table.filters = filterpanel.parse_query(ds.table.query);
                        filterpanel.expanded = true;
                        config.search = false;
                        ds.table.search = false;
                    }
                }),
                */
                m('input[type=button]', {
                    value: 'Nullstill skjema',
                    disabled: Object.keys(ds.table.filters).filter(function(label) {
                        var filter = ds.table.filters[label];
                        return filter.value || ['IS NULL', 'IS NOT NULL'].includes(filter.operator);
                    }).length === 0,
                    onclick: function() {
                        ds.table.filters = {};
                    }
                }),
                m('input[type=checkbox]', {
                    class: 'ml2',
                    checked: config.edit_search,
                    onchange: function() {
                        config.edit_search = !config.edit_search;
                        Cookies.set('edit_search', config.edit_search, {expires:14});
                        if (config.edit_search) {
                            ds.table.filters = filterpanel.parse_query(ds.table.query);
                        }
                    }
                }), ' Vis aktive søkekriterier'
            ]
        } else if (ds.table.edit) {
            return [
                m('input[type=button]', {
                    value: 'Lagre og lukk',
                    onclick: function() {
                        if (ds.table.dirty) {
                            grid.save();
                        }
                        config.edit_mode = false;
                    }
                }),
                m('input[type=button]', {
                    value: 'Avbryt',
                    onclick: function() {
                        if (ds.table.dirty) {
                            grid.update(ds.table, {});
                        }
                        ds.table.edit = false;
                        config.edit_mode = false;
                        m.redraw();
                    }
                }),
            ]
        }

        return m('ul', {target: '_blank', class: 'f6 list pl0 mb0 mt0'}, [
            m('li', [
                m('form#action', [
                    m('input', {type: 'hidden', name: 'base', value: ds.base.name}),
                    m('input', {type: 'hidden', name: 'table', value: ds.table.name}),
                    m('input', {type: 'hidden', name: 'primary_key'}),
                ]),
            ]),
            m('li', {class: 'dib'}, [
                m('i', {
                    class: [
                        'fa fa-table ml1 mr2 pointer dim',
                        config.show_table ? 'gray' : ''
                    ].join(' '),
                    title: 'Vis/skjul tabell',
                    onclick: function() {
                        config.show_table = !config.show_table;

                        if (config.show_table) {
                            m.redraw();
                            grid.align_thead();
                        }
                    }
                })
            ]),
            !config.show_table ? '' : m('i', {
                class: 'ml1 mr2 fa pointer ' + (config.compressed ? 'fa-expand' : 'fa-compress'),
                title: config.compressed ? 'Ekspander' : 'Komprimer',
                onclick: function() {
                    config.compressed = !config.compressed;
                }
            }),
            !config.show_table || config.std_search == 'simple' ? '' : m('li', {class: 'dib'}, [
                m('i', {
                    class: 'fa fa-filter ml1 mr2 pointer dim',
                    title: 'Filtrer tabell',
                    onclick: function() {
                        config.filter = true;
                        ds.table.filters = filterpanel.parse_query(ds.table.query);
                        filterpanel.expanded = !filterpanel.expanded
                    }
                }),
            ]),
            !config.show_table || config.std_search == 'advanced' ? '' : m('i', {
                class: 'fa fa-search ml1 mr2 pointer dim',
                title: 'Søk',
                onclick: function() {
                    config.filter = false;
                    ds.table.filters = filterpanel.parse_query(ds.table.query);
                    ds.table.search = !ds.table.search;
                }
            }),
            !config.show_table ? '' : m('select', {
                class: 'ml1 mr2',
                name: 'btn_saved_searches',
                title: 'Lagrede søk',

                onupdate: function(vnode) {
                    if ($(vnode.dom).val() != 'custom') {
                        $(vnode.dom).find('option[value="custom"]').hide();
                        $(vnode.dom).find('option[value="save_search"]').hide();
                        if ($(vnode.dom).val() == 'alle') {
                            $(vnode.dom).find('option[value="separator_save"]').hide();
                        }
                    } else {
                        $(vnode.dom).find('option[value="custom"]').show();
                    }

                    if ($(vnode.dom).val() == 'alle' || $(vnode.dom).val() == 'custom' || $(vnode.dom).val() == 'delete_search') {
                        if ($(vnode.dom).val() == 'delete_search') {
                            $(vnode.dom).val('custom');
                        }
                        $(vnode.dom).find('option[value="delete_search"]').hide();
                    } else {
                        $(vnode.dom).find('option[value="delete_search"]').show();
                    }
                },
                onchange: function(vnode) {
                    var id = $(this).val();

                    var filter = ds.table.saved_filters.find(function(row) {
                        return row.id == id;
                    });
                    var query;

                    if (id == 'save_search') {
                        filterpanel.save_filter(ds.table);
                        return;
                    }

                    if (id == 'delete_search') {
                        $(vnode.dom).find('option[value="custom"]').show();
                        $(vnode.dom).val('custom');
                        filterpanel.delete_filter();
                        return;
                    }

                    if (id == 'alle') {
                        query = 'query=';
                    } else if (filter.advanced == 0) {
                        query = 'query=' + filter.expression.replace(/=/g, '%3D');
                    } else {
                        query = 'where=' + filter.expression.replace(/=/g, '%3D');
                    }
                    m.route.set('/' + ds.base.name + '/tables/' + ds.table.name + '?' + query);
                }
            }, [
                m('option', {value: 'alle'}, 'Alle'),
                m('option', {disabled: true}, '—————'),

                (param.query || param.where) ? [
                    m('option', {value: 'custom', selected: true}, 'Søkeresultat'),
                    m('option', {value: 'save_search'}, 'Lagre søk …'),
                    m('option', {value: 'delete_search'}, 'Slett søk …'),
                    m('option', {value: 'separator_save', disabled: true}, '—————'),
                ] : '',

                ds.table.saved_filters.map(function(filter, idx) {
                    var selected = (
                        param.query && param.query === filter.expression ||
                            m.route.param('where') == filter.expression
                    )
                    if (selected) {
                        ds.table.delete_search = true;
                    }

                    return m('option', {
                        value: filter.id,
                        selected: selected
                    }, filter.label)
                }),


            ]),
            m('li', {class: 'dib'}, [
                config.button_view != 'text' ? [
                    m('i', {
                        class: [
                            'fa fa-file-o ml3 mr1',
                            ds.table.permission.add == true ? 'dim pointer' : 'moon-gray'
                        ].join(' '),
                        title: 'Ny post',
                        onclick: function() {
                            if (ds.table.permission.add != true) return;
                            entry.create(ds.table);
                            if (!config.edit_mode) {
                                ds.table.edit = true;
                                config.edit_mode = true;
                            }
                        }
                    }),
                    config.button_view == 'both' ? m('span', {
                        class: ds.table.permission.add == true ? 'dim pointer' : 'moon-gray',
                        onclick: function() {
                            if (ds.table.permission.add != true) return;
                            entry.create(ds.table);
                            if (!config.edit_mode) {
                                ds.table.edit = true;
                                config.edit_mode = true;
                            }
                        }
                    }, 'Ny') : ''
                ]
                : (config.button_view == 'text') ? m('input[type=button]', {
                    value: 'Ny',
                    disabled: ds.table.permission.add == false,
                    onclick: function() {
                        if (ds.table.permission.add != true) return;
                        entry.create(ds.table);
                        if (!config.edit_mode) {
                            ds.table.edit = true;
                            config.edit_mode = true;
                        }
                    }
                })
                : ''
            ]),
            m('li', {class: 'dib'}, [
                config.button_view != 'text' ? [
                    m('i', {
                        class: [
                            'fa fa-copy ml2 mr1 pointer f6 dim',
                            ds.table.permission.add == true ? 'dim pointer' : 'moon-gray'
                        ].join(' '),
                        title: 'Kopier post',
                        onclick: function() {
                            if (ds.table.permission.add != true) return;
                            entry.copy();
                            if (!config.edit_mode) {
                                ds.table.edit = true;
                                config.edit_mode = true;
                            }
                        }
                    }),
                    config.button_view == 'both' ? m('span', {
                        class: ds.table.permission.add == true ? 'dim pointer' : 'moon-gray',
                        onclick: function() {
                            if (ds.table.permission.add != true) return;
                            entry.copy();
                            if (!config.edit_mode) {
                                ds.table.edit = true;
                                config.edit_mode = true;
                            }
                        }

                    }, 'Kopier') : ''
                ]
                : (config.button_view == 'text') ? m('input[type=button]', {
                    value: 'Kopier',
                    disabled: ds.table.permission.add == false,
                    onclick: function() {
                        if (ds.table.permission.add != true) return;
                        entry.copy();
                        if (!config.edit_mode) {
                            ds.table.edit = true;
                            config.edit_mode = true;
                        }
                    }
                })
                : ''
            ]),
            !config.edit_mode ? m('li', {class: 'dib'}, [
                config.button_view != 'text' ? [
                    m('i', {
                        class: [
                            'fa fa-edit ml2 mr1 pointer f6',
                            ds.table.permission.edit == true ? 'dim pointer' : 'moon-gray'
                        ].join(' '),
                        title: 'Rediger post',
                        onclick: function() {
                            ds.table.edit = true;
                            config.edit_mode = true;
                        }
                    }),
                    config.button_view == 'both' ? m('span', {
                        class: ds.table.permission.edit == true ? 'dim pointer' : 'moon-gray',
                        onclick: function() {
                            ds.table.edit = true;
                            config.edit_mode = true;
                        }
                    }, 'Rediger') : ''
                ]
                : (config.button_view == 'text') ? m('input[type=button]', {
                    value: 'Rediger',
                    disabled: ds.table.permission.edit == false,
                    onclick: function() {
                        ds.table.edit = true;
                        config.edit_mode = true;
                    }
                }) : ''
            ]) : '',
            m('li', {class: 'dib'}, [
                config.autosave || !config.edit_mode ? '' : [
                    config.button_view != 'text' ? [
                        m('i', {
                            class: [
                                'fa fa-save ml2 mr1',
                                ds.table.dirty ? 'dim pointer' : 'moon-gray'
                            ].join(' '),
                            title: 'Lagre endringer',
                            onclick: function() {
                                if (!ds.table.dirty) return;
                                grid.save();
                            }
                        }),
                        config.button_view == 'both' ? m('span', {
                            class: ds.table.dirty ? 'dim pointer' : 'moon-gray',
                            onclick: function() {
                                if (!ds.table.dirty) return;
                                grid.save();
                            }
                        }, 'Lagre') : ''
                    ]
                    : (config.button_view == 'text') ? m('input[type=button]', {
                        value: 'Lagre',
                        disabled: ds.table.dirty == false,
                        onclick: function() {
                            if (!ds.table.dirty) return;
                            grid.save();
                        }
                    }) : ''
                ]
            ]),
            m('li', {class: 'dib'}, [
                config.button_view != 'text' ? [
                    m('i', {
                        class: [
                            'fa fa-trash-o ml2 mr1 pointer dim',
                            ds.table.permission.delete == true ? 'dim pointer' : 'moon-gray'
                        ].join(' '),
                        title: 'Slett post',
                        onclick: toolbar.delete_record
                    }),
                    config.button_view == 'both' ? m('span', {
                        class: ds.table.permission.delete == true ? 'dim pointer' : 'moon-gray',
                        onclick: toolbar.delete_record
                    }, 'Slett') : ''
                ]
                : (config.button_view == 'text') ? m('input[type=button]', {
                    value: 'Slett',
                    disabled: ds.table.permission.delete == false,
                    onclick: toolbar.delete_record
                }) : ''
            ]),
            config.show_table ? '' : m('li', {class: 'dib'}, [
                m('i', {
                    class: 'fa fa-print ml1 mr2 pointer dim',
                    onclick: function() {
                        print();
                    }
                })
            ]),
            m('li', {class: 'dib relative'}, [
                m('i', {
                    class: 'fa fa-cog ml2 mr1 pointer dim',
                    title: 'Flere handlinger',
                    onclick: function() {
                        $('ul#actions').toggle();
                    }
                }),
                m('ul#actions', {
                    class: 'absolute left-0 bg-white list pa1 shadow-5 dn pointer z-999'
                }, [
                    m('li', {
                        class: 'nowrap hover-blue',
                        onclick: function() {
                            $('#export-dialog').show();
                            $('div.curtain').show();
                            $('ul#actions').hide();
                        }
                    }, '- Eksporter poster ...'),
                    Object.keys(ds.table.actions).map(function(label, idx) {
                        var action = ds.table.actions[label];

                        var txt = action.communication != 'ajax'
                            ? action.label + ' ...'
                            : action.label;

                        return action.disabled ? '' : m('li', {
                            class: 'nowrap hover-blue',
                            title: action.description,
                            onclick: function() {
                                toolbar.run_action(action);
                                $('ul#actions').toggle();
                            }
                        }, '- ' + txt);
                    })
                ])
            ]),
            m('li.dib', m('i.reload', {
                hidden: true,
                onclick: function() {
                    grid.update(ds.table, {});
                }
            })),
            config.show_table ? '' : m('li.dib', {
                onclick: function(e) {
                    // toolbar.navigate(e.target.name);
                }
            }, [
                m('button[name="first"]', {
                    class: [
                        'icon fa fa-angle-double-left ba b--light-silver br0 bg-white',
                        toolbar.button.disabled('first') ? 'moon-gray' : '',
                    ].join(' '),
                    disabled: toolbar.button.disabled('first'),
                    onclick: function() {
                        if (ds.table.offset == 0) {
                            entry.select(ds.table, 0, true);
                        } else {
                            pagination.navigate('first');
                        }
                    }
                }),
                m('button[name=previous]', {
                    class: [
                        'icon fa fa-angle-left bt br bl-0 bb b--light-silver br0 bg-white',
                        toolbar.button.disabled('previous') ? 'moon-gray' : ''
                    ].join(' '),
                    disabled: toolbar.button.disabled('previous'),
                    onclick: function() {
                        var idx = ds.table.selection;
                        var prev = idx - 1;
                        if (prev == -1) {
                            pagination.navigate('previous');
                        } else {
                            entry.select(ds.table, prev, true);
                        }
                    }
                }),
                m('button[name=next]', {
                    class: [
                        'icon fa fa-angle-right bt br bb bl-0 b--light-silver br0 bg-white',
                        toolbar.button.disabled('next') ? 'moon-gray' : '',
                    ].join(' '),
                    disabled: toolbar.button.disabled('next'),
                    onclick: function() {
                        var idx = ds.table.selection;
                        var next = idx + 1;
                        if (next == ds.table.records.length) {
                            pagination.navigate('next');
                        } else {
                            entry.select(ds.table, next, true);
                        }
                    }
                }),
                m('button[name=last]', {
                    class: [
                        'icon fa fa-angle-double-right bt br bb bl-0 b--light-silver br0 bg-white',
                        toolbar.button.disabled('last') ? 'moon-gray' : '',
                    ].join(' '),
                    disabled: toolbar.button.disabled('last'),
                    onclick: function() {
                        pagination.navigate('last');
                    }
                }),
            ]),

            /*
            !ds.table.help ? '' : m('i', {
                id: 'btn_help_table',
                class: 'fa fa-question',
                title: 'Hjelp',
                style: 'margin-left: 10px; cursor: pointer',
                onclick: function() {
                    // TODO: Se på hvordan dette skal implementeres
                }
            }),
            m('br'),
            m('input', {
                id: 'advanced_search',
                type: 'text',
                style: 'display:none',
                value: decodeURI(m.route.param('query')),
                onkeypress: function(e) {
                    if (e.which == 13) {
                        m.route.set('/' + ds.table.base.name + '/' + ds.table.name + '?query=' + encodeURI($(this).val()));
                    }
                }
            })
            */


        ]);
    }
}

module.exports = toolbar;

var m = require('mithril');
var mousetrap = require('mousetrap');
var config = require('./config.js');
var grid = require('./grid.js');
var filterpanel = require('./filterpanel');
var entry = require('./entry.js');
var pagination = require('./pagination.js');
var Cookies = require('js-cookie');

