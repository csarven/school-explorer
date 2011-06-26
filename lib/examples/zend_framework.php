<?php
    set_include_path(get_include_path() . PATH_SEPARATOR . '../lib/');

    // require the Zend Autoloader
    require_once 'Zend/Loader/Autoloader.php';
    $autoloader = Zend_Loader_Autoloader::getInstance();
    $autoloader->setFallbackAutoloader(true);
    
    // use the CURL based HTTP client adaptor
    $client = new Zend_Http_Client(
        null, array(
            'adapter' => 'Zend_Http_Client_Adapter_Curl',
            'keepalive' => true,
            'useragent' => "EasyRdf/zendtest"
        )
    );
    EasyRdf_Http::setDefaultHttpClient($client);
    
    // Load the parsers and serialisers that we are going to use
    # FIXME: better way to do this?
    $autoloader->autoload('EasyRdf_Serialiser_Ntriples');   
    $autoloader->autoload('EasyRdf_Parser_Ntriples');
?>

<html>
<head>
  <title>Zend Framework Example</title>
</head>
<body>
<h1>Zend Framework Example</h1>

<?php
    # Load some sample data into a graph
    $graph = new EasyRdf_Graph('http://example.com/joe');
    $joe = $graph->resource('http://example.com/joe#me', 'foaf:Person');
    $joe->add('foaf:name', 'Joe Bloggs');
    $joe->addResource('foaf:homepage', 'http://example.com/joe/');
    
    # Store it in a local graphstore
    $store = new EasyRdf_GraphStore('http://localhost:8080/data/');
    $store->replace($graph);

    # Now make a query to the graphstore
    $sparql = new EasyRdf_SparqlClient('http://localhost:8080/sparql/');
    $result = $sparql->query('SELECT * WHERE {<http://example.com/joe#me> ?p ?o}');
    echo $result->dump();
?>

</body>
</html>
