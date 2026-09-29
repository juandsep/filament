<?php

return [

    'filter' => [
        'label' => 'Vibe',
        'statement' => 'Azok a sorok, ahol…',
        'placeholder' => 'Az ügyfél dühös',
        'indicator' => 'Vibe: :statement',
    ],

    'limit' => [
        'title' => ':count sor vár pontozásra',
        'body' => 'A többi szűrő és a keresés után :total sor maradt a táblában, és ebből :unscored sornak még nincs mentett pontszáma erre az állításra. A korlát :limit. Szűkítsd a táblát más szűrővel vagy kereséssel, hogy beleférjen, vagy futtasd le most mindegyikre. Addig a tábla nincs szűrve.',
        'run_anyway' => 'Futtasd mégis',
    ],

    'progress' => [
        'scoring' => ':count sor pontozása',
        'requests' => ':done / :total kérés kész',
        'retried' => ':count újrapróbálva',
        'label' => 'A vibe-szűrő haladása',
    ],

    'report' => [
        'title' => ':total sorból :matching felel meg',
        'scored' => ':count új sor pontozva',
        'scored_in' => ':count új sor pontozva :requests alatt',
        'requests' => ':count kérés',
        'retried' => ':count újrapróbálva, mert az API nem válaszolt',
        'seconds' => ':seconds mp',
        'cached' => ':count a cache-ből',
    ],

    'failure' => [
        'title' => 'A vibe-szűrő nem tudott lefutni',
        'unfiltered' => 'A tábla nincs szűrve.',
        'partial_title' => ':total sorból :scored pontozva',
        'partial_body' => ':count sorra nem válaszolt az API, ezért a tábla csak a pontozott sorok közül mutatja a találatokat. Újrapróbáláskor csak a hiányzó :count sor megy ki.',
        'try_again' => 'Újrapróbálás',
    ],

];
