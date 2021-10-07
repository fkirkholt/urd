var m = require('mithril');
var $ = require('jquery');

var login = {

    view: function() {
        return m('div', [
            m('div', {class: 'f4 mb2'}, 'Logg inn'),
            !login.error ? '' : m('div', {class: 'red'}, 'Feil brukernavn/passord'),
            m('input[type=text]', {
                id: 'brukernavn',
                name: 'brukernavn',
                placeholder: 'Brukernavn',
                class: 'db w-100 mb1'
            }),
            m('input[type=password]', {
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
            m('input[type=button]', {
                id: 'btn_login',
                value: 'Logg inn',
                class: 'db w-100',
                onclick: function() {
                    var param = {};
                    param.brukernavn = $('#brukernavn').val();
                    param.passord = $('#passord').val();

                    m.request({
                        method: 'post',
                        url: 'login',
                        params: param
                    }).then(function(result) {
                        window.location.reload()
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
