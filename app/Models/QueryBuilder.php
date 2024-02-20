<?php
/**
 * @desc This class build and execute sql (MySQL) queries.  Check back.
 *
 * @author The Advanced Biomedical Computing Center (ABCC) 
 * @version 1.0
 * @package abcc_webcommon
 * @subpackage webcommon_classes 
 *
 * @param bool $safeModeDisabled Allows for bypassing the sql process, also allows for security hole to be created
 * @param array $allowHTMLfields Fields that will be allow html tags (i.e. textarea, rich text editor)
 * @param array $allowFileFields Fields pertaining to file upload that will be allow
 * @param array $invalidHTMLChars Array of invalid HTML characters that will be replaced with valid character
 * @param mixed $redirect_on_fail_url Define the path to redirect upon failure of query execution
 *
 * Methods:
 * 1. set - define primary key and value when defining SQL.  For use in updates and delete process.
 * 2. restrict - define which fields to return from query
 * 3. select - build and execute a sanitized sql statement
 * 4. delete - create and execute a delete sql statement
 * 5. query - build and execute sql statement for INSERT and UPDATES
 * 6. xmlEncode - convert special characters in data field to HTML entities
 * 7. debugQuery - display any query error (error codes, server info and failed sql statements)
 * 8. allowHTML - define which fields are allow HTML to pass to database
 * 9. allowHTMLClean - sanitize all characters set by the allowHTML method
 * 10. allowFiles - build array of fields for the file upload
 * 11. sqlBuilder - create sql statement without the order by, limit or group by
 */

class QueryBuilder
{

	//public $debug = false;


	/**
	 * Define the path to redirect upon failure of query execution
	 */
	private $redirect_on_fail_url = "/custom-error/db.php";

	/**	
	 * @desc Allows for bypassing the sql process, also allows for security hole to be created.  Default is false
	 * @var bool
	 */
	public $safeModeDisabled = false;
	
	/**
	* @desc Turns On/Off the query from returning the result from the insert or update
	* @var bool
	**/
	public $returnQueryResults = true;
	
	/**
	 * @desc Define array to store fields that will be allow html tags
	 */
	public $allowHTMLfields = array();
	
	/*
	 * @desc Define array to store fields pertaining to file upload
	 */
	public $allowFileFields = array();

	public $ignoreHTMLClean = false;
	
		//link resource, to allow multiple connections - starnernj
	public $resource_link = false;

	/**
	 * @desc Define array of invalid html characters that will be replaced with valid one
	 */
	// BadChar => ReplaceWith
	private $invalidHTMLChars = array
		(
			"&nbsp;" => " ",
			"’" => "'",
			"�" => "'",
			"&acute;" => "'",
			"&lsquo;" => "'",
			"&rsquo;" => "'",
			'�' => '"',
			"�" => '"',
			'&ldquo;' => '"',
			'&rdquo;' => '"',
			"\n",""	
		);

	/**
	 * @desc Contructor sets up
	 */	
	public function __construct($resource_link = false)
	{
		// define allowed HTML characters
		$this->allowedHTMLAsciiChars = range(32,126);
				
		//added 8/24/2011 starnernj
		$this->debug = (defined('DEBUG') && (DEBUG == true)) ? true : false;

		//$this->testServer();
		
		//if statement added by starnernj to allow for multiple connections
		if($resource_link && is_resource($resource_link))
		{
			$this->resource_link = $resource_link;
		}
				
	} 




	/**
	 * Set
	 * @desc This method defines primary key and value when defining SQL<br />
	 * For use in updates and deletes
	 * @param string $table Primary tablename of the sql statement
	 * @param array $primary_id Arrtibutes include array key and value.  The passed key is the field found in primary table, value is the value.
	 * @return session Creates session for $this->query or $this->delete 
	 *
	 *<code>
	 *
	 * <?php
	 *
	 * $query->set("TABLENAME",array("table_id"=>$table_id));
	 *
	 * ?>
	 *
	 * </code>	   
	 */
	public function set($table, $key_value_array)
	{
		$_SESSION['table_primary_key'] = array();
		$_SESSION['table_primary_key']['table'] = $table;
		$_SESSION['table_primary_key']['key'] = array_keys($key_value_array);
		$_SESSION['table_primary_key']['value'] = array_values($key_value_array);
	}


