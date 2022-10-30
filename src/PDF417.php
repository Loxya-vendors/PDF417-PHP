<?php
declare(strict_types=1);

namespace BigFish\PDF417;

/**
 * Constructs a PDF417 barcodes.
 */
class PDF417
{
    public const MIN_COLUMNS = 1;
    public const MAX_COLUMNS = 30;
    public const DEFAULT_COLUMNS = 6;

    public const MIN_SECURITY_LEVEL = 0;
    public const MAX_SECURITY_LEVEL = 8;
    public const DEFAULT_SECURITY_LEVEL = 2;

    // TODO: Check barcode respects rows/codeword limits.
    public const MIN_ROWS = 3;
    public const MAX_ROWS = 90;
    public const MAX_CODE_WORDS = 925;

    public const START_CHARACTER = 0x1fea8;
    public const STOP_CHARACTER  = 0x3fa29;

    public const PADDING_CODE_WORD = 900;


    // -- Properties -----------------------------------------------------------

    /**
     * Number of data columns in the bar code.
     *
     * The total number of columns will be greater due to adding start, stop,
     * left and right columns.
     *
     * Valid values are between 3 and 30, defaults to 6.
     *
     * @var int
     */
    private $columns = self::DEFAULT_COLUMNS;

    /**
     * The security level to use for Reed Solomon error correction.
     *
     * Valid values are between 0 and 8, defaults to 2.
     *
     * @var int
     */
    private $securityLevel = self::DEFAULT_SECURITY_LEVEL;

    // -- Accessors ------------------------------------------------------------

    /**
     * Returns the column count.
     *
     * @return int
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Sets the column count.
     *
     * @param int $columns
     */
    public function setColumns($columns)
    {
        $min = self::MIN_COLUMNS;
        $max = self::MAX_COLUMNS;

        if (!is_numeric($columns)) {
            throw new \InvalidArgumentException("Column count must be numeric. Given: $columns");
        }

        if ($columns < $min || $columns > $max) {
            throw new \InvalidArgumentException("Column count must be between $min and $max. Given: $columns");
        }

        $this->columns = intval($columns);
    }

    /**
     * Returns the security level.
     *
     * @return int
     */
    public function getSecurityLevel()
    {
        return $this->securityLevel;
    }

    /**
     * Sets the security level.
     *
     * @param int $securityLevel
     */
    public function setSecurityLevel($securityLevel)
    {
        $min = self::MIN_SECURITY_LEVEL;
        $max = self::MAX_SECURITY_LEVEL;

        if (!is_numeric($securityLevel)) {
            throw new \InvalidArgumentException("Security level must be numeric. Given: $securityLevel");
        }

        if ($securityLevel < $min || $securityLevel > $max) {
            throw new \InvalidArgumentException("Security level must be between $min and $max. Given: $securityLevel");
        }

        $this->securityLevel = intval($securityLevel);
    }

    // -------------------------------------------------------------------------

    /**
     * Encodes the given data to low level code words.
     *
     * @param  string $data
     * @return BarcodeData
     */
    public function encode($data)
    {
        $codeWords = $this->encodeData($data);
        $secLev = $this->securityLevel;
        $columns = $this->columns;

        // Arrange codewords into a rows and columns
        $grid = array_chunk($codeWords, $columns);
        $rows = count($grid);

        // Iterate over rows
        $codes = [];
        foreach ($grid as $rowNum => $row) {
            $table = $rowNum % 3;
            $rowCodes = [];

            // Add starting code word
            $rowCodes[] = self::START_CHARACTER;

            // Add left-side code word
            $left = $this->getLeftCodeWord($rowNum, $rows, $columns, $secLev);
            $rowCodes[] = Codes::getCode($table, $left);

            // Add data code words
            foreach ($row as $word) {
                $rowCodes[] = Codes::getCode($table, $word);
            }

            // Add right-side code word
            $right = $this->getRightCodeWord($rowNum, $rows, $columns, $secLev);
            $rowCodes[] = Codes::getCode($table, $right);

            // Add ending code word
            $rowCodes[] = self::STOP_CHARACTER;

            $codes[] = $rowCodes;
        }

        $data = new BarcodeData();
        $data->codes = $codes;
        $data->rows = $rows;
        $data->columns = $columns;
        $data->codeWords = $codeWords;
        $data->securityLevel = $secLev;

        return $data;
    }

    /** Encodes data to a grid of codewords for constructing the barcode. */
    public function encodeData($data)
    {
        $columns = $this->columns;
        $secLev = $this->securityLevel;

        // Encode data to code words
        $encoder = new DataEncoder();
        $dataWords = $encoder->encode($data);

        // Number of code correction words
        $ecCount = pow(2, $secLev + 1);
        $dataCount = count($dataWords);

        // Add padding if needed
        $padWords = $this->getPadding($dataCount, $ecCount, $columns);
        $dataWords = array_merge($dataWords, $padWords);

        // Add length specifier as the first data code word
        // Length includes the data CWs, padding CWs and the specifier itself
        $length = count($dataWords) + 1;
        array_unshift($dataWords, $length);

        // Compute error correction code words
        $reedSolomon = new ReedSolomon();
        $ecWords = $reedSolomon->compute($dataWords, $secLev);

        // Combine the code words and return
        return array_merge($dataWords, $ecWords);
    }

    // -------------------------------------------------------------------------

    private function getLeftCodeWord($rowNum, $rows, $columns, $secLev)
    {
        // Table used to encode this row
        $tableID = $rowNum % 3;

        $x = match ($tableID) {
            0 => intval(($rows - 1) / 3),
            1 => ($secLev * 3) + (($rows - 1) % 3),
            2 => $columns - 1,
            default => 0,
        };

        return 30 * intval($rowNum / 3) + $x;
    }

    private function getRightCodeWord($rowNum, $rows, $columns, $secLev)
    {
        $tableID = $rowNum % 3;

        $x = match ($tableID) {
            0 => $columns - 1,
            1 => intval(($rows - 1) / 3),
            2 => ($secLev * 3) + (($rows - 1) % 3),
            default => 0,
        };

        return 30 * intval($rowNum / 3) + $x;
    }

    private function getPadding($dataCount, $ecCount, $columns)
    {
        // Total number of data words and error correction words, additionally
        // reserve 1 code word for the length descriptor
        $totalCount = $dataCount + $ecCount + 1;
        $mod = $totalCount % $columns;

        if ($mod > 0) {
            $padCount = $columns - $mod;
            $padding = array_fill(0, $padCount, self::PADDING_CODE_WORD);
        } else {
            $padding = [];
        }

        return $padding;
    }
}
