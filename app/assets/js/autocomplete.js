var m = require('mithril');
var $ = require('jquery');
var _last = require('lodash/last');
var _find = require('lodash/find');
require('jquery-ui/ui/widgets/autocomplete');


var autocomplete = {

    view: function(vnode) {
        // Avoid onchange to attach to input
        // vnode.attrs.change = vnode.attrs.onchange;
        // delete vnode.attrs.onchange;

        return m('input', vnode.attrs);
    },

    oncreate: function(vnode) {

        var attrs = vnode.attrs;
        var options = [];

        function split(val) {
            return val.split( /;\s*/  );
        }
        function last(term) {
            return split(term).pop();
        }

        $(vnode.dom).autocomplete({
            source: function(request, response) {
                if (attrs.options) {
                    response($.ui.autocomplete.filter(
                        attrs.options, last(request.term)
                    ));
                } else {
                    attrs.ajax.data.q = last(request.term);
                    $.getJSON(attrs.ajax.url, attrs.ajax.data, response);
                }
            },
            // Must have 0 for minLength to show menu on click and arrow down
            minLength: 0,
            focus: function() {
                // prevent value inserted on focus
                return false;
            },
            select: function(event, ui) {
                if (vnode.attrs.multiple) {
                    var terms = split(this.value);
                    // remove the current input
                    terms.pop();
                    // add the selected item
                    terms.push(ui.item.label);
                    if (!_find(options, {value: ui.item.value})) {
                        options.push({value: ui.item.value, label: ui.item.label});
                    }
                    // add placeholder to get the comma-and-space at the end
                    terms.push("");
                    this.value = terms.join( "; "  );
                    vnode.attrs.item.text = this.value;
                } else {
                    this.value = ui.item.label;
                    $(this).data('value', ui.item.value);
                    $(this).data('coltext', ui.item.coltext);
                    $(this).trigger('change');
                }

                return false;
            },
            change: function(event, ui) {
                if (!vnode.attrs.multiple) return;
                var terms = split(this.value);
                var chosen = options.filter(function(option) {
                    return terms.indexOf(option.label) !== -1;
                });
                var values = chosen.map(function(option) {
                    return option.value;
                });
                vnode.attrs.item.value = values;
                terms = chosen.map(function(option) {
                    return option.label;
                });
                vnode.attrs.item.text = terms.join('; ');
                $(vnode.dom).val(terms.join('; '));
                $(vnode.dom).data('value', values);
            },
            response: function(event, ui) {
                if (ui.content.length === 1 && !_find(options, {value: ui.content[0].value})) {
                    options.push(ui.content[0]);
                }
            }
        });

        if (vnode.attrs.item.value === null) {
            $(vnode.dom).val('')
        } else if (vnode.attrs.item.text) {
            $(vnode.dom).val(vnode.attrs.item.text)
        } else {
            $(vnode.dom).val(vnode.attrs.item.value)
        }

        // The change method does not fire if we don't change item.text
        $(vnode.dom).on('input', function() {

            if ($(this).val() == '') {
                $(this).data('value', null);
                $(this).trigger('change');
            }

            // Sets value for self referencing fields
            /*
            if (!vnode.attrs.item.foreign_key) {
                vnode.attrs.item.value = $(this).val();
            }
            */
        });
    },

    onupdate: function(vnode) {
        var attrs = vnode.attrs;
        var text;
        var items;

        if (vnode.attrs.item.value === null) {
            $(vnode.dom).val('');
        } else if (vnode.attrs.item.text) {
            if (typeof vnode.attrs.item.text == 'string') {
                items = vnode.attrs.item.text.split(/;\s*/);
                if (items[items.length - 1] === '') items.pop();
                text = items.join('; ');
            } else {
                text = vnode.attrs.item.text;
            }
            if (vnode.attrs.multiple && items.length) text += '; ';
            $(vnode.dom).val(text);
        } else if (attrs.item.value && attrs.ajax) {
            var data = JSON.parse(JSON.stringify(attrs.ajax.data));
            var value_string = vnode.attrs.multiple ? attrs.item.value.join("','") : attrs.item.value;

            key = JSON.parse(data.key)

            data.condition = data.alias + '.' + _last(key) + ' IN ' + "('" + value_string + "')";

            $.ajax({
                url: attrs.ajax.url,
                type: 'GET',
                dataType: 'JSON',
                data: data,
                success: function(res) {
                    var labels = res.map(function(rec) {
                        return rec.label;
                    });
                    $(vnode.dom).data('value', attrs.item.value);
                    $(vnode.dom).val(labels.join('; '));
                    $(vnode.dom).data('coltext', res[0].coltext);
                    $(vnode.dom).trigger('change');
                    m.redraw();
                }
            })
        } else if (attrs.item.value) {
            var labels = [];
            $.each(attrs.item.value, function(i, value) {
                var label = _find(attrs.options, {value: value}).label;
                labels.push(label);
            });
            text = labels.join('; ');
            if (vnode.attrs.multiple) text += '; ';
            $(vnode.dom).val(text);
            $(vnode.dom).trigger('change');
        }
    }

};

module.exports = autocomplete;
