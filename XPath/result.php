<?php
// {{{ license

// +----------------------------------------------------------------------+
// | PHP version 4.0                                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2001 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Dan Allen <dan@mojavelinux.com>                             |
// +----------------------------------------------------------------------+

// $Id$

// }}}
// {{{ description

// Result class for the Xpath/DOM XML manipulation and query interface.

// }}}
// {{{ constants

define('XML_XPATH_SORT_TEXT_ASCENDING',     1);
define('XML_XPATH_SORT_NUMBER_ASCENDING',   2);
define('XML_XPATH_SORT_NATURAL_ASCENDING',  3);
define('XML_XPATH_SORT_TEXT_DESCENDING',    4);
define('XML_XPATH_SORT_NUMBER_DESCENDING',  5);
define('XML_XPATH_SORT_NATURAL_DESCENDING', 6);

// }}}
/**
 I need to handle sort when result is retrieved using childNodes()
 */

// {{{ class XML_XPath_result

/**
 * Interface for an XML_XPath result so that one can cycle through the result set and manipulate
 * the main tree with DOM methods using a seperate pointer then the original class.
 *
 * @version  Revision: 1.0
 * @author   Dan Allen <dan@mojavelinux.com>
 * @access   public
 * @since    PHP 4.2.1
 * @package  XML_XPath
 */

// }}}
class XML_XPath_result extends XML_XPath_common {
    // {{{ properties

    /** @var object reference to result object */
    var $result;

    /** @var string original xpath query, stored just for debug info */
    var $query;

    /** @var int current index of the result set */
    var $index;
    
    /** @var boolean determines if we have counted the first node */
    var $rewound;
    
    /** @var int one of 4 constants that correspond to the xpath result types */
    var $type;

    /** @var mixed either array of nodesets, string, boolean or number from xpath result */
    var $data;
    
    /** @var object pointer to xml data object */
    var $xml;

    /** @var object pointer to xpath context object for the xml data object */
    var $ctx;

    // }}}
    // {{{ constructor

    function XML_XPath_result($in_result, $in_query, &$in_xml, &$in_ctx) 
    {
        $this->result = &$in_result; 
        $this->query = $in_query;
        $this->type = $this->result->type;
        $this->data = isset($this->result->nodeset) ? $this->result->nodeset : $this->result->value;
        $this->index = 0;
        $this->xml = &$in_xml;
        $this->ctx = &$in_ctx;
        // move the pointer to the first node if at least one node in the result exists
        // for convience, just so we don't have to call nextNode() if we expect only one
        $this->rewind();
    }

    // }}}
    // {{{ mixed   getData()
    
    /**
     * Return the data from the xpath query.  This function will be used mostly for xpath
     * queries that result in scalar results, but in the case of nodesets, returns size
     *
     * @access public
     * @return mixed scalar result from xpath query or size of nodeset
     */
    function getData()
    {
        switch($this->type) {
            case XPATH_BOOLEAN:
                return $this->data ? true : false;
                break;
            case XPATH_NODESET:
                return $this->isNodeType(XML_ATTRIBUTE_NODE) ? $this->pointer->value() : $this->substringData();
                break;
            case XPATH_STRING:
            case XPATH_NUMBER:
                return $this->data;
                break;
        }
    }

    // }}}
    // {{{ int     resultType()

    /**
     * Retrieve the type of result that was returned by the xpath query.
     *
     * @access public
     * @return int code corresponding to the xpath result types constants
     */
    function resultType() 
    {
        return $this->type;
    }

    // }}}
    // {{{ int     numResults()

    /**
     * Return the number of nodes if the result is a nodeset or 1 for scalar results.
     * result (boolean, string, numeric) xpath queries
     *
     * @access public
     * @return int number of results returned by xpath query
     */
    function numResults() {
        return count($this->data);
    }

    // }}}
    // {{{ int     getIndex()

    /**
     * Return the index of the result nodeset.
     *
     * @access public
     * @return int current index of the result nodeset
     */
    function getIndex()
    {
        return key($this->data);
    }

    // }}}
    // {{{ void    sort()

