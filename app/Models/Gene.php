<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use DB,Log;

/**
 *
 * Gene class. All operations on 'gene' table are defined in this class.
 *
 * @copyright 2018 Javed Khan's group
 * @author Hsien-chao Chou, Scott Goldweber
 * @package models
 */
class Gene {

	/** @var string The gene symbol */
	private $symbol = null;
	/** @var string ENSEMBL accession */
	private $ensembl_id;
	/** @var string chromosome (e.g 'chr1','chrX') */
	private $chr;
	/** @var string strand ('+' or '-') */
	private $strand;
	/** @var Hash Hash of Transcript objects */
	private $trans_list;
	
	/**
	 * 
	 * Singleton method to get the Gene object
	 *
	 * Example:
	 * 
	 * $gene = Gene::getGene("MYCN");
	 *	 
	 *	 
	 * @param string $id The ID could be gene symbol or ENSEMBL accession
	 * @return Gene Gene object
	 */
	static public function getGene($id) {
		$gene = new Gene($id);
		if ($gene->symbol == null)
			return null;
		return $gene;
	}

	/**
	 * 
	 * Constructor
	 *
	 * Example:
	 * 
	 * $gene = new Gene("MYCN");
	 *	 
	 *	 
	 * @param string $id The ID could be gene symbol or ENSEMBL accession
	 * @param string $chr If gene symbol is ambiguous (in different chromosome), this parameter can specific the gene by chromosome.
	 * @return Gene Gene object
	 */
	function __construct($gene_id, $chr = null) {
		$sql = "select * from gene where (UPPER(gene) =UPPER('$gene_id') or UPPER(symbol) =UPPER('$gene_id')) and target_type='ensembl' and species = 'hg19'";
		if ($chr != null)
			$sql .= " and chromsome = $chr";
		//Log::info($sql);
		$rows = \DB::select($sql);
		$ensembl_id = null;
		if (count($rows) == 0) {
			$sql = "select * from gene g where exists(select * from gene_alias a where (g.symbol=a.symbol or g.symbol=a.gene_synonym) and (UPPER(a.symbol)=UPPER('$gene_id') or UPPER(a.gene_synonym)=UPPER('$gene_id'))) and target_type='ensembl' and species = 'hg19'";
			$rows = \DB::select($sql);
		}
		if (count($rows) > 0) {
			$this->ensembl_id = $rows[0]->gene;
			$this->symbol = $rows[0]->symbol;
			$this->chr = $rows[0]->chromosome;
			$this->strand = $rows[0]->strand;
		}
	}

	/**
	 * 
	 * Get transcript from a gene
	 *
	 * Example:
	 * 
	 * $gene = new Gene("MYCN");
	 * $trans = $gene->getTrans("NM_005378")
	 *	 
	 * @param string $trans_id Transcript ID
	 * @param bool $coding_seq Whether the coding DNA sequence is returned (false to make query faster)
	 * @param bool $aa_seq Whether the protein sequence is returned (false to make query faster)
	 * @param bool $domain Whether protein domain is returned (false to make query faster)
	 * @return Transcript Transcript object
	 */
	public function getTrans($trans_id, $coding_seq=false, $aa_seq=false, $domain=false) {
		if ($this->trans_list != null)
			return $this->trans_list[$trans_id];
		return Transcript::getTranscriptsByID($trans_id, $coding_seq, $aa_seq, $domain);					
	}

	/**
	 * 
	 * Get hash of all transcripts from a gene
	 *
	 * Example:
	 * 
	 * $gene = new Gene("MYCN");
	 * $trans_list = $gene->getTransList();
	 * $trans = trans_list["NM_005378"];
	 *	 
	 * @param bool $coding_seq Whether the coding DNA sequence is returned (false to make query faster)
	 * @param bool $aa_seq Whether the protein sequence is returned (false to make query faster)
	 * @param bool $domain Whether protein domain is returned (false to make query faster)
	 * @return Transcript Transcript object
	 */
	public function getTransList($coding_seq=false, $aa_seq=false, $domain=false, $target_type="all") {
		if ($this->trans_list == null) {			
			$rows = Transcript::getTranscriptsBySymbol($this->symbol, $coding_seq, $aa_seq, $domain, $target_type);
			$this->trans_list = array();
			foreach ($rows as $row) {
				$this->trans_list[$row->trans] = $row;
			}			
		}
		return $this->trans_list;
	}

