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

// }}}
// {{{ description

// Result class for the Xpath/DOM XML manipulation and query interface.

// }}}

// {{{ class XPath_result

/**
 * Interface for an XPath result so that one can cycle through the result set and manipulate
 * the main tree with DOM methods using a seperate pointer then the original class.
 *
 * @version  Revision: 1.1
 * @author   Dan Allen <dan@mojavelinux.com>
 * @access   public
 * @since    PHP 4.2
 * @package  XML/XPath
 */

// }}}
class XPath_result extends XPath_common {
    // {{{ properties

    /** @var object reference to result object */
    var $result;

    /** @var string original xpath query, stored just for debug info */
    var $query;

    /** @var int current index of the result set */
    var $index;
    
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

    function XPath_result($in_result, $in_query, &$in_xml, &$in_ctx) 
    {
        $this->result = &$in_result; 
        $this->query = $in_query;
        $this->type = $this->result->type;
        $this->data = $this->result->nodeset ? $this->result->nodeset : $this->result->value;
        $this->index = 0;
        $this->xml = &$in_xml;
        $this->ctx = &$in_ctx;
        // move the pointer to the first node if at least one node in the result exists
        // for convience, just so we don't have to call nextNode() if we expect only one
        if ($this->type == XPATH_NODESET && !empty($this->data)) {
            $this->pointer = $this->data[0];
        }
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
                return $this->numResults();
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
        return sizeOf($this->data);
    }

    // }}}
    // {{{ boolean nextNode()

    /**
     * Move to the next node in the nodeset of results.  This can be used inside of a
     * while loop, so that it is possible to step through the nodes one by one.
     *
     * @access public
     * @return boolean next node existed and pointer moved
     */
    function nextNode()
    {
        if (sizeOf($this->data) > $this->index) {
            $this->index++; 
            $this->pointer = $this->data[$this->index - 1];
            return true;
        }
        else {
            return false;
        }
    }

    // }}}
    // {{{ void    setNodeIndex()

    /**
     * Explicitly set the node index for the result nodeset.  This can be used for stepping
     * through the result nodeset manually, and can be used in conjunction with numResults()
     * The function takes either an numeric index (1 based) or the words 'first' or 'last'
     *
     * @param mixed  $in_index either an integer index or the string 'first' or 'last'
     *
     * @access public
     * @return void {or XPath_Error exception}
     */
    function setNodeIndex($in_index) 
    {
        if (!$this->type == XPATH_NODESET) {
            return PEAR::raiseError(null, XPATH_INVALID_NODESET, null, E_USER_NOTICE, "Cannot assign index $in_index to non-nodeset result in query {$this->query}", 'XPath_Error', true);
        }
        if ($in_index == 'last') {
            $in_index = sizeOf($this->data);
        }
        elseif ($in_index == 'first') {
            $in_index = 1;
        }
        elseif (is_int($in_index)) {
            if ($in_index > sizeOf($this->data)) {
                return PEAR::raiseError(null, XPATH_INVALID_INDEX, null, E_USER_NOTICE, "Invalid index $in_index in query {$this->query}", 'XPath_Error', true);
            }
            elseif ($in_index > 0) {
                $this->index = $in_index; 
            }
            else {
                return PEAR::raiseError(null, XPATH_INVALID_INDEX, null, E_USER_NOTICE, "Negative index $in_index is invalid", 'XPath_Error', true);
            }
        }
        $this->pointer = $this->data[$this->index];
        return true;
    }

    // }}}
    // {{{ void    reset() ?

    /**
     * Reset the result index back to the beginning.
     *
     * @access public
     * @return void
     */
    function resetResults()
    {
        $this->index = 0;
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
        $this->result = null; 
        $this->data = null;
    }

    // }}}
}
?>
