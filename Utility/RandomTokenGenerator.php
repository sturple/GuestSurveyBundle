<?php

namespace Fgms\Bundle\SurveyBundle\Utility;

/**
 * Provides randomly generated token strings.
 */
class RandomTokenGenerator implements TokenGenerator
{
    
    private $bits;
    
    /**
     * Creates a RandomTokenGenerator object which
     * generates tokens of a certain length.
     *
     * @param int $bits
     *  The number of bits each token shall be.  Must
     *  be strictly positive and divisible by 8.
     */
    public function __construct ($bits)
    {
        if ($bits<=0) throw new \LogicException(
            sprintf(
                '%s is not strictly positive',
                $bits
            )
        );
        if (($bits%8)!==0) throw new \LogicException(
            sprintf(
                '%s is not evenly divisible by 8 and therefore cannot be converted to a number of bytes',
                $bits
            )
        );
        $this->bits=$bits;
    }
    
    public function generate()
    {
        $str=random_bytes($this->bits/8);
        return bin2hex($str);
    }
    
}