	/**
	 * 
	 * Get hash of all transcripts from a set of genes
	 *
	 * Example:
	 * 
	 * $genes_data = Gene::getTransByGenes(array("MYCN","ALK"));
	 * foreach ($genes_data as $symbol => $gene_data) {
	 *	foreach ($gene_data as $chr => $trans_list) {
	 *		foreach ($trans_list as $trans) {
	 *			//do something with $trans
	 *		}
	 * 	}
	 * }	 
	 *	 
	 * @param bool $coding_seq Whether the coding DNA sequence is returned (false to make query faster)
	 * @param bool $aa_seq Whether the protein sequence is returned (false to make query faster)
	 * @param bool $domain Whether protein domain is returned (false to make query faster)
	 * @return hash hash of all transcripts
	 */
	static public function getTransByGenes($genes, $target_type="refseq") {
		$gene_list = implode("','", $genes);
		$target_type_clause = ($target_type == "all")? "" : "and target_type = '$target_type'";
		$sql = "select trans, chromosome, symbol from transcripts where symbol in ('$gene_list') $target_type_clause";
		$rows = \DB::select($sql);
		$genes_data = array();
		foreach ($rows as $row)
			$genes_data[$row->symbol][$row->chromosome][] = $row->trans;
		return $genes_data;
	}

	
	/**
	 * 
	 * Get hash of all transcripts from a set of genes
	 *
	 * Example:
	 * 
	 * $genes_data = Gene::getTransByGenes(array("MYCN","ALK"));
	 * foreach ($genes_data as $symbol => $gene_data) {
	 *	foreach ($gene_data as $chr => $trans_list) {
	 *		foreach ($trans_list as $trans) {
	 *			//do something with $trans
	 *		}
	 * 	}
	 * }	 
	 *	 
	 * @param bool $coding_seq Whether the coding DNA sequence is returned (false to make query faster)
	 * @param bool $aa_seq Whether the protein sequence is returned (false to make query faster)
	 * @param bool $domain Whether protein domain is returned (false to make query faster)
	 * @return hash hash of all transcripts
	 */
	static public function getEnsemblList($genes) {
		$search_list = array();
		$result_list = array();
		foreach ($genes as $gene)
			if (strpos($gene, "ENSG") == 0)
				$search_list[] = $gene;
			else
				$result_list[] = $gene;
		$list = implode("','", $search_list);
		$sql = "select gene from gene_ensembl where symbol in ('$list') and species = 'Hs'";
		$rows = \DB::select($sql);
		foreach ($rows as $row)
			$result_list[] = $row->gene;
		return $result_list;
	}

	static public function getSurfaceInfo($genes) {
		$list = implode("','", $genes);
		$sql = "select * from gene_surface_protein where gene in ('$list')";
		$rows = \DB::select($sql);
		$data = array();
		foreach ($rows as $row)
			$data[$row->gene] = array(($row->membranous_protein == '1')?'Y':'N', $row->evidence_count);		
		return array("data" =>$data, "attr_list" => array("Membranous protein", "Evidence count"));
	}

	static public function getGeneListByLocus($chr, $start_pos, $end_pos, $target_type="refseq") {
		$rows = \DB::select("select distinct symbol from trans_coordinate where target_type='$target_type' and chromosome='$chr' and start_pos <= $end_pos and end_pos >= $start_pos");
		$genes = array();
		foreach ($rows as $row)
			$genes[] = $row->symbol;		
		return $genes;
	}

	static public function getAllSymbols() {
		return \DB::select("select distinct symbol from gene where target_type='ensembl' and type='protein-coding' order by symbol");
	}

	function getEnsemblID() {
		return $this->ensembl_id;
	}

	function getSymbol() {
		return $this->symbol;
	}

