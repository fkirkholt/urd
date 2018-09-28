# Brukeradministrasjon

Brukeradministrasjonen brukes til å administrere brukere og deres rettigheter.

URD tillater pålogging uten at en bruker er registrert i brukertabellen. Det skjer gjennom pålogging via active directory, dvs. hvis man har konfigurert systemet slik at man kan logge på med samme brukernavn og passord som man logger på Windows-maskinen.

Hvis man ikke er registrert bruker, vil man kun få tilgang til de standard-rollene som er definert i config-filen.

Hvis man skal gi en bruker tilganger ut over det, må man legge disse inn i brukeradministrasjonen.

Tilgang til brukeradministrasjonen har de som er registrert med rettigheter i forhold til urd-basen, eller de som er registrert som administrator for et skjema.

Hvis man har tilgang til brukeradministrasjonen, vil man se et menyvalg "Admin" eller tilsvarende i hovedmenyen i URD. Hva denne kalles er konfigurerbart, så det kan variere.

## Rolle

Her registreres roller som brukere kan ha i databasen.

En rolle er alltid knyttet til et skjema, slik at denne rollen kun kan administreres av brukere som har administratortilgang til dette skjemaet. En administrator kan følgelig også kun knytte til skjemaer som vedkommende er administrator for.

Man gir rollen et beskrivende navn, som forteller noe om hva slags tilganger denne rollen gir, f.eks. 'visning' eller 'registrator' eller 'administrator'.

En rolle kan så knyttes til en bruker, og spesifiseres i "Tillatelse for roller".

## Tillatelser for roller

Her defineres hvilke tillatelser hver enkelt rolle skal ha.

Man velger et skjema, og deretter en rolle som tilhører dette skjemaet. Deretter kan man velge hvilken tabell denne tillatelsen skal gjelde for. Hvis man velger stjerne (*), gjelder tillatelsen for alle tabeller i skjemaet (bortsett fra grunndatatabeller, som kun administrator har tilgang til).

Merk at man kan opprette flere poster i denne tabellen, med samme verdi for rolle, men forskjellige verdier for tabeller, og slik definere spesielle tillatelser for så mange tabeller man ønsker.

Merk også at hvis man oppretter en post her med stjerne (alle tabeller), så vil rettighetene til hver enkelt tabell overstyre de tillatelsene som er valgt for alle tabeller. Slik kan man f.eks. velge at en rolle skal ha skrivetilgang til alle tabeller med unntak av én, ved at man registrerer først en stjerne, og gir skrivetillatelse, og deretter registrerer en ny post med tabellnavnet på den tabellen rollen ikke skal ha skrivetilgang for.

Feltene "Vis", "Legge til", "Redigere", "Slette" og "Admin" definerer rettighetene
- Vis: Rollen gir tilgang til å se dataene i tabellen(e)
- Legge til: Rollen gir tilgang til å opprette nye poster
- Redigere: Rollen gir tilgang til å redigere eksisterende poster
- Slette: Rollen gir tilgang til å slette poster
- Admin: Rollen gir administrasjonstilgang til skjemaet

En admin-tilgang gir altså tilgang til å administrere brukere og deres rettigheter i forhold til angitt skjema. Typisk vil en administratorrolle ha alle tillatelser for alle tabeller (*).

## Organisasjon

Her kan organisasjonsstrukturen registreres.

Denne kan brukes av ulike databaser til å finne ut hvem som er leder for organisajonsenhet, og sende epost om ulike ting til denne lederen.

Her kan man registrere et hierarki av enheter. Man ekspanderer en enhet ved å klikke på ekspansjonsmerket i navnekolonnen.

## Brukere

Her registreres alle brukerne av systemet.

For "ID" registreres som regel det brukernavnet som brukeren har i organisasjonen. Dette er særlig viktig hvis man logger på via det brukernavnet og passordet man bruker ellers.

Navnet man registrerer her, vil dukke opp i nedtrekkslister i systemet, hvor man velger brukere, eller i statusfelter som viser sist endret av.

Man står fritt til å knytte en person til en organisasjonsenhet. Merk at noen systemer kan bruke organisasjonsenhet til ulik funksjonalitet, og slik forvente at brukeren er knytta til en enhet.

Epost brukes bl.a hvis systemet skal sende epost til personer, f.eks. med påminnelser. Slike påminnelser lages for hvert enkelt system.

Man kan velge om en person er aktiv bruker eller ei. Dette utnyttes av enkelte skjemaer slik at når man knytter en person til en post, får opp kun aktive brukere. Inaktive brukere kan heller ikke logge på med de rettighetene de har fått tildelt. Hvis de får logget seg på, har de kun gjesteroller.

# Databaseadministrasjon

# Navigering

## Tittellinje/navigasjonslinje

Den øverste sorte linja viser hvor man er i systemet. Når man åpner programmet, kommer man til hovedmenyen med oversikt over databaser, og da vises kun "URD" der. Linjen viser hele tiden stien til der man er, med "URD" først, deretter hvilken database man er inne på, og så hvilken tabell man ser på.

Man kan klikke på "URD" for å gå hjem til listen over databaser, og man kan klikke på databasenavnet for å få listen over tabeller.

I navigasjonslinjen finnes også avkrysningsboksen "Visningsmodus". Hvis man krysser av for denne, vises ikke lenger hver enkelt post redigerbar, men kun informasjonen om denne posten.

Lengst til høyre i navigasjonslinja ligger en tannhjul-knapp hvor man kan redigere innstillinger, aktivere utskriftsvisning eller logge ut.

### Innstillinger

"Autolagring" aktiverer automatisk lagring ved endringer. Da fjernes lagre-knappen, og endringer i feltverdier lagres så snart fokus er borte fra feltet.

"Ant. poster" definerer hvor mange poster som skal vises i tabellen om gangen.

I "Standardsøk" velges hvordan man vil at søkebildet skal framtre. @todo(mer)

"Relasjoner" bestemmer hvordan relasjoner skal vises. Enten som ekspanderbare vertikalt under hver sin overskrift, eller at de vises i kolonner horisontalt bortover skjermen.

### Utskrift

Viser en utskriftsvennlig versjon av tabellen. Velg "Skriv ut …" fra fil-menyen til nettleseren for å skrive ut tabellen.

### Logg ut

Logger deg ut av programmet, og viser påloggingsvinduet.

# Søking

## Enkelt søk

Trykk på søkeknappen (forstørrelsesglass) for å åpne søkebildet.

Fyll inn søkekriteriene, ved å velge operator og verdi, og trykk på knappen "Utfør søk" for å søke.

Avkrysningsboksen "Vis aktive søkekriterier" viser søkekriteriene til aktivt søk. Det kan brukes til å justere søkekriterier etter at man har søkt. Hvis denne ikke er avkrysset, er søkeskjemaet nullstilt når det åpnes. Markering av denne boksen blir husket av systemet, slik at den har samme markering neste gang du åpner søkevinduet.

## Avansert søk

For å komme til avansert søk, krysser man av for "Avansert" i filtrerings-panelet.

Da åpnes et tekstfelt hvor man kan skrive inn en betingelser som så brukes i et where-uttrykk i spørringen mot basen. Dette søket krever altså litt kjennskap med hvordan man skriver sql-setninger.

Nedenfor tekstfeltet ligger nedtrekkslister hvor man kan velge tabell og felt, og så settes dette inn i betingelsen.

# Registrering
