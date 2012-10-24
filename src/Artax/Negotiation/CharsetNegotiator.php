<?php

namespace Artax\Negotiation;

use Artax\Negotiation\Terms\Term;

class CharsetNegotiator extends AbstractNegotiator {
    
    /**
     * Negotiates the appropriate charset from a raw HTTP Accept-Charset header
     * 
     * ```
     * <?php
     * use Artax\Negotiation\CharsetNegotiator;
     * 
     * $rawHeader = 'utf-8, *;q=0.2';
     * $availableTypes = array(
     *     'utf-8' => 1,
     *     'iso-8859-5' => 0.5
     *     'unicode-1-1' => 0.2
     * );
     * 
     * $negotiator = new CharsetNegotiator();
     * $negotiatedCharset = $negotiator->negotiate($rawHeader, $availableTypes);
     * echo $negotiatedCharset; // utf-8
     * ```
     * 
     * @param string $rawAcceptCharsetHeader A raw Accept-Charset HTTP header value
     * @param array $availableCharsets An array of available charsets
     * @throws \Spl\ValueException On invalid available charset array
     * @throws NotAcceptableException If no acceptable charsets exist
     * @return string Returns the negotiated character set
     */
    public function negotiate($rawAcceptCharsetHeader, array $availableCharsets) {
        $this->validateQualityValues($availableCharsets);
        
        // Order available types from highest to lowest preference
        arsort($availableCharsets);
        
        // rfc2616-sec3.4: "HTTP character sets are identified by case-insensitive tokens."
        $availableKeys = array_map('strtolower', array_keys($availableCharsets));
        $availableVals = array_values($availableCharsets);
        $availableCharsets = array_combine($availableKeys, $availableVals);
        $rawAcceptCharsetHeader = strtolower($rawAcceptCharsetHeader);
        
        // rfc2616-sec14.2: "If no Accept-Charset header is present, the default is that 
        // any character set is acceptable."
        if (!$rawAcceptCharsetHeader) {
            return key($availableCharsets);
        }
        
        $parsedHeaderTerms = $this->parseTermsFromHeader($rawAcceptCharsetHeader);
        
        if ($negotiatedType = $this->doNegotiation($availableCharsets, $parsedHeaderTerms)) {
            return $negotiatedType;
        } else {
            throw new NotAcceptableException(
                "No available charsets match `Accept-Charset: $rawAcceptCharsetHeader`. " .
                'Available set: [' . implode('|', $availableKeys) . ']'
            );
        }
    }
    
    /**
     * @param string $rawAcceptCharsetHeader
     * @return array[Term]
     */
    protected function parseTermsFromHeader($rawAcceptCharsetHeader) {
        $terms = parent::parseTermsFromHeader($rawAcceptCharsetHeader);
        
        // As per rfc2616-sec14.2:
        //
        // The special value "*", if present in the Accept-Charset field, matches every 
        // character set (including ISO-8859-1) which is not mentioned elsewhere in the 
        // Accept-Charset field. If no "*" is present in an Accept-Charset field, then all
        // character sets not explicitly mentioned get a quality value of 0, except for 
        // ISO-8859-1, which gets a quality value of 1 if not explicitly mentioned.
        $coalescedTerms = $this->coalesceWildcardAndIso88591($terms);
        
        return $coalescedTerms;
    }
    
    /**
     * @param array $terms
     * @return array[Term]
     */
    private function coalesceWildcardAndIso88591(array $terms) {
        if (in_array('iso-8859-1', $terms) || in_array('*', $terms)) {
            return $terms;
        }
        
        $terms[] = new Term(count($terms), 'iso-8859-1', 1, false);
        return $terms;
    }
    
    /**
     * @param array $availableTypes
     * @param array $parsedHeaderTerms
     * @return string Returns negotiated charset or NULL if no acceptable values
     */
    private function doNegotiation(array $availableTypes, array $parsedHeaderTerms) {
        $scratchTerms = array();
        
        $wildcardAllowed = false;
        $asteriskTermKey = array_search('*', $parsedHeaderTerms);
        if (false !== $asteriskTermKey) {
            $wildcardAllowed = true;
            $asteriskQval = $parsedHeaderTerms[$asteriskTermKey]->getQuality();
            $asteriskIsExplicit = $parsedHeaderTerms[$asteriskTermKey]->hasExplicitQuality();
        }
        
        foreach ($availableTypes as $type => $qval) {
            $termKey = array_search($type, $parsedHeaderTerms);
            if (false !== $termKey) {
                $term = $parsedHeaderTerms[$termKey];
                $position = $term->getPosition();
                $negotiatedQval = $this->negotiateQualityValue($term->getQuality(), $qval);
                $hasExplicitQval = $term->hasExplicitQuality();
                
                $scratchTerms[] = new Term(
                    $position,
                    $type,
                    $negotiatedQval,
                    $hasExplicitQval
                );
            } elseif ($wildcardAllowed) {
                $negotiatedQval = $this->negotiateQualityValue($asteriskQval, $qval);
                $scratchTerms[] = new Term(
                    $asteriskTermKey,
                    $type,
                    $negotiatedQval,
                    $asteriskIsExplicit
                );
            }
        }
        
        $scratchTerms = $this->filterRejectedTerms($scratchTerms);
        $scratchTerms = $this->sortTermsByPreference($scratchTerms);
        
        if ($scratchTerms) {
            return current($scratchTerms)->getType();
        } else {
            return null;
        }
    }
}