	function getPfamDomains() {
		$sql = "select p.* from uniprot_mapping u, entrez e, pfam p where u.entrez_gene_id=e.entrez_gene_id and p.ac=u.ac and e.symbol='".$this->symbol."'";
		$domain_range = array("start_pos" => 9999, "end_pos" => 0);
		$rows = \DB::select($sql);
		$domains = array();
		foreach ($rows as $row) {
			$row->json = str_replace('""', '"', $row->json);
			$row->json = trim($row->json, '"');
			$json = json_decode($row->json);
			foreach ($json[0]->regions as $region_json) {
				$domains[] = array("name"=>$region_json->text, "coord"=>$region_json->start."-".$region_json->end);
				if ($domain_range["start_pos"] > $region_json->start)
					$domain_range["start_pos"] = $region_json->start;
				if ($domain_range["end_pos"] < $region_json->end)
					$domain_range["end_pos"] = $region_json->end;
			}
			
		}
		return array($domains, $domain_range);
	}

	function getAnnotationInfo() {
		$sql = "select * from gene_annotation where ID ='".$this->ensembl_id."' order by attr_name";
		$anno_rows = \DB::select($sql);
		$anno_info = array();
		foreach ($anno_rows as $row) {
			if ($row->attr_value == '-' || $row->attr_name == 'Input Type' || $row->attr_name == 'InputValue') continue;
			$anno_info[$row->attr_name][] = $this->parse_annotation($row->attr_value);
		}
		return $anno_info;
	}

	function parse_annotation($input) {
		$output = [];
		$output['ID'] = explode(' ', $input)[0];
		preg_match_all('/\\[(.*?)\\]/s', $input, $matches);
		foreach ($matches[1] as $match) {
			$outputs = explode(':', $match);
			if (count($outputs)>1)
				$output[$outputs[0]] = $outputs[1];
		}
		return $output;     
	}

	function getChr() {		
		return $this->chr;
	}

	function getStrand() {		
		return $this->strand;
	}

	
	function getGeneStructure($target_type) {
		$stand = $this->getStrand();
		$table_name = $this->getExonCoordTableName($target_type);
		$id = ($target_type == 'Ensembl')? $this->ensembl_id: $this->symbol;
		$sql = "select * from $table_name where gene ='".$id."' order by start_pos";
		$rows = \DB::select($sql);
		$structure = array();
		$structure['strand'] = $stand;
		foreach ($rows as $row) {
			$trans_list = explode(',', $row->trans);
			foreach ($trans_list as $trans) {
				$exon = array();
				$exon[] = $row->start_pos;
				$exon[] = $row->end_pos;
				$exon[] = $row->seq;
				$structure['data'][$trans][] = $exon;
			}			
		}
		return $structure;
	}
	

	function getExons($trans, $target_type="refseq") {
		if ($this->exons != null)
			return $this->exons;
		$stand = $this->getStrand();
		$target_type_clause = ($target_type == "all")? "" : "and target_type = '$target_type'";
		$sql = "select * from exon_coordinate where trans='$trans' $target_type_clause and gene ='".$this->symbol."' order by start_pos";
		return \DB::select($sql);		
	}

	function getCodingSequences() {
		$sql = "select a.trans, strand, start_pos, coding_start, coding_end, seq from anno_rsu_trans a, refgene_seq r where a.gene='".$this->symbol."' and a.trans=r.trans";
		$rows = \DB::select($sql);
		$seqs = array();
		foreach ($rows as $row) {
			$seqs[$row->trans] = array("seq"=>$row->seq, "start_pos"=>$row->start_pos, "coding_start"=>$row->coding_start, "coding_end" => $row->coding_end);
		}
		return $seqs;
	}

	static function reverseComplement($seq) {
		// change the sequence to upper case
        $seq = strtoupper(strrev($seq));
        // the system used to get the complementary sequence is simple but fas
        $seq=str_replace("A", "t", $seq);
        $seq=str_replace("T", "a", $seq);
        $seq=str_replace("G", "c", $seq);
        $seq=str_replace("C", "g", $seq);
        // change the sequence to upper case again for output
        $seq = strtoupper ($seq);
        return $seq;
	}

