<?php

////  Function:   xml2array  ///////////////////////////////////////
/**
 *    Converts an XML string into an array structure for easier use.
 *
 *    @param   mixed    $p_xml_string     XML String to parse
 *    @param   mixed    $p_list_elements  Array of XML elements that
 *                                        should be treated as a list
 *                                        Ex. If more than one <user>
 *                                        element exists, pass
 *                                        array('user') so that they
 *                                        will not overwrite each other
 *    @return  mixed                      Array
 */
//////////////////////////////////////////////////////////////////////

function xml2array($p_xml_str, $p_list_elements = array(), $force = false) {

   if (!$force) {		/* MedDream does not ensure this format when writing own settings.xml! */
	   /* Check whether we have a valid file beginning as the parser is
		  unable to produce a comprehensive message on its own. This will
		  help to troubleshoot a case when language.xml contains accented
		  characters but wasn't saved as UTF-8 (such an XML is often
		  fundamentally wrong and the parser will fail on it).
        */
	   $fb_bin = substr($p_xml_str, 0, 8);
	   if ($fb_bin != pack('C*', 0xEF, 0xBB, 0xBF, 0x3C, 0x3F, 0x78, 0x6D, 0x6C))
       {
	      trigger_error("XML encoded in UTF-8 with BOM is required. First bytes are " .
	         strtoupper(chunk_split(@array_shift(unpack('H*', $fb_bin)), 2, ' ')) . ".",
	         E_USER_WARNING);
          return false;
       }
   }

   // create parser
   if (!extension_loaded('xml')) // avoid calling non-existent functions
   {
      trigger_error('Missing PHP extension "XML"', E_USER_WARNING);
      return false;
   }
   $l_parser = xml_parser_create('UTF-8');
   xml_parser_set_option($l_parser, XML_OPTION_CASE_FOLDING, 0);
   xml_parser_set_option($l_parser, XML_OPTION_SKIP_WHITE, 1);
   xml_parse_into_struct($l_parser, $p_xml_str, $l_values, $l_tags);
   $e = xml_get_error_code($l_parser);
   if ($e)
      return false;	/* won't display the error message as it is confusing in most occasions */

   xml_parser_free($l_parser);

   // we store our path here
   $l_hash_stack = array();

   // this is our target
   $l_ret = array();
   foreach ($l_values as $l_key => $l_val) {

      switch ($l_val['type']) {

         case 'open':
            array_push($l_hash_stack, $l_val['tag']);
            if (isset($l_val['attributes']))
               $l_ret = xml_compose_array(
                           $p_list_elements, $l_ret, $l_hash_stack,
                           $l_val['attributes']);
            else
               $l_ret = xml_compose_array($p_list_elements, $l_ret,
                        $l_hash_stack);
            break;

         case 'close':
            array_pop($l_hash_stack);
            break;

         case 'complete':
            array_push($l_hash_stack, $l_val['tag']);
			if (array_key_exists('value', $l_val))
				$val = $l_val['value'];
			else
				$val = NULL;
            $l_ret = xml_compose_array($p_list_elements, $l_ret,
                     $l_hash_stack, $val);
            array_pop($l_hash_stack);

            // handle attributes
            if (isset($l_val['attributes'])) {

               while(list($l_a_k,$l_a_v) = each($l_val['attributes'])) {
                  $l_hash_stack[] = $l_val['tag'] . "_attribute_"
                                       . $l_a_k;
                  $l_ret = xml_compose_array($p_list_elements, $l_ret,
                              $l_hash_stack, $l_a_v);
                  array_pop($l_hash_stack);
               }

            }

         break;

      }  // end switch

   }  // end foreach

   return prepareArray($l_ret, $l_ret);
}

function prepareArray(&$array, &$element)
{
	if (is_array($element))
	foreach ($element as $key => $value)
	{
		if ((is_array($element[$key])) && (sizeof($element[$key])) == 1)
			$element[$key] = $element[$key][0];

		if (is_array($element[$key]))
			prepareArray($array, $element[$key]);
	}
	return $array;
}

////  Function:   xml_compose_array  ///////////////////////////////
/**
 *    Used by xml2array for building the parsed array structure
 *
 *    @param
 */
//////////////////////////////////////////////////////////////////////

function xml_compose_array($p_list_elements, $p_array, $p_elements,
                                 $p_value = array()) {

   // get current element
   $l_element = array_shift($p_elements);

   // does the current element refer to a list
   if (1){
//   if (in_array($l_element, $p_list_elements)){
      // more elements?
      if (sizeof($p_elements) > 0)
         $p_array[$l_element][sizeof(@$p_array[$l_element]) - 1] =
            xml_compose_array($p_list_elements,
               @$p_array[$l_element][sizeof(@$p_array[$l_element]) - 1],
               $p_elements, $p_value);
      else
         $p_array[$l_element][sizeof(@$p_array[$l_element])] = $p_value;
   }
   else {
      // more elements?
      if (sizeof($p_elements) > 0)
         $p_array[$l_element] = xml_compose_array($p_list_elements,
            @$p_array[$l_element], $p_elements, $p_value);
      else
         $p_array[$l_element] = $p_value;
   }

   return $p_array;

}

//////////////////////////////////////////////////////////////////////

?>
