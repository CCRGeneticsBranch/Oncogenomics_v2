<?php

use App;

class GeneController extends BaseController {

   #==========View Gene Details=========================        

#$start = microtime(true);
#$time_elapsed_secs = microtime(true) - $start;
#echo "Query time: $time_elapsed_secs <BR>";

	private $annotations =array('GO - Cellular Component'=>['ID', 'http://amigo.geneontology.org/cgi-bin/amigo/term-details.cgi?term='], 'GO - Biological Process'=>['ID', 'http://amigo.geneontology.org/cgi-bin/amigo/term-details.cgi?term='], 'GO - Molecular Function'=>['ID', 'http://amigo.geneontology.org/cgi-bin/amigo/term-details.cgi?term='], 'CTD Disease Info'=>['ID', 'http://www.nlm.nih.gov/cgi/mesh/2015/MB_cgi?field=uid&term='], 'GAD Disease Info'=>[], 'KEGG Pathway Info'=>['ID','http://www.genome.jp/kegg-bin/show_pathway?'], 'Reactome Pathway Name'=>[], 'General'=>['Ensembl Gene Info'=>'http://www.ensembl.org/Homo_sapiens/Gene/Summary?g=','Gene Symbol'=>'https://genome.ucsc.edu/cgi-bin/hgTracks?clade=mammal&org=Human&db=hg38&position=']);

	public function predictPfamDomain($id, $seq) {
		$fasta = ">$id\n$seq";
		//Log::info($fasta);
		return Gene::predictPfamDomain($fasta);
	}

	public function getGeneListByLocus($chr, $start_pos, $end_pos, $target_type) {
		return json_encode(Gene::getGeneListByLocus($chr, $start_pos, $end_pos, $target_type));
	}

	public function getGeneStructure($gid, $data_type) {
		$gene = Gene::getGene($gid);
		$structure = $gene->getGeneStructure($data_type);
		$seqs = $gene->getCodingSequences();		
		$structure["seqs"] = $seqs;
		return json_encode($structure);
	}	

	public function formatScientific($someFloat) {
		$power = ($someFloat % 10) - 1;
		return ($someFloat / pow(10, $power)) . "e" . $power;
	}
	
	#convert array of hash to array of array (table)
	function hash2table($input) {
		$header_idx = [];
		$headers = [];
		$idx = 0;  
		foreach ($input as $row) {
			foreach ($row as $key => $value) {
				if (!isset($header_idx[$key])) {
					$headers[$idx] = $key;
					$header_idx[$key] = $idx++;
				}
			}
		}
  
		for ($i=0;$i<count($input);$i++) {
			for ($j=0;$j<count($header_idx);$j++) {
				$output[$i][$j] = "";
			}
		}

		$i=0;
		foreach ($input as $row) {
			foreach ($row as $key => $value) {
				$j = $header_idx[$key];
				$output[$i][$j] = $value;               
			}
			$i++;  
		} 
		return array($headers, $output);
	}

	public function get_json_columns ($input) {

		$j_col ='[';
		foreach($input as $header) {
			$j_col .= '{"title":"'.$header.'"},';
		}
		$j_col .=']';
		return $j_col;
	}
   
	function array2jsontable($input) {
		$output ='[';
		foreach ($input as $row) {
			$output .='[';
			foreach ($row as $value) {
				$output .='["'.$value.'"],';
			}
			$output .='],';   
		}
		$output .=']';
		return $output;
	}

}