	static function translateDNA($seq, $search_range){

		$triplets = array(
                'TCA'=>'S','TCC'=>'S','TCG'=>'S',
                'TCT'=>'S','TTC'=>'F','TTT'=>'F',
                'TTA'=>'L','TTG'=>'L','TAC'=>'Y',
                'TAT'=>'Y','TAA'=>'*','TAG'=>'*',
                'TGC'=>'C','TGT'=>'C','TGA'=>'*',
                'TGG'=>'W','CTA'=>'L','CTC'=>'L',
                'CTG'=>'L','CTT'=>'L','CCA'=>'P',
                'CCC'=>'P','CCG'=>'P','CCT'=>'P',
                'CAC'=>'H','CAT'=>'H','CAA'=>'Q',
                'CAG'=>'Q','CGA'=>'R','CGC'=>'R',
                'CGG'=>'R','CGT'=>'R','ATA'=>'I',
                'ATC'=>'I','ATT'=>'I','ATG'=>'M',
                'ACA'=>'T','ACC'=>'T','ACG'=>'T',
                'ACT'=>'T','AAC'=>'N','AAT'=>'N',
                'AAA'=>'K','AAG'=>'K','AGC'=>'S',
                'AGT'=>'S','AGA'=>'R','AGG'=>'R',
                'GTA'=>'V','GTC'=>'V','GTG'=>'V',
                'GTT'=>'V','GCA'=>'A','GCC'=>'A',
                'GCG'=>'A','GCT'=>'A','GAC'=>'D',
                'GAT'=>'D','GAA'=>'E','GAG'=>'E',
                'GGA'=>'G','GGC'=>'G','GGG'=>'G',
                'GGT'=>'G'
        );		

		/*
		$start_pos = -1;
		for ($i=0;$i<strlen($seq);$i++) {
			$triplet = substr($seq, $i, 3);
			if ($triplet == 'ATG') {
				$start_pos = $i;
				break;
			}
		}
		*/

		$start_pos = 0;
		$orf_length = 0;
		$longest_aa = "";
		$longest_orf = "";
		$offset = 0;
		$aa_seqs = array();
		//for ($start_pos=$search_range[0];$start_pos<=$search_range[1];$start_pos++) {
		
		$num_frame = ($search_range[1]==0)?1:3;
		for ($start_pos=0;$start_pos<$num_frame;$start_pos++) {
			$aa_seq = "";
			$orf_seq = "";
	        for ($i=$start_pos;$i<strlen($seq);$i+=3) {
	        	$triplet = substr($seq, $i, 3);
	        	if (strlen($triplet) == 3)        	
	        		$aa_seq .= $triplets[$triplet];
	        	else        	
	        		$aa_seq .= 'X';
	        }	        
	        $aa_seqs[] = $aa_seq;
	        $orf_seq = $aa_seq;
	        $ls = 0;
	        while (1) {
	        	//search for first M
	        	$ls = strpos($aa_seq, 'M', $ls);
	        	if ($ls === false || $ls > (int)($search_range[1]/3))
	        		break;
	        	//search for first *
	        	$le = strpos($aa_seq, '*', $ls);
	        	//if no stop codon
	        	if ($le === false) {	        		
	        		$orf = substr($aa_seq, $ls);
	        		if (strlen($orf) > strlen($longest_aa)) {
	        			$offset = $ls * 3 + $start_pos;
	        			$longest_aa = $orf;
	        		}
	        		break;
	        	}
	        	if ($le == strlen($aa_seq) - 1) {	        		
	        		$orf = substr($aa_seq, $ls, ($le - $ls + 1));	        		
	        		if (strlen($orf) > strlen($longest_aa)) {
	        			$longest_aa = $orf;
	        			$offset = $ls * 3 + $start_pos;
	        		}
	        		break;
	        	}
	        	
	        	
	        	$ls = $le + 1;
	        	
	        }
	    }

	    if ($longest_aa == "") {
	    	//if no s
	    	$longest_aa = $aa_seqs[0];
	    	for ($i=0;$i<count($aa_seqs);$i++) {
	    		$aa_seq = $aa_seqs[$i];
	    		if (substr($aa_seq, strlen($aa_seq) - 1) == '*') {
	    			$offset = $i;
	    			$longest_aa = $aa_seq;
	    			//Log::info($aa_seq);
	    		}
	    		if (strpos($aa_seq, '*') === false) {
	    			$offset = $i;
	    			$longest_aa = $aa_seq;	
	    		}
	    	}
			
	    }
	    //Log::info("offset2: $offset");
        // return peptide sequence
        //return array($start_pos, $aa_seq);
        return array($longest_aa, $offset);
	}

