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

// Core DOM and internal pointer methods for the Xpath/DOM XML manipulation and query interface.

// }}}
// {{{ hacks

# HACK ID:   Description:
# 1          unlink() causes internal corruption and segfault.  If you just replace_node with
#            empty text node you can emulate the functionality
#
# 2          function is_a() is only defined in php CVS, so I implemented it for the time being
if (!function_exists('is_a')) {
    function is_a($class, $match) 
    {
        if (empty($class)) {
            return false;
        }
        $class = is_object($class) ? get_class($class) : $class;
        if (strtolower($class) == strtolower($match)) {
            return true;
        }
        return is_a(get_parent_class($class), $match);
    }
}

// }}}

// {{{ class XPath_common

/**
 * The XPath_common class contains the DOM functions used to manipulate
 * and maneuver through the xml tree.  The main thing to understand is
 * that all operations work around a single pointer.  This pointer is your
 * place holder within the document.  Each function you run assumes the
 * node in reference is your pointer.  However, every function can take
 * an xpath query or DOM object reference, so that the pointer can be set
 * before working on the node, and can retain this position if specified.
 * Every DOM function call has a init() and shutdown() call.  This function
 * prepares the pointer to the requested location in the tree if an xpath query
 * or pointer object is provided.  In addition, the init() function checks to
 * see that the node type is acceptable for the method, and if not throws an
 * XPath_Error exception.
 *
 * Note: All offsets in the CharacterData interface start from 0.
 *
 * The object model of XPath is as follows (indentation means inheritance):
 *
 * XPath_common The main functionality of the XPath class is here.  This 
 * |            holds all the DOM functions for manipulating and maneuvering
 * |            through the DOM tree.
 * |
 * +-XPath      The frontend for the XPath implementation.  Provides default
 * |            functions for preparing the main document, running xpath queries
 * |            and handling errorMessages.
 * |
 * +-Result     Extended from the XPath_common class, this object is returned when
 *              an xpath query is executed and can be used to cycle through the
 *              result nodeset or data
 *
 * @package  XML/XPath
 * @version  1.1
 * @author   Dan Allen <dan@mojavelinux.com>
 * @since    PHP 4.2
 */

// }}}
class XPath_common {
    // {{{ properties

    /** @var object  current location in xml document */
    var $pointer;

    /** @var object  bookmark used for holding a place in the document */
    var $bookmark;

    /** @var boolean when stepping through nodes, should we skip empty text nodes */
    var $skipBlanks = true;

    // }}}
    // {{{ string  nodeName()

