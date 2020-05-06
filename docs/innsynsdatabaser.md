# Innsynsdatabaser

URD har mulighet til å vise fram data fra databaser definert på en spesiell måte, uten at man behøver å registrere ekstra metadata for hvordan framvisningen skal være. Fordelen med dette er at man kan definere i ren SQL hvordan en database vises fram. Dermed har man et format som kan leses og brukes mange år fram i tid, og på mange måter er uavhengig av spesielle systemer for framvisning.

Tanken har vært å definere opp databasestrukturen på mest mulig intuitiv måte, og bruke teknikker som allerede er ganske utbredt i databasedesign. Man skal da få en godt strukturert database, som samtidig er selvdokumenterende i forhold til hvordan den skal vises fram.

Her gjennomgås hvordan slike databaser må defineres for at URD skal kunne vise dem fram.

## Tabellnavn

Tabellnavnene brukes direkte som ledetekst i grensesnittet i URD. Man må derfor navngi dem slik man ønsker at navnene skal vises i URD.

For å skille ord, brukes understrekning (`_`). F.eks. kan man bruke navnet `administrativ_inndeling` og få opp visningsnavnet "Administrativ inndeling" i grensesnittet i URD.

Tabellnavnet har også betydning for hvordan URD tolker en tabell. Her gjennomgås alle skrivemåter som har betydning for framvisning.

### Oppslagstabeller/referansetabeller

Oppslagstabeller, dvs. tabeller som representerer grunndata, og består av et visst antall mulige verdier, er tabeller man oftest ikke vil skal vises i en innholdsoversikt over de viktigste tabellene. Man kan angi at en tabell er en oppslagstabell eller en "referansetabell" ved å sette prefixet "ref_" foran, eller postfix "_ref" bakerst.

Hvis man vil legge en referansetabell til en spesifikk modul, er det greit å bruke postfix, da man i slike tilfeller må bruke prefix til å angi modul (jf. under)

F.eks. kan man navngi tabell for sakstyper i en noark-base som "ref_sakstype" eller "sakstype_ref"

### Gruppering av tabeller i moduler

Man kan gruppere tabeller sammen ved å gi dem samme prefix. En slik gruppering gjenspeiler måten man ofte vil gjøre det på når man designer databaser.

Tabeller med samme prefix opptrer under samme overskrift i innholdsfortegnelsen. Overskriften er utledet fra prefixet, enten ved at den er det samme som prefixet, eller at prefixet er angitt som term i tabellen "meta_terminology", og gitt en ledetekst (label) der. Da vises denne ledeteksten som overskrift. (jf. under om tabell for terminologi).

### Uselvstendige tabeller

Man kan angi at en tabell er underordnet en annen tabell, ved å sette et prefix lik tabellnavnet til hovedtabellen. Dette fører til at den underordnede tabellen ikke vises i innholds-listen i URD. F.eks. vil tabellen `person_adresse` være underordnet `person`-tabellen, og dermed vises den ikke på innholds-oversikten. Tanken er at slike tabeller kun er relevante i forbindelse med den overordnede tabellen, og det er derfor uaktuelt å vise dem som selvstendige tabeller man kan søke i.

Dette vil ofte gjelde mange tabeller i en større database - også kryssreferanse-tabeller. Det er også i tråd med ofte brukt databasedesign å angi navn på en kryssreferansetabell som en kombinasjon av tabellnavnene til de to tabellene den knytter sammen.

## Kolonnenavn

På samme måte som tabellnavn, brukes kolonnenavnene direkte til ledetekster. Man kan her også skille ord vha. understrek (`_`).

Kolonner som starter med understrek, behandles som usynlige/skjulte, dvs. de vises ikke i tabellen eller i postvisningen til høyre.

### Gruppering av kolonner

Kolonner kan grupperes vha. prefix (liksom tabeller). Felter med samme prefix havner under samme overskrift (som kan lukkes og ekspanderes i grensesnittet).

Eks: Hvis man har to kolonner `dato_fra` og `dato_til`, vises de slik (skjematisk) i URD:

**Dato:**
  Fra: 14.01.2001
  Til: 31.12.2008

Overskriften utledes fra prefixet, enten ved at prefixet vises som overskrift, eller at prefixet er angitt i tabellen `meta_terminology`. Da vises som overskrift det som er satt som `label` der.

## Indekser

