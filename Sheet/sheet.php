<?php

/**
 * Sheet - CSV and Excel tools
 *
 * The contents of this file are subject to the terms of the GNU General
 * Public License Version 3.0. You may not use this file except in
 * compliance with the license. Any of the license terms and conditions
 * can be waived if you get permission from the copyright holder.
 *
 * (c) Christian Knuth
 *
 * @date: 09.09.14
 * @version 0.3.0
 */


class Sheet extends \Prefab {

	/**
	 * multiline-aware CSV parser
	 * @param $filepath
	 * @param string $delimiter
	 * @param string $enclosure
	 * @return array|bool
	 */
	public function parseCSV($filepath,$delimiter=";",$enclosure='"') {
		if (!is_file($filepath)) {
			user_error('File not found: '.$filepath);
			return false;
		}
		$data = \Base::instance()->read($filepath,true);
		if(!preg_match_all('/((?:.*?)'.$delimiter.'(?:'.$enclosure.'.*?'.
			$enclosure.'|['.$delimiter.'\d])*\n)/s',$data,$matches))
			user_error('no rows found');
		$out = array_map(function($val) use($delimiter,$enclosure) {
			return str_getcsv($val,$delimiter,$enclosure);
		},$matches[0]);
		return $out;
	}

	/**
	 * use specified headers or first row as label for each row item key
	 * @param $rows
	 * @param null $headers
	 * @return array
	 */
	public function applyHeader($rows,$headers=null) {
		if (!$headers)
			$headers=array_shift($rows);
		return array_map(function($row) use($headers) {
			return array_combine(array_values($headers),array_values($row));
		},$rows);
	}

	/**
	 * build and return xls file data
	 * @param $rows
	 * @param $headers
	 * @return string
	 */
	public function dumpXLS($rows,$headers) {
		$numColumns = count($headers);
		$numRows = count($rows);
		foreach($headers as $key=>$val)
			if (is_numeric($key)) {
				$headers[$val]=ucfirst($val);
				unset($headers[$key]);
			}
		$xls = $this->xlsBOF();
		for ($i = 0; $i <= $numRows; $i++) {
			for ($c = 0; $c <= $numColumns; $c++) {
				$ckey = key($headers);
				$val='';
				if ($i==0)
					$val = current($headers);
				elseif (isset($rows[$i-1][$ckey]))
					$val = $rows[$i-1][$ckey];
				if (is_array($val))
					$val = json_encode($val);
				$xls.= (is_numeric($val))
					? $this->xlsWriteNumber($i,$c,$val)
					: $this->xlsWriteString($i,$c,utf8_decode($val));
				next($headers);
			}
			reset($headers);
		}
		$xls .= $this->xlsEOF();
		return $xls;
	}

	/**
	 * render xls file and send to HTTP client
	 * @param $rows
	 * @param $headers
	 * @param $filename
	 */
	function renderXLS($rows,$headers,$filename) {
		$data = $this->dumpXLS($rows,$headers);
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header('Content-Type: application/vnd.ms-excel');
		header("Content-Disposition: attachment;filename=".$filename);
		header("Content-Transfer-Encoding: binary");
		echo $data;
		exit();
	}

	/**
	 * start file
	 * @return string
	 */
	protected function xlsBOF() {
		return pack("ssssss", 0x809, 0x8, 0x0, 0x10, 0x0, 0x0);
	}

	/**
	 * end file
	 * @return string
	 */
	protected function xlsEOF() {
		return pack("ss", 0x0A, 0x00);
	}

	/**
	 * put number
	 * @param $row
	 * @param $col
	 * @param $val
	 * @return string
	 */
	protected function xlsWriteNumber($row, $col, $val) {
		$out = pack("sssss", 0x203, 14, $row, $col, 0x0);
		$out.= pack("d", $val);
		return $out;
	}

	/**
	 * put string
	 * @param $row
	 * @param $col
	 * @param $val
	 * @return string
	 */
	protected function xlsWriteString($row, $col, $val ) {
		$l = strlen($val);
		$out = pack("ssssss", 0x204, 8+$l, $row, $col, 0x0, $l);
		$out.= $val;
		return $out;
	}

}