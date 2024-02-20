<?php

namespace App\Models;

class Utility {

	static public function getMean($array){
		return array_sum($array)/count($array);
	}

	static public function getStdev($array){
        $average = Utility::getMean($array);
		$sqtotal = 0;
		foreach ($array as $item)
			$sqtotal += pow($average-$item, 2);        
		$std = sqrt($sqtotal / (count($array)-1));
		return $std;
	}

	static public function getMedian($array) {
		sort($array);
		$count = count($array);
		$middleval = floor(($count-1)/2);
		$median = 0;
		if ($count % 2)
			$median = $array[$middleval];
		else {
			$low = $array[$middleval];
			$high = $array[$middleval+1];
			$median = (($low+$high)/2);
		}
		return $median;
	}

	static public function addArrayIfNotExists($value, $array) {
		if (!in_array($value, $array))
			$array[] = $value;
	}

	static public function readFileWithHeader($file_name) {		
		$lines = Utility::readFile($file_name);		
		$header = array();
		$content = array();
		for ($i=0; $i<count($lines); $i++) {
			//print $lines[$i]."<BR>";
			$fields = explode("\t", $lines[$i]);
			$data_row = array();
			if ($i == 0) {
				foreach ($fields as $col)
					$header[] = $col;
			} else {
				foreach ($fields as $value)
					$data_row[] = $value;
				$content[] = $data_row;
			}
		}
		//print json_encode($content);
		return array($header, $content);
	}

	static public function readStringWithHeader($str) {		
		$lines = explode("\n", $str);
		$header = array();
		$content = array();
		for ($i=0; $i<count($lines); $i++) {
			//print $lines[$i]."<BR>";
			if ($lines[$i] == "")
				continue;
			$fields = explode("\t", $lines[$i]);
			$data_row = array();
			if ($i == 0) {
				foreach ($fields as $col)
					$header[] = $col;
			} else {
				foreach ($fields as $value)
					$data_row[] = $value;
				$content[] = $data_row;
			}
		}
		//print json_encode($content);
		return array($header, $content);
	}

	static public function readFile($file) {		
		$fh = fopen($file, "rb");
		$lines = array();
		while (!feof($fh) ) {
			$line = fgets($fh);
			$line = trim($line);
			if ($line == '') continue;
			$lines[] = $line;
		}
		fclose($fh);
		return $lines;
	}
	
}
