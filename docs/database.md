# Database

URD har sin egen database, som holder styr på databasene som skal vises, brukere, rettigheter mm.

## Tabeller

### database_

Oversikt over alle databasene som man har tilgang til via URD. @todo(mer)

| Feltnavn | Beskrivelse |
| :------- | :---------- |
| name     | Databasenavnet |
| alias    | Hva brukeren ser databasen identifisert som i adresselinja |
| platform | Hvilken databaseplattform som databasen ligger på. <br> Tillatte verdier: 'oracle', 'mysql', 'sqlite' |
| host     | IP-nummer til host som databasen ligger på |
| port     | Hvilken port databasen bruker |
| username | Brukernavn til databasebrukeren som URD skal koble seg på basen med |
| password | Passord til databasen |
| label    | Hva databasen skal kalles i brukergrensesnittet |
| description | Beskrivelse av databasen |
| schema_  | Hvilket skjema denne basen tilhører. Gjør at URD vet hvordan data skal presenteres |
| log      | Bestemmer om endringer skal logges, og i så fall hvor. <br> 0 - Ingen logging <br> 1 - Logging til en `log`-tabell i databasen <br>  |

Hvis man setter `log: 1` må altså databasen som angis ha en tabell `log` med følgende kolonner (foreslått datatype i parentes):
- id (int 11)
- table_ (varchar 30)
- column_ (varchar 30)
- prim_key (varchar 200)
- updated_by (varchar 30)
- updated_at (timestamp)
- type (varchar 30)
- new_value (text)

### filter

Angir søkekriterier for lagrede filtreringer/søk i en tabell

| Feltnavn | Beskrivelse |
| :------- | :---------- |
| id       | Unik id til søkekriteriet |
| schema_  | Hvilket databaseskjema dette søket tilhører |
| table_   | Hvilken tabell dette søket tilhører |
| expression | Søkekriteriene |
| label    | Hva brukeren har kalt søket |
| user_    | Hvilken bruker som har lagret søket.<br>Merk: Hvis brukeren er 'urd' er søket tilgjengelig for alle |
| standard | Angir om dette skal være standardsøket, dvs. at tabellen filtreres basert på dette kriteriet når man åpner tabellen |
| advanced | Angir om dette er avansert søk |

### format

Bestemmer formatering av rader i en tabell. Man kan nemlig legge på farge eller annen formatering på rader, etter gitte kriterier.

| Feltnavn | Beskrivelse |
| :------- | :---------- |
| schema_  | Hvilket databaseskjema formateringen gjelder for |
| table_   | Hvilken tabell formateringen gjelder for |
| class    | Navnet på klassen(e) som brukes til formatering. Bruker [Tachyons](https://tachyons.io) |
| filter   | where-uttrykk som brukes til å finne de radene som skal formateres |

### message

Her listes opp feil og advarsler fra systemet. Blir logget automatisk når det oppstår en feil.

Er også mulig å skrive ulike beskjeder til denne tabellen, f.eks. ved debugging.

| Feltnavn | Beskrivelse |
| :------- | :---------- |
| id       | Unik id     |
| time     | Tidsstempel for når feilen/hendelsen oppsto |
| user_    | Hvilken bruker som fikk feilen |
| type     | Hvilken type feil/hendelse dette var |
| text     | Feilmelding eller beskjed |
| file_    | Hvilken fil denne feilen oppsto i |
| line     | Hvilken linje denne feilen oppsto i |
| trace    | "Stack trace", dvs. sti til funksjonskallene som leda opp til feilen |
| parameters | Parametre fra ajax-kallet som endte opp i feilen |

### organization

| Feltnavn | Beskrivelse |
| :------- | :---------- |
| id       | Unik id til enheten |
| name     | Navn på enheten |
| parent   | Angir evt. hvilken enhet denne er underlagt |
| leader   | Angir lederen for enheten. Oppslag mot urd.user_ |

### role

Definerer roller.

| Feltnavn | Beskrivelse |
| :------- | :---------- |
| id       | Unik id til rollen |
| name     | Navn på rollen |
| schema_  | Skjema rollen tilhører |

MERK: Feltet `schema_` gjør det mulig å la administratorer for et skjema få tilgang kun til de rollene de skal administrere.

### role_permission @todo(mer)

Definerer opp rettigheter for roller.

| Feltnavn | Beskrivelse |
| :------- | :---------- |
| role     | Rollen som det beskrives rettigheter for. Oppslag mot urd.role |
| schema_  | Skjema som rettigheten tilhører |
| table_   | Tabellen rettigheten er knyttet til.<br>En stjerne (*) angir at rettigheten gjelder alle tabeller |
| view_    | Angir om rollen gir rettighet til å se tabellen |
| add_     | Angir om rollen gir rettighet til å opprette nye poster |
| edit     | Angir om rollen gir rettighet til å redigere en eksisterende post |
| delete_  | Angir om rollen gir rettighet til å slette poster |
| admin    | Angir om rollen gir adminrettigheter til databasen @todo(finnes det adminrettighet til en tabell?) |

MERK: Feltet `schema_` gjør det mulig å la administratorer for et skjema få tilgang kun til de rettighetene de skal administrere.

### user_ @todo(mer)

Brukertabellen

| Feltnavn | Beskrivelse |
| :------- | :---------- |
| id       | Unik id til brukeren.<br>Hvis man bruker Active Directory til pålogging, skal denne være samme som brukernavnet der |
| name     | Navnet til brukeren |
| email    | Brukerens epostadresse. Brukes f.eks. til å sende ut påminnelser |
| organization | Hvilken organisasjonsenhet brukeren tilhører |
| hash     | Hashet passord |
| active   | Angir om brukeren er aktiv eller historisk. Kan brukes til å filtrere nedtrekksliste over brukere |

### user_role

Knytter brukere til roller.

| Feltnavn | Beskrivelse |
| :------- | :---------- |
| user_    | Oppslag mot tabellen `user_` |
| schema_  | Skjema som rollen tilhører |
| role     | Oppslag mot tabellen `role` |