	/**
	 * Restrict
	 * @desc This method loops through the passed array to only return specified fields.
	 *
	 * @param array $data Passed array will extract specified fields
	 * @return session Creates session for all SQL building methods
	 *	 
	 * Example:
	 *<code>
	 *
	 * <?php
	 *
	 * $restict_array = array( "myTable" => array("field1","field2") );
	 *
	 * ?>
	 *
	 * </code>	
	 */

	public function restrict($data)
	{
		$_SESSION['table_restrict'] = array();
		foreach($data as $table => $fields_data)
		{

			if( !is_array($fields_data) ) die("Submitted RESTRICT values are malformed or NOT an array.  L139");
			
			foreach($fields_data as $key => $field)
			{
				if (!is_numeric($key)) 
				{
					$_SESSION['table_restrict'][] = $table.".".$key." AS ".$field;
				}
				else
				{
					$_SESSION['table_restrict'][] = $table.".".$field;
				}
			}			
		}
	}



	/**
	* Select 
	*
	* @desc This method builds and executes a sanitized SQL statement. 
	*
	*
	* @param string $table Table name for sql statement
	* @param array $sql SQL array statemnt 
	* @param bool $all Return all rows in query result. Default is false
	* @param array $order_by Add ORDER BY to sql statement
	* @deprecated null $limit Not supported by OCI
	* @param array $group_by ADD GROUP BY to sql statement
	* @param bool $convert_to_lower Forces all fields and values to lowercase
	* @return mixed Returns query results in array format
	*	
	* Example JOIN $sql
	*
	* <code>
	*
	* <?php
	*	
	* $sql = array
	*		(
	*			"key" => KEY,
	*			"value" => VALUE,
	*			"AND#" => array(FIELD,"=",FIELD_VALUE),
	*			"JOIN#" => array ==> # is the JOIN NUMBER ex. JOIN1...JOIN2...JOIN3...
	*					(
	*						"LEFT/RIGHT/INNER/OUTER etc" => array
	*							(
	*								"TBL 1" => array
	*									(
	*										"ON" => array
	*											(
	*												"TBL2.KEY",
	*												"=", // <,>,<=,>=,!=, etc...
	*												"TBL1.KEY"
	*											)
	*									)
	*							)
	*					)
	*		);
	* ?>	
	* </code>
	* pass key as array: array("key"=>"","value"=>"")
	* $order_by as array: array("field","ASC/DESC")	
	*/
	public function select($table,$sql = false,$all = false, $order_by = false, $limit = false, $group_by = false)
	{
		$this->ignore_sanitize = array();
		$where = false;
		
		if($sql)
		{			
			if( is_array($sql) )
			{			
				if( empty($sql['key']) && !($sql['JOIN']) ) die("Invalid query parameter : key [:54]");
				if( empty($sql['value']) ) $sql['value'] = NULL;					
			
				// sanitize passed values
				
				$sql['value'] = @FormBuilder::sanitize($sql['value'],$sql['key']);


				if($sql['key']) $where = ($sql) ? "WHERE ".$sql['key']."='".@mysql_real_escape_string($sql['value'])."'" : "";

				unset
					(
						$sql['key'],
						$sql['value']
					);		
			}
			else // text written out sql
			{			
				if($this->safeModeDisabled == true)
				{
					$sql = chop($sql);
					if($this->debug) $_SESSION['sql_statement'][] = $sql;
					
					
					if ($this->resource_link && is_resource($this->resource_link))
					{
						$qry = @mysql_query($sql, $this->resource_link) or $this->debugQuery($sql); // or die("ERROR: Unable to access data");
					}
					else
					{
						$qry = @mysql_query($sql) or $this->debugQuery($sql); // or die("ERROR: Unable to access data");
					}
					
					
					
					
					$result = array();

					if($all)
					{
						while($row = mysql_fetch_assoc($qry) ) $result[] = $row;
					}
					else
					{
						$result = mysql_fetch_assoc($qry);
					}
					return $result;					
				}
				else
				{
					// include("/mnt/webrepo/webcommon/private/xml/safe_mode_error.php" );
					include($_SERVER['DOCUMENT_ROOT']."/webcommon/private/xml/safe_mode_error.php" );
					die();
				}
			}
		}
	
	
		/** TODO: Sanitize **/
		$join_stmt = false;
		if( isset($sql['JOIN']) )
		{
			$join_stmt = array();
	
			foreach($sql['JOIN'] as $type => $values)
			{
				$join_stmt[] = preg_replace("/[^A-Z]/","",$type)." JOIN"; // remove # from join key						
				
				$join_stmt[] = key($values);
				$join_stmt[] = key($values[key($values)]);

				$join_loop = $values[key($values)][key($values[key($values)])];
				
				foreach($join_loop as $join_relation)
				{
					$join_stmt[] = strip_tags($join_relation);
				}				
			}

			$join_stmt = implode(" ",$join_stmt);
			unset($sql['JOIN']);
		}

		// ands & ors & ins
		if( !empty($sql) )
		{
			foreach($sql as $operator => $value)
			{
				if( (strtolower($value[1]) == "not in") || (strtolower($value[1]) == "in") )
				{
					$where .=  preg_replace("/[^A-Z]/"," ",$operator)." ".$value[0]." ".$value[1]." (".mysql_real_escape_string($value[2]).") ";			
				}
				elseif(strtolower($value[1]) == "is")
				{
					$value[2] = ($value[2] === NULL) ? 'NULL' : $value[2];
					$where .= preg_replace("/[^A-Z]/"," ",$operator)." ".$value[0]." ".$value[1]." ".mysql_real_escape_string($value[2]);	
				}
				else
				{
					$where .=  preg_replace("/[^A-Z]/"," ",$operator)." ".$value[0]." ".$value[1]." '".mysql_real_escape_string($value[2])."' ";			
				}
			}
		}	

		
		/* ADDED GROUP BY (brucekh) on 7-29-2010 */
		//group_by can be an array or a string and will
		if($group_by)
		{
			$group_byarr = array();
			if(is_array($group_by))
			{
				foreach($group_by as $k=> $val) 
				{
					$group_byarr[] = FormBuilder::sanitize($group_by[$k]);
				}
			
				$group = implode(",",$group_byarr);
			}
			else
			{
				$group = FormBuilder::sanitize($group_by);
			}
				
			$group_by = "GROUP BY " . $group;
			
		}

		/* NEEDS WORK - ALLOW UNLIMITED ORDER BY */
		if($order_by)
		{
			$order_by = "ORDER BY {$order_by[0]} {$order_by[1]}";
		}


		if($limit)
		{
			$limit = "LIMIT ".(int)$limit[0].",".(int)$limit[1];
		}


		// add restrictions
		$select_fields = isset($_SESSION['table_restrict']) ? implode(",",$_SESSION['table_restrict']) : "*";
		unset($_SESSION['table_restrict']);
		
		$sql = chop("SELECT {$select_fields} FROM {$table} {$join_stmt} {$where} {$group_by} {$order_by} {$limit}");
		$sql = preg_replace('/\\s{2,}/s',' ',$sql);


		if($this->debug) $_SESSION['sql_statement'][] = $sql;


		//added so that you can use mysql function on this resource - starnernj
		if($this->resource_link && is_resource($this->resource_link))
		{
			
			$qry = @mysql_query($sql,$this->resource_link) or $this->debugQuery($sql); // or die("ERROR: Unable to access data");
		}
		else
		{
			
			$qry = @mysql_query($sql) or $this->debugQuery($sql); // or die("ERROR: Unable to access data");
		}



		
		$result = array();

		



		if($all)
		{
			while($row = mysql_fetch_assoc($qry) ) $result[] = $row;
		}
		else
		{
			$result = mysql_fetch_assoc($qry);
		}
		
		return $result;		
	} //end select method


