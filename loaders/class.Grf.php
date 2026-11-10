<?php

/**
* @fileoverview Grf - Load and Parse .grf file: versions 0x200 and 0x300 (the 2025 kRO client's, with 64-bit
* offsets), without DES encryption.
* @author Vincent Thibault (alias KeyWorld - Twitter: @robrowser)
* @version 1.0.0
*/



class Grf
{

	private $fileTable, $header;

	private $fileTableLower = null; // fileTable in lower case (getFile)
	public $loaded = false;
	public $filename = '';
	/** Bytes of file info after each name in the file table: 17 in 0x200, 21 in 0x300 (64-bit offset). */
	private $entrySize = 17;
	protected $fp;

	const HEADER_SIZE = 46;


	/**
	 * Constructor, open the filename if specify
	 */
	public function __construct($filename=false)
	{
		if( $filename )
			$this->open($filename);
	}


	/**
	 * Clean up memory
	 */
	public function __destruct()
	{
		if( $this->fp && is_resource($this->fp) ) {
			fclose($this->fp);
		}
	}


	/**
	 * Open a file
	 */
	public function open($filename)
	{
		if( !file_exists($filename) || !is_readable($filename) )
			throw new Exception("GRF::load() - Can't open '{$filename}'.");

		if( filesize($filename) < self::HEADER_SIZE )
			throw new Exception("GRF::load() - Not enough data to contain a valid header.");

		// Open it
		$this->fp   = fopen( $filename, 'r' );
	}


	/**
	 * Load the GRF
	 */
	public function load()
	{
		if( empty($this->fp) )
			throw new Exception("GRF::load() - No file specify.");

		// Parse header: 0x300 has a 64-bit table offset (and no seed) where 0x200 has two 32-bit fields.
		$raw          = fread($this->fp, self::HEADER_SIZE);
		$this->header = unpack("a15signature/a15key/Ltable_offset/Lseeds/Lfilecount/Lversion", $raw);

		if ( $this->header['version'] === 0x300 ) {
			$this->header['table_offset'] = unpack("P", substr($raw, 30, 8))[1];
			$this->entrySize = 21;
		}
		else if ( $this->header['signature'] !== 'Master of Magic' || $this->header['version'] !== 0x200 ) {
			throw new Exception("GRF::load() - Sorry, can only open 0x200 and 0x300 versions.");
		}

		// Load table list (0x300: 4 bytes before its sizes)
		fseek( $this->fp, $this->header['table_offset'] + ($this->header['version'] === 0x300 ? 4 : 0), SEEK_CUR);
		$fileTableInfo   = unpack("Lpack_size/Lreal_size", fread($this->fp, 0x08));
		$this->fileTable = @gzuncompress( fread( $this->fp, $fileTableInfo['pack_size'] ), $fileTableInfo['real_size'] );

		// Extraction error
		if($this->fileTable === false )
			throw new Exception("GRF::load() - Can't extract fileTable.");

		// Grf now loaded
		$this->loaded    = true;
	}


	/**
	 * Search a filename
	 */
	public function getFile($filename, &$content)
	{
		if( !$this->loaded )
			return false;

		// GRF names are case-insensitive (the client opens them as Windows paths): look the name up in lower case in
		// a lower-cased copy of the table, then read the entry from the table itself at the same place (strtolower
		// changes only A-Z, so lengths and positions stay; names' CP949 bytes are left alone).
		if( $this->fileTableLower === null )
			$this->fileTableLower = strtolower($this->fileTable);
		$filename = strtolower($filename);
		$position = strpos( $this->fileTableLower, $filename . "\0" );

		// File not found
		if( $position === false )
			return false;

		// Extract file info from fileList
		$position += strlen($filename) + 1;
		$fileInfo  = $this->entrySize === 21
			? unpack('Lpack_size/Llength_aligned/Lreal_size/Cflags/Pposition', substr($this->fileTable, $position, 21) )
			: unpack('Lpack_size/Llength_aligned/Lreal_size/Cflags/Lposition', substr($this->fileTable, $position, 17) );

		// Just open file.
		if( $fileInfo['flags'] !== 1 )
			return false;

		// Extract file
		fseek( $this->fp, $fileInfo['position'] + self::HEADER_SIZE, SEEK_SET );
		$content = gzuncompress( fread($this->fp, $fileInfo['pack_size']), $fileInfo['real_size'] );

		return true;
	}


	/**
	 * Filter
	 * Find all occurences of a string in GRF list
	 */
	public function search( $regex ) {
		$list = array();
		@preg_match_all( $regex, $this->fileTable, $matches );

		if ( !empty($matches) ) {
			$list = $matches[0];
			sort($list);
		}

		return $list;
	}
}