	static public function getHintHtml($data) {
		$html = "";
		foreach ($data as $key=>$value) {
			//print_r($value);
			//echo "<BR>";
			$html .= "<strong>$key: </strong> <div style='color:red'>$value</div>";
		}
		return $html;
	}

	static public function getCytobandRange() {
		$rows = \DB::select("select chromosome, min(start_pos) as min_pos, max(end_pos) as max_pos, substr(name, 1,1) as arm from cytoband group by chromosome, substr(name,1,1) order by chromosome");
		$cytoband_range = array();
		foreach ($rows as $row) {
			$cytoband_range[$row->chromosome][$row->arm] = array($row->min_pos, $row->max_pos);
		}
		return $cytoband_range;
	}

	static public function predictPfamDomain($seq) {
		$pep_file = tempnam(sys_get_temp_dir() , "");
		$out_file = tempnam(sys_get_temp_dir() , "");
		$sorted_out_file = tempnam(sys_get_temp_dir() , "");		
		$handle = fopen($pep_file, "w");
		fwrite($handle,$seq);
		fclose($handle);
		//$cmd = app_path()."/scripts/predictPfamDomain.sh $pep_file $out_file";		
		$cmd = app_path()."/bin/hmmer/binaries/hmmscan --domtblout $out_file -E 1e-5 --domE 1e-5 ".app_path()."/bin/hmmer/pfam/Pfam-A.hmm $pep_file";
		$ret = exec($cmd);
		$cmd = "cat $out_file | grep -v '^#' | sed 's/\s\+/ /g' | cut -d' ' -f1,2,23-";
		$ret = exec($cmd, $domain_descs);
		$descs = array();
		$accs = array();
		foreach ($domain_descs as $demain_desc) {
			$fields = preg_split('/\s/', $demain_desc);
			$descs[$fields[0]] = implode(' ', array_slice($fields, 2));
			$accs[$fields[0]] = $fields[1]; 
		}

		$ret = exec("cat $out_file | grep -v '^#' | awk '{print $1,$3,$4,$6,$7,$13,$16,$17,$18,$19}' | sed 's/ /\t/g' | sort -k 3,3 -k 6n > $sorted_out_file");		
		#Log::info("\n".file_get_contents($sorted_out_file));
		//$ret = exec("sed -i 's/ \+/ /g' $out_file");
		//$ret = exec("grep -v '#' $out_file | sort -n -t ' ' -k 20 > $sorted_out_file");
		//echo $sorted_out_file;
		$domains = array();
		foreach(file($sorted_out_file) as $line) {
			//echo $line."<BR>";
			if (substr($line, 0, 1) == "#")
				continue;
			$fields = preg_split('/\t/', $line);
			//$desc = implode(' ', array_slice($fields, 22));
			//$desc = 'desc';
   			//$domains[$fields[3]][] = array($fields[19], $fields[20], $fields[0], $fields[6],$desc, $fields[1]);
   			$domains[$fields[2]][] = array($fields[8], $fields[9], $fields[0], $fields[6], $descs[$fields[0]], $accs[$fields[0]]);
		}
		//return $domains;
		$filtered_domains = array();
		foreach ($domains as $gene=>$domain) {
			$selected_domains = array();
			for ($i=0;$i<count($domain);$i++) {
				$d1 = $domain[$i];				 
				$s1 = (int)$d1[0];
				$e1 = (int)$d1[1];
				$name = $d1[2];
				$evalue = $d1[3];
				$desc = $d1[4];
				$acc = $d1[5];				
				$len1 = $e1 - $s1;
				$overlapped = false;
				for ($j=0;$j<count($selected_domains);$j++) {
					$d2 = $selected_domains[$j];
					$s2 = (int)$d2[0];
					$e2 = (int)$d2[1];					
					$len2 = $e2 - $s2;
					$len = min($len1, $len2);	
					if (($e1 > $s2) && ($e2 > $s1)) {
						if ($s1 >= $s2 && (($e2 - $s1) / $len) > 0.1) {
							$overlapped = true;
							break;
						}
						if ($s2 >= $s1 && (($e1 - $s2) / $len) > 0.1) {
							$overlapped = true;
							break;
						}						
					}					
				}
				if (!$overlapped)
					$selected_domains[] = $d1;
			}
			usort($selected_domains, array("Gene", "compareDomains"));
			for ($i=0;$i<count($selected_domains);$i++) {
				$d1 = $selected_domains[$i];				 
				$s1 = chop($d1[0]);
				$e1 = chop($d1[1]);
				$name = $d1[2];
				$evalue = $d1[3];
				$desc = $d1[4];
				$acc = $d1[5];
				$len1 = $e1 - $s1;				
				$filtered_domains[$gene][] = array("start_pos" => $s1, "end_pos" => $e1, "name" => $name, "hint" => array("Name" => $name, "Coordinate" => "$s1 - $e1", "Length" => $e1-$s1+1, "Description" => $desc, "Accession" => "<a target=_blank href=http://pfam.xfam.org/family/$acc>$acc</a>"));				
			}
		}
		//Log::info(json_encode($filtered_domains));
		unlink($pep_file);
		unlink($out_file);
		unlink($sorted_out_file);
		return $filtered_domains;
	}

