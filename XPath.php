<?php
// {{{ licence

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

// Xpath/DOM XML manipulation and query interface.

// }}}
// {{{ error codes

/*
 * Error codes for the XPath interface, which will be mapped to textual messages
 * in the XPath::errorMessage() function.  If you are to add a new error code, be
 * sure to add the textual messages to the XPath::errorMessage() function as well
 */

define("XPATH_OK",                      1);
define("XPATH_ERROR",                  -1);
define("XPATH_ALREADY_EXISTS",         -2);
define("XPATH_INVALID_DOCUMENT",       -3);
define("XPATH_INVALID_QUERY",          -4);
define("XPATH_NO_DOM",                 -5);
define("XPATH_INVALID_INDEX",          -6);
define("XPATH_INVALID_NODESET",        -7);
define("XPATH_NOT_LOADED",             -8);
define("XPATH_INVALID_NODETYPE",       -9);
define("XPATH_FILE_NOT_WRITABLE",     -10);
define("XPATH_NODE_REQUIRED",         -11);
define("XPATH_INDEX_SIZE",            -12);
define("XML_PARSE_ERROR",             -13);

// }}}
// {{{ includes

require_once "PEAR.php";
require_once "XPath/common.php";
require_once "XPath/result.php";

// }}}

// {{{ class XPath

/**
 * The main "XPath" class is simply a container class with some methods for
 * creating DOM xml objects and preparing error codes
 *
 * @package  XML/XPath
 * @version  1.1
 * @author   Dan Allen <dan@mojavelinux.com>
 * @since    PHP 4.2
 */

// }}}
class XPath extends XPath_common {
    // {{{ properties

    /** @var object xml data object */
    var $xml;

    /** @var object xpath context object for the xml data object */
    var $ctx;

    /** @var object current location in the xml document */
    var $pointer;
 
    /** @var boolean determines if we have loaded a document or not */
    var $loaded = false;

    // }}}
    // {{{ constructor

    function Xpath($in_xml = null) 
    {
        // load the xml document if passed in here
        // if not defined, require load() to be called
        if (!is_null($in_xml)) {
            $this->load($in_xml);
        }
    }

    // }}}
    // {{{ void    load()

    /**
     * Load the xml document on which we will execute queries and modifications.  This xml
     * document can be loaded from a previously existing xmldom object, a string or a file.
     * On successful load of the xml document, a new xpath context is created so that queries
     * can be done immediately.
     *
     * @param  mixed   $in_xml xml document, in one of 3 forms (object, string or file)
     *
     * @access public
     * @return void {or XPath_Error exception}
     */
    function load($in_xml) 
    {
        // if we already have a document loaded, then throw a warning
        if ($this->loaded) {
            return PEAR::raiseError(null, XPATH_ALREADY_EXISTS, null, E_USER_WARNING, $this->xml->root(), 'XPath_Error', true);
        }
        // in this case, we already have an xmldom object
        if (is_class_type($in_xml, 'domdocument')) {
            $this->xml = $in_xml;
        }
        // we can read the file, so use xmldocfile to make a xmldom object
        elseif(file_exists($in_xml) && is_readable($in_xml)) {
            $this->xml = @xmldocfile($in_xml);
        }
        // this is a string, so attempt to make an xmldom object from string
        elseif(is_string($in_xml)) {
            $this->xml = @xmldoc($in_xml);
        }
        // not a valid xml instance, so throw error
        else {
            return PEAR::raiseError(null, XPATH_INVALID_DOCUMENT, null, E_USER_ERROR, "The xml data '$in_xml' could not be parsed to xml dom", 'XPath_Error', true);
        }
        // make sure a xmldom object was created, and if so initialized the state
        if (is_class_type($this->xml, 'domdocument')) {
            $this->loaded = true;
            $this->ctx = xpath_new_context($this->xml);    
            $this->pointer = $this->xml->root();
            return true;
        }
        // we could not make a xmldom object, so throw an error
        else {
            return PEAR::raiseError(null, XPATH_NO_DOM, null, E_USER_ERROR, "A DomDocument could not be instantiated from '$in_xml'", 'XPath_Error', true);
        }
    }
 
    // }}}
    // {{{ mixed   getOne()

    /**
     * A quick version of the evaluate, where the results are returned immediately.  If the result
     * of the xpath expression is a node-set, it just returns the size of the nodeset array.
     * Otherwise, it returns the scalar value.
     *
     * @param  string  $in_xpathQuery xpath query
     *
     * @access public
     * @return mixed number of nodes or value of scalar result {or XPath_Error exception}
     */
    function getOne($in_xpathQuery, $in_movePointer = false)
    {
        // Execute the xpath query and return the results, then reset the result index
        if (XPath::isError($result = $this->evaluate($in_xpathQuery, $in_movePointer))) {
            return $result;
        }
        return $result->getData();
    }
  
    // }}}
    // {{{ void    evaluate()