    /**
     * Return the name of this node, depending on its type, according to the DOM recommendation
     *
     * @param  string  $in_xpathQuery (optional) quick xpath query
     * @param  boolean $in_movePointer (optional) move internal pointer with quick xpath query
     *
     * @access public
     * @return string name of node corresponding to DOM recommendation {or XPath_Error exception}
     */
    function nodeName($in_xpathQuery = null, $in_movePointer = false) 
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer))) {
            return $result;
        }
        $nodeName = $this->pointer->node_name();
        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);
        return $nodeName;
    }

    // }}}
    // {{{ int     nodeType()

    /**
     * Returns the integer value constant corresponding to the DOM node type
     *
     * @param  string  $in_xpathQuery (optional) quick xpath query
     * @param  boolean $in_movePointer (optional) move internal pointer with quick xpath query
     *
     * @access public
     * @return int DOM type of the node {or XPath_Error exception}
     */
    function nodeType($in_xpathQuery = null, $in_movePointer = false)
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer))) {
            return $result;
        }
        $nodeType = $this->pointer->node_type();
        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);
        return $nodeType;
    }

    // }}}
    // {{{ boolean parentNode()

    /**
     * Moves the internal pointer to the parent of the current node or returns the pointer.
     *
     * @param  boolean  $in_movePointer (optional) move the internal pointer or return reference 
     *
     * @access public
     * @return boolean whether pointer was moved or object pointer to parent
     */
    function parentNode($in_movePointer = true)
    {
        $parent = $this->pointer->parent(); 
        if ($parent) {
            if ($in_movePointer) {
                $this->pointer = $parent;
                return true;
            }
            else {
                return $parent;
            }
        }
        else {
            return false;
        }
    }

    // }}}
    // {{{ boolean nextSibling()

    /**
     * Moves the internal pointer to the next sibling of the current node, or returns the pointer.
     * If the flag is on to skip blank nodes then the first non-blank node is used.
     *
     * @param  boolean  $in_movePointer (optional) move the internal pointer or return reference 
     *
     * @access public
     * @return boolean whether the pointer was moved or object pointer to next sibling
     */
    function nextSibling($in_movePointer = true)
    {
        if (!$this->pointer->next_sibling()) {
            return false;
        }
        $next = $this->pointer->next_sibling();
        if ($this->skipBlanks) {
            while (true) {
                $next = $next->next_sibling();
                // we have found a non-blank node
                if (!$next->is_blank_node()) {
                    break;
                }
                // we have arrived at the end
                elseif (!$next = $next->next_sibling()) {
                    $next = false;
                    break;
                }
            }
        }
        if ($next) {
            if ($in_movePointer) {
                $this->pointer = $next;
                return true;
            }
            else {
                return $next;
            }
        }
        else {
            return false;
        }
    }

    // }}}
    // {{{ boolean previousSibling()

    /**
     * Moves the internal pointer to the previous sibling 
     * of the current node or returns the pointer.
     * If the flag is on to skip blank nodes then the first non-blank node is used.
     *
     * @param  boolean  $in_movePointer (optional) move the internal pointer or return reference 
     *
     * @access public
     * @return boolean whether the pointer was moved or object pointer to previous sibling
     */
    function previousSibling($in_movePointer = true)
    {
        if (!$this->pointer->previous_sibling()) {
            return false;
        }
        $previous = $this->pointer->previous_sibling();
        if ($this->skipBlanks) {
            while (true) {
                // we have found a non-blank node
                if (!$previous->is_blank_node()) {
                    break;
                }
                // we have arrived at the beginning
                elseif (!$previous = $previous->previous_sibling()) {
                    $previous = false;
                    break;
                }
            }
        }
        if ($previous) {
            if ($in_movePointer) {
                $this->pointer = $previous;
                return true;
            }
            else {
                return $previous;
            }
        }
        else {
            return false;
        }
    }

    // }}}
    // {{{ boolean firstChild()

    /**
     * Moves the pointer to the first child of this node or returns the first node.  
     * If the flag is on to skip blank nodes then the first non-blank node is used.
     *
     * @param  boolean  $in_movePointer (optional) move the internal pointer or return reference 
     *
     * @access public
     * @return boolean whether the pointer was moved to the first child or returns the first child
     */
    function firstChild($in_movePointer = true)
    {
        if (!$this->hasChildNodes()) {
            return false;
        }
        $first = $this->pointer->first_child();
        if ($this->skipBlanks) {
            while (true) {
                // we have found a non-blank node
                if (!$first->is_blank_node()) {
                    break;
                }
                // we have arrived at the end
                elseif (!$first = $first->next_sibling()) {
                    $first = false;
                    break;
                }
            }
        }
        if ($first) {
            if ($in_movePointer) {
                $this->pointer = $first;
                return true;
            }
            else {
                return $first;
            }
        }
        else {
            return false;
        }
    }

    // }}}
    // {{{ boolean lastChild()

    /**
     * Moves the pointer to the last child of this node or returns the last child.
     * If the flag is on to skip blank nodes then the first non-blank node is used.
     *
     * @param  boolean  $in_movePointer (optional) move the internal pointer or return reference 
     *
     * @access public
     * @return boolean whether the pointer was moved to the last child or returns the last child
     */
    function lastChild($in_movePointer = true)
    {
        if (!$this->hasChildNodes()) {
            return false;
        }
        $last = $this->pointer->last_child();
        if ($this->skipBlanks) {
            while (true) {
                // we have found a non-blank node
                if (!$last->is_blank_node()) {
                    break;
                }
                // we have arrived at the beginning
                elseif (!$last = $last->previous_sibling()) {
                    $last = false;
                    break;
                }
            }
        }
        if ($last) {
            if ($in_movePointer) {
                $this->pointer = $last;
                return true;
            }
            else {
                return $last;
            }
        }
        else {
            return false;
        }
    }

    // }}}
    // {{{ boolean isNodeType()

    /**
     * Determines if the node type is equivalent to the node type requested.
     *
     * @param  int     $in_type DOM node type defined by the xml node type constants 
     * @param  string  $in_xpathQuery (optional) quick xpath query
     * @param  boolean $in_movePointer (optional) move internal pointer with quick xpath query
     *
     * @access public
     * @return boolean same type of node {or XPath_Error exception}
     */
    function isNodeType($in_type, $in_xpathQuery = null, $in_movePointer = false)
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer))) {
            return $result;
        }
        $isNodeType = $this->pointer->node_type() == $in_type ? true : false;
        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);
        return $isNodeType;
    }

    // }}}
    // {{{ boolean hasChildNodes()

    /**
     * Returns whether this node has any children.
     *
     * @param  string  $in_xpathQuery (optional) quick xpath query
     * @param  boolean $in_movePointer (optional) move internal pointer
     *
     * @access public
     * @return boolean has child nodes {or XPath_Error exception}
     */
    function hasChildNodes($in_xpathQuery = null, $in_movePointer = false)
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer))) {
            return $result;
        }
        $hasChildNodes = $this->pointer->has_child_nodes() ? true : false;
        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);
        return $hasChildNodes;
    }

    // }}}
    // {{{ boolean hasAttributes()

    /**
     * Returns whether this node has any attributes.
     *
     * @param string  $in_xpathQuery (optional) quick xpath query
     * @param boolean $in_movePointer (optional) move internal pointer
     *
     * @access public
     * @return boolean attributes exist {or XPath_Error exception}
     */
    function hasAttributes($in_xpathQuery = null, $in_movePointer = false)
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer, array(XML_ELEMENT_NODE)))) {
            return $result;
        }
        $hasAttributes = $this->pointer->has_attributes() ? true : false;
        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);
        return $hasAttributes;
    }

    // }}}
    // {{{ boolean hasAttribute()

    /**
     * Returns true when an attribute with a given name is specified on this element 
     * false otherwise.
     *
     * @param string  $in_name name of attribute
     * @param string  $in_xpathQuery (optional) quick xpath query
     * @param boolean $in_movePointer (optional) move internal pointer
     *
     * @access public
     * @return boolean existence of attribute {or XPath_Error exception}
     */
    function hasAttribute($in_name, $in_xpathQuery = null, $in_movePointer = false)
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer, array(XML_ELEMENT_NODE)))) {
            return $result;
        }
        $hasAttribute = false;
        if (!is_array($attributeNodes = $this->pointer->attributes())) {
            $attributeNodes = array();
        }
        foreach($attributeNodes as $attributeNode) {
            if ($attributeNode->name == $in_name) {
                $hasAttribute = true;
                break;
            }
        }
        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);
        return $hasAttribute;
    }

    // }}}
    // {{{ array   getAttributes()

    /**
     * Return an associative array of attribute names as the keys and attribute values as the
     * values.  This is not a DOM function, but is a convenient addition.
     *
     * @param string  $in_xpathQuery (optional) quick xpath query
     * @param boolean $in_movePointer (optional) move internal pointer
     *
     * @access public
     * @return array associative array of attributes {or XPath_Error exception}
     */
    function getAttributes($in_xpathQuery = null, $in_movePointer = false) 
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer, array(XML_ELEMENT_NODE)))) {
            return $result;
        }
        $return = array();
        if (is_array($attributeNodes = $this->pointer->attributes())) {
            foreach($attributeNodes as $attributeNode) {
                $return[$attributeNode->name] = $attributeNode->value;
            }
        }
        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);
        return $return;
    }
 
    // }}}
    // {{{ string  getAttribute()

    /**
     * Retrieves an attribute value by name from the element node at the current pointer.
     *
     * @param string  $in_name Name of the attribute
     * @param string  $in_xpathQuery (optional) quick xpath query
     * @param boolean $in_movePointer (optional) move the internal pointer with quick xpath query
     *
     * @access public
     * @return string value of attribute {or XPath_Error exception}
     */
    function getAttribute($in_name, $in_xpathQuery = null, $in_movePointer = false) 
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer, array(XML_ELEMENT_NODE)))) {
            return $result;
        }
        $result = $this->pointer->get_attribute($in_name);
        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);
        return $result;
    }

    // }}}
    // {{{ boolean setAttribute()

    /**
     * Adds a new attribute. If an attribute with that name is already present 
     * in the element, its value is changed to be that of the value parameter.
     * Invalid characters are escaped.
     *
     * @param string  $in_name name of the attribute to be set
     * @param string  $in_value new attribute value
     * @param string  $in_xpathQuery (optional) quick xpath query
     * @param boolean $in_movePointer (optional) move internal pointer
     *
     * @access public
     * @return void {or XPath_Error exception}
     */
    function setAttribute($in_name, $in_value, $in_xpathQuery = null, $in_movePointer = false)
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer, array(XML_ELEMENT_NODE)))) {
            return $result;
        }
        $this->pointer->set_attribute($in_name, $in_value);
        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);
    }

    // }}}
    // {{{ void    removeAttribute()

    /**
     * Remove the attribute by name.
     *
     * @param  string  $in_name name of the attribute
     * @param  string  $in_xpathQuery (optional) quick xpath query
     * @param  boolean $in_movePointer (optional) move internal pointer
     *
     * @access public
     * @return void {or XPath_Error exception}
     */
    function removeAttribute($in_name, $in_xpathQuery = null, $in_movePointer = false)
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer, array(XML_ELEMENT_NODE)))) {
            return $result;
        }
        $this->pointer->remove_attribute($in_name);
        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);
    }

    // }}}
    // {{{ string  substringData()

    /**
     * Extracts a range of data from the node.  Takes an offset and a count, which are optional
     * and will default to retrieving the whole string.  If an XML_ELEMENT_NODE provided, then
     * it first concats all the adjacent text nodes recusively and works on those.
     * ??? implement wholeText() which concats all text nodes adjacent to a text node ???
     *
     * @param int  $in_offset offset of substring to extract
     * @param int  $in_count length of substring to extract
     * @param string $in_xpathQuery (optional) quick xpath query
     * @param boolean $in_movePointer (optional) move internal pointer
     *
     * @access public
     * @return string substring of the character data {or XPath_Error exception}
     */
    function substringData($in_offset = 0, $in_count = 0, $in_xpathQuery = null, $in_movePointer = false) 
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer, array(XML_TEXT_NODE, XML_ELEMENT_NODE, XML_CDATA_SECTION_NODE, XML_COMMENT_NODE)))) {
            return $result;
        }

        if (!is_int($in_offset) || $in_offset < 0 || $in_offset > strlen($this->pointer->get_content())) {
            $return = PEAR::raiseError(null, XPATH_INDEX_SIZE, null, E_USER_WARNING, "Offset: $in_offset", 'XPath_Error', true);
        }
        elseif (!is_int($in_count) || $in_count < 0) {
            $return = PEAR::raiseError(null, XPATH_INDEX_SIZE, null, E_USER_WARNING, "Count: $in_offset", 'XPath_Error', true);
        }
        else {
            // if this is an element node, concat all the text children recursively
            $return = $in_count ? substr($this->pointer->get_content(), $in_offset, $in_count) : 
                                  substr($this->pointer->get_content(), $in_offset);
        }
        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);
        return $return;
    }

    // }}}
    // {{{ void    insertData()

    /**
     * Will insert data at offset for a text node.
     *
     * @param string  $in_content content to be inserted
     * @param int     $in_offset offset to insert data
     * @param string  $in_xpathQuery (optional) quick xpath query
     * @param boolean $in_movePointer (optional) move internal pointer
     *
     * @access public
     * @return void {or XPath_Error exception}
     */
    function insertData($in_content, $in_offset = 0, $in_xpathQuery = null, $in_movePointer = false)
    {
        return $this->_set_content($in_content, $in_xpathQuery, $in_movePointer, false, $in_offset);
    }

    // }}}
    // {{{ void    deleteData()

    /**
     * Will delete data at offset and for count for a text node.
     *
     * @param int     $in_offset (optional) offset to delete data
     * @param int     $in_count (optional) number of characters to delete
     * @param string  $in_xpathQuery (optional) quick xpath query
     * @param boolean $in_movePointer (optional) move internal pointer
     *
     * @access public
     * @return void {or XPath_Error exception}
     */
    function deleteData($in_offset = 0, $in_count = 0, $in_xpathQuery = null, $in_movePointer = false)
    {
        return $this->_set_content(null, $in_xpathQuery, $in_movePointer, true, $in_offset, $in_count);
    }

    // }}}
    // {{{ void    replaceData()

    /**
     * Will replace data at offset and for count with content
     *
     * @param string  $in_content content to insert
     * @param int     $in_offset (optional) offset to replace data
     * @param int     $in_count (optional) number of characters to replace
     * @param string  $in_xpathQuery (optional) quick xpath query
     * @param boolean $in_movePointer (optional) move internal pointer
     *
     * @access public
     * @return void {or XPath_Error exception}
     */
    function replaceData($in_content, $in_offset = 0, $in_count = 0, $in_xpathQuery = null, $in_movePointer = false)
    {
        return $this->_set_content($in_content, $in_xpathQuery, $in_movePointer, true, $in_offset, $in_count);
    }

    // }}}
    // {{{  void    appendData()

    /**
     * Will append data to end of text node.
     *
     * @param string  $in_content content to append
     * @param string  $in_xpathQuery (optional) quick xpath query
     * @param boolean $in_movePointer (optional) move internal pointer
     *
     * @access public
     * @return void {or XPath_Error exception}
     */
    function appendData($in_content, $in_xpathQuery = null, $in_movePointer = false)
    {
        return $this->_set_content($in_content, $in_xpathQuery, $in_movePointer, false, null);
    }

    // }}}
    // {{{ mixed   replaceChild()

    /**
     * Replaces the old child with the new child.  If the new child is already in the document,
     * it is first removed (not implemented yet).  If the new child is a document fragment, then
     * all of the nodes are inserted in the location of the old child.
     *
     * @param mixed   $in_xmlData document fragment or node
     * @param boolean $in_returnOriginal (optional) clone the old node and return reference to it
     * @param string  $in_xpathQuery (optional) quick xpath query
     * @param boolean $in_movePointer (optional) move internal pointer
     *
     * @access public
     * @return object pointer to old node (optional) {or XPath_Error exception} 
     */
    function replaceChild($in_xmlData, $in_returnOriginal = false, $in_xpathQuery = null, $in_movePointer = false)
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer, array(XML_ELEMENT_NODE, XML_TEXT_NODE, XML_COMMENT_NODE, XML_CDATA_SECTION_NODE, XML_PI_NODE)))) {
            return $result;
        }

        if (XPath::isError($importedNodes = $this->_build_fragment($in_xmlData))) {
            $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);
            return $importedNodes;
        }
        $parent = $this->pointer->parent();
        foreach($importedNodes as $index => $importedNode) {
            $node = $parent->insert_before($importedNode, $this->pointer);
            if ($index == 0) {
                $newNode = $node;
            }
        }
        $oldNode = $this->pointer;
        $this->pointer = $newNode;

        // to save memory we only return the original node if requested
        $return = $in_returnOriginal ? $oldNode->clone_node(1) : null;

        // HACK ID: 1 - get rid of the node (little hack to deal with faulty unlink())
        $oldNode->replace_node($this->xml->create_text_node(''));

        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);

        return $return; 
    }

    // }}}
    // {{{ object  appendChild()

    /**
     * Adds the node or document fragment to the end of the list of children.  If the node
     * is already in the tree it is first removed (not sure if this works yet)
     *
     * @param mixed   $in_xmlData string document fragment or node
     * @param string  $in_xpathQuery (optional) quick xpath query
     * @param boolean $in_movePointer move internal pointer
     *
     * @access public
     * @return object pointer to the first of the nodes appended {or XPath_Error exception}
     * ??? should we allow this if they already have a root for document node ??? *
     */
    function appendChild($in_xmlData, $in_xpathQuery = null, $in_movePointer = false) 
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer, array(XML_ELEMENT_NODE, XML_DOCUMENT_NODE)))) {
            return $result;
        }

        if (XPath::isError($importedNodes = $this->_build_fragment($in_xmlData))) {
            return $importedNodes;
        }
        foreach($importedNodes as $index => $importedNode) {
            $node = $this->pointer->add_child($importedNode);
            if ($index == 0) {
                $newNode = $node;
            }
        }

        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);

        return $newNode;
    }

    // }}}
    // {{{ object  insertBefore()

    /**
     * Inserts the node before the current pointer.
     *
     * @param mixed   $in_xmlData either a document fragment xml string or a node
     * @param string  $in_xpathQuery (optional) quick xpath query
     * @param boolean $in_movePointer (optional) move internal pointer
     *
     * @access public
     * @return object pointer to the first of the new inserted nodes
     */
    function insertBefore($in_xmlData, $in_xpathQuery = null, $in_movePointer = false)
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer, array(XML_ELEMENT_NODE)))) {
            return $result;
        }

        // we do some fance stuff here to make this general...make a fake node
        $importedNodes = $this->_build_fragment($in_xmlData);
        if (XPath::isError($importedNodes)) {
            return $importedNodes;
        }

        $parent = $this->pointer->parent();
        foreach($importedNodes as $index => $importedNode) {
            $node = $parent->insert_before($importedNode, $this->pointer);
            if ($index == 0) {
                $newNode = $node;
            }
        }

        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);

        return $newNode;
    }

    // }}}
    // {{{ object  removeChild()

    /**
     * Removes the child node at the current pointer and returns it (optionally). 
     *
     * @param boolean $in_returnOriginal (optional) return the original node as a clone
     * @param string  $in_xpathQuery (optional) quick xpath query
     * @param boolean $in_movePointer (optional) move internal pointer
     *
     * @access public
     * @return object cloned node of the removed node, ready to be put in another document
     * ??? either put the pointer on the parent or previous sibling, then parent ???
     */
    function removeChild($in_returnOriginal = false, $in_xpathQuery = null, $in_movePointer = false)
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer, array(XML_ELEMENT_NODE, XML_TEXT_NODE, XML_COMMENT_NODE, XML_PI_NODE, XML_CDATA_SECTION_NODE)))) {
            return $result;
        }

        // if we are returning the removedChild, clone it now
        $return = $in_returnOriginal ? $this->pointer->clone_node(1) : true;
        // set the node to be removed
        $removeNode = $this->pointer;
        // set the pointer to the parent (since this node is gone)
        $this->pointer = $this->pointer->parent();
        // HACK ID: 1 - get rid of the node (little hack to deal with faulty unlink())
        $removeNode->replace_node($this->xml->create_text_node(''));

        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);
       
        return $return;
    }

    // }}}
    // {{{ object  replaceChildren()

    /** 
     * Not in the DOM specification, but certainly a convenient function.  Allows you to pass
     * in an xml document fragment which will be parsed into an xml object and merged into the
     * xml document, replacing all the previous children of the node.
     *
     * @param string  $in_fragment xml fragment which will be merged into tree
     * @param string  $in_xpathQuery (optional) quick xpath query
     * @param boolean $in_movePointer (optional) move internal pointer
     *
     * @access public
     * @return {XPath_Error exception}
     */
    function replaceChildren($in_xmlData, $in_xpathQuery = null, $in_movePointer = false)
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer, array(XML_ELEMENT_NODE)))) {
            return $result;
        }
        if (XPath::isError($importedNodes = $this->_build_fragment($in_xmlData))) {
            $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);
            return $importedNodes;
        }
        // nix all the children
        $oldPointer = $this->pointer;
        if (!is_array($attributeNodes = $this->pointer->attributes())) {
            $attributeNodes = array();
        }
        $this->pointer = $this->xml->create_element($this->nodeName());
        foreach ($attributeNodes as $attributeNode) {
            $this->setAttribute($attributeNode->name, $attributeNode->value);
        }
        foreach ($importedNodes as $importedNode) {
            $this->pointer->add_child($importedNode);
        }
        /** should just be able to replace_node here, but doesn't work like it should **/
        $this->pointer = $this->xml->insert_before($this->pointer, $oldPointer);

        // HACK ID: 1 - unlink() just does crazy shit, and this is a nice replacement
        $oldPointer->replace_node($this->xml->create_text_node(''));

        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);
    } 

    // }}}
    // {{{ string  dumpChildren()

    /**
     * Returns all the contents of an element node, regardless of type, as is.
     *
     * @param string  $in_xpathQuery quick xpath query
     * @param boolean $in_movePointer move internal pointer
     *
     * @access public
     * @return string xml string, a concatenation of all the children of the element node
     */
    function dumpChildren($in_xpathQuery = null, $in_movePointer = false) 
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer, array(XML_ELEMENT_NODE)))) {
            return $result;
        }

        $xmlString = trim($this->xml->dump_node($this->pointer));
        $xmlString = substr($xmlString,strpos($xmlString,'>')+1,-(strlen($this->nodeName())+3));
        
        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);

        return $xmlString;
    }

    // }}}
    // {{{ void    toFile()

    /**
     * Exports the xml document to a file.  Only works for the whole document right now.
     *
     * @param  file  $in_file file to export the xml to
     * @param  int   $in_compression (optional) ratio of compression using zlib (0-9)
     *
     * @access public
     * @return void {or XPath_Error exception}
     */
    function toFile($in_file, $in_compression = 0)
    {
        // If the file does not exist, make sure we can write in this directory
        if (!file_exists($in_file)) {
            if (!is_writable(dirname($in_file))) {
                return PEAR::raiseError(null, XPATH_FILE_NOT_WRITABLE, null, E_USER_WARNING, "File: $in_file", 'XPath_Error', true);
            }
        }
        // If the file exists, make sure we can overwrite it
        else {
            if (!is_writable($in_file)) {
                return PEAR::raiseError(null, XPATH_FILE_NOT_WRITABLE, null, E_USER_WARNING, "File: $in_file", 'XPath_Error', true);
            }
        }

        if (!(is_int($in_compression) && $in_compression >= 0 && $in_compression <= 9)) {
            $in_compression = 0;
        }
        $this->xml->dump_mem_file($in_file, $in_compression);
        return true;
    }

    // }}}
    // {{{ string  toString()

    /**
     * Export the xml document to a string, beginning from the pointer.
     *
     * @param string  $in_xpathQuery quick xpath query
     * @param boolean $in_movePointer move internal pointer
     *
     * @access public
     * @return string xml string, starting at pointer
     */
    function toString($in_xpathQuery = null, $in_movePointer = false) 
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer))) {
            return $result;
        }
         
        if ($this->nodeType() == XML_DOCUMENT_NODE) {
            $xmlString = $this->xml->dump_mem();
        }
        else {
            $xmlString = $this->xml->dump_node($this->pointer);
        }

        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);
     
        return $xmlString;
    }

    // }}}
    // {{{ object  getPointer()

    /**
     * Get the current pointer in the xml document.
     *
     * @access public
     * @return object current pointer object
     */
    function getPointer() 
    {
        return $this->pointer;
    }

    // }}}
    // {{{ object  setPointer()

    /**
     * Set the pointer in the xml document
     *
     * @param  object  $in_node node to move to in the xml document
     *
     * @access public
     * @return void {or XPath_Error exception}
     */
    function setPointer($in_node) 
    {
        // if this is an error object, just return it
        if (XPath::isError($in_node)) {
            return $in_node;
        }
        elseif (!$this->_is_dom_node($in_node)) {
            return PEAR::raiseError(null, XPATH_NODE_REQUIRED, null, E_USER_WARNING, $in_node, 'XPath_Error', true);
        }
        else {
            // we are okay, set the node and return true
            $this->pointer = $in_node;
            return true;
        }
    }
    
    // }}}
    // {{{ _build_fragment()

    /**
     * For functions which take a document fragment I have a general way to import the data
     * into a nodeset and then I return the nodeset.  If the xml data was already a node, I
     * just cast it to a single element array so the return type is consistent.
     *
     * @param mixed  $in_xmlData either document fragment string or dom node
     *
     * @access private
     * @return array nodeset array
     */
    function _build_fragment($in_xmlData)
    {
        if ($this->_is_dom_node($in_xmlData)) {
            $fakeChildren = array($in_xmlData);
        }
        else {
            $fake = @xmldoc('<fake>'.$in_xmlData.'</fake>');
            if (!$fake) {
                return PEAR::raiseError(null, XML_PARSE_ERROR, null, E_USER_WARNING, $in_xmlData, 'XPath_Error', true);
            }
            $fakeRoot = $fake->root();
            $fakeChildren = $fakeRoot->children();
        }
        return $fakeChildren;
    }

    // }}}
    // {{{ _set_content()
    
    /**
     * Generic function to handle manipulation of a data string based on manipulation parameters.
     *
     * @param string  $in_content data to be added
     * @param boolean $in_replace method of manipulation
     * @param int     $in_offset offset of manipulation
     * @param int     $in_count length of manipulation
     *
     * @access private
     * @return object XPath_Error on fail
     */
    function _set_content($in_content, $in_xpathQuery, $in_movePointer, $in_replace, $in_offset = 0, $in_count = 0)
    {
        if (XPath::isError($result = $this->_quick_evaluate_init($in_xpathQuery, $in_movePointer, array(XML_TEXT_NODE, XML_CDATA_SECTION_NODE, XML_COMMENT_NODE, XML_PI_NODE)))) {
            return $result;
        }

        $data = $this->pointer->get_content();
        // little hack to get appendData to use this function here...special little exception
        $in_offset = is_null($in_offset) ? strlen($data) : $in_offset;
        if (!is_int($in_offset) || $in_offset < 0 || $in_offset > strlen($data)) {
            $return = PEAR::raiseError(null, XPATH_INDEX_SIZE, null, E_USER_WARNING, "Offset: $in_offset", 'XPath_Error', true);
        }
        elseif (!is_int($in_count) || $in_count < 0) {
            $return = PEAR::raiseError(null, XPATH_INDEX_SIZE, null, E_USER_WARNING, "Count: $in_offset", 'XPath_Error', true);
        }
        else {
            if ($in_replace) {
                $data = $in_count ? substr($data, 0, $in_offset) . $in_content . substr($data, $in_offset + $in_count) : substr($data, 0, $in_offset) . $in_content;
            }
            else {
                $data = substr($data, 0, $in_offset) . $in_content . substr($data, $in_offset);
            }
            $this->pointer->set_content($data);
        }

        $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);

        return $return;
    }

    // }}}
    // {{{ _is_dom_node()

    /**
     * Determines if the provided object is a domnode descendent.
     *
     * @param  object  $in_object object in question
     *
     * @access private
     * @return boolean whether the object is a domnode
     */
    function _is_dom_node($in_object)
    {
        return (is_object($in_object) && is_a($in_object, 'domnode'));
    }

    // }}}
    // {{{ _restore_bookmark()

    /**
     * Restore the internal pointer after a quick query operation
     *
     * @access private
     * @return void
     */
    function _restore_bookmark()
    {
        $this->setPointer($this->bookmark);
    }

    // }}}
    // {{{ _set_bookmark()

    /**
     * Set a temporary bookmark of the internal pointer while doing a quick xpath query
     *
     * @access private
     * @return void
     */
    function _set_bookmark($in_location)
    {
        $this->bookmark = $this->getPointer();
        $this->setPointer($in_location);
    }

    // }}}
    // {{{ _quick_evaluate_init()

    /**
     * The function will allow an on the quick xpath query to move the internal pointer before
     * invoking the xmldom function.  The requirements are that the xpath query must return
     * an XPATH_NODESET and have at least one node.  If not, an XPath_Error will be returned
     * ** In addition this function does a check on the correct nodeType to run the caller method
     *
     * @param string  $in_xpathQuery optional xpath query to move the internal pointer
     * @param boolean $in_movePointer move the pointer temporarily or permanently
     * @param array   $in_nodeTypes required nodeType list for the caller method
     *
     * @access private
     * @return boolean true on success, XPath_Error on error
     */
    function _quick_evaluate_init($in_xpathQuery = null, $in_movePointer = false, $in_nodeTypes = null) 
    {
        if (!is_null($in_xpathQuery)) {
            if ($this->_is_dom_node($in_xpathQuery)) {
                $tmpPointer = $in_xpathQuery;
            } 
            else {
                if (XPath::isError($resultObj = $this->evaluate($in_xpathQuery))) {
                    return $resultObj;
                }
                // make sure we have at least one node
                if (!($resultObj->numResults() && $resultObj->resultType() == XPATH_NODESET)) {
                    return PEAR::raiseError(null, XPATH_INVALID_NODESET, null, E_USER_WARNING, "XPath query: $in_xpathQuery", 'XPath_Error', true);
                }
                $resultObj->nextNode();
                $tmpPointer = $resultObj->getPointer();
            }
            // if we are moving the internal pointer, then do it now
            if ($in_movePointer) {
                $this->setPointer($tmpPointer);
            }
            // set the bookmark if we only want to temporarily move the pointer
            else {
                $this->_set_bookmark($tmpPointer);
            }
        }
        // see if we have a restricted nodeType requirement
        if (is_array($in_nodeTypes) && !in_array($this->nodeType(), $in_nodeTypes)) {
            $this->_quick_evaluate_shutdown($in_xpathQuery, $in_movePointer);
            return PEAR::raiseError(null, XPATH_INVALID_NODETYPE, null, E_USER_WARNING, "Required type: ".implode(" or ", $in_nodeTypes).", Provided type: ".$this->nodeType(), 'XPath_Error', true);
        }
        else {
            return true;
        }
    }

    // }}}
    // {{{ _quick_evaluate_shutdown()

    /**
     * Restore the internal pointer if there was no request to permanently move it with the
     * quick query.  This should never return error because it is only called if the init was
     * successful.
     *
     * @param string  $in_xpathQuery options quick xpath query to execute
     * @param boolean $in_movePointer allow quick query to move the internal pointer
     *
     * @access private
     * @return boolean always true
     */
    function _quick_evaluate_shutdown($in_xpathQuery = null, $in_movePointer = false)
    {
        // if we did quick xpath query and didn't want to move 
        // our internal pointer, then reset it
        if (!is_null($in_xpathQuery) && !$in_movePointer) {
            $this->_restore_bookmark();
        }
        return true;
    }

    // }}}
}
?>
