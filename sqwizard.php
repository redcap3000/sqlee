<?php
/**
 * sqlee v3  - Sqwizard (PHP)
  
 * @author		Ronaldo Barbachano http://www.redcapmedia.com
 * @copyright  (c) May 2011
 * @license		http://www.fsf.org/licensing/licenses/agpl-3.0.html
 * @link		http://www.myparse.org
 
 sqwizard - form wizard 
 
 Use to create arguments for sqlee record editors! Also be aware of the alternative modes.
 
 By default sqlee will create a record selector with edit/delete buttons for each row.
 
 If you add a '2' to the end of the function call 
 	
 	$form->record_editor('id&&+~block_name&&+~block_template&&+~status&&required','mp_blocks&&id--id--block_name--status',2)
 
 You can create an insert-only form
 
 If you add a 'NULL' you will have a 'new record' form beneath the list of records.
 	
 	$form->record_editor('id&&+~block_name&&+~block_template&&+~status&&required','mp_blocks&&id--id--block_name--status',NULL)
 
 Also if you remove the duplicate field (which is the pkey) from the second argument you can disable the edit/delete buttons.

	$form->record_editor('id&&+~block_name&&+~block_template&&+~status&&required','mp_blocks&&id--block_name--status')
 And if you need to filter (think sql where clause without the where) the records that appears in the record list, add another parameter to the end of your full call
 	$form->record_editor('id&&+~block_name&&+~block_template&&+~status&&required','mp_blocks&&id--id--block_name--status',NULL,"block_name = 'block'")
 
*/
 
// FILL OUT DB STRING with your INFO!
//$db_connx = ''; You can either pass sqwizard a mysqli object, or a string containing mysqli login info in the order which the function needs it.
$wizard = new sqwizard($db_connx);
$wizard->form_wizard();
echo
'<html>
	<head>
		<title>Sqlee Sqwizard</title>
	</head>
	<body>	
		'.$wizard->html .'</body>
</html>';