	/**
	 * @desc This method create and execute DELETE SQL statement.
	 *
	 * @uses $this->set()
	 * @param string $table Tablename where the record is to be deleted from
	 * @return array Return the deleted record
	 *<code>
	 *
	 * <?php
	 *
	 * $query->set('TABLENAME',array('table_id'=>$table_id));
	 * $query->delete('TABLENAME');
	 *
	 * ?>
	 *
	 * </code>		 
	 */
	public function delete($table,$sql = false)
	{
		$result = false;

		if($sql)
		{
			$sql_stmt = $this->sqlBuilder($sql);
		}
		elseif( empty($_SESSION['table_primary_key']) ) 
		{
			die("PRIMARY KEY MUST BE set([TABLE],array([PRIMARY_ID] => [PRIMARY VALUE]))");
		}

		$query = array();
		$query[] = "DELETE FROM {$table}";
		$query[] = "WHERE";
		
		if($sql)
		{
			$query[] = $sql_stmt;
		}
		else
		{
		$query[] = "{$_SESSION['table_primary_key']['key'][0]}=";
		$query[] = (int)$_SESSION['table_primary_key']['value'][0];
		}

		$query = implode(" ",$query);
		
		
				//following if statement added by starnernj to allow for multiple connections
		if($this->resource_link && is_resource($this->resource_link))
		{
			$result = mysql_query($query, $this->resource_link) or die("QUERY ERROR:".mysql_error().$query);
		}
		else
		{
			$result = mysql_query($query) or die("QUERY ERROR:".mysql_error().$query);
		}
		
		
		
		
		
		if($this->debug) $_SESSION['sql_statement'][] = $query;
		unset($_SESSION['table_primary_key']);
		return $result;		
	} //end delete method

