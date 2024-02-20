<?php

namespace App\Models;

class PCA {

	private $loading_file = null;
	private $coord_file = null;
	private $std_file = null;

	function __construct($loading_file, $coord_file, $std_file) {
		$this->loading_file = $loading_file;
		$this->coord_file = $coord_file;
		$this->std_file = $std_file;
	}

	public function runPCA($in_file) {
		$cmd = "Rscript ".app_path()."/scripts/PCA.r $in_file $this->loading_file $this->coord_file $this->std_file T";
		echo $cmd;
		shell_exec($cmd);		
	}

	public function getPCAResult() {
		$loadings = $this->readPCAOutput($this->loading_file, 20);
		$coord = $this->readPCAOutput($this->coord_file, 3);
		$std = $this->readPCAOutput($this->std_file, 1);
		return array($loadings, $coord, $std);
	}

	public function readPCAOutput($file, $col_num) {
		$output = array();
		$handle = fopen($file, "r");
		if ($handle) {
			while (($line = fgets($handle)) !== false) {
				$line = trim($line);
				$fields = preg_split('/\s+/', $line);		
				//if (count($fields) < $col_num + 1) continue; 
				//if ($fields[0] == '') continue;
				$key = str_replace('"', '', $fields[0]);				
				$fields = array_slice($fields, 1);
				foreach ($fields as $field)
					$output[$key][] = $field;
			}
			fclose($handle);
		}
		return $output;
	}

}
