<?php

class STLCalc {
	
	private $volume;
	private $weight;
	private $density = 1.04;
	private $triangles_count;
	private $triangles_data;
	private $b_binary;
	private $points;
	private $fstl_handle;
	private $fstl_path;
	private $triangles;
	private $flag = false;
	
	function __construct ( $filepath ) {
		$b = $this->IsAscii ( $filepath );
		if( !$b ) {
			echo "BINARY STL Suspected.\n";
			$this->b_binary = TRUE;
			$this->fstl_handle = fopen ( $filepath, 'rb' );
			$this->fstl_path = $filepath;
		} else {
			echo "ASCII STL Suspected.\n";
		}
		$this->triangles = array();
	}
	
	public function GetVolume ( $unit ) {
		if ( !$this->flag ) {
			$v = $this->CalculateVolume();
			$this->volume = $v;
			$this->flag = true;
		}
		$volume = 0;
		if( $unit == 'cm' ) {
			$volume = ( $this->volume / 1000 );
		} else {
			$volume = $this->Inch3 ( $this->volume / 1000 );
		}
		return $volume;
	}
	
	public function getWeight() {
		$volume = $this->GetVolume ( 'cm' );
		$weight = $this->CalculateWeight ( $volume );
		return $weight;
	}
	
	public function GetDensity() {
		return $this->density;
	}
	
	public function SetDensity ( $den ) {
		$this->density = $den;
	}
	
	public function GetTrianglesCount() {
		$tcount = $this->triangles_count;
		return $tcount;
	}
	
	private function CalculateVolume(){
		$totalVolume = 0;
		if ( $this->b_binary ) {
			$totbytes = filesize ( $this->fstl_path );
			$totalVolume = 0;
			try {
				$this->ReadHeader();
				$this->triangles_count = $this->ReadTrianglesCount();
				$totalVolume = 0;
				try {
					while ( ftell ( $this->fstl_handle ) < $totbytes ){
						$totalVolume += $this->ReadTriangle();
					}
				}
				catch ( Exception $e ) {
					return $e;
				}	
			}
			catch ( Exception $e ) {
				return $e;
			}
			fclose ( $this->fstl_handle );
		} else {
			$k = 0;
			while ( sizeof ( $this->triangles_data[4] ) > 0 ) {
				$totalVolume += $this->ReadTriangleAscii();
				$k += 1;
			}
			$this->triangles_count = $k;
		}
		return abs ( $totalVolume );
	}
	
	function PhUnpack ( $sig, $l ) {
		$s = fread ( $this->fstl_handle, $l );
		$stuff = unpack ( $sig, $s );
		return $stuff;
	}
	
	function PhAppend ( $myarr, $mystuff ) {
		if ( gettype ( $mystuff ) == 'array' ) {
			$myarr = array_merge ( $myarr, $mystuff );
		} else {
			$ctr = sizeof ( $myarr );
			$myarr[$ctr] = $mystuff;
		}
		return $myarr;
	}
	
	function ReadHeader() {
		fseek ( $this->fstl_handle, ftell ( $this->fstl_handle ) + 80 );
	}
	
	function ReadTrianglesCount() {
		$length = $this->PhUnpack ( 'I', 4 );		
		return $length[1];
	}
	
	function ReadTriangle() {
		$n	= $this->PhUnpack ( 'f3', 12 );
		$p1	= $this->PhUnpack ( 'f3', 12 );
		$p2	= $this->PhUnpack ( 'f3', 12 );
		$p3	= $this->PhUnpack ( 'f3', 12 );
		$b	= $this->PhUnpack ( 'v', 2 );
		$l = sizeof ( $this->points );
		$this->PhAppend ( $this->triangles, array ( $l, $l+1, $l+2 ) );
		return $this->SignedVolumeOfTriangle ( $p1, $p2, $p3 );
	}
	