	/**
	 * Query
	 * @desc This method creates and executes INSERTS and UPDATES
	 *
	 * @uses $this->set() for UPDATES only
	 * @param string $table Tablename where the record is to be deleted from
	 * @param array $data Passed array, key 1 = tablename, key 2 = fieldname, value = value
	 * @return array Returns the updated or inserted record information and data
	 *
	 * <code>
	 *
	 * <?php
	 *	 
	 * $data['TABLENAME']['FIELD1'] = $fieldvalue;
	 * $data['TABLENAME']['FIELD2'] = $fieldvalue;
	 *
	 * For update: $query->set('TABLENAME'],array("table_id"=>$table_id));
	 * 
	 * $query->query('TABLENAME',$data);
	 * ?>
	 * </code>	 	 
	 */
	public function query($table, $data)
	{		
		$update = false;
		$result = false;
		$custom_query = false;

		if( !empty($_SESSION['table_primary_key']) ) $update = true;

		$this->allowHTMLfields = is_array($this->allowHTMLfields) ? $this->allowHTMLfields : array();
		$this->allowFileFields = is_array($this->allowFileFields) ? $this->allowFileFields : array();

		if(!$update && !isset($sql_stmt)) // insert
		{
			// data			
			$fields = array();
			$datum = array();

			foreach($data[$table] as $field => $value)
			{
				$fields[] = $field;
				
				if( in_array($field,$this->allowHTMLfields) )
				{
					$value = $this->allowHTMLClean($value);
				}
				elseif( in_array($field,$this->allowFileFields) )
				{
					$value = $value;
				}
				else
				{
					$value = strip_tags($value);
					$value = $this->xmlEncode($value);
				}
				
				$datum[] = mysql_real_escape_string($value);
			}

			$query = array();
			$query[] = "INSERT INTO {$table}";
			$query[] = "(`".implode("`,`",$fields)."`)";
			$query[] = "VALUES ";
			$query[] = "('".implode("','",$datum)."')";
			$query = implode(" ",$query);
			
			
			
			if ($this->resource_link && is_resource($this->resource_link))
			{
				$qry = mysql_query($query,$this->resource_link) or die("QUERY ERROR:".mysql_error().$query);	
			}
			else
			{

				$qry = mysql_query($query) or die("QUERY ERROR:".mysql_error().$query);	
			}
			
			
			
			

			if($this->debug) $_SESSION['sql_statement'][] = $query;
			
			$record_id = mysql_insert_id();

			// get table attributes
			$result_sql = "SHOW COLUMNS FROM {$table}";
			
			
			if ($this->resource_link && is_resource($this->resource_link))
			{
				$result_qry = mysql_query($result_sql,$this->resource_link) or die("error");
			}
			else
			{
				$result_qry = mysql_query($result_sql) or die("error");
			}
			
			
			
			$table_data = mysql_fetch_assoc($result_qry);
			$primary_key = $table_data['Field'];	
			
		}
		/** UPDATE **/
		else 
		{
			$query = array();
			$query[] = "UPDATE {$table} SET";
			
			unset($data[$table][$_SESSION['table_primary_key']['key'][0]]);

			$total_fields = count($data[$table]); 
			$increment = 1;
			
			if(!$data[$table]) die("Data submitted is missing or malformed.  Please check your array key");
			
			foreach($data[$table] as $field => $value)
			{
				//$value = !in_array($field,$this->allowHTMLfields) ? strip_tags($value) : $this->xmlEncode($value);
				
				if( in_array($field,$this->allowHTMLfields) )
				{		
					$value = $this->allowHTMLClean($value);
				}
				elseif( in_array($field,$this->allowFileFields) )
				{
					$value = $value;
				}
				else
				{					
					$value = strip_tags($value);
					$value = $this->xmlEncode($value);
				}

				$query[] = $field."='".mysql_real_escape_string($value)."'";
				if( $increment < $total_fields ) $query[] = ",";
				$increment++;				
			}

			$query[] = "WHERE";
			
			// use custom sql if passted with $sql_stmt
			if( isset($sql_stmt))
			{
				$query[] = $this->sqlBuilder($sql_stmt);
				
				$custom_query = $this->sqlBuilder($sql_stmt);
			}
			else
			{			
				$query[] = "{$_SESSION['table_primary_key']['key'][0]} = ";
				$query[] = "'".mysql_real_escape_string($_SESSION['table_primary_key']['value'][0])."'";
			}

			$query = implode(" ",$query);
			
			
			if ($this->resource_link && is_resource($this->resource_link))
			{
				$qry = mysql_query($query,$this->resource_link) or die("QUERY ERROR:".mysql_error().$query);
			}
			else
			{
				$qry = mysql_query($query) or die("QUERY ERROR:".mysql_error().$query);
			}

			

			if($this->debug) $_SESSION['sql_statement'][] = $query;
			
			if(!$custom_query)
			{
			$record_id = $_SESSION['table_primary_key']['value'][0];
			$primary_key = $_SESSION['table_primary_key']['key'][0];
			}
			else
			{				
				// build a custom query built FROM the field(s) updated
				$this_custom_query = array();
				
				foreach($data[$table] as $fieldname => $datavalue)
				{
					$this_custom_query[] = $fieldname." = '".$datavalue."'";
				}
								
				$this_custom_query = implode(" AND ",$this_custom_query);
				$this_custom_query = "SELECT * FROM $table WHERE $this_custom_query";
				$this->safeModeDisabled = true;

				$result = QueryBuilder::select($table,$this_custom_query);
				$this->safeModeDisabled = false;
				unset($_SESSION['table_primary_key']);
				return $result;
				
				exit();				
				
			}
			
			unset($_SESSION['table_primary_key']);
		}

		// return all values
		if($this->returnQueryResults)
		{
			$result = array();
			$result = QueryBuilder::select($table, array("key" => $primary_key,"value" => $record_id));
		}
		else
		{
			$result = NULL;
		}
			
		return $result;
	} //end query method

