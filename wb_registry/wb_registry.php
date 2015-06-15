<?php

/*
	this class replaces wb_get_registry_key and wb_set_registry_key
	
	keys can be any of the following,
	
	"HKCU" or "HKEY_CURRENT_USER"
	"HKLM" or "HKEY_LOCAL_MACHINE"
	"HKCR" or "HKEY_CLASSES_ROOT"
	"HKU" or "HKEY_USERS"
	"HKCC" or "HKEY_CURRENT_CONFIG"
	"HKD" or "HKEY_DYN_DATA"

	get_key($key, $sub_key, $entry)
		$key -> string -> see above
		$sub_key -> string -> registry sub key
		$entry -> string -> entry to get, null for default
		
	set_key($key, $sub_key, $entry, $value = null)
		$key -> string -> see above
		$sub_key -> string -> registry sub key
		$entry -> string -> entry to set, null for default
		$value -> string -> value to set, null removes entry

*/

class wb_registry
{
	public function get_key($key, $sub_key, $entry)
	{
		$key		= wb_registry::clean_reg_key( $key );
		$sub_key	= wb_registry::clean_sub_key( $sub_key );
		$entry		= wb_registry::clean_reg_entry( $entry );
		$cmnd		= 'reg query "'.$key.'\\'.$sub_key.'" ';
		$cmnd		.= ( empty( $entry ) ) ? '/ve' : '/v "'.$entry.'"' ;
		$cmnd		.= ' 2> nul';
		
		exec( $cmnd, $output, $return_var );
		
		if ( $return_var )
			return NULL;
		
		$p = preg_split( '/\s\s+/', trim( $output[2] ) );
		return $p[2];
	}
	
	public function set_key($key, $sub_key, $entry, $value = null)
	{
		if ( $value === null )
			return remove_key($key, $sub_key, $entry);
		
		$key		= wb_registry::clean_reg_key( $key );
		$sub_key	= wb_registry::clean_sub_key( $sub_key );
		$entry		= wb_registry::clean_reg_entry( $entry );
		$value		= wb_registry::clean_reg_value( $value );
		$cmnd		= 'reg add "'.$key.'\\'.$sub_key.'" /d "'.$value.'" /f ';
		$cmnd		.= ( empty( $entry ) ) ? '/ve' : '/v "'.$entry.'"' ;
		$cmnd		.= ' 2> nul';
		
		exec( $cmnd, $output, $return_var );
		return ($return_var) ? false : true ;
	}
	
	public function remove_key($key, $sub_key, $entry)
	{
		$key		= wb_registry::clean_reg_key( $key );
		$sub_key	= wb_registry::clean_sub_key( $sub_key );
		$entry		= wb_registry::clean_reg_entry( $entry );
		$cmnd		= 'reg delete "'.$key.'\\'.$sub_key.'" /f ';
		$cmnd		.= ( empty( $entry ) ) ? '/ve' : '/v "'.$entry.'"';
		$cmnd		.= ' 2> nul';
		
		exec( $cmnd, $output, $return_var );
		return ($return_var) ? false : true ;
	}
	
	private function clean_reg_value( $value )
	{
		return str_replace( array('%', '"'), array('', '\\"'), $value );
	}
	
	private function clean_reg_entry( $entry )
	{
		return str_replace( array('%', '"'), array('', '\\"'), $entry );
	}
	
	private function clean_sub_key( $sub_key )
	{
		return str_replace( array('%', '"', '//'), array('', '\\"', '/'), $sub_key );
	}
	
	private function clean_reg_key( $key )
	{
		$key = strtoupper( $key );
		
		$valid_keys = array(
			'HKCU',		'HKEY_CURRENT_USER',
			'HKLM',		'HKEY_LOCAL_MACHINE',
			'HKCR',		'HKEY_CLASSES_ROOT',
			'HKU',		'HKEY_USERS',
			'HKCC',		'HKEY_CURRENT_CONFIG',
			'HKD',		'HKEY_DYN_DATA'
		);
		
		return ( in_array($key, $valid_keys ) ) ? $key : false ;
	}
}

?>