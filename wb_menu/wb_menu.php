<?PHP

/*
	*** Requires the wb_dll_address() function ***
	
	this class replaces the menu control in winbinder
	it allows sub menus and also allows changing options of item menus
	you can now also build pop up menus
	
	__construct($window, $is_popup = false, $data = false)
		$window -> int -> id of window to insert the menu
		$is_popup -> bool -> is this a pop up menu
		$data -> array -> see examples
		
	add_menu( $parent, $label );
		$parent -> int -> id of parent menu, 0 for root
		$label -> string -> label shown in parent menu
		returns -> int -> id of this menu
	
	add_item( $parent, $label, $id, $checked = false, $disabled = false, $menubreak = false );
		$parent -> int -> id of parent menu
		$label -> string -> label shown in parent menu
		$id -> int -> unique number used when processing menu
		returns -> int -> position of this item in the parent menu
	
	add_separator( $parent );
		$parent -> int -> id of parent menu
		returns -> int -> position of this item in the parent menu
		
	build( );
		returns -> nothing
		
	check_item( $menu, $item, $check = true ); [Not for popup menus]
		$menu -> int -> id of menu that contains item
		$item -> int -> position of item in menu, 0 based
		$checked -> bool -> true = checked, false = not checked
		returns -> nothing
		
	disable_item( $menu, $item, $enabled = true ); [Not for popup menus]
		$menu -> int -> id of menu that contains item
		$item -> int -> position of item in menu, 0 based
		$checked -> bool -> true = disabled, false = enabled
		returns -> nothing
		
	findItem( $menu, $label );
		$menu -> int -> id of menu that contains item
		$label -> string -> label of menu item
		returns -> int -> position of the first match in the parent menu
		
	item_image( $parent, $item, $img )
		$parent -> int -> id of parent menu
		$item -> int -> position of item in menu, 0 based
		$img -> string -> image to use
		returns -> nothing
		
*/

class wb_menu
{
	private $cmds		= array();
	
	public $menuMap 	= array();
	
	public $itemMap		= array();
	
	private $iId		= 0;
	
	private $isBuilt	= false;
	
	private $isPopup	= false;
	
	private $window;
	
	private $images		= array();
	
	public function __construct($window, $is_popup = false, $data = false)
	{
		$this->cmds[] = $this->iId++;
		
		$whwnd = unpack('Vhwnd', wb_peek($window, 72));
		$this->window = $whwnd['hwnd'];
		
		$this->isPopup = $is_popup;
		
		if ( $data )
		{
			$this->build_from_array( 0, $data );
			$this->build();
		}
	}
	
	public function __destruct()
	{
		if ( $this->isBuilt )
		{
			wb_call_function( wb_dll_address('USER', 'DestroyMenu'), array($this->menuMap[0]) );
		}
		
		if( !empty( $this->images ) )
		{
			foreach( $this->images as $i )
			{
				wb_destroy_image( $i );
			}
		}
	}
	
	private function build_from_array( $parent, $data )
	{
		foreach( $data as $a )
		{
			if( $a[1] === NULL )
			{
				$this->add_separator($parent);
			}
			elseif ( is_array( $a[1] ) )
			{
				$k = $this->add_menu($parent, $a[0]);
				$this->build_from_array( $k, $a[1] );
			}
			else
			{
				$this->add_item($parent, $a[0], $a[1]);
			}
		}
	}
	
