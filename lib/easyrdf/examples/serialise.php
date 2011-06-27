<?php
    set_include_path(get_include_path() . PATH_SEPARATOR . '../lib/');
    require_once "EasyRdf.php";

    ## Uncomment these if you have rapper / Arc2 installed
    #require_once "EasyRdf/Serialiser/Rapper.php";
    #require_once "EasyRdf/Serialiser/Arc.php";

    $graph = new EasyRdf_Graph();
    $me = $graph->resource('http://www.example.com/joe#me', 'foaf:Person');
    $me->set('foaf:name', 'Joseph Bloggs');
    $me->set('foaf:title', 'Mr');
    $me->set('foaf:nick', 'Joe');
    $me->add('foaf:homepage', $graph->resource('http://example.com/joe/'));

    if (isset($_REQUEST['format'])) {
        $format = preg_replace("/[^\w\-]+/", '', strtolower($_REQUEST['format']));
    } else {
        $format = 'ntriples';
    }
?>
<html>
<head><title>Serialiser</title></head>
<body>
<h1>Serialisation example</h1>

<ul>
<?php
    foreach (EasyRdf_Format::getFormats() as $f) {
        if ($f->getSerialiserClass()) {
            if ($f->getName() == $format) {
                print "<li><b>".$f->getLabel()."</b></li>\n";
            } else {
                print "<li><a href='?format=$f'>";
                print $f->getLabel()."</a></li>\n";
            }
        }
    }
?>
</ul>

<pre style="margin: 0.5em; padding:0.5em; background-color:#eee; border:dashed 1px grey;">
<?php
    $data = $graph->serialise($format);
    if (!is_scalar($data)) {
        $data = var_export($data, true);
    }
    print htmlspecialchars($data);
?>
</pre>

</body>
</html>