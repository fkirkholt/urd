
var jsoned = {

    view: function(vnode) {
        return m('div', vnode.attrs)
    },

    oncreate: function(vnode) {
        console.log('vnode', vnode)
        var options = {
            "mode": vnode.attrs.mode || "tree",
            "mainMenuBar": false,
            onChange: function() {
                var value = JSON.stringify(vnode.state.jsoned.get())
                vnode.attrs.onchange(value)
                // entry.update_field(value, vnode.attrs.name, vnode.attrs.rec);
            }

        }
        vnode.state.jsoned = new jsoneditor(vnode.dom, options)
        vnode.state.jsoned.set(vnode.attrs.value)
    }
}

var m = require('mithril')
var jsoneditor = require('jsoneditor')
var entry = require('./entry.js')

module.exports = jsoned;
