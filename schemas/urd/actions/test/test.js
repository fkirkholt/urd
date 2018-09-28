var urd_base;
console.clear();

// Init events
$('#test_database').change(function() {
    var db_name = $(this).val();
    console.group('Base: ' + db_name);

    // Henter liste over tabeller
    $.getJSON("skript/hent_tabell_liste.php", {base: db_name}, function(data) {
        $.each(data.poster, function(name, tbl) {
            var tbl_name = tbl.postdata.tabell;
            console.group('Tabell: ' + tbl_name);
            var param = {};
            param.base = db_name;
            param.tabell = tbl_name;
            $.ajax({
                type: 'GET',
                url: 'skript/hent_tabell.php',
                data: param,
                // dataType: 'json',
                async: false
            }).done(function(tbl) {
                if (typeof tbl != 'object') {
                    console.error('Feil med henting av tabell');
                } else {
                    console.log('Hentet tabell: ' + tbl.ant_poster + ' poster');

                    var first_rec;

                    // Henter postdata
                    var param = {};
                    param.base = db_name;
                    param.tabell = tbl_name;
                    param.prim_nokler = JSON.stringify(tbl.poster[0].primary_key);
                    param.fra_base = tbl.fra_base;
                    $.ajax({
                        type: 'GET',
                        url: 'skript/hent_postdata.php',
                        data: param,
                        // dataType: 'json',
                        async: false
                    }).done(function(rec) {
                        if (typeof rec != 'object') {
                            console.error('Feil med henting av postdata');
                        } else if (rec.poster.length == 1) {
                            first_rec = rec.poster[0];
                            console.log('Hentet postdata');
                        }
                    });

                    // Henter postrelasjoner
                    param = {};
                    param.base = db_name;
                    param.tabell = tbl_name;
                    var betingelse = {};
                    // TODO: Må forenkle, og sende primærnøkler
                    //       liksom for hent_postdata.php
                    $.each(tbl.prim_nokkel, function(i, felt) {
                        betingelse[felt] = {};
                        betingelse[felt].operator_type = '=';
                        betingelse[felt].verdi = tbl.poster[0].postdata[felt];
                    });
                    param.prim_nokler = JSON.stringify(betingelse);
                    $.ajax({
                        type: 'GET',
                        url: 'skript/hent_postrelasjoner.php',
                        data: param,
                        async: false
                    }).done(function(relations) {
                        if ($.inArray(typeof relations, ['array', 'object']) == -1) {
                            console.error('Kunne ikke hente relasjoner');
                        } else {
                            console.log('Hentet ' + relations.length + ' relasjoner');
                        }
                    });

                    // Lagrer endringer
                    console.log(first_rec);
                    param = {};
                    param.base = db_name;
                    param.tabell = tbl_name;
                    param.felter = JSON.stringify(tbl.felter);
                    param.prim_nokkel = JSON.stringify(tbl.prim_nokkel);
                    // TODO: Legg inn param.poster
                    var changed = null;
                    $.each(tbl.felter, function(field_name, field) {
                        if (field.datatype == 'string') {
                            first_rec.postdata_ny[field_name] = 'test';
                            changed = field_name;
                            return;
                        }
                    });
                    if (changed) {
                        param.poster = JSON.stringify([first_rec]);
                        $.ajax({
                            type: 'POST',
                            url: 'skript/lagre_ds.php',
                            data: param,
                            async: false
                        }).done(function(res) {
                            if (typeof res == 'object' && res.success) {
                                console.log(res);
                                console.log('Lagring lyktes');
                            } else {
                                console.error('Lagring feilet');
                            }
                        });
                        // Tilbakestiller til opprinnelig verdi
                        if (changed) {
                            first_rec.postdata_ny[changed] = first_rec.postdata[changed];
                        }
                        param.poster = JSON.stringify([first_rec]);
                        $.ajax({
                            type: 'POST',
                            url: 'skript/lagre_ds.php',
                            data: param,
                            async: false
                        }).done(function(res) {
                            if (typeof res == 'object' && res.success) {
                                console.log('Tilbakestilling lyktes');
                            } else {
                                console.error('Tilbakestilling feilet');
                            }
                        });
                    }
                }
                console.groupEnd();
            });
        });
        console.groupEnd();
    });
});


$.getJSON('skript/hent_urd_base.php', function(data) {
    urd_base = data.urd_base;

    var param = {};
    param.base = urd_base;
    param.tabell = 'database_';
    param.limit = 50;
    param.offset = 0;
    $.getJSON("skript/hent_tabell.php", param, function(data) {
        var options = [];
        options.push('<option value="">&nbsp;</option>');
        $.each(data.poster, function(i, db) {
            var option = '<option value="'+db.postdata.databasenavn+'">';
            option += db.postdata.betegnelse+'</option>';
            options.push(option);
        });
        $('#test_database').html(options.join(''));
    });
});