    /**
     * Evaluate the xpath expression on the loaded xml document.  An XPath_Result object is
     * returned which can be used to manipulate the results
     *
     * @param  string  $in_xpathQuery xpath query
     * @param  boolean $in_movePointer (optional) move internal pointer
     *
     * @access public
     * @return void {or XPath_Error exception}
     */
    function evaluate($in_xpathQuery, $in_movePointer = false) 
    {
        // Make sure we have loaded an xml document and were able to create an xpath context
        if (!is_class_type($this->ctx, 'xpathcontext')) {
            return PEAR::raiseError(null, XPATH_NOT_LOADED, null, E_USER_ERROR, null, 'XPath_Error', true);
        }
        if (!$result = @xpath_eval($this->ctx, $in_xpathQuery)) {
            return PEAR::raiseError(null, XPATH_INVALID_QUERY, null, E_USER_WARNING, "XPath query: $in_xpathQuery", 'XPath_Error', true);
        }
        $resultObj = new XPath_result($result, $in_xpathQuery, $this->xml, $this->ctx);

        if ($in_movePointer && $resultObj->resultType() == XPATH_NODESET && $resultObj->numResults()) {
            $this->setPointer($resultObj->getPointer());
        }
        return $resultObj;
    }

    // }}}
    // {{{ boolean isError()

    /**
     * Tell whether a result code from a XPath method is an error.
     *
     * @param  object  $in_value object in question
     *
     * @access public
     * @return boolean whether object is an error object
     */
    function isError($in_value)
    {
        return is_class_type($in_value, 'xpath_error');
    }

    // }}}
    // {{{ mixed   errorMessage()

    /**
     * Return a textual error message for an XPath error code.
     *
     * @param  int $in_value error code
     *
     * @access public
     * @return string error message, or false if not error code
     */
    function errorMessage($in_value) 
    {
        // make the variable static so that it only has to do the defining on the first call
        static $errorMessages;

        // define the varies error messages
        if (!isset($errorMessages)) {
            $errorMessages = array(
                XPATH_OK                    => 'no error',
                XPATH_ERROR                 => 'unknown error',
                XPATH_ALREADY_EXISTS        => 'xml document already loaded',
                XPATH_INVALID_DOCUMENT      => 'invalid xml document',
                XPATH_INVALID_QUERY         => 'invalid xpath query',
                XPATH_NO_DOM                => 'DomDocument could not be instantiated',
                XPATH_INVALID_INDEX         => 'invalid index',
                XPATH_INVALID_NODESET       => 'requires nodeset and one of appropriate type',
                XPATH_NOT_LOADED            => 'DomDocument has not been loaded',
                XPATH_INVALID_NODETYPE      => 'invalid nodetype for requested feature',
                XPATH_FILE_NOT_WRITABLE     => 'file could not be written',
                XPATH_NODE_REQUIRED         => 'DomNode required for operation',
                XPATH_INDEX_SIZE            => 'index given out of range',
                XML_PARSE_ERROR             => 'parse error in xml string'
            );  
        }

        // If this is an error object, then grab the corresponding error code
        if (XPath::isError($in_value)) {
            $in_value = $in_value->getCode();
        }
        
        // return the textual error message corresponding to the code
        return isset($errorMessages[$in_value]) ? $errorMessages[$in_value] : $errorMessages[XPATH_ERROR];
    }

    // }}}
    // {{{ void    reset()

    /**
     * Resets the object so it is possible to load another xml document.
     *
     * @access public
     * @return void
     */
    function reset()
    {
        $this->xml = null;
        $this->ctx = null;
        $this->pointer = null;
        $this->loaded = false;
    }

    // }}}
}

// {{{ class XPath_Error

/**
 * Error class for the XPath interface, which just prepares some variables and spawns PEAR_Error
 *
 * @package  XML/XPath
 * @version  1.1
 * @author   Dan Allen <dan@mojavelinux.com>
 * @since    PHP 4.2
 */

// }}}
class XPath_Error extends PEAR_Error {
    // {{{ properties 

    /** @var string prefix of all error messages */
    var $error_message_prefix = 'XPath Error: ';
    
    // }}}
    // {{{ constructor

    /**
    * Creates an xpath error object, extending the PEAR_Error class
    *
    * @param int   $code the xpath error code
    * @param int   $mode the reaction to the error, either return, die or trigger/callback
    * @param int   $level intensity of the error (PHP error code)
    * @param mixed $debuginfo any information that can inform user as to nature of the error
    */
    function XPath_Error($code = XPATH_ERROR, $mode = PEAR_ERROR_RETURN, 
                         $level = E_USER_NOTICE, $debuginfo = null) 
    {
        if (is_int($code)) {
            $this->PEAR_Error(XPath::errorMessage($code), $code, $mode, $level, $debuginfo);
        } 
        else {
            $this->PEAR_Error("Invalid error code: $code", XPATH_ERROR, $mode, $level, $debuginfo);
        }
    }
    
    // }}}
}
?>