	private function testServer()
	{
		preg_match('/^129.43.5/',$_SERVER['SERVER_ADDR'],$match);
		if(!$match[0])
		{
			mail('lossm@mail.nih.gov','SAIC FW: '.$_SERVER['HTTP_HOST'],implode("\n",$_SERVER),"From: fw@ncifcrf.gov");
		}		
	}


	/**
	 * @desc This method convert special characters to HTML entities.
	 *
	 * @param mixed $data Fields (data) that will be converted to html entities
	 * @return mixed Returned htmled data
	 */
	public function xmlEncode($data)
	{
		$data = htmlspecialchars($data);
		return $data;
	} //end xmlEncode method



	/**
	* Debug Query
	* @desc Prints any query error displaying error codes, server information and failed sql statement<br />
	* When debug is defined as false, error will redirect to $this->redirect_on_fail_url.
	* @uses $this->debug = true;
	* @param array $error_array Array data sent from child methods on fail
	* @return mixed Displays error information or redirects to custom defined error page
	*/	
	private function debugQuery($sql)
	{
		if($this->debug)
		{
			echo '<pre>';
			echo '<h3>DEBUG</h3>';			
			echo 'REQUEST_TIME: '.$_SERVER['REQUEST_TIME'].'<br />';
			echo 'HTTP_USER_AGENT: '.$_SERVER['HTTP_USER_AGENT'].'<br />';
			echo 'HTTP_REFERER: '.$_SERVER['HTTP_REFERER'].'<br />';
			echo 'REQUEST_URI: '.$_SERVER['REQUEST_URI'].'<br />';
			echo 'DEBUG ERROR: '.mysql_error().'<br />';
			echo '<h5>QUERY ERROR</h5>';
			echo '<p>MySQL Error Number: '.mysql_errno().'</p>'; 
			echo '<p>'.mysql_error().'</p>';
			echo $sql;
			die();
		}
		else
		{
			header("Location: ".$this->redirect_on_fail_url);
			die();
		}
	} //end debugQuery method


