var m = require('mithril');
var login = require('./login')

var store = {
    base: {},
    user: {},
    schema: {
        config: {
            urd_structure: false,
            replace: false,
            threshold: 10,
            count_rows: false
        }
    },
    urd_base: $('#urd-base-name').data('value'),

    load_database: function(base_name, callback) {
        return m.request({
            method: "GET",
            url: "database",
            params: {base: base_name}
        }).then(function(result) {
            var data = result.data;
            store.base = data.base;
            store.user = data.user;
            store.branch = data.branch;

            if (data.config) {
                $.extend(store.schema.config, data.config);
            }

            if (typeof callback === 'function') {
                callback(data);
            }
        }).catch(function(e) {
            if (e.code === 401 || e.code === 404) {
                login.msg = e.response.detail
                login.error = true
                $('div.curtain').show();
                $('#login').show();
                $('#brukernavn').focus();
            } else {
                alert(e.response.detail)
            }
        });
    },

    set_cfg_value: function(tbl, attr, value) {
        if (ds.schema.config.tables === undefined) {
            ds.schema.config.tables = {};
        }

        if (ds.schema.config.tables[tbl.name] === undefined) {
            ds.schema.config.tables[tbl.name] = {};
        }

        ds.schema.config.tables[tbl.name][attr] = value;
    }
}

module.exports = store;
