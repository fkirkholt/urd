var m = require('mithril');
var $ = require('jquery');
var moment = require('moment');
var Pikaday = require('pikaday');

var datepicker = {

    view: function(vnode) {
        return m('input', vnode.attrs);
    },

    oncreate: function(vnode) {
        if (vnode.attrs.readOnly) return;

        vnode.state.picker = new Pikaday({
            field: vnode.dom,
            format: 'YYYY-MM-DD'
        });
    },

    onupdate: function(vnode) {
        if (!vnode.state.picker) return;
        // Open the picker with correct date selected.
        // Needed when navigating between already loaded records.

        var date = vnode.attrs.value ? moment(vnode.attrs.value, 'YYYY-MM-DD').toDate() : null;

        if (date) {
            vnode.state.picker.setDate(date);
        } else {
            vnode.state.picker.gotoToday();
        }
    }
};

module.exports = datepicker;