Indekser brukes til å angi hvilke kolonner som skal vises, sortering, visningsverdi til felter som representerer fremmednøkler, visning av har-mange-relasjoner, samt angivelse av felter som representerer filbaner.

### `<tabellnavn>_grid_idx`

I visningen i URD får man til venstre opp en tabell - en grid - hvor man kan velge poster fra, og få opp en detaljvisning til høyre i bildet.

Hvilke kolonner som skal vises i denne tabellen, bestemmes av indexen `<tabellnavn>_grid_idx`. Man må altså opprette en index med et slikt navn, og angi kolonnene i den rekkefølgen man vil ha dem i tabellen.

### `<tabellnavn>_sort_idx`

For å angi standardsortering i en tabell når man åpner den i URD, kan man angi indexen `<tabellnavn>_sort_idx`, med de kolonnene man vil sortere på. Rekkefølgen på kolonnene i indexen bestemmer rekkefølgen på sorteringen.

Det støttes ikke avtakende (descending) sortering ennå, men det er planer om å få det til å virke også. Noen databasemotorer støtter jo å angi `asc` og `desc` for index-kolonner.

Denne indexen brukes også til å angi hvilke kolonner man ser som visningsverdi for fremmednøkkel-felter. Det er valgt gjort slik fordi det i de aller fleste tilfeller vil være samsvar mellom de verdiene man ønsker å se fra en tabell og de kolonnene man ønsker å sortere tabellen etter. Visningsverdien skal jo identifisere en post i tabellen, og som standardsortering vil man som regel ha kolonner som identifiserer en post.

Eksempel: Hvis man har referanse til saksansvarlig på en sak, vil man som regel se navnet på saksansvarlig. Og når man ser på persontabellen, vil man som regel ha standardsortering etter navn.

### `<tabellnavn>_file_path_idx`

Man kan angi at felter representerer filbaner ved å opprette en index med navn `<tabellnavn>_file_path_idx`. Da kan man også sette sammen filbaner vha. flere felter. F.eks. kan ett felt angi mappe, og ett kan angi filnavn. URD bygger opp filbanen ved å sette inn en slash - `/` - mellom verdiene angitt av de ulike kolonnene i indeksen.

Det støttes både absolutte og relative filbaner. URD detekterer selv om en filbane er absolutt eller relativ. Hvis man angir relativ filbane, må man definere `fileroot` i config-filen. Da bygges filbanen opp på følgende måte: `<fileroot>/<databasenavn>/<relativ_filbane>`. Man må altså ha en mappe for hver database under filrot.

### Indeks for relasjoner (har-mange-relasjoner)

Relasjoner angis i en relasjonsdatabase med foreign keys (jf. under). Men når man står på en post i en tabell, og skal finne alle poster i en annen tabell som peker på denne posten (f.eks. alle dokumenter tilhørende en sak), er det en fordel å ha indeks på fremmednøkkel-kolonnen(e) i den refererende tabellen. Indeksen brukes altså til å finne alle disse postene. Derfor krever URD at fremmednøkler er knytta til en indeks, hvis relasjoner skal vises andre veien (fra referert tabell).

Ettersom indeksen representerer denne har-mange-relasjonen, brukes også navnet på indeksen til å lage overskrift for relasjonen.

Ofte vil en relasjon kalles det samme som tabellen til de postene som refererer til aktuell post. Hvis man f.eks. står på en sak, vil man nok at journalpostene skal refereres til som nettopp "Journalposter". Slik er det ofte tabellnavnet til refererende tabell man vil vise som overskrift for relasjonen.

Man bruker da navnet på indeksen til å bestemme hva som skal vises som overskrift/ledetekst. Dermed må indeksene bygges opp etter et spesielt mønster. Det er forsøkt å bygge dette opp slik at man i stor grad kan bruke mønstre som allerede er utbredt for navngivning av indekser.

En veldig utbredt måte å angi indekser på er å bruke mønsteret `<prefix>_<tabellnavn>_<kolonnenavn>`, hvor `<prefix>` betegner typen indeks. Foreløpig gjenkjenner URD kun `idx` som prefiks for indekstype. Så da kan man f.eks. lage indeksen `idx_journalpost_sak` som kan brukes til å finne alle journalposter tilhørende en sak.

For å lage ledeteksten/overskriften til alle journalpostene når man står på en sak, kuttes prefikset og kolonnenavnet, og man står igjen med "Journalpost" som ledetekst, som altså er det vi vil ha.

