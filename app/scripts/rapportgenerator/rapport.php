<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <script type="text/javascript" src="../../lib/jquery/jquery.min.js"></script>
        <script type="text/javascript" src="../../lib/jquery/jquery.download.js"></script>
        <script type="text/javascript" src="rapport.js"></script>
    </head>
    <body>
        <input style="display:none" name="schema" value="<?php echo $_GET['skjema'] ?>">
        <input style="display:none" name="id" value="<?php echo $_GET['id'] ?>">
        <input type="button" id="csv" value="Last ned som csv">
        <table id="report">
        </table>
    </body>
</html>