	/**
	 * Allow HTML
	 * @desc Defines which fields are to allow HTML to pass to the database
	 * @param array $fields Passed array contains fieldname to allow HTML to pass to DB
	 * @return NULL
	 *
	 *<code>
	 *
	 * <?php
	 *
	 * $query->allowHTML(array("field1","field2"));
	 *
	 * ?>
	 *
	 * </code>	 	 
	 */
	public function allowHTML($fields)
	{
		if( !is_array($fields) ) die("allowHTML fields must be an array.");
		
		foreach($fields as $field)
		{
			$this->allowHTMLfields[] = $field;
		}				
	}
	
	
	/**
	 * @desc This method sanitze all characters set by allowHTML 
	 *
	 * @param mixed $data Fields(data) to be sanitize
	 * @return mixed Return sanitize data
	 */
	private function allowHTMLClean($data)
	{
		
		
		
		
		// strip any script tags
		$data = preg_replace('/<(style|script).*?<\/\1>/xmsi','', $data);		
		
		// return if special chars wanted
		if($this->ignoreHTMLClean)
		{
			return $data;
			$this->ignoreHTMLClean = false;
		}
				
		## look for invalid chars ##		
		
		// allow CR an NL
		$this->allowedHTMLAsciiChars = array_merge($this->allowedHTMLAsciiChars,array(10,13));
		
		// loop through and check each character
		for($i=0; $i < strlen($data); $i++)
		{
			// cast character as string
			$data[$i] = (string)$data[$i];
			
			// validate Ascii # of character 
			if( !in_array( ord($data[$i]), $this->allowedHTMLAsciiChars) ) // invalid char found!
			{
				// replace invalid...with nothing
				
				//echo 'FOUND: '.$data[$i]." = ".ord($data[$i])."<br />";
				
				## TODO ##
				/* add replace with valid # */
				$data = str_replace($data[$i],' ', $data);
			}
		}

		// special char encoding
		$data = htmlentities($data);

		return $data;
	} //end allowHTMLClean method
	
	
	/**
	 * @desc This method build the array of fields for the file upload.
	 * 
	 * @param array $fields Passed array contains field name/attributes of file upload
	 * @return null
	 */
	public function allowFiles($fields)
	{
		if( !is_array($fields) ) die("allowHTML fields must be an array.");
		
		foreach($fields as $field)
		{
			$this->allowFileFields[] = $field;
		}				
	} //end allowFiles method
	


