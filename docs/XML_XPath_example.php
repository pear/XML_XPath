<?php
error_reporting(E_ALL);
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
echo '----------' . "\n";
/* Begin test for childNodes()
$children = $xml->childNodes();
echo 'Showing each child of <doc> (Please note blank nodes are skipped)' . "\n";
while ($children->next()) {
    echo 'Index: ' . $children->getIndex() . ' Name: ' . $children->nodeName() . "\n";
}
echo '----------' . "\n";
*/
$reports = $xml->evaluate('//report[@id = "5"]');
echo 'The report title is: ' . $reports->substringData(0, 0, array('.', 'title')) . "\n";
echo '----------' . "\n";
echo 'Here we want to generate a null pointer for an empty result misuse' . "\n";
echo '----------' . "\n";
$result = $xml->evaluate(array('.', 'i/dont/exist'));
print_r($result);
print_r($result->substringData());
$now = systime();
foreach (range(0, 50) as $i) {
    $xml->nodeName();
}
echo 'Timing:' . "\n";
echo systime() - $now;
echo "\n";
if ($reports->evaluate(array('.', '..'), true)) {
    echo $reports->nodeName();
}
else {
    echo 'Invalid query';
}
?>
