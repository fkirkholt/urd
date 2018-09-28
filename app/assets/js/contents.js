var m = require('mithril');
var $ = require('jquery');
var _assign = require('lodash/assign');
var _get = require('lodash/get')
var _isArray = require('lodash/isArray');
var ds = require('./datastore.js');
var Stream = require('mithril/stream');
var config = require('./config.js');

contents = {

    oninit: function(vnode) {

        $('#right_content').hide();

        var report = m.route.param('report');
        var base_name = m.route.param('base');

        ds.table = null;
        ds.type = 'contents';

        ds.load_database(base_name);
    },

    check_display: function(item) {
        if (typeof item == 'object') {
            Object.keys(item.items).map(function(label) {
                var subitem = item.items[label];
                if (typeof subitem == 'object') {
                    subitem.display = Stream('none');
                    if (item.display() == 'none') {
                        item.display = subitem.display
                    }
                } else {
                    var object = _get(ds.base, subitem);
                    if (object === undefined) return;
                    if (object.type != 'reference' || config.admin == true) {
                        item.display('block');
                    }
                }
                return contents.check_display(subitem);
            })
        }
    },

    draw_item: function(label, item, level) {
        if (typeof item == 'object') {
            return m('.module', {
                class: item.class_module,
                style: 'display:' + item.display
            }, [
                m('.label', {class: item.class_label || 'pb2 pt2 f'+level}, label),
                m('.content', {class: item.class_content}, [
                    Object.keys(item.items).map(function(label) {
                        var subitem = item.items[label];
                        if (_isArray(item.items)) {
                            var obj = _get(ds.base, subitem);
                            if (obj === undefined) return;
                            label = obj.label;
                        }
                        return contents.draw_item(label, subitem, level+1);
                    })
                ])
            ]);
        } else {
            var object = _get(ds.base, item);
            var display = object.type && (object.type.indexOf('reference') !== -1) && !config.admin
                    ? 'none'
                    : 'block';
            return m('div', [
                m('a', {
                    style: 'display:' + display,
                    href: '#/' + ds.base.name + '/' + item.replace('.', '/')
                }, label)
            ]);
        }
    },

    view: function() {
        if ((!ds.base.contents && !ds.base.tables) || ds.base.name !== m.route.param('base')) return;

        // if (!ds && !ds.base.contents) return;

        return m('.contents', [
            ds.base.contents && Object.keys(ds.base.contents).length
                ? Object.keys(ds.base.contents).map(function(label) {
                    var item = ds.base.contents[label];
                    item.display = Stream('none');
                    contents.check_display(item);
                    var retur = contents.draw_item(label, item, 3);
                    return retur;
                })
                : Object.keys(ds.base.tables).map(function(name) {
                    var table = ds.base.tables[name];
                    return contents.draw_item(table.label, 'tables.'+name, 3);
                })
        ]);

    }
}

module.exports = contents;
