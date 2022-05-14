var m = require('mithril');
var $ = require('jquery');
var select = require('./select.js');

var login = {

    view: function() {
        return m('form', [
            m('div', {class: 'f4 mb2'}, 'Logg inn'),
            !login.error ? '' : m('div', {class: 'red'}, login.msg || 'Logg inn'),
            m(select, {
                class: 'w-100 mb1',
                id: 'system',
                name: 'system',
                label: 'System',
                value: ds.base.system || 'sqlite3',
                onchange: function() {
                    ds.base.system = $('#system').val()
                },
                options: [

                    {
                        label: 'SQLite',
                        value: 'sqlite3'
                    },
                    {
                        label: 'MySQL',
                        value: 'mysql'
                    },
                    {
                        label: 'Oracle',
                        value: 'oracle'
                    },
                    {
                        label: 'PostgreSQL',
                        value: 'postgres'
                    }
                ]
            }),
            m('input[type=text]', {
                id: 'server',
                name: 'server',
                placeholder: $('#system').val() == 'sqlite3' ? 'Sti til mappe' : 'localhost',
                value: ds.base.server,
                class: 'db w-100 mb1'
            }),
            $('#system').val() == 'sqlite3' ? '' : m('input[type=text]', {
                id: 'brukernavn',
                name: 'brukernavn',
                placeholder: 'Brukernavn',
                class: 'db w-100 mb1'
            }),
            $('#system').val() == 'sqlite3' ? '' : m('input[type=password]', {
                id: 'passord',
                name: 'passord',
                placeholder: 'Passord',
                class: 'db w-100 mb1',
                onkeypress: function(e) {
                    if (e.which == 13) {
                        $('#btn_login').click();
                    }
                }
            }),
            m('input[type=text]', {
                id: 'database',
                name: 'database',
                placeholder: 'Database',
                value: ds.base.name,
                class: 'db w-100 mb1'
            }),
            m('input[type=button]', {
                id: 'btn_login',
                value: 'Logg inn',
                class: 'db w-100',
                onclick: function() {
                    login.error = false
                    var param = {};
                    param.system = $('#system').val()
                    param.server = $('#server').val().trim()
                    param.username = $('#brukernavn').val()
                    param.password = $('#passord').val()
                    param.database = $('#database').val().trim()

                    m.request({
                        method: 'post',
                        url: 'login',
                        params: param
                    }).then(function(result) {
                        if (param.database && param.database != ds.base.name) {
                            m.route.set('/' + param.database)
                            $('div.curtain').hide();
                            $('#login').hide();
                        } else {
                            window.location.reload()
                        }
                    }).catch(function(e) {
                        if (e.code == 401) {
                            login.error = true
                            $('#brukernavn').focus().select()
                        }
                    })
                }
            })
        ])
    }
}

module.exports = login;
