<?php
require_once '../XPath.php';
$xml = new XML_XPath();
$string = '<?xml version="1.0"?>
<doc foo="bar">
  <child1>foo</child1>
  hey
  <foo>bar</foo>
  <child2>foo</child2>
  <foo>stuff</foo>
  <report id="5">
    <title>Summary</title>
  </report>
</doc>';
$xml->load($string, 'string');
$children = $xml->childNodes();
echo '----------' . "\n";
echo 'Showing each child of <doc> (Please note blank nodes are skipped)' . "\n";
while ($children->next()) {
    echo 'Index: ' . $children->getIndex() . ' Name: ' . $children->nodeName() . "\n";
}
echo '----------' . "\n";
$xml->evaluate('//report[@id = "5"]', true);
$xml->evaluate('title', true, true);
echo 'The report title is: ' . $xml->substringData() . "\n";
echo '----------' . "\n";
?>
