# Skjema

Et skjema definerer opp strukturen til en bestemt database, og kan også inneholde rapporter og handlinger spesifikt for databaser som tilhører dette skjemaet.

Man knytter en database til et skjema i tabellen `database_` i urd-basen. Dermed vet URD hvordan data fra denne databasen skal presenteres.

## Installasjon

For installering av skjema, se `installasjon.md`.

## Lage nytt skjema

Et URD-skjema trenger minimum to filer: `schema.json` og `composer.json`.

`schema.json` definerer opp databasestrukturen, og `composer.json` forteller at dette er et urd-skjema.

I tillegg kan det opprettes rapporter og handlinger. Det må da defineres opp en fil `routes.php` slik at URD vet hvordan det skal rutes til disse rapportene og handlingene, jf. under.

Et skjema kan opprettes direkte fra en database. Man kan vise alle databaser i admingrensesnitt, markere en, og velge handlingen "Oppdater skjema fra database".

Hvis databasen er definert på en spesiell måte, kan URD generere et skjema som man ikke behøver å redigere manuelt etterpå - all relevant info ligger i databasen, jf. [innsynsdatabaser](./innsynsdatabaser.md).

### schema.json

Denne filen definerer opp databasestrukturen til et skjema, samt hvordan dataene skal presenteres. Her følger en gjennomgang av oppbygningen av skjemaet.

- `tables`: Objekt som inneholder alle tabellene i basen
    - `<table_alias>`: Alias som man vil bruke om en tabell. Brukes til å referere til en tabell fra andre steder i skjemaet.
        - `name`: Tabellnavnet. Kan utelates dersom det er det samme som alias-et.
        - `label`: Hva man vil tabellen skal benevnes i brukergrensesnittet
        - `indexes`: Angir indekser til tabellen. Kan også til å bestemme hvilken info som skal presenters for fremmednøker. Hvis det for et fremmednøkkelfelt ikke angis hva som skal vises fra fremmednøkkelen, sjekkes indeksene her, og ser om det finnes en unik index som ikke er primærnøkkel. Kolonnen(e) fra denne brukes da som visning av fremmednøkkelen. Tanken bak dette er at det som skal identifisere en post i en tabell, bortsett fra primærnøkkelen, også bør være unik, f.eks. et navnefelt eller betegnelsesfelt.
            - `<alias>`: Alias for indeksen
                - `name`: Navn til indeksen
                - `unique`: Angir om dette er en unik indeks eller ei, `true/false`.
                - `primary`: Angir om dette er primær indeks (dvs. primærnøkkel), `true/false`
                - `columns`: Array med kolonnene indeksen består av.
        - `primary_key`: Primærnøkkel til tabellen. Her henvises det til `fields`.
        - `foreign_keys`: Fremmednøkler i tabellen.
	        - `<alias>`: Alias til nøkkelen. Brukes til referanse andre steder i json-fila.
		        - `name`: Navnet til fremmednøkkelen brukt i databasen.
		        - `local`: Array med fremmednøkkelfelter i gjeldende tabell. Henviser til `fields`.
		        - `schema`: Hvilket skjema referansen går til.
		        - `table`: Hvilken tabell i skjemaet referansen går til.
		        - `foreign`: Array med felter i tabellen det refereres til. Henviser til `fields` i tabellen til det aktuelle skjemaet.
        - `filter`: Hvis tabellen skal filtreres, settes en sql where-setning her.
        - `description`: Beskrivelse av tabellen
        - `type`: Hva slags type tabell dette er. Kan ha tre verdier: `data`, `reference` og `cross-reference`.
        - `fields`: Objekt som inneholder alle feltene i tabellen.
            - `<alias>`: Alias man vil bruke om feltet
                - `name`: Kolonnenavnet i databasen. Kan sløyfes hvis samme som alias.
                - `label`: Hvilken ledetekst man vil bruke for feltet i brukergrensesnittet.
                - `element`: Hvilken type input-element feltet skal representeres av i redigeringsbildet i brukergrensesnittet. Tillatte verdier: `input[type=text]`, `input[type=checkbox]`, `input[type=date]`, `select`, `textarea`.
                - `description`: Beskrivelse av feltet.
                - `view`: Dersom feltet representerer en fremmednøkkel, og man vil vise et felt fra tabellen som refereres, kan man legge inn dette feltet her. Det joines med alias til refererende felt som alias for den joinede tabellen, så feltet angis som `<alias>.<feltnavn>`. Kan sløyfes hvis tabellen det refereres til har en unik index forskjellig fra primærnøkkelen.
        - `grid`: Brukes til å definere opp hvordan tabellen som viser dataene skal se ut. Kan utelates - i så fall vil de fem første feltene i `fields` brukes.
            - `columns`: Definerer opp hvilke kolonner som skal være i tabellen. Kan enten angis med en array med felt-alias, eller et objekt med ledetekst som nøkkel og feltalias som verdi.
            - `sort_clumns`: Array som anger sorting av tabellen.
        - `form`: Brukes til å definere hvordan en post skal vises fram i visningsbilde og registreringsbilde. Kan utelates. I så fall vil det brukes alle feltene definert i `fields`, og alle relasjoner definert i `relations`.
            - `items`: Hvilke elementer som skal vises fram. Kan angis som array med felt-alias eller relasjoner. Det refereres til relasjoner med `relations.<alias>` (jf. under). Kan også angis som objekt med ledetekst og alias. Hvis man angir som objekt, kan man også legge inn overskrifter for å gruppere felter på. I så fall blir nøkkelen lik overskriften, og verdien blir et objekt som man igjen legger inn `items` i. TODO: eksempel.
            - TODO: mer
        - `relations`: Oversikt over alle har-mange-relasjoner for tabellen.
            - `<alias>`: Alias til denne relasjonen.
                - `label`: Hva relasjonen skal kalles i brukergrensesnittet
                - `table`: Hvilken tabell relasjonen kommer fra.
                - `foreing_key`: Referanse til fremmednøkkelen.
        - `actions`: Definerer handlinger for tabellen. Disse kan nås via tannhjul-ikonet i verktøylinje, eller knyttes til knapper i registreringsskjemaet.
            - `<alias>`: Alias til handlingen.
                - `label`: Tekst som vises for handlingen i grensesnittet.
                - `icon`: Evt. ikon man vil knytte til handlingen. Her kan brukes ikoner fra Fontawesome
                - `url`: Adressen til handlingen. Er definer i `routes.php`.
                - `communication`: Angir hvordan handlingen kalles. Tillatte verdier er `ajax`, `submit`, `dialog` og `download`. TODO: beskriv hva disse gjør.
                - `disabled`: Kan settes til `true/false`, eller kan være et sql-uttrykk. Hvis det er et sql-uttrykk, hentes verdien ut sammen med kolonner for aktuell tabell. Slik kan man vite om handlingen skal være aktiv for hver enkelt post.