    /**
     * Sort the nodeset in this result.  The sort can be either ascending or descending, and
     * the comparisons can be text, number or natural (see the constants above). The sort
     * axis is provided as an xpath query and is the location path relative to the node given.
     * For example, so sort on an attribute, you would provide '@foo' and it will look at the
     * attribute for each node.  If the axis is not found, it comes first in the sort order for
     * ascending order.
     *
     * @param  string $in_sortXpath relative xpath query location to each node in nodeset
     * @param  int $in_order either XML_XPATH_SORT_TEXT_[DE|A]SCENDING, 
     *                              XML_XPATH_SORT_NUMBER_[DE|A]SCENDING,
     *                              XML_XPATH_SORT_NATURAL_[DE|A]SCENDING
     *
     * @access public
     * @return void {or XML_XPath_Error exception}
     */
    function sort($in_sortXpath = '.', $in_order = XML_XPATH_SORT_TEXT_ASCENDING) 
    {
        if ($this->resultType() != XPATH_NODESET) {
            return PEAR::raiseError(null, XML_XPATH_INVALID_NODESET, null, E_USER_NOTICE, $this->data, 'XPath_Error', true);
        }
        if ($in_sortXpath == '') {
            $in_sortXpath = '.';
        }
        $xpathResult = @$this->ctx->xpath_eval($this->query . '/' . $in_sortXpath . '|' . $this->query);
        if (!$xpathResult || !$xpathResult->nodeset) {
            return PEAR::raiseError(null, XML_XPATH_INVALID_QUERY, null, E_USER_NOTICE, "Query {$this->query}/$in_sortXPath", 'XML_XPath_Error', true);
        }
        $data = array();
        $this->index = 0;
        if (sizeof($xpathResult->nodeset) == sizeof($this->data)) {
            foreach ($xpathResult->nodeset as $index => $node) {
                array_push($data, $xpathResult->nodeset[$index]->get_content());
            }
        }
        else {
            foreach ($xpathResult->nodeset as $index => $node) {
                if (!isset($this->data[$this->index]) || $node != $this->data[$this->index]) {
                    continue;
                }

                if (isset($xpathResult->nodeset[$index + 1])) {
                    if ($xpathResult->nodeset[$index + 1] == $this->data[$this->index]) {
                        array_push($data, '');
                    }
                    else {
                        array_push($data, $xpathResult->nodeset[$index + 1]->get_content());
                    }
                }

                $this->index++;
            }
        }

        switch ($in_order) {
            case XML_XPATH_SORT_TEXT_ASCENDING:
                asort($data, SORT_STRING);
            break;

            case XML_XPATH_SORT_NUMBER_ASCENDING:
                asort($data, SORT_NUMERIC);
            break;

            case XML_XPATH_SORT_NATURAL_ASCENDING:
                natsort($data);
            break;

            case XML_XPATH_SORT_TEXT_DESCENDING:
                arsort($data, SORT_STRING);
            break;

            case XML_XPATH_SORT_NUMBER_DESCENDING:
                arsort($data, SORT_NUMERIC);
            break;

            case XML_XPATH_SORT_NATURAL_DESCENDING:
                natsort($data);
                $data = array_reverse($data, TRUE);
            break;

            default:
                asort($data);
            break;
        }
        $dataReordered = array();
        $this->index = 0;
        foreach ($data as $reindex => $value) {
            $dataReordered[$this->index++] = $this->data[$reindex];
        }
        $this->data = $dataReordered;
        $this->index = 0;
        $this->pointer = $this->data[$this->index];
    }
     
    // }}}
    // {{{ boolean rewind()

    /**
     * Reset the result index back to the beginning, if this is an XPATH_NODESET
     *
     * @access public
     * @return boolean success
     */
    function rewind()
    {
        if (is_array($this->data)) {
            $this->pointer = reset($this->data);
            $this->rewound = true;
            return true;
        }
        
        return false;
    }

    // }}}
    // {{{ boolean next()

    /**
     * Move to the next node in the nodeset of results.  This can be used inside of a
     * while loop, so that it is possible to step through the nodes one by one.
     * It is important to note that the first call to next will put the pointer at
     * the first index and not the second...this is just a more convenient way of
     * handling the logic.  If you rewind() the data and then call next() as the conditional
     * on a while loop, you can work through each of the results from the first to the last.
     *
     * @access public
     * @return boolean success node found and pointer advanced
     */
    function next()
    {
        if (is_array($this->data)) {
            if ($this->rewound) {
                $this->rewound = false;
                $seekFunction = 'reset';
            }
            else {
                $seekFunction = 'next';
            }

            if ($node = $seekFunction($this->data)) {
                $this->pointer = $node;
                return true;
            }
        }

        return false;
    }

    // }}}
    // {{{ boolean nextByNodeName()

    /**
     * Move to the next node in the nodeset of results where the node has the name provided.
     * This can be used inside of a while loop, so that it is possible to step through the 
     * nodes one by one.
     *
     * @param  string $in_name name of node to find
     *
     * @access public
     * @return boolean next node existed and pointer moved
     */
    function nextByNodeName($in_name)
    {
        if (is_array($this->data)) {
            if ($this->rewound) {
                $this->rewound = false;
                if (($node = reset($this->data)) && $node->node_name() == $in_name) {
                    $this->pointer = $node;
                    return true;
                }
            }

            while ($node = next($this->data)) {
                if ($node->node_name() == $in_name) {
                    $this->pointer = $node;
                    return true;
                }
            }
        }

        return false;
    }

    // }}}
    // {{{ boolean nextByNodeType()

    /**
     * Move to the next node in the nodeset of results where the node has the type provided.
     * This can be used inside of a while loop, so that it is possible to step through the 
     * nodes one by one.
     *
     * @param  int  $in_type type of node to find
     *
     * @access public
     * @return boolean next node existed and pointer moved
     */
    function nextByNodeType($in_type)
    {
        if (is_array($this->data)) {
            if ($this->rewound) {
                $this->rewound = false;
                if (($node = reset($this->data)) && $node->node_type() == $in_type) {
                    $this->pointer = $node;
                    return true;
                }
            }

            while ($node = next($this->data)) {
                if ($node->node_type() == $in_type) {
                    $this->pointer = $node;
                    return true;
                }
            }
        }

        return false;
    }

    // }}}
    // {{{ boolean end()

    /**
     * Move to last result node, if this is an XPATH_NODESET
     *
     * @access public
     * @return boolean success
     */
    function end()
    {
        if (is_array($this->data)) {
            $this->pointer = end($this->data);
            return true;
        }

        return false;
    }

    // }}}
    // {{{ void    free()

    /**
     * Free the result object in order to save memory.
     *
     * @access public
     * @return void
     */
    function free()
    {
        $this = null; 
    }

    // }}}
}
?>