	static public function compareDomains($a, $b) {
		return ($a[0] > $b[0]);
	}

	static public function getCodingRange($trans) {
		$sql = "select * from refgene_coding where trans = '$trans'";
		$rows = \DB::select($sql);
		if (count($rows) > 0)
			return array($rows[0]->coding_start, $rows[0]->coding_end);
			//return array($rows[count($rows)-1]->coding_start + 1, $rows[count($rows)-1]->coding_end);
		return [];
	}

	// get the distance between two positions. The distance does not include intron part
	// pos2 must be bigger than pos1
	static public function getDistInTrans($exons, $pos1, $pos2) {
		if ($pos2 < $pos1)
			return -1;
		$dist = 0;
		$found_pos1 = false;
		$pre_exon = null;
		foreach ($exons as $exon) {			
			//if pos in intron
			if ($pre_exon != null) {
				if ($pos1 > $pre_exon->end_pos && $pos1 < $exon->start_pos)
					$pos1 = $exon->start_pos + 1;
				if ($pos2 > $pre_exon->end_pos && $pos2 < $exon->start_pos)
					$pos2 = $exon->start_pos + 1;
			}
			$pre_exon = $exon;
			$exon_start = $exon->start_pos;
			$exon_end = $exon->end_pos;
			$exon_dna = $exon->seq;
			$has_pos1 = ($exon_end >= $pos1 && $exon_start < $pos1);
			$has_pos2 = ($exon_end >= $pos2 && $exon_start < $pos2);

			// in the same exon
			if ($has_pos1 && $has_pos2)
				return ($pos2 - $pos1 + 1);
			//if pos1 in exon
			if ($has_pos1) {
				//Log::info("found_pos1");
				$dist = $exon_end - $pos1 + 1;
				$found_pos1 = true;
				continue;
			}								
			//if pos2 in exon
			if ($has_pos2) {
				//Log::info("found_pos2");
				if (!$found_pos1)
					return -2;
				return $dist + ($pos2 - $exon_start);
			}
				
			// if exon is in between			
			$dist += ($exon_end - $exon_start);			
		}
		return -2;
	}