class sqwizard{
	function __construct($db=NULL){
	// load sqlee.php (hope there is a valid db connx! make new object and use the band_aid method couldn't get auto load to work properly :/
		if(is_string($db)){
			$this->db = mysqli_init();
			$db = explode(' ',$db);
			if (!$this->db)     die('mysqli_init failed');
			if (!mysqli_real_connect($this->db, $db[0], $db[1], $db[2],$db[3])) die('Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
			unset($db);
		}elseif($db==NULL || $db=='') {
		
			die("\n<h1>Please use valid mysqli object</h1>");
		}
		
		;	
		
	}
	// my down and dirty band_aid mysqli function that supports several modes, insert with returned ID, return an object, or return
	// a mysqli resource (to let another loop iterate through)
	private function band_aid($query,$mode=NULL){
		$this->query_count += 1;
		if ($mode == 2) {
		// mode 2 inserts query and returns the id of the inserted record
			mysqli_query($this->db,$query);
			return mysqli_insert_id($this->db);
		}elseif($mode >2){
		// mode '3' returns true false
			$result = mysqli_query($this->db,$query);
			if($mode>3)
			// mode '4' returns array with associative array
				while($row = mysqli_fetch_assoc($result)) 
					$return []= $row;
			elseif($mode==3)
			// mode '3' returns regular array with values, or array with array with values
				while($row = mysqli_fetch_row($result))
					$return []= (count($row) == 1?$row[0]:$row); 
			return $return;
		}
		// otherwise if no mode, we return an assoc- which must be looped through to retrieve
		// if mode is equal to 1 then we simply pass the query through without any special return vales
		return ($mode == NULL?mysqli_fetch_assoc(mysqli_query($this->db,$query)):mysqli_query($this->db,$query)) ;	
	}
	
	private function show_tables($option=NULL){
		foreach(self::band_aid('show tables;',3) as $table_name)
			$return .= "\n\t\t<option value='$table_name'>$table_name</option>";
		return "<option value='' selected>Select $option</option>". $return;
	}
	
	private function makeArg($array=NULL){
		$the_count = count($array);
		$counter = 1;
		foreach($array as $key=>$item){
			if(is_array($item)){
				if(is_string($key)) $result .= "$key&&";
				foreach($item as $key2=>$item2){
					if(is_array($item2))$result .= $key2.'[]'.implode('||',$item2) . ($n?'--':'');
					$n=1;
					}
				foreach($item as $key2=>$item2)
					if(is_array($item2)) unset($item[$key2]); 
				$result .= implode('--',$item) . ($the_count != $counter?'+~':'')  ;
				}
		$counter++;
		}
	return  $result;
	}
	public function show_syntax($arg1,$arg2,$mode=NULL,$filter=NULL){
	
	return "Your record editor php syntax is <textarea class='record_syntax' width='200px'> ".'$'."form->record_editor('$arg1','$arg2'".($mode != NULL?",'$mode'":NULL). ($filter!=NULL?",'$filter'":NULL) .")</textarea>";
	
	}
	
	public function form_wizard(){
	// need to create a master array with all the 'static' post variables, but for now this sorta works
		if($_POST['handler']) return;
		if($_POST && $_POST['table']){ 
		// LEVEL 1 - Column selector we pick out what columns are in the table to be able to select options for them at this page needs improvement
			foreach(self::band_aid('show columns from `'.$_POST['table'].'`;',4) as $row)
				foreach($row as $field=>$value)
					if($field == 'Field'){
						$fields[]= $value;
						$field_name = $value;
					}
					elseif($field== 'Type'){
						if(strpos($value , '(') > 0 || strpos($value , ' ') > 0){
							$field_size = explode('(',$value);
							$field_size[1] = substr($field_size[1], 0, strrpos($field_size[1], ')'));
							$field_type[$field_name]= $field_size[0];
							}
						else $field_type[$field_name] = trim($value);
						// look at first three chars and look for 'enum' process fieldtype here (and make exception lists for record_selects for fields of enum type
					}
					elseif($field == 'Null' && $value=='NO') $checked[$field_name]=$value;
						// force values of NO to validate (hide the 'require' button for these fields)
					elseif($field == 'Key' && $value=='PRI') $list_pkey = $field_name;
		// now generate the lists.. to do show/hide checkboxes when the option can't really use them according to column settings 
		// at the very least hide the first row ... ?
		// idea 'check boxes' to force certain values and set 'edible to false ...
			$field_options = array('list','editor','required','unique','record_select');
			$result.= "<table class='sqlee_wizard'>\n\t<form method='post'>\n\t\t<tr>\n\t\t\t<th>Field</th>\n";
			foreach ($field_options as $header)
					$result .= "\t\t\t<th id='h_$header'>$header</th>\n";
			$result .= "\t\t</tr>\n";
			// get column info here?
			foreach($fields as $field){
				$result .= "\t\t<tr>\n\t\t\t<td class='fname'>$field</td>\n";
				foreach($field_options as $option){
					if($list_pkey != $field){
						// we don't want to show record select for enum fields as well as 
						if($option =='record_select' && ($field_type[$field] != 'enum'))$result .= "<td><select name = '$field-$option'>" . self::show_tables() . '</select></td>';
						else $result .= ($field_type[$field] == 'enum' && $option =='record_select' ?'':"\t\t\t<td><input type='checkbox' name='$field"."[]' value='$option'".($checked[$field] && $option == 'required'?' CHECKED ':'')."></td>\n");
						}
					// so we go ahead and show options if thy are 'list' or 'editor' inside of the 'pkey' column we may want to make this row different too
					elseif($list_pkey == $field && ($option == 'list' || $option == 'editor'))
					// this is the row for the pkey (the first row)
						$result .= "\t\t\t<td class='pkey'><input type='checkbox' name='$field"."[]' value='$option' ".($option=='list' || $option=='editor'?"CHECKED ":'')."></td>\n";
						}
				$result .= "\t\t\t</tr>\n";
			}	
			$result .= "\t\t<tr><td><input type='hidden' name='table_e' value='".$_POST['table']."'><input class='s_button' type='submit' value='Continue...'></td></tr>\n\t</form>\n</table>\n";
		}elseif($_POST && !$_POST['level_4']){
		// LEVEL 2 - weed out variables that are empty this cleans up 'checked' boxes that have been selected and contain no other attributes ties into the automatic null/not null column processing
			foreach($_POST as $key=>$value)
				if($value == '') unset($_POST[$key]);
				if(is_array($value) && count($value == 1) && $value[0] == 'required') unset($_POST[$key]);	
				elseif(is_array($value)){				
					if(in_array('list',$value)) $list_fields [] = $key;
					if(in_array('editor',$value)) $editor_fields [] = $key;
							}
			// this should only show if we have items in the post that have a 'record select'
			$table = $_POST['table_e'];
			unset($_POST['table_e']);
			foreach($_POST as $key2=>$value2){
				$temp =explode('-',$key2);
				if(count($temp) > 1){
					$fields = self::band_aid("SELECT * FROM $value2 LIMIT 1;",4);
					$fields = array_keys($fields[0]);
					$option_menu ='';
					foreach($fields as $field) 
						$option_menu .= "<option value='$field'>$field</option>\n\t"; 
					$_POST[$temp[0]][array_search($temp[1],$_POST[$temp[0]])]= $temp[1] . '[]' . $value2;
					$result .= "<tr><td>$temp[0]</td>\n\t<td>\n\t<select name='$temp[0]-$temp[1]-field'>\n\t$option_menu</td>\n\t<td>\n\t<select name='$temp[0]-$temp[1]-value'>\n\t$option_menu</select></td><td><input type='radio' name='$temp[0]-$temp[1]-null' value='false' CHECKED>Null<input type='radio' name='$temp[0]-$temp[1]-null' value='true'>Not Null</td><td><input type = 'text' name='$temp[0]-$temp[1]-message' size='20'></tr>\n";
					$x =1;	
					}				
				}
			// this means we have no record selects to process and should spit out the makeArgs ...	
			// is this needed ?????
			$result =($x != 1?'': "\n<table>\n\t<form method='POST'>\n\t<tr>\n\t\t<td><h3>Choose Additional Options for Record Selections</h3></td></tr><tr><td><p>These setting define the behavior of the specified fields below.</p></td></tr><tr><th>Field</th><th>Select Value</th><th>Select Title</th><th>Null</th><th>Selection message</th></tr>\n\t$result\n\t<tr><td><input type='submit' value='Next'></td></tr>\n\t<input type='hidden' name='level_4' value='".(serialize($_POST))."'><input type='hidden' name='table_e' value='$table'></form>\n\t</table>");
			if(!$x){
				foreach($_POST as $key=>$array){
					foreach($array as $key2=>$param){
						if($param == 'editor' || $param == 'list') {
							unset($_POST[$key][$key2]);
							if($param == 'list') $list_fields []=$key;
							elseif($param == 'editor') $edit_fields []= $key;
							}
					}
				}
				foreach($_POST as $key=>$array){	
					if(count($array) > 0){
							$kloc = array_search($key,$edit_fields);
							$edit_fields[$kloc] = $edit_fields[$kloc] . '&&' . implode('--',$array);
							}
				}
				// so we can add a conditional to check that the param is both editor and list ?, this is that unique action that turns a list into a list editor and vis versa
				// we need more '&&' here ?
				$arg1 =  implode('&&+~',$edit_fields);
				// adding in an extra 'pkey' field for security feature (also can be used to quickly disable edit/delete capability in any form)
				$arg2 = $table .'&&' . $list_fields[0] . '--' .implode('--',$list_fields);
				// need to return instead of echoing 
				$this->html .= self::show_syntax($arg1,$arg2);
			}
		}elseif($_POST && $_POST['level_4']){
			$level_4 = unserialize(stripslashes($_POST['level_4']));
			//heres another simpler way to do this ... repurpose the fields and then combine
			foreach($_POST as $key=>$value){
				if($key != 'level_4' && $key != 'table_e'){
				$exp = explode('-',$key);
				$old_loc = strpos($level_4[$exp[0]], $exp[1]);
				foreach($level_4 as $key2=>$value2){
					if($key2 == $exp[0]){
						foreach($value2 as $key3=>$value3)
							if(is_string($value3)){ 
								$temp = explode('[]',$value3);
								if(count($temp)==2){
									$con = $temp[0];
									$level_4[$key2][$con] []= $temp[1];
									unset($level_4[$key2][$key3]);
									}
							}
					}
				}
				if($value != '')
				$level_4[$exp[0]][$exp[1]][] = ($value == 'true'?1:($value == 'false'?0:$value)); 
				}
			}
			unset($_POST['level_4']);
			foreach($level_4 as $key=>$item){
			if($item == '') unset($level_4[$key]);
			else{
					if(is_array($item)){
						foreach($item as $key2=>$value){
							// clean this up
							if($value == 'list'){
								$the_fields[$value] []=$key;
								if(count($item) > 1)
								unset($level_4[$key][$key2]);
							}elseif($value == 'editor' && array_key_exists('record_select',$level_4[$key]))
							// we push the first value of the record select array to allow for the list to contain the edit/delete buttons
								unset($level_4[$key][$key2]);
							elseif($value =='editor')
								unset($level_4[$key][$key2]);
						}
					}
				}
			}
			// the 'safe argument...'		
			$arg2 = $_POST['table_e'] . '&&'. implode('--',$the_fields['list']);
			unset($level_4['table_e']);
			$arg1 = self::makeArg($level_4);
			// do a bit of processing on arg2, basically 're-add' the pkey to the 2nd argument so that the editor buttons appear, this is a quick way to make a list without any editing features
			$arg3= explode('&&',$arg2);
			$fields_temp = explode('--',$arg3[1]);
			$arg3[1] = $fields_temp[0] . '--' . $arg3[1];
			$arg3 = implode('&&',$arg3);
			// arg three adds the first field to itself to deal with a little security design pattern i created to quickly disable editing features of a table
			
			
			$this->html .= self::show_syntax($arg1,$arg2);
		}
		elseif (!$_POST) $result .= "<h4>Select a Table to create a form from</h4> <form method='post'><select name='table'>\n".self::show_tables()."\n</select><input type='submit' value='Create Form'></form>";
		$this->html .= $result;
		// make a screen to let the user enter in their database information to connect to maybe use genf input to do the easy work for us?
	}

}