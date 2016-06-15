<?php

namespace Fgms\Bundle\SurveyBundle\Utility;

/**
 * Provides an interface which may be implemented
 * to supply probabilistically-unique string tokens.
 */
interface TokenGenerator
{
    
    /**
     * Generates a string which is likely to be unique.
     * I.e. the probability of subsequent calls to this
     * method generating the same string should be so
     * that it may be treated as though it is zero.
     *
     * @return string
     */
    public function generate();
    
}