Men det er ikke alltid så enkelt. En bruker kan f.eks. ha flere relasjoner til saker. Han/hun kan være saksansvarlig for en rekke saker, men også registrert som den som sist har gjort endringer på noen saker.

Når man står på en person, vil man derfor kunne ha to relasjoner til sakstabellen, hvor den ene betegner de sakene vedkommende har saksansvar for, og den andre betegner de sakene vedkommende sist har oppdatert. Begge disse relasjonene kan ikke kalles "Saker".

Vi kan navngi indeksene på følgende måte for å få de ledetekstene vi ønsker:
- `idx_sak_saksansvar`
- `idx_sak_oppdatert_av`

Her har vi navngitt indeksene etter standarden referert til ovenfor. Når man står på en bruker, vil disse relasjonene framstå som:
- Sak (saksansvar)
- Sak (oppdatert av)

Det URD gjør her, er å legge kolonnenavnet fra sakstabellen i parentes etter tabellnavnet. Dermed ser vi hvilken relasjon vi har med å gjøre.

Regelen er slik at dersom kolonnenavnet er det samme som navnet på den refererte tabellen, så vises det ikke i ledeteksten til relasjonen, men dersom det ikke samsvarer med navn på referert tabell, vises det i parentes.

URD gjenkjenner følgende mønster på index-navn som betegner har-mange-relasjoner:
`<prefix>_<tabellnavn>_<kolonnenavn>_<postfix>`

`<prefix>` er valgritt. Det kan være enten `idx` eller `fk`. Førstnevnte er mye brukt som prefiks for indekser, mens sistnevnte er tatt med fordi MySQL automatisk oppretter en indeks (hvis det ikke eksisterer en fra før som kan brukes) når man oppretter en fremmednøkkel. Da denne indeksen får samme navn som fremmednøkkelen, og det er vanlig å ha prefiks `fk_` på fremmednøkler, tillates altså dette prefikset. Merk at man i MySQL spesifikt må sette navn på foreign key constraint - hvis man ikke gjør det, opprettes en index med navn lik kolonnen som indekseres.

`<tabellnavn>` er navnet på tabellen hvor indexen er definert. Det blir brukt som ledetekst til relasjonen når man står på en post i referert tabell. Man behøver ikke å bruke tabellnavnet her, da det man skriver her uansett blir brukt som ledetekst. Man kan altså skrive her det som man ønsker skal framstå som ledetekst.

`<kolonnenavn>` er navnet på den indekserte kolonnen, som også representerer fremmednøkkelen. Hvis dette navnet samsvarer med navn på referert tabell, vil det ikke tas med i ledeteksten. Hvis det ikke samsvarer med navn på referert tabell, vil det settes i parentes etter navnet på referert tabell.

`<postfix>` er valgfritt, og kan ha samme verdier som `<prefix>`. Dvs. man kan sette `idx` eller `fk` enten som prefix eller som postfix.


## Fremmednøkler

Fremmednøkler brukes i en database til å knytte sammen poster fra ulike tabeller. De samme nøklene brukes i URD til å vise sammenhengene i databasen.

## Tabellen `meta_terminology`

Dette er den eneste metadata-tabellen som URD behøver for å generere skjema. Den er nødvendig å ha med, for det sjekkes om denne tabellen eksisterer når URD skal generere skjema fra databasestrukturen. Hvis denne eksisterer, brukes alle reglene angitt ovenfor, ellers går URD ut ifra at databasen ikke følger disse reglene.

Denne tabellen brukes til å angi ledetekst og beskrivelser for felter og tabeller. Det finnes ingen felles sql-standard for å legge til beskrivelser for tabeller og kolonner i databaser, så derfor er det veldig nyttig å kunne legge det inn her.

Mange databaser er fagsystemer med spesialisert terminologi. Det er derfor viktig å kunne beskrive denne terminologien. Og når vi har en tabell som kun beskriver termer, behøver vi ofte ikke beskrive hvert enkelt felt. Mange termer går igjen flere steder i en database, så det er hensiktsmessig å kunne beskrive disse kun én gang.

Termene gjenkjennes når de brukes følgende steder i databasen:
- tabellnavn
- prefix i tabellnavn
- kolonnenavn
- prefix i kolonnenavn

Ved prefix i tabellnavn eller kolonnenavn, gjenkjennes også som term det som kommer etter prefix.