var select = {

    view: function(vnode) {
        var attrs = vnode.attrs;
        attrs.oncreate = select.config[config.select](attrs);

        return m('select', attrs);
    },

    config: {
        native: function(attrs) {

            return function(vnode) {
                $.each(attrs.options, function(idx, item) {
                    $(vnode.dom).append($('<option></option>')
                        .attr('value', item.value)
                        .text(item.text));
                });
                $(vnode.dom).val(attrs.value);
            }
        },

        selectize: function(attrs) {
            return function(el, init, ctx) {
                var selectize;

                if (!init) {
                    $(el).selectize({
                        options: attrs.options,
                        valueField: attrs.valueField ? attrs.valueField : 'value',
                        placeholder: attrs.placeholder,
                        // maxItems: attrs.multiple ? 3 : 1,
                        openOnFocus: false,
                        selectOnTab: true,
                        create: !attrs.create ? false : function(input) {
                            return {value: input, text: input};
                        },
                        load: !attrs.ajax ? null : function(query, callback) {
                            if (!query.length) return callback();
                            attrs.ajax.data.q = query;
                            $.ajax({
                                url: attrs.ajax.url,
                                type: attrs.ajax.type,
                                dataType: attrs.ajax.dataType,
                                data: attrs.ajax.data,
                                success: function(res) {
                                    callback(res);
                                }
                            })
                        }
                    });
                    // TODO: Why set width in this way?
                    var width = attrs.width ? attrs.width : '100%';
                    $(el).next().width(width);

                    // Select initial option
                    selectize = $(el)[0].selectize;
                    if (attrs.ajax) {
                        if (attrs.value === '' || attrs.value === null) {
                            // Removes all options from the control
                            selectize.clearOptions();
                        } else if (attrs.text) {
                            selectize.addOption({value: attrs.value, text:attrs.text});
                            selectize.setValue(attrs.value, true);
                        } else {
                            var data = attrs.ajax.data;
                            var condition = data.alias + '.' + data.nokkel[0] + ' = ' + "'" + attrs.value + "'";
                            data.betingelse = condition;
                            $.ajax({
                                url: attrs.ajax.url,
                                type: attrs.ajax.type,
                                dataType: attrs.ajax.dataType,
                                data: data,
                                success: function(res) {
                                    selectize.addOption({value: attrs.value, text: res[0].text});
                                    selectize.setValue(attrs.value, true);
                                }
                            })
                        }
                    } else {
                        selectize.setValue(attrs.value, true);
                    }

                }
                selectize = $(el).data('selectize');
                selectize.settings.placeholder = attrs.placeholder;
                selectize.updatePlaceholder();
            }
        },

        select2: function(attrs) {

            if (attrs.options) {
                attrs.options.unshift({value: '', text: ''});
            }

            var options = $.map(attrs.options, function(obj) {
                obj.id = obj.value;

                return obj;
            });

            return function(el, init, ctx) {

                if (!init) {
                    $(el).select2({
                        width: attrs.width ? attrs.width : '100%',
                        data: options,
                        placeholder: attrs.placeholder,
                        allowClear: attrs.clear ? attrs.clear : false,
                        ajax: !attrs.ajax ? null : {
                            url: attrs.ajax.url,
                            method: attrs.ajax.type,
                            data: function(params) {
                                attrs.ajax.data.q = params.term;

                                return attrs.ajax.data;
                            },
                            processResults: function(data) {
                                var items = $.map(data, function(obj) {
                                    obj.id = obj.value;

                                    return obj;
                                });

                                return {results: items};
                            }
                        }
                    });

                    if (attrs.ajax) {
                        if (attrs.value === '' || attrs.value === null) {
                            // TODO: clear options?
                        } else if (attrs.text) {
                            $(el).val(attrs.value).trigger('change.select2');
                        } else {
                            var data = attrs.ajax.data;
                            var condition = data.alias + '.' + data.nokkel[0] + ' = ' + "'" + attrs.value + "'";
                            data.betingelse = condition;
                            $.ajax({
                                url: attrs.ajax.url,
                                type: attrs.ajax.type,
                                dataType: attrs.ajax.dataType,
                                data: data,
                                success: function(res) {
                                    selectize.addOption({value: attrs.value, text: res[0].text});
                                    selectize.setValue(attrs.value, true);
                                }
                            })
                        }
                    }

                    if (attrs.value) {
                        $(el).val(attrs.value).trigger('change.select2');
                    }
                }
            }
        }
    }
}
