<?php
declare(strict_types=1);

namespace BigFish\PDF417;

interface EncoderInterface
{
    /**
     * Checks whether the given character can be encoded using this encoder.
     *
     * @param string $char The character.
     *
     * @return bool
     */
    public function canEncode($char);

    /**
     * Encodes a string into codewords.
     *
     * @param string $string String to encode.
     * @param bool $addSwitchCode Whether to add the mode switch code at the beginning.
     *
     * @return array An array of code words.
     *
     * @throws Exception If any of the characters cannot be encoded
     */
    public function encode($string, $addSwitchCode);

    /**
     * Returns the switch code word for the encoding mode implemented by the
     * encoder.
     *
     * @param string $data Data being encoded (can make a difference).
     *
     * @return int The switch code word.
     */
    public function getSwitchCode($data);
}