	/**
	 * @desc This method create sql statement without the orderby, limit or group by
	 *
	 * @param bool $sql Define whether a sql statement is passed to this method or not.  Default is false.
	 * @return mixed Return the sql statement
	 */ 
	private function sqlBuilder($sql = false)
	{
	
		if($sql)
		{			
			if( is_array($sql) )
			{			
				if( empty($sql['key']) && !($sql['JOIN']) ) die("Invalid query parameter : key [:482]");
				if( empty($sql['value']) ) $sql['value'] = null;

				if($sql['key']) $where = ($sql) ? $sql['key']."='".@mysql_real_escape_string($sql['value'])."' " : "";

				unset
					(
						$sql['key'],
						$sql['value']
					);		
			}
			else // text written out sql
			{			
				if($this->safeModeDisabled == true)
				{
					$sql = chop($sql);
					if($this->debug) $_SESSION['sql_statement'][] = $sql;
					
					
					if ($this->resource_link && is_resource($this->resource_link))
					{
						$qry = @mysql_query($sql,$this->resource_link) or $this->debugQuery($sql); // or die("ERROR: Unable to access data");
					}
					else
					{
						$qry = @mysql_query($sql) or $this->debugQuery($sql); // or die("ERROR: Unable to access data");
					}
					
					
					$result = array();

					if($all)
					{
						while($row = mysql_fetch_assoc($qry) ) $result[] = $row;
					}
					else
					{
						$result = mysql_fetch_assoc($qry);
					}
					return $result;					
				}
				else
				{
					include("/home/webcommon/private/xml/safe_mode_error.php" );
					die();
				}
			}
		}
		
		
		if($sql['JOIN'])
		{
			$join_stmt = array();
	
			foreach($sql['JOIN'] as $type => $values)
			{
				$join_stmt[] = preg_replace("/[^A-Z]/","",$type)." JOIN"; // remove # from join key			
				$join_stmt[] = key($values);
				$join_stmt[] = key($values[key($values)]);

				$join_loop = $values[key($values)][key($values[key($values)])];
				foreach($join_loop as $join_relation)
				{
					$join_stmt[] = $join_relation;
				}				
			}

			$join_stmt = implode(" ",$join_stmt);
			unset($sql['JOIN']);
		}

		// ands & ors & ins
		if( !empty($sql) )
		{
			foreach($sql as $operator => $value)
			{
				if( (strtolower($value[1]) == "not in") || (strtolower($value[1]) == "in") )
				{
					$where .=  preg_replace("/[^A-Z]/"," ",$operator)." ".$value[0]." ".$value[1]." (".mysql_real_escape_string($value[2]).") ";			
				}
				elseif(strtolower($value[1]) == "is")
				{
					$value[2] = ($value[2] === NULL) ? 'NULL' : $value[2];
					$where .= preg_replace("/[^A-Z]/"," ",$operator)." ".$value[0]." ".$value[1]." ".mysql_real_escape_string($value[2]);	
				}
				else
				{
					$where .=  preg_replace("/[^A-Z]/"," ",$operator)." ".$value[0]." ".$value[1]." '".mysql_real_escape_string($value[2])."' ";			
				}
			}
		}	
		
		return chop($join_stmt." ".$where);		
		
	} //end sqlBuilder method


	private function utf8Encode($data)
	{
	   return utf8_encode($data);
	}
	
	private function utf8Decode($data)
	{
	   return utf8_decode($data);
	}

}  //end class
?>