	public function add_menu($parent, $label)
	{
		$k = $this->iId++;
		
		if ( $this->isBuilt )
		{
			$this->menuMap[$k] = wb_call_function( wb_dll_address("USER", "CreateMenu") );
			wb_call_function( wb_dll_address("USER", "InsertMenu"), array( $this->menuMap[$parent], -1, 0x00000400 | 0x00000010, $this->menuMap[$k], $label) );
			wb_call_function( wb_dll_address("USER", "DrawMenuBar"), array($this->window) );
		}
		else
		{
			$this->cmds[] = '$this->menuMap[' . $k . '] = wb_call_function( wb_dll_address("USER", "CreateMenu") );';
			$this->cmds[] = 'wb_call_function( wb_dll_address("USER", "InsertMenu"), array( $this->menuMap[' . $parent . '], -1, 0x00000400 | 0x00000010, $this->menuMap[' . $k . '], "'.str_replace('"', '\"', $label).'") );';
		}
		
		$this->itemMap[$k] = array();
		$this->itemMap[$parent][] = $label;
		return $k;
	}
	
	public function add_item($parent, $label, $id)
	{
		if ( $this->isBuilt )
		{
			wb_call_function( wb_dll_address("USER", "InsertMenu"), array( $this->menuMap[$parent], -1, 0x00000400 | 0x00000000, $id, $label) );
			wb_call_function( wb_dll_address("USER", "DrawMenuBar"), array($this->window) );
		}
		else
			$this->cmds[] = 'wb_call_function( wb_dll_address("USER", "InsertMenu"), array( $this->menuMap[' . $parent . '], -1, 0x00000400 | 0x00000000, '.$id.', "'.str_replace("'", "\'", $label).'") );';
		
		$this->itemMap[$parent][] = $label;
		return count( $this->itemMap[$parent] ) - 1;
	}
	
	public function remove_item($parent, $item)
	{
		if ( !$this->isBuilt || $this->isPopup )
			return;
		
		wb_call_function( wb_dll_address("USER", "DeleteMenu"), array($this->menuMap[$parent], $item, 0x00000400) );
		wb_call_function( wb_dll_address("USER", "DrawMenuBar"), array($this->window) );
		unset( $this->itemMap[$parent][$item] );
		$this->itemMap[$parent] = array_values( $this->itemMap[$parent] );
	}
	
	public function add_separator($parent)
	{
		if ( $this->isBuilt )
		{
			wb_call_function( wb_dll_address("USER", "InsertMenu"), array( $this->menuMap[$parent], -1, 0x00000400 | 0x00000800, NULL, NULL) );
			wb_call_function( wb_dll_address("USER", "DrawMenuBar"), array($this->window) );
		}
		else
			$this->cmds[] = 'wb_call_function( wb_dll_address("USER", "InsertMenu"), array( $this->menuMap[' . $parent . '], -1, 0x00000400 | 0x00000800, NULL, NULL) );';
		
		$this->itemMap[$parent][] = '*separator*';
		return count( $this->itemMap[$parent] ) - 1;
	}
	
	public function item_image( $parent, $item, $img )
	{
		$h = wb_load_image( $img );
		
		if ( $this->isBuilt )
		{
			wb_call_function( wb_dll_address("USER", "SetMenuItemBitmaps"), array( $this->menuMap[$parent], $item, 0x00000400, $h, $h) );
			wb_call_function( wb_dll_address("USER", "DrawMenuBar"), array($this->window) );
		}
		else
			$this->cmds[] = 'wb_call_function( wb_dll_address("USER", "SetMenuItemBitmaps"), array( $this->menuMap[' . $parent . '], '.$item.', 0x00000400, '.$h.', '.$h.') );';
		
		$this->images[] = $h;
	}
	
	public function build()
	{
		if ( $this->isBuilt )
			return;
		
		if ( $this->isPopup )
			$this->cmds[0] = '$this->menuMap[0] = wb_call_function( wb_dll_address("USER", "CreatePopupMenu") );';
		else
			$this->cmds[0] = '$this->menuMap[0] = wb_call_function( wb_dll_address("USER", "CreateMenu") );';
		
		foreach( $this->cmds as $c ) {
			//echo $c."\r\n";
			eval( $c );
		}
		
		if ( $this->isPopup )
		{
			$data = pack( 'l2', 0, 0 );
			wb_call_function( wb_dll_address('USER', 'GetCursorPos'), array($data));
			$cords = unpack( "lx/ly", $data );
			wb_call_function( wb_dll_address('USER', 'TrackPopupMenu'), array( $this->menuMap[0], 0x0000 | 0x0000, $cords['x'], $cords['y'], 0, $this->window, NULL ));
		}
		else
		{
			wb_call_function( wb_dll_address('USER', 'SetMenu'), array($this->window, $this->menuMap[0]) );
		}
		
		$this->isBuilt = true;
	}
	
