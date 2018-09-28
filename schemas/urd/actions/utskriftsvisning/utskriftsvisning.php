<!--<script type="text/javascript"
        src="/schemas/urd/actions/utskriftsvisning/utskriftsvisning.js"></script>-->
<?php
echo '<script type="text/javascript">';
    echo file_get_contents(__DIR__.'/utskriftsvisning.js');
    echo '</script>';
?>

<button id="print-view-close">Lukk</button>

<table id="tabell" class="collapse">
    <thead id="thead"></thead>
    <tbody id="tbody"></tbody>
    <tfoot id="tfoot"></tfoot>
</table>