	static public function getTranscriptSeq($exons, $strand, $utr5, $utr3) {
		$dna_string = "";
		foreach ($exons as $exon) {
			if ($utr5 && $exon->region_type == "utr5")
				$dna_string .= $exon->seq;
			if ($utr3 && $exon->region_type == "utr3")
				$dna_string .= $exon->seq;
			if ($exon->region_type == "cds")
				$dna_string .= $exon->seq;			
		}
		
		if ($strand == "-")
			$dna_string = Gene::reverseComplement($dna_string);		
		return $dna_string;
	}

	static public function getCodingSeq($exons, $strand, $include_5utr=false) {
		$dna_string = "";
			
		foreach ($exons as $exon) {
			$exon_start = $exon[0];
			$exon_end = $exon[1];
			$exon_dna = $exon[2];
			$has_coding_start = ($exon_end >= $coding_start && $exon_start <= $coding_start);
			$has_coding_end = ($exon_end >= $coding_end && $exon_start <= $coding_end);

			// in the same exon
			if ($has_coding_start && $has_coding_end) {
				$dna_string = substr($exon_dna, $coding_start - $exonstart, $coding_end - $coding_start + 1);
				break;
			}				
			
			//if coding start in exon
			if ($has_coding_start) {
				$dna_string = substr($exon_dna, $coding_start - $exon_start, $exon_end - $coding_start + 1);
				continue;
			}				
				
			//if coding end in exon
			if ($has_coding_end) {
				$dna_string .= substr($exon_dna, 0, $coding_end - $exon_start + 1);
				break;
			}
			// if exon is in between			
			$dna_string .= $exon_dna;						
		}
		
		if ($strand == "-")
			$dna_string = Gene::reverseComplement($dna_string);		
		return $dna_string;
	}

	static public function getGenesInfo() {
		$key = "genes_info";
		
		if (Cache::has($key)) {
			Log::info("get genes from cache");
			return Cache::get($key);
		}

		$gene_rows = Gene::getGenes('ensembl');
		$gene_infos = array();
		foreach($gene_rows as $gene_row) {
			$gene_infos[$gene_row->gene] = $gene_row;
		}

		Cache::forever($key, $gene_infos);
		
		return $gene_infos;
	}

	static public function getGenes($target_type = "ensembl") {
		$key = "genes.$target_type";
		
		if (Cache::has($key)) {
			Log::info("get genes from cache");
			return Cache::get($key);
		}
		
		$rows = \DB::table('gene')->where('target_type', $target_type)->get();		
		Cache::forever($key, $rows);
		return $rows;
	}

	private function getExonCoordTableName($data_type) {
		return ($data_type == 'Ensembl')? 'exon_coord_ensembl': 'exon_coord';
	}

	private function getGeneAnnotationTableName($data_type) {
		return ($data_type == "UCSC")? 'anno_rsu_gene': 'gene_ensembl';
	}

	private function getTransAnnotationTableName($data_type) {
		return ($data_type == "UCSC")? 'anno_rsu_trans': 'anno_rse_trans';
	}

	static public function getExpGeneSummary_OLD($gene_id, $category, $target_type="refseq", $lib_type="all") {
		$starttime = microtime(true);
		$lib_type_condition = "";
		if ($lib_type == "polyA")
			$lib_type_condition = " and library_type = 'polyA'";
		if ($lib_type == "nonPolyA")
			$lib_type_condition = " and library_type <> 'polyA'";
		if ($category == "diagnosis")
			$sql = "select distinct s.sample_id, s.sample_name, p.patient_id, p.diagnosis as category, value from sample_values v, samples s, patients p where 
					exp_type='RNAseq' and symbol='$gene_id' and target_type='$target_type' and target_level='gene' and v.sample_id=s.sample_id and s.patient_id=p.patient_id $lib_type_condition";
		else
			$sql = "select distinct s.sample_id, s.sample_name, s.patient_id, p.name as category, value from sample_values v, project_samples s, projects p where 
					symbol='$gene_id' and target_type='$target_type' and target_level='gene' and v.sample_id=s.sample_id and s.project_id=p.id $lib_type_condition";
		Log::info($sql);
		$rows = \DB::select($sql);
		$data = array();
		foreach ($rows as $row) {
			$data[$row->category][] = array($row->patient_id, $row->sample_id, round($row->value, 2));
		}
		$endtime = microtime(true);
		$timediff = $endtime - $starttime;
		Log::info("execution time (getExpGeneSummary): $timediff seconds");
		return $data;
	}

