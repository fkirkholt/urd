var m = require('mithril');
var ds = require('./datastore.js');

var report = {

    get: function(url) {
        $.ajax({
            method: 'get',
            // dataType: 'html',
            url: url
        }).then(function(data) {
            $('div#report').html(data);

        });
    },

    oncreate: function(vnode) {
        var base_name = m.route.param('base');
        var report_name = m.route.param('report');

        ds.type = 'report';
        ds.report = 'Laster ...';

        ds.load_database(base_name, function(data) {
            var rapport = ds.base.reports[report_name];
            var query = window.location.href.split('?')[1];
            var rapport_innh = report.get(rapport.url + '?' + query);
        });
    },

    view: function(vnode) {
        return m('div#report');
    }
}

module.exports = report;
