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

### Gruppering av kolonner

Kolonner kan grupperes vha. prefix (liksom tabeller). Felter med samme prefix havner under samme overskrift (som kan lukkes og ekspanderes i grensesnittet).

Eks: Hvis man har to kolonner `dato_fra` og `dato_til`, vises de slik (skjematisk) i URD:

**Dato:**
  Fra: 14.01.2001
  Til: 31.12.2008

Overskriften utledes fra prefixet, enten ved at prefixet vises som overskrift, eller at prefixet er angitt i tabellen `meta_terminology`. Da vises som overskrift det som er satt som `label` der.

## Indexer

Indexer brukes til å angi hvilke kolonner som skal vises, sortering, og visningsverdi til felter som representerer fremmednøkler. Dette representeres av kun to indexer.

### `<tabellnavn>_grid_idx`

I visningen i URD får man til venstre opp en tabell - en grid - hvor man kan velge poster fra, og få opp en detaljvisning til høyre i bildet.

Hvilke kolonner som skal vises i denne tabellen, bestemmes av indexen `<tabellnavn>_grid_idx`. Man må altså opprette en index med et slikt navn, og angi kolonnene i den rekkefølgen man vil ha dem i tabellen.

### `<tabellnavn>_sort_idx`

For å angi standardsortering i en tabell når man åpner den i URD, kan man angi indexen `<tabellnavn>_sort_idx`, med de kolonnene man vil sortere på. Rekkefølgen på kolonnene i indexen bestemmer rekkefølgen på sorteringen.

Det støttes ikke avtakende (descending) sortering ennå, men det er planer om å få det til å virke også. Noen databasemotorer støtter jo å angi `asc` og `desc` for index-kolonner.

Denne indexen brukes også til å angi hvilke kolonner man ser som visningsverdi for fremmednøkkel-felter. Det er valgt gjort slik fordi det i de aller fleste tilfeller vil være samsvar mellom de verdiene man ønsker å se fra en tabell og de kolonnene man ønsker å sortere tabellen etter. Visningsverdien skal jo identifisere en post i tabellen, og som standardsortering vil man som regel ha kolonner som identifiserer en post.

Eksempel: Hvis man har referanse til saksansvarlig på en sak, vil man som regel se navnet på saksansvarlig. Og når man ser på persontabellen, vil man som regel ha standardsortering etter navn.

## Fremmednøkler

Fremmednøkler brukes i en database til å knytte sammen poster fra ulike tabeller. De samme nøklene brukes i URD til å vise sammenhengene i databasen.

### Navngivning

Det er innført en spesiell navngivning av fremmednøkler slik at URD skal vite hva en relasjon skal kalles når man skal hente opp liste over poster fra andre tabeller som refererer til aktuell post.

Ofte vil en relasjon kalles det samme som tabellen til de postene som refererer til aktuell post. Hvis man f.eks. står på en sak, vil man nok at journalpostene skal refereres til som nettopp "Journalposter". Og kalles tabellen med journalposter for `journalposter`, er det nettopp det man ser.

Men det er ikke alltid så enkelt. En person kan f.eks. ha flere relasjoner til saker. Han/hun kan være saksansvarlig for en rekke saker, men også registrert som den som sist har gjort endringer på noen saker.

Når man står på en person, vil man derfor kunne ha to relasjoner til sakstabellen, hvor den ene betegner de sakene vedkommende har saksansvar for, og den andre betegner de sakene vedkommende sist har oppdatert. Begge disse relasjonene kan ikke kalles "Saker".

Derfor er det gjort slik at betegnelsen på relasjonen kan hentes ut fra navn på fremmednøkkelen. Dette er ikke en helt ulogisk måte å gjøre det på, da fremmednøkkelen nettopp betegner relasjonen, og å ha en slags beskrivelse av relasjonen i navnet på fremmednøkkelen gir jo mening.

Malen er på følgende format:
`fk_<referert_tabell>_<navn_på_relasjon>_<refererende_tabell>` (prefix `fk_` er valgfritt)

Fremmednøklene i eksemplene over kan da navngis slik:
`fk_saker_journalposter`
`fk_person_har_saksansvar_for_saker`
`fk_person_har_sist_oppdatert_saker`

Når URD da skal finne relasjonsteksten, fjernes første del av navnet, dvs. `fk_<referert_tabell>_`, og så brukes siste del som ledetekst for relasjonen.

Står man på en person, får man altså følgende overskrifter for relasjonene:
- "Har saksansvar for saker"
- "Har sist oppdatert saker"

Denne måten å navngi fremmednøkler på likner litt på en meget utbredt måte å navngi fremmednøkler på, nemlig `fk_<refererende_tabell>_<referert_tabell>`. Dvs. at i URD snur man om på rekkefølgen av de to tabellene, og legger til relasjonstekst mellom. Rekkefølgen snus om fordi det er fra referert tabell vi trenger relasjonsteksten (fra refererende tabell utledes den jo bare fra kolonnenavnet til fremmednøkkelen).

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