	static public function getExpGeneSummary($gene_id, $category,$tissue, $target_type="refseq", $lib_type="all") {
		$starttime = microtime(true);
		$lib_type_condition = "";
		if ($lib_type == "polyA")
			$lib_type_condition = " and library_type = 'polyA'";
		if ($lib_type == "nonPolyA")
			$lib_type_condition = " and library_type <> 'polyA'";
		$sql='select distinct s.patient_id, s.sample_id, p.diagnosis from samples s, patients p where s.patient_id=p.PATIENT_ID';
		$patients = \DB::select($sql);
		Log::info($sql);
		
#		$sql="select * from project_values where (symbol='$gene_id' or symbol='_list') and  target_level='gene' and value_type='tpm' and TARGET_TYPE='$target_type'";
		$sql="select p.GENE,p.PROJECT_ID,p.SYMBOL,p.TARGET,p.TARGET_LEVEL,p.TARGET_TYPE,p.VALUE_LIST,p.VALUE_TYPE ,n.name from project_values p,projects n where (p.symbol='$gene_id' or p.symbol='_list') and  p.target_level='gene' and p.value_type='tpm' and p.TARGET_TYPE='$target_type' and p.project_id=n.ID";

		Log::info($sql);
		$rows = \DB::select($sql);
		
		$data = array();
		$patient_data=array();
		$keys=array();
		$values=array();
		$projects=array();
		$distinct_samples=array();
		foreach ($rows as $row) {
			$project_id=$row->project_id;
			if($row->symbol=='_list')
				$keys[$project_id]=explode(",",$row->value_list);
			if($row->symbol==$gene_id)
				$values[$project_id]=explode(",",$row->value_list);
			$projects[$project_id]=$row->name;
		}
		foreach ($patients as $patient) {
			$patient_data[$patient->sample_id]=array($patient->patient_id,$patient->diagnosis);
		}
		Log::info("Processing expression data");
		foreach ($keys as $project_id=>$samples) {
			foreach ($samples as $i =>$sample) {
				if(array_key_exists($sample,$patient_data) && array_key_exists($project_id,$values)&& !array_key_exists($sample,$distinct_samples)){
					$distinct_samples[$sample]="";
					$patient_id=$patient_data[$sample][0];
					#Log::info($project_id.": ".$patient_id);
					$diagnosis=$patient_data[$sample][1];
					$project_name=$projects[$project_id];					

					$sample_values=$values[$project_id];
					if ($category == "diagnosis"){
						if($diagnosis=="Normal" && $tissue=="normal")
							$data[$diagnosis][] = array($patient_id, $sample, round($sample_values[$i], 2));
						else if($diagnosis!="Normal" && $tissue=="tumor")
							$data[$diagnosis][] = array($patient_id, $sample, round($sample_values[$i], 2));
						else if($tissue=="all")
							$data[$diagnosis][] = array($patient_id, $sample, round($sample_values[$i], 2));
					}
					else{
						if($diagnosis=="Normal" && $tissue=="normal")
							$data[$project_name][] = array($patient_id, $sample, round($sample_values[$i], 2));
						else if($diagnosis!="Normal" && $tissue=="tumor")
							$data[$project_name][] = array($patient_id, $sample, round($sample_values[$i], 2));
						else if($tissue=="all")
							$data[$project_name][] = array($patient_id, $sample, round($sample_values[$i], 2));
					}

					
				}
			}

		}
		$endtime = microtime(true);
		$timediff = $endtime - $starttime;
		Log::info("execution time (getExpGeneSummary): $timediff seconds");
		return $data;
	}

}
