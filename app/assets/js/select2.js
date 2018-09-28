var select2 = {
    // returns a select box
    view: function(ctrl, attrs) {
        var selected_id = attrs.value;
        return m("select", {
            name: attrs.name,
            style: attrs.style,
            oncreate: select2.config(attrs)
        }, [
            m('option', {value: ''}, m.trust('&nbsp;')),
            m('option', {value: attrs.value, selected: "selected"}, attrs.text),
        ]);
    },

    config: function(ctrl) {
        return function(vnode) {
            var $el = $(vnode.dom);
            // TODO: Seems we need to initialize everytime
            //       to be able to change value
            if (!false) {
                $el.select2({
                    ajax: ctrl.ajax,
                    width: '100%',
                    data: ctrl.data,
                    tags: ctrl.tags,
                    minimumInputLength: ctrl.minimumInputLength || null
                }).on("change", function(event) {
                    var id = $el.val();
                    // set the value to the selected option
                    $.map(ctrl.data, function(d, id) {
                        if (d.id == id) {
                            ctrl.value = id;
                        }
                    });

                    if (typeof ctrl.onchange == "function") {
                        ctrl.onchange(event);
                    }
                });
                ctx.onunload = function() {
                    $el.select2('destroy');
                };
            }
            $el.val(ctrl.value);
        };
    }
};

