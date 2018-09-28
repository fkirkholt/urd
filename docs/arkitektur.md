# Arkitektur

URD er kodet i php, javascript, html og css.

Den støtter databasene MySQL, Oracle og SQLite.

URD har en egen database som holder styr på brukere og deres rettigheter, samt hvilke databaser som URD skal ha tilgang til.

Hver database knyttes til et såkalt "skjema". Et skjema består i sin minste form av en json-fil som beskriver strukturen til databasen (se schema.md). Men den kan også inneholde rapporter og spesifikke handlinger som kan utføres mot basen.

Se "skjema.md" for nærmere beskrivelse av urd-skjemaer.

Løsningen bruker Webpack til å administrere js-filer. URD sine egne js-filer ligger i `app/assets/js`, og bundles sammen med js-biblioteker i en `bundle.js`-fil.

Man kjører `npm start` for å bundle filene.

Det brukes js-rammeverket [Mithril](https://mithril.js.org/) for å lage applikasjonen som en såkalt "single page application", og det brukes css-rammeverket [Tachyons](http://tachyons.io/).

På PHP-siden brukes Slim v2 som rammeverk, og Dibi til spørringer mot databasen.