	function ReadTriangleAscii() {
		$p1[1] = floatval ( array_pop ( $this->triangles_data[4] ) );
		$p1[2] = floatval ( array_pop ( $this->triangles_data[5] ) );
		$p1[3] = floatval ( array_pop ( $this->triangles_data[6] ) );
		$p2[1] = floatval ( array_pop ( $this->triangles_data[7] ) );
		$p2[2] = floatval ( array_pop ( $this->triangles_data[8] ) );
		$p2[3] = floatval ( array_pop ( $this->triangles_data[9] ) );
		$p3[1] = floatval ( array_pop ( $this->triangles_data[10] ) );
		$p3[2] = floatval ( array_pop ( $this->triangles_data[11] ) );
		$p3[3] = floatval ( array_pop ( $this->triangles_data[12] ) );
		$l = sizeof ( $this->points );
		$this->PhAppend ( $this->triangles, array ( $l, $l+1, $l+2 ) );
		return $this->SignedVolumeOfTriangle ( $p1, $p2, $p3 );
	}
	
	function IsAscii ( $filename ) {
		$b = FALSE;
		$namePattern = '/facet\\s+normal\\s+([-+]?\\b(?:[0-9]*\\.)?[0-9]+(?:[eE][-+]?[0-9]+)?\\b)\\s+([-+]?\\b(?:[0-9]*\\.)?[0-9]+(?:[eE][-+]?[0-9]+)?\\b)\\s+([-+]?\\b(?:[0-9]*\\.)?[0-9]+(?:[eE][-+]?[0-9]+)?\\b)\\s+'
			. 'outer\\s+loop\\s+'
			. 'vertex\\s+([-+]?\\b(?:[0-9]*\\.)?[0-9]+(?:[eE][-+]?[0-9]+)?\\b)\\s+([-+]?\\b(?:[0-9]*\\.)?[0-9]+(?:[eE][-+]?[0-9]+)?\\b)\\s+([-+]?\\b(?:[0-9]*\\.)?[0-9]+(?:[eE][-+]?[0-9]+)?\\b)\\s+'
			. 'vertex\\s+([-+]?\\b(?:[0-9]*\\.)?[0-9]+(?:[eE][-+]?[0-9]+)?\\b)\\s+([-+]?\\b(?:[0-9]*\\.)?[0-9]+(?:[eE][-+]?[0-9]+)?\\b)\\s+([-+]?\\b(?:[0-9]*\\.)?[0-9]+(?:[eE][-+]?[0-9]+)?\\b)\\s+'
			. 'vertex\\s+([-+]?\\b(?:[0-9]*\\.)?[0-9]+(?:[eE][-+]?[0-9]+)?\\b)\\s+([-+]?\\b(?:[0-9]*\\.)?[0-9]+(?:[eE][-+]?[0-9]+)?\\b)\\s+([-+]?\\b(?:[0-9]*\\.)?[0-9]+(?:[eE][-+]?[0-9]+)?\\b)\\s+'
			. 'endloop\\s+' . 'endfacet/';
		$fdata = file_get_contents ( $filename );
		preg_match_all ( $namePattern, $fdata, $matches );
		if ( sizeof ( $matches[0] ) > 0 ) {
			$b = TRUE;
			$this->triangles_data = $matches;
		}
		return $b;
	}
	
	function SignedVolumeOfTriangle ( $p1, $p2, $p3 ) {
		$v321 = $p3[1] * $p2[2] * $p1[3];
		$v231 = $p2[1] * $p3[2] * $p1[3];
		$v312 = $p3[1] * $p1[2] * $p2[3];
		$v132 = $p1[1] * $p3[2] * $p2[3];
		$v213 = $p2[1] * $p1[2] * $p3[3];
		$v123 = $p1[1] * $p2[2] * $p3[3];
		return ( 1.0 / 6.0 ) * ( -$v321 + $v231 + $v312 - $v132 - $v213 + $v123 );
	}
	
	function Inch3 ( $v ) {
		return $v * 0.0610237441;
	}
	
	function CalculateWeight ( $volumeInCm ) {
		return $volumeInCm * $this->density;
	}
}
?>