var config = require('./config.js');
var breadcrumb = require('./breadcrumb.js');

var header = {

    set_admin: function(value) {
        config.admin = value;
        if (value == 0) config.show_table = true;
        m.redraw();
    },

    set_view: function(value) {
        config.edit_mode = value;
    },

    view: function(vnode) {

        if (!ds.user) return;

        return [
            m(breadcrumb),
            m('div#menu', {
                class: 'fr relative mt2'
            }, [
                m('div', {
                    class: 'fa fa-cog ml3 mr3',
                    onclick: function() {
                        $('div#menu ul').toggle();
                    }
                }),
                m('ul', {
                    class: 'fixed right-0 list dn pa1 shadow-5 pointer bg-white black mt0',
                    onclick: function() {
                        $('div#menu ul').hide();
                    }
                }, [
                    m('li', {
                        onclick: function() {
                            $('#preferences').show();
                            $('#preferences [name=autosave]').prop('checked', config.autosave);
                            $('div.curtain').show();
                        }
                    }, 'Innstillinger'),
                    m('li', {
                        class: 'dn',
                        onclick: function() {
                            if ($('#keyboard-shortcuts').css('visibility') == 'visible') {
                                $('#keyboard-shortcuts').css('visibility', 'hidden');
                                $(this).html('Vis hurtigtaster');
                            }
                            else {
                                $('#keyboard-shortcuts').css('visibility', 'visible');
                                $(this).html('Skjul hurtigtaster');
                            }
                        }
                    }, 'Hurtigtaster'),
                    m('li', {
                        class: '',
                        onclick: function() {
                            if ($('div.print-view').is(':visible')) {
                                $('div.print-view').hide();
                                $('#header').show();
                                $('#page-container').show();
                                $('#meny option[value="utskrift"]').html('Utskriftsvisning');
                            }
                            else {
                                var adresse = 'printable_table';
                                $('#print-view .content').load(adresse);
                                $('#header').hide();
                                $('#page-container').hide();
                                $('#print-view').show();
                                $('#meny option[value="utskrift"]').html('Lukk utskriftsvisning');
                            }
                        }
                    }, 'Utskrift'),
                    m('li', {
                        class: 'dn',
                        onclick: function() {

                        }
                    }, 'Test'),
                    m('li', {
                        class: 'dn',
                        onclick: function() {
                            if ($('#test_window').is(':visible')) {
                                $('#test_window').hide();
                            } else {
                                var adr = 'templates/urd/handlinger/test/test.htm';
                                $('#action-dialog').load(adr+'?version=1').show();
                            }
                        }
                    }, 'Test'),
                    m('li', {
                        onclick: function() {
                            $.getJSON('logout', function() {
                                window.location.reload();
                            });
                        }
                    }, 'Logg ut'),
                ])
            ]),
            m('div#user', {class: 'fr mr1 mt2'}, ds.user.name),
            (!ds.user.admin) ? null : m('div#admin', {class: 'fr mr3 mt2'}, [
                m('input#admin_checkbox', {
                    class: 'mr1',
                    type: "checkbox",
                    value: 1,
                    checked: config.admin,
                    onclick: m.withAttr("checked", this.set_admin)
                }),
                m('label', 'Admingrensesnitt')
            ]),
            m('label', {
                class: 'fr mr3 mt2'
            }, [
                m('input#view_checkbox', {
                    class: 'mr1',
                    type: 'checkbox',
                    value: 1,
                    checked: config.edit_mode,
                    onclick: m.withAttr('checked', this.set_view)
                })
            ], 'Redigeringsmodus')
        ]
    }
}

module.exports = header;
