window.m = require('mithril');
window.$ = require('jquery');
window.ds = require('./datastore.js');

var contents = require('./contents.js');
var header = require('./header.js');
var breadcrumb = require('./breadcrumb.js');
var filterpanel = require('./filterpanel.js');
var toolbar = require('./toolbar.js');
var home = require('./home.js');
var datapanel = require('./datapanel.js');
var export_dialog = require('./export.js');
var config = require('./config.js');
var login = require('./login.js');
var report = require('./report.js');
var grid = require('./grid.js');

var adresse_tilbakestilling = false;

m.route.prefix = "#";
m.route($('#main')[0], '/', {
    "/": home,
    "/:base": {
        onmatch: function(args, requestedPath) {
            var base_name = args.base;
            if (ds.table && ds.table.dirty) {
                if (!confirm('Du har ulagrede data. Vil du fortsette?')) {
                    m.route.set(grid.url);
                }
            }

            ds.type = 'contents';
            delete ds.table;
            ds.load_database(base_name);

            return contents;
        }
    },
    "/:base/:table": {
        onmatch: function(args, requestedPath) {
            if (ds.table && ds.table.dirty && grid.url !== requestedPath) {
                if (!confirm('Du har ulagrede data. Vil du fortsette?')) {
                    m.route.set(grid.url);
                } else {
                    return datapanel;
                }
            } else {
                return datapanel;
            }
        }
    },
    "/:base/reports/:report": report
});

window.onbeforeunload = function(event) {
    if (ds.table && ds.table.dirty) {
        event.returnValue = "Du har ulagrede endringer i listen din";
    }
};

// Ugly hack to work around https://github.com/MithrilJS/mithril.js/issues/1734
function getHashPath() {
	return location.hash.replace(/^#/, '')
}

var popstatePath;
window.addEventListener('popstate', function() {
	// This event should trigger before hashchange, but IE11 fails to trigger it
	// on back button click. We save the hash path to confirm that it happened.
	popstatePath = getHashPath()
}, false)

var hashchangeTimeoutRef;
window.addEventListener('hashchange', function() {
	// This event triggers after popstate, and is more reliable in IE11.

	// We cancel timeout in the rare case that another hash change happened in
	// the time frame when doing a double check.
	clearTimeout(hashchangeTimeoutRef)

	let hashPath = getHashPath()
	if (popstatePath != hashPath) {
		// The popstate event never happened. This should be IE11. We need to
		// force it.

		m.route.set(hashPath, undefined, {
			replace: true, // To let the browser navigate back
		})
	} else {
		// The popstate event triggered, and we should be good, except...

	}

	// Despite all our efforts, Mithril does not recognize the popstate event
	// occasionally. Need to double check it all went fine. And what's more, if
	// it goes fine, it will happen in the future – hence the timeout.

	// To reproduce in IE11, click cart then book then back then a different
	// book then cart.
	hashchangeTimeoutRef = setTimeout(function() {
		hashPath = getHashPath()
		if (m.route.get() != hashPath) {
			// Mithril failed to recognize the new path, so we need to force
			// it.

			m.route.set(hashPath, undefined, {
				replace: true, // To let the browser navigate back
			})
		}
	}, 100)
}, false)

var $header = $('#header');
m.mount($header[0], header);

var $filterpanel = $('#filterpanel');
m.mount($filterpanel[0], filterpanel);

var $export = $('#export-dialog');
m.mount($export[0], export_dialog);

var $config = $('#preferences');
m.mount($config[0], config);

var $login = $('#login');
m.mount($login[0], login);
