# Installasjon

Krever PHP 5.4 eller høyere.

Installeres ved å klone repositoriet.

## Oppretting av database

Det må det opprettes en database som URD bruker til å holde oversikt over andre databaser, bruker, rettigheter mm.

Da kjøres sql-filen `schemas/urd/sql/create_tables_mysql.sql`.
Hvis man ønsker å ha denne basen på en Oracle-installasjon, må man foreløpig selv migrere basen til Oracle.

## Konfigurasjon

Det følger med en default konfigurasjonsfil `app/config/config.default.php`. Denne skal ikke endres. Isteden må man opprette en lokal konfigurasjonsfil i `app/config/config.php`.

URD bruker Slim som PHP-rammeverk. Noen av config-opsjonene er følgelig også opsjoner til Slim (jf. https://www.slimframework.com/docs/v2/configuration/settings.html)

Kan sette følgende verdier:

| verdi | Forklaring |
| ----- | ---------- |
| debug | Hvis true brukes Slims innebyde error handler |
| single_sign_on | Bestemmer om det brukes oppslag mot ldap-server for å kunne logge på med samme brukernavn og passord som man logger på Windows-maskiner |
| ldap  | Konfigurasjon av ldap-server og oppsett |
| db    | Databasekonfigurering av urd-basen. Bruker pdo til oppkobling. `platform` kan være 'mysql', 'sqlite' eller 'oracle' |
| session_timeout | Hvor lang tid uten aktivitet (i minutter) det går får man må logge på igjen |
| mail | Konfigurering av epost-oppsett. Hvis `send_errors` er `true`, sendes feilmeldinger i systemet til den eller de angitt i `error_recipients` |
| default_roles | Hvis man logger på med Windows-passord, så har man ikke nødvendigvis noen bruker i systemet. Her kan man definere hvilke roller slike brukere skal være tilknyttet. Kan brukes til å la visse databaser være åpne for alle |

## Installere skjemaer

Hvis man vil installere skjemaer til URD, kan man gjøre det enten manuelt eller ved å opprette en fil `composer.local.json`.

Filen `composer.local.json` skal ha følgende struktur:

``` json
"repositories": [
    {
        "url": "(url til repositoriet til skjemaet)",
        "type": "vcs"
    }
],
"require": {
    "(navn på skjemaet)": "(versjon)"
}
```

Skjemaet vil da installeres når man kjører `composer install`, og oppdateres når man kjører `composer update`.

Hvis man vil installere et skjema manuelt, kan man klone repositoriet til dette skjemaet inn i egen mappe under `schemas/`. Navnet på mappen må være det samme som navnet på skjemaet.




