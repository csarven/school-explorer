<?php

/**
 * EasyRdf
 *
 * LICENSE
 *
 * Copyright (c) 2009-2011 Nicholas J Humfrey.  All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 * 3. The name of the author 'Nicholas J Humfrey" may be used to endorse or
 *    promote products derived from this software without specific prior
 *    written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package    EasyRdf
 * @copyright  Copyright (c) 2009-2011 Nicholas J Humfrey
 * @license    http://www.opensource.org/licenses/bsd-license.php
 * @version    $Id$
 */

/**
 * Class for returned for SPARQL SELECT and ASK query responses.
 *
 * @package    EasyRdf
 * @copyright  Copyright (c) 2009-2011 Nicholas J Humfrey
 * @license    http://www.opensource.org/licenses/bsd-license.php
 */
class EasyRdf_SparqlResult extends ArrayIterator
{
    protected $_type = null;
    protected $_boolean = null;

    protected $_ordered = null;
    protected $_distinct = null;
    protected $_fields = array();

    public function __construct($data, $mimeType)
    {
        if ($mimeType == 'application/sparql-results+xml') {
            return $this->_parseXml($data);
        } else if ($mimeType == 'application/sparql-results+json') {
            return $this->_parseJson($data);
        } else {
            throw new EasyRdf_Exception(
                "Unsupported SPARQL Query Results format: $mimeType"
            );
        }
    }

    public function getType()
    {
        return $this->_type;
    }

    public function getBoolean()
    {
        return $this->_boolean;
    }

    public function isTrue()
    {
        return $this->_boolean == true;
    }

    public function isFalse()
    {
        return $this->_boolean == false;
    }

    public function numFields()
    {
        return count($this->_fields);
    }

    public function numRows()
    {
        return count($this);
    }

    public function getFields()
    {
        return $this->_fields;
    }

    public function dump($html=true)
    {
        if ($this->_type == 'bindings') {
            $result = '';
            if ($html) {
                $result .= "<table class='sparql-results' style='border-collapse:collapse'>";
                $result .= "<tr>";
                foreach ($this->_fields as $field) {
                    $result .= "<th style='border:solid 1px #000;padding:4px;".
                               "vertical-align:top;background-color:#eee;'>".
                               "?$field</th>";
                }
                $result .= "</tr>";
                foreach ($this as $row) {
                    $result .= "<tr>";
                    foreach ($this->_fields as $field) {
                        $result .= "<td style='border:solid 1px #000;padding:4px;".
                                   "vertical-align:top'>".
                                   $row->$field->dumpValue($html)."</td>";
                    }
                    $result .= "</tr>";
                }
                $result .= "</table>";
            } else {
                // First calculate the width of each comment
                $colWidths = array();
                foreach ($this->_fields as $field) {
                    $colWidths[$field] = strlen($field);
                }

                $textData = array();
                foreach ($this as $row) {
                    $textRow = array();
                    foreach ($row as $k => $v) {
                        $textRow[$k] = $v->dumpValue(false);
                        $width = strlen($textRow[$k]);
                        if ($colWidths[$k] < $width) {
                            $colWidths[$k] = $width;
                        }
                    }
                    $textData[] = $textRow;
                }

                // Create a horizontal rule
                $hr = "+";
                foreach ($colWidths as $k => $v) {
                    $hr .= "-".str_repeat('-', $v).'-+';
                }

                // Output the field names
                $result .= "$hr\n|";
                foreach ($this->_fields as $field) {
                    $result .= ' '.str_pad("?$field", $colWidths[$field]).' |';
                }

                // Output each of the rows
                $result .= "\n$hr\n";
                foreach ($textData as $textRow) {
                    $result .= '|';
                    foreach ($textRow as $k => $v) {
                        $result .= ' '.str_pad($v, $colWidths[$k]).' |';
                    }
                    $result .= "\n";
                }
                $result .= "$hr\n";

            }
            return $result;
        } else if ($this->_type == 'boolean') {
            $str = ($this->_boolean ? 'true' : 'false');
            if ($html) {
                return "<p>Result: <span style='font-weight:bold'>$str</span></p>";
            } else {
                return "Result: $str";
            }
        } else {
            throw new EasyRdf_Exception(
                "Failed to dump SPARQL Query Results format, unknown type: ". $this->_type
            );
        }
    }

    protected function _newTerm($data)
    {
        switch($data['type']) {
          case 'bnode':
            return new EasyRdf_Resource('_:'.$data['value']);
          case 'uri':
            return new EasyRdf_Resource($data['value']);
          case 'literal':
          case 'typed-literal':
            return new EasyRdf_Literal($data);
          default:
            throw new EasyRdf_Exception(
                "Failed to parse SPARQL Query Results format, unknown term type: ".
                $data['type']
            );
        }
    }

    protected function _parseXml($data)
    {
        $doc = new DOMDocument();
        $doc->loadXML($data);
        # FIXME: check for SPARQL top-level element

        # Is it the result of an ASK query?
        $boolean = $doc->getElementsByTagName('boolean');
        if ($boolean->length) {
            $this->_type = 'boolean';
            $value = $boolean->item(0)->nodeValue;
            $this->_boolean = $value == 'true' ? true : false;
            return;
        }

        # Get a list of variables from the header
        $head = $doc->getElementsByTagName('head');
        if ($head->length) {
            $variables = $head->item(0)->getElementsByTagName('variable');
            foreach ($variables as $variable) {
                $this->_fields[] = $variable->getAttribute('name');
            }
        }

        # Is it the result of a SELECT query?
        $resultstag = $doc->getElementsByTagName('results');
        if ($resultstag->length) {
            $this->_type = 'bindings';
            $results = $resultstag->item(0)->getElementsByTagName('result');
            foreach ($results as $result) {
                $bindings = $result->getElementsByTagName('binding');
                $t = new stdClass();
                foreach ($bindings as $binding) {
                    $key = $binding->getAttribute('name');
                    $term = $binding->firstChild;
                    $data = array(
                        'type' => $term->nodeName,
                        'lang' => $term->getAttribute('lang'),
                        'datatype' => $term->getAttribute('datatype'),
                        'value' => $term->nodeValue
                    );
                    $t->$key = $this->_newTerm($data);
                }
                $this[] = $t;
            }
            return $this;
        }

        throw new EasyRdf_Exception(
            "Failed to parse SPARQL XML Query Results format"
        );
    }

    protected function _parseJson($data)
    {
        // Decode JSON to an array
        $data = json_decode($data, true);

        if (isset($data['boolean'])) {
            $this->_type = 'boolean';
            $this->_boolean = $data['boolean'];
        } else if (isset($data['results'])) {
            $this->_type = 'bindings';
            if (isset($data['head']['vars'])) {
                $this->_fields = $data['head']['vars'];
            }

            foreach ($data['results']['bindings'] as $row) {
              $t = new stdClass();
              foreach ($row as $key => $value) {
                  $t->$key = $this->_newTerm($value);
              }
              $this[] = $t;
            }
        } else {
            throw new EasyRdf_Exception(
                "Failed to parse SPARQL JSON Query Results format"
            );
        }
    }

    public function __toString()
    {
        if ($this->_type == 'boolean') {
            return $this->_boolean ? 'true' : 'false';
        } else {
            return $this->dump(false);
        }
    }
}
