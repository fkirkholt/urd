var m = require('mithril');

var store = {
    base: {},
    schema: {
        config: {
            replace: false,
            threshold: 0
        }
    },
    urd_base: $('#urd-base-name').data('value'),
    load_database: function(base_name, callback) {
        return m.request({
            method: "GET",
            url: "database",
            data: {base: base_name}
        }).then(function(result) {
            var data = result.data;
            store.base = data.base;
            store.user = data.user;

            if (typeof callback === 'function') {
                callback(data);
            }
        }).catch(function(e) {
            if (e.message === 'login') {
                $('div.curtain').show();
                $('#login').show();
                $('#brukernavn').focus();
            } else {
                alert(e.message);
            }
        });
    }
}

module.exports = store;