- `reports`: Oversikt over alle rapporter i skjemaet. TODO: mer
    - `<alias>`: Alias til rapporten.
        - `url`: Adresse til rapporten. Definert i `routes.php`
        - `label`: Benevnelse på rapporten i innholdsfortegnelsen.

- `contents`: Definerer innholds-fortegnelsen til databaser basert på skjemaet. Her kan man ha overskrifter på så mange nivåer man ønsker, og lenke til tabeller og rapporter som skal vises.
    - `<overskrift>`: Overskriften som skal vises.
        - `items`: Hvilke tabeller eller rapporter man vil vise under overskriften. Kan enten være et objekt med nye overskrifter, eller en array med referanse til tabeller og rapporter. Det refereres da ved `tables.<alias>` eller `reports.<alias>`.


### composer.json

Hvis skjemaet skal kunne installeres via en fil `composer.local.json` (se installasjon.md), behøves denne filen.

Eksempel på innhold:

```
{
	"name": "fkirkholt/test",
	"license": "MIT",
	"type": "urd-schema"
}
```

### routes.php

Her defineres ruter til rapporter eller handlinger, som er definert i `schema.json`. Se dokumentasjon for Slim v2 hvordan ruter defineres.

Rutene angir da hvor rapporter og handlinger befinner seg i kodebasen til skjemaet. URD legger følgelig ingen føringer på hvor koden til rapportene og handlingene skal legges.


