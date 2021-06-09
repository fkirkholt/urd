var m = require('mithril');
var $ = require('jquery');
var Cookies = require('js-cookie');

var config = {

    limit: Cookies.get('limit') ? Cookies.get('limit') : 20,
    autosave: Cookies.get('autosave') === 'true' ? 1 : 0,
    std_search: Cookies.get('std_search') ? Cookies.get('std_search') : 'simple',
    edit_search: Cookies.get('edit_search') === 'true' ? 1 : 0,
    select: Cookies.get('select') ? Cookies.get('select') : 'native',
    theme: Cookies.get('theme') ? Cookies.get('theme') : 'standard',
    compressed: Cookies.get('compressed') ? Cookies.get('compressed') : false,
    relation_view: Cookies.get('relation_view') ? Cookies.get('relation_view') : 'expansion',
    button_view: Cookies.get('button_view') ? Cookies.get('button_view') : 'both',
    expand_headings: Cookies.get('expand_headings') === 'true' ? 1 : 0,

    admin: false,
    edit_mode: false,
    hide_empty: false,
    show_table: true,

    save: function() {
        var autosave = $('#preferences [name="autosave"]').prop('checked');
        if (autosave) {
            grid.save();
        }
        var limit = $('#limit').val();
        var search = $('#preferences [name="std_search"]').val();
        var select = $('#preferences [name="select"]').val();
        var theme = $('#preferences [name="theme"]').val();
        var relation_view = $('#preferences [name="relation_view"]').val();
        var button_view = $('#preferences [name="button_view"]').val();
        var expand_headings = $('#preferences [name="expand_headings"]').prop('checked');
        if (
            limit != config.limit
            || select != config.select
            || autosave != config.autosave
            || search != config.std_search
            || theme != config.theme
            || relation_view != config.relation_view
            || button_view != config.button_view
            || expand_headings != config.expand_headings
        ) {
            config.limit = limit ? limit : config.limit;
            config.select = select;
            config.autosave = autosave;
            config.std_search = search;
            config.theme = theme;
            config.relation_view = relation_view;
            config.button_view = button_view;
            config.expand_headings = expand_headings;
            // TODO: Update grid
        }
        $('#preferences').hide();
        $('div.curtain').hide();
        Cookies.set('limit', parseInt(limit), {expires:14});
        Cookies.set('select', select, {expires:14});
        Cookies.set('autosave', autosave, {expires:14});
        Cookies.set('std_search', search, {expires:14});
        Cookies.set('theme', theme, {expires:14});
        Cookies.set('relation_view', relation_view, {expires:14});
        Cookies.set('button_view', button_view, {expires:14});
        Cookies.set('expand_headings', expand_headings, {expires:14});
        m.redraw();
    },

    view: function() {
        return m('div', [
            m('table', [
                m('tr', [
                    m('td', 'Autolagring'),
                    m('td', [
                        m('input[type=checkbox]', {
                            name: 'autosave',
                            checked: config.autosave
                        })
                    ])
                ]),
                m('tr', [
                    m('td', 'Ant. poster'),
                    m('td', [
                        m('input#limit', {
                            value: config.limit
                        })
                    ])
                ]),
                m('tr', [
                    m('td', 'Standardsøk'),
                    m('td', m('select[name=std_search]', {value: config.std_search}, [
                        m('option', {value: 'simple'}, 'Enkelt'),
                        m('option', {value: 'advanced'}, 'Avansert'),
                    ]))
                ]),
                m('tr', [
                    m('td', 'Knappevisning'),
                    m('td', m('select[name=button_view]', {value: config.button_view}, [
                        m('option', {value: 'icon'}, 'Ikon'),
                        m('option', {value: 'text'}, 'Tekst'),
                        m('option', {value: 'both'}, 'Begge')
                    ]))
                ]),
                /*
                m('tr', [
                    m('td', 'Nedtrekksliste'),
                    m('td', [
                        m('select[name=select]', {value: config.select}, [
                            m('option', {value: 'native'}, 'Standard'),
                            m('option', {value: 'selectize'}, 'Selectize'),
                            m('option', {value: 'select2'})
                        ])
                    ])
                ]),
                m('tr', [
                    m('td', 'Stil'),
                    m('td', [
                        m('select[name=theme]', {value: config.theme}, [
                            m('option', {value: 'default'}, 'Standard'),
                            m('option', {value: 'material'}, 'Material design')
                        ])
                    ])
                ]),
                */
                m('tr', [
                    m('td', 'Relasjoner'),
                    m('td', [
                        m('select[name=relation_view]', {value: config.relation_view}, [
                            m('option', {value: 'expansion'}, 'Ekspansjon'),
                            m('option', {value: 'column'}, 'Kolonne'),
                        ])
                    ])
                ]),
                m('tr', [
                    m('td', 'Ekspander overskrifter'),
                    m('td', [
                        m('input[type=checkbox]', {
                            name: 'expand_headings',
                            checked: config.expand_headings
                        })
                    ])
                ])
            ]),
            m('div', {class: 'pa2'}, [
                m('input[type=button]', {
                    class: 'fr',
                    value: 'OK',
                    onclick: function() {
                        config.save();
                        $('#preferences').hide();
                        $('div.curtain').hide();

                    }
                }),
                m('input[type=button]', {
                    class: 'fr',
                    value: 'Avbryt',
                    onclick: function() {
                        $('#preferences').hide();
                        $('div.curtain').hide();
                    }
                })
            ])
        ])
    }
}

module.exports = config;

// Placed here to avoid problems with circular inclusion
var grid = require('./grid.js');
