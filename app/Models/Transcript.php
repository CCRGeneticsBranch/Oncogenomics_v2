<?php

class Transcript extends Eloquent {

	protected $fillable = [];
    //protected $table = 'trans_coordinate';
    protected $table = 'transcripts';
    private $exons;    

    function __construct($row=null) {
    	if ($row == null)
    		return;
    	$this->chromosome = $row->chromosome;
    	$this->start_pos = $row->start_pos;
    	$this->end_pos = $row->end_pos;
    	$this->coding_start = $row->coding_start;
    	$this->coding_end = $row->coding_end;
    	$this->target_type = $row->target_type;
    	$this->strand = $row->strand;
    	$this->gene = $row->gene;
    	$this->symbol = $row->symbol;
    	$this->trans = $row->trans;
    	$this->canonical = $row->canonical;
    	if (isset($row->coding_seq))
    		$this->coding_seq = $row->coding_seq;
    	if (isset($row->aa_seq))
    		$this->aa_seq = $row->aa_seq;
    	if (isset($row->domain))
    		$this->domain = $row->domain;
    }

	function getExons() {
		if ($this->exons != null)
			return $this->exons;
		$sql = "select * from exon_coordinate where trans='".$this->trans."' order by start_pos";
		$this->exons = DB::select($sql);
		return $this->exons;
	}	

	static public function getTranscriptsBySymbol($symbol, $coding_seq=false, $aa_seq=false, $domain=false, $target_type="all") {
		$target_type_clause = "";
		if ($target_type != "all")
			$target_type_clause = " and target_type = '$target_type'";
		$sql = "select chromosome, start_pos, end_pos, coding_start, coding_end, target_type, strand, gene, symbol, trans, canonical ".(($coding_seq)? ",coding_seq" : "").(($aa_seq)? ",aa_seq" : "").(($domain)? ",domain" : "")." from Transcripts where symbol='$symbol' $target_type_clause";
		$trans_list = array();
		$rows = DB::select($sql);
		foreach ($rows as $row) {
			$trans_list[] = new Transcript($row);
		}
		return $trans_list;
	}

	static public function getTranscriptsByID($trans, $coding_seq=false, $aa_seq=false, $domain=false) {
		$sql = "select chromosome, start_pos, end_pos, coding_start, coding_end, target_type, strand, gene, symbol, trans, canonical ".(($coding_seq)? ",coding_seq" : "").(($aa_seq)? ",aa_seq" : "").(($domain)? ",domain" : "")." from Transcripts where trans='$trans'";
		$rows = DB::select($sql);
		Log::info("getTranscriptsByID sql: $sql");
		if (count($rows) > 0)
			return new Transcript($rows[0]);
		return null;
	}

	public function getTranscriptSeq($utr5, $utr3) {
		$dna_string = "";
		foreach ($this->exons as $exon) {
			if ($utr5 && $exon->region_type == "utr5")
				$dna_string .= $exon->seq;
			if ($utr3 && $exon->region_type == "utr3")
				$dna_string .= $exon->seq;
			if ($exon->region_type == "cds")
				$dna_string .= $exon->seq;			
		}		
		if ($this->strand == "-")
			$dna_string = Gene::reverseComplement($dna_string);		
		return $dna_string;
	}

	public function getCodingSeq() {
		if ($this->coding_seq == null)
			$this->coding_seq = $this->getTranscriptSeq(false, false);	
		return $this->coding_seq;
		
	}

	public function getAASeq() {
		if ($this->aa_seq == null) {			
			$coding_seq = $this->getCodingSeq();
			list($this->aa_seq, $offset) = Gene::translateDNA($coding_seq, [0, strlen($coding_seq) - 1]);
		}
		return $this->aa_seq;		
	}

	public function getExonPosInProtein() {
		$pos_in_protein = array();
		if ($this->strand == "+") {
			for ($i=0; $i<count($this->exons) - 1; $i++) {
				$exon = $this->exons[$i];
				$pos = $this->getDistInTrans($this->coding_start, $exon->end_pos);
				$pos = (int)($pos/3);
				if ($pos > 0)
					$pos_in_protein[] = $pos;
			}
		} else {
			for ($i=count($this->exons)-1; $i>0; $i--) {
				$exon = $this->exons[$i];
				$pos = $this->getDistInTrans($exon->end_pos, $this->coding_end);
				$pos = (int)($pos/3);
				if ($pos > 0)
					$pos_in_protein[] = $pos;
			}
		}
		return $pos_in_protein;
	}
	// get the distance between two positions. The distance does not include intron part
	// pos2 must be bigger than pos1.
	// pos1 and pos2 is 1-base. The exon coordinate is 0-base
	public function getDistInTrans($pos1, $pos2) {
		if ($pos2 < $pos1)
			return -1;
		$dist = 0;
		$found_pos1 = false;
		$pre_exon = null;
		if ($pos1 < $this->exons[0]->start_pos)
			$pos1 = $this->exons[0]->start_pos + 1;
		if ($pos2 > $this->exons[count($this->exons)-1]->end_pos)
			$pos2 = $this->exons[count($this->exons)-1]->end_pos;
		#Log::info("pos1:".$pos1);
		#Log::info("pos2:".$pos2);
		foreach ($this->exons as $exon) {
			//if pos in intron	
			#Log::info("start:".$exon->start_pos);
			#Log::info("end:".$exon->end_pos);		
			if ($pre_exon != null) {
				if ($pos1 > $pre_exon->end_pos && $pos1 <= $exon->start_pos)
					$pos1 = $exon->start_pos + 1;
				if ($pos2 > $pre_exon->end_pos && $pos2 <= $exon->start_pos)
					$pos2 = $exon->start_pos + 1;
			}
			$pre_exon = $exon;
			$exon_start = $exon->start_pos;
			$exon_end = $exon->end_pos;
			$exon_dna = $exon->seq;
			$has_pos1 = ($exon_end >= $pos1 && $exon_start < $pos1);
			$has_pos2 = ($exon_end >= $pos2 && $exon_start < $pos2);
			// in the same exon
			if ($has_pos1 && $has_pos2) {
				return ($pos2 - $pos1 + 1);
			}
			//if pos1 in exon
			if ($has_pos1) {
				$dist = $exon_end - $pos1 + 1;
				$found_pos1 = true;
				continue;
			}								
			//if pos2 in exon
			if ($has_pos2) {
				if (!$found_pos1)
					return -2;
				return $dist + ($pos2 - $exon_start);
			}
				
			// if exon is in between			
			$dist += ($exon_end - $exon_start);
		}
		return -2;
	}
}
