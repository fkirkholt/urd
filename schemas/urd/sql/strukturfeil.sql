-- Finner objekter som skal ekspanderes og hvor det ikke er definert opp
-- noen tabell for objektet
select k.databasemal, k.tabell, k.kolonne
from   kolonne k
       left join tabell t
              on ( k.kandidatmal = t.databasemal
                   or ( k.kandidatmal IS NULL
                        and k.databasemal = t.databasemal ))
                 and k.kandidattabell = t.tabell
where kandidatnokkel is not null
      and relasjonsvisning = 1
      and t.tabell is null;

-- Finner objekter som skal ekspanderes og hvor det ikke er definert opp noen
-- kolonner for objektet
select k.databasemal, k.tabell, k.kolonne, count(k2.tabell)
from   kolonne k
       left join kolonne k2
              on ( k.kandidatmal = k2.databasemal
                   or ( k.kandidatmal IS NULL
                        and k.databasemal = k2.databasemal ))
                 and k.kandidattabell = k2.tabell
where  k.kandidatnokkel is not null and k.relasjonsvisning = 1
group  by ( k.databasemal, k.tabell, k.kolonne )
having count( k2.tabell ) = 0;

-- Finner kolonner som er satt som checkbox men som har datatype != boolean
select k.databasemal, k.tabell, k.kolonne
from   kolonne k
where  felttype = 'checkbox' and datatype != 'boolean';

-- Finner kolonner av type checkbox som er obligatorisk
-- men som ikke har standardverdi
select k.databasemal, k.tabell, k.kolonne, k.standardverdi
from   kolonne k
where  felttype = 'checkbox' and obligatorisk = 1 and standardverdi is null;

-- Finner sammensatte kolonner som ikke er av typen 'compound'
select k.databasemal, k.tabell, k.kolonne, k.standardverdi
from   kolonne k
where  k.kolonne LIKE '%,%' AND datatype != 'compound';

-- Finner relasjoner som skal vises hvor tabell ikke er definert opp
select *
from   kolonne
       left join tabell
            on tabell.tabell = kolonne.kandidattabell
               and ( tabell.databasemal = kolonne.kandidatmal
                     OR ( tabell.databasemal = kolonne.databasemal
                          AND kolonne.kandidatmal is null ))
where kandidatnokkel is not null
      and relasjonsvisning = 1
      and tabell.tabell is null;

-- Finner steder hvor det er satt at urd_bruker skal utvides
-- (noe man ikke bør kunne gjøre)
-- TODO: vurder å legge sperre for utvidelse av slike felter i koden
select *
from   kolonne
where  kandidatmal = 'urd'
       and kandidattabell = 'bruker'
       and relasjonsvisning = 1;
-- Retter de forekomstene som blir funnet med setningen over
update kolonne
set    relasjonsvisning = 0
where  kandidatmal = 'urd'
       and kandidattabell = 'bruker'
       and relasjonsvisning = 1;

-- Finner kolonner som ikke er obligatoriske, men hvor det er satt inn
-- standardverdi
-- Dette er egentlig ingen feil, men er dårlig design, og vanskelig å forholde
-- seg til for en bruker
select *
from   kolonne
where  obligatorisk = 0 and standardverdi is not null and datatype != 'derived';