	public function findItem( $menu, $label )
	{
		if ( !isset($this->itemMap[$menu] ) )
			return FALSE;
		
		return array_search( $label, $this->itemMap[$menu] );
	}
	
	public function get_real_handle( $key )
	{
		return ( isset( $this->menuMap[$key] ) ) ? $this->menuMap[$key] : false ;
	}
	
	public function check_item( $menu, $item, $check = true )
	{
		if ( $this->isPopup )
			return;
		
		if ( $this->isBuilt )
		{
			$menu = $this->get_real_handle( $menu );
			
			if( $check )
				wb_call_function( wb_dll_address('USER', 'CheckMenuItem'), array($menu, $item, 0x00000400 | 0x00000008 ) );
			else
				wb_call_function( wb_dll_address('USER', 'CheckMenuItem'), array($menu, $item, 0x00000400 | 0x00000000 ) );
		}
		else
		{
			if( $check )
				$this->cmds[] = 'wb_call_function( wb_dll_address("USER", "CheckMenuItem"), array($this->get_real_handle('.$menu.'), '.$item.', 0x00000400 | 0x00000008 ) );';
			else
				$this->cmds[] = 'wb_call_function( wb_dll_address("USER", "CheckMenuItem"), array($this->get_real_handle('.$menu.'), '.$item.', 0x00000400 | 0x00000000 ) );';
			
		}
	}
	
	public function enable_item( $menu, $item, $enabled = true )
	{
		if ( $this->isPopup )
			return;
		
		if ( $this->isBuilt )
		{
			$menu = $this->get_real_handle( $menu );
			
			if( $enabled )
				wb_call_function( wb_dll_address('USER', 'EnableMenuItem'), array($menu, $item, 0x00000400 | 0x00000000 ) );
			else
				wb_call_function( wb_dll_address('USER', 'EnableMenuItem'), array($menu, $item, 0x00000400 | 0x00000002 ) );
		}
		else
		{
			if( $enabled )
				$this->cmds[] = 'wb_call_function( wb_dll_address("USER", "EnableMenuItem"), array($this->get_real_handle('.$menu.'), '.$item.', 0x00000400 | 0x00000000 ) );';
			else
				$this->cmds[] = 'wb_call_function( wb_dll_address("USER", "EnableMenuItem"), array($this->get_real_handle('.$menu.'), '.$item.', 0x00000400 | 0x00000002 ) );';
			
		}
	}
}

// required helper function for wb_menu
if ( !function_exists('wb_dll_address'))
{
	function wb_dll_address( $dll, $function )
	{
		global $winbinderHelperDllAddresses;
	
		if ( !isset( $winbinderHelperDllAddresses[$dll] ) )
			$dll = preg_replace('/.DLL$/', '', strtoupper( $dll ) );
	
		if ( !isset( $winbinderHelperDllAddresses[$dll] ) )
			$winbinderHelperDllAddresses[$dll]['lib'] = wb_load_library($dll);
	
		if( $winbinderHelperDllAddresses[$dll]['lib'] === NULL )
			return false;
	
		if ( !isset( $winbinderHelperDllAddresses[$dll][$function] ) )
			$winbinderHelperDllAddresses[$dll][$function] = wb_get_function_address($function, $winbinderHelperDllAddresses[$dll]['lib']);
	
		if ( $winbinderHelperDllAddresses[$dll][$function] === NULL )
			return false;
	
		return $winbinderHelperDllAddresses[$dll][$function];
	}
}

?>