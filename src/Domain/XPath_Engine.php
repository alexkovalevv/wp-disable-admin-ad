<?php

namespace ADSD\Domain;

/**
 * Encapsulates DOM parsing and XPath application with safe options and fail-safes.
 */
class XPath_Engine {
    /** @var bool */
    private bool $use_html5 = false;

    public function __construct() {
        $this->use_html5 = class_exists('Masterminds\\HTML5');
    }
    /**
     * Apply XPath rules to provided HTML.
     *
     * @param string $html
     * @param string[] $xpaths
     * @param bool $delete_nodes True to delete nodes, false to hide via inline CSS on node.
     * @return string
     */
    public function apply_rules( string $html, array $xpaths, bool $delete_nodes ): string {
        if ( trim( $html ) === '' || empty( $xpaths ) ) {
            return $html;
        }

        // Ensure HTML has a root element for DOMDocument.
        $has_html_tag = str_contains( $html, '<html' );
        if ( ! $has_html_tag ) {
            $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>';
        }

        if ( $this->use_html5 ) {
            // Safer HTML5 parsing
            $html5 = new \Masterminds\HTML5([ 'disable_html_ns' => true ]);
            $dom = $html5->loadHTML( $html );
        } else {
            libxml_use_internal_errors( true );
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = false;

            $loaded = $dom->loadHTML( $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NOXMLDECL | LIBXML_COMPACT );
            if ( ! $loaded ) {
                libxml_clear_errors();
                return $html;
            }
        }

        $xpath = new \DOMXPath( $dom );

        foreach ( $xpaths as $expr ) {
            $expr = $this->sanitize_xpath( (string) $expr );
            if ( $expr === '' ) {
                continue;
            }
            
            // Try multiple strategies to find the element
            $nodes = $this->find_nodes_with_fallback( $xpath, $expr );
            if ( ! $nodes || $nodes->length === 0 ) {
                continue;
            }
            // Collect nodes and process deepest-first
            $collected = [];
            foreach ( $nodes as $n ) {
                if ( $n instanceof \DOMElement || $n instanceof \DOMText ) {
                    $depth = 0; $p = $n; while ( $p = $p->parentNode ) { $depth++; }
                    $collected[] = [ 'node' => $n, 'depth' => $depth ];
                }
            }
            usort( $collected, static function( $a, $b ) { return $b['depth'] <=> $a['depth']; } );

            $count = 0;
            foreach ( $collected as $item ) {
                /** @var \DOMNode $node */
                $node = $item['node'];
                if ( $delete_nodes ) {
                    if ( $node->parentNode ) {
                        // Replace with hidden placeholder element to preserve flow more safely than comments
                        $placeholder = $dom->createElement('span');
                        $placeholder->setAttribute('data-adsd-removed', '1');
                        $placeholder->setAttribute('aria-hidden', 'true');
                        $node->parentNode->replaceChild( $placeholder, $node );
                    }
                } else {
                    // Hide via CSS style attribute (non-destructive).
                    if ( $node instanceof \DOMElement ) {
                        $existing = $node->getAttribute( 'style' );
                        $node->setAttribute( 'style', trim( $existing . ';display:none !important;' ) );
                    } elseif ( $node instanceof \DOMText ) {
                        if ( $node->parentNode instanceof \DOMElement ) {
                            $existing = $node->parentNode->getAttribute( 'style' );
                            $node->parentNode->setAttribute( 'style', trim( $existing . ';display:none !important;' ) );
                        }
                    }
                }
                $count++;
                if ( $count > 2000 ) {
                    // Safety guard to avoid excessive operations.
                    break;
                }
            }
        }

        // Serialize output safely
        if ( $this->use_html5 ) {
            $html5 = new \Masterminds\HTML5([ 'disable_html_ns' => true ]);
            $result = $html5->saveHTML( $dom );
            // Validate by reloading and resaving once more to ensure balance
            try {
                $tmpDom = $html5->loadHTML( $result );
                $result = $html5->saveHTML( $tmpDom );
            } catch ( \Throwable $e ) {
                // Fallback to original HTML if anything goes wrong
                return $html;
            }
            return is_string( $result ) ? $result : $html;
        } else {
            $result = $dom->saveHTML();
            libxml_clear_errors();
            return is_string( $result ) ? $result : $html;
        }
    }

    /**
     * Sanitize XPath expression to mitigate injection and errors.
     */
    private function sanitize_xpath( string $expr ): string {
        $expr = preg_replace( '/[\x00-\x1F\x7F]/u', '', $expr );
        $expr = trim( $expr );
        if ( strlen( $expr ) > 500 ) {
            $expr = substr( $expr, 0, 500 );
        }
        return $expr;
    }

    /**
     * Remove contains(@class, 'token') predicates for likely dynamic class tokens.
     */
    private function relax_dynamic_classes( string $expr ): string {
        $out = $expr;
        // Remove individual contains() segments that match dynamic classes
        // Pattern captures contains(concat(' ', normalize-space(@class), ' '), ' TOKEN ')
        $out = preg_replace_callback(
            "/contains\(concat\(\' \\', normalize-space\(@class\), \\': \\'\), \'\s([^\']+)\s\'\)/",
            function ( $m ) {
                $token = $m[1];
                if ($this->is_probably_dynamic_class($token)) {
                    return 'true()'; // neutral element for 'and' chains
                }
                return $m[0];
            },
            $out
        );
        // Clean up [ ... and true() and ... ] sequences
        $out = preg_replace('/\[\s*(true\(\)\s*(and\s*true\(\)\s*)*)\]/', '', $out);
        $out = preg_replace('/and\s*true\(\)\s*/', '', $out);
        $out = preg_replace('/\[\s*\]/', '', $out);
        return $out;
    }

    private function is_probably_dynamic_class( string $c ): bool {
        if ($c === '') return true;
        if (preg_match('/^(sc|css)-[a-zA-Z0-9]+$/', $c)) return true; // styled-components, css-in-js
        if (preg_match('/^[a-zA-Z]{5,}$/', $c) && preg_match('/[A-Z]/', $c) && preg_match('/[a-z]/', $c)) return true; // jPPOou
        if (preg_match('/(\\d{3,}|^wp-\\d+|active|selected|focus|hover|tmp|nonce|token|hash)/i', $c)) return true;
        return false;
    }

    /**
     * Find nodes using multiple fallback strategies for better targeting.
     *
     * @param \DOMXPath $xpath
     * @param string $expr
     * @return \DOMNodeList|null
     */
    private function find_nodes_with_fallback( \DOMXPath $xpath, string $expr ): ?\DOMNodeList {
        // Strategy 1: Try original XPath
        $nodes = @$xpath->evaluate( $expr );
        if ( $nodes instanceof \DOMNodeList && $nodes->length > 0 ) {
            return $nodes;
        }

        // Strategy 2: Try relaxed version without dynamic class predicates
        $relaxed = $this->relax_dynamic_classes( $expr );
        if ( $relaxed !== $expr ) {
            $nodes = @$xpath->evaluate( $relaxed );
            if ( $nodes instanceof \DOMNodeList && $nodes->length > 0 ) {
                return $nodes;
            }
        }

        // Strategy 3: Try to find by content-based targeting for notices
        $content_based = $this->create_content_based_xpath( $expr );
        if ( $content_based ) {
            $nodes = @$xpath->evaluate( $content_based );
            if ( $nodes instanceof \DOMNodeList && $nodes->length > 0 ) {
                return $nodes;
            }
        }

        // Strategy 4: Try simplified path-based targeting
        $simplified = $this->simplify_xpath_for_notices( $expr );
        if ( $simplified !== $expr ) {
            $nodes = @$xpath->evaluate( $simplified );
            if ( $nodes instanceof \DOMNodeList && $nodes->length > 0 ) {
                return $nodes;
            }
        }

        return null;
    }

    /**
     * Create content-based XPath for better element targeting.
     *
     * @param string $expr
     * @return string|null
     */
    private function create_content_based_xpath( string $expr ): ?string {
        // Extract key terms from the original XPath
        $key_terms = $this->extract_key_terms_from_xpath( $expr );
        
        if ( empty( $key_terms ) ) {
            return null;
        }
        
        // Build content-based XPath using key terms
        $content_conditions = [];
        foreach ( $key_terms as $term ) {
            $content_conditions[] = "contains(., '{$term}')";
        }
        
        // Try different element types
        $element_types = [ 'div', 'section', 'article', 'aside', 'p', 'span' ];
        $xpath_variants = [];
        
        foreach ( $element_types as $element ) {
            $xpath_variants[] = "//{$element}[" . implode( ' or ', $content_conditions ) . "]";
        }
        
        // Return the first variant (most specific)
        return $xpath_variants[0] ?? null;
    }

    /**
     * Simplify XPath by removing complex path dependencies and focusing on key attributes.
     *
     * @param string $expr
     * @return string
     */
    private function simplify_xpath_for_notices( string $expr ): string {
        // Extract key attributes from the original XPath
        $attributes = $this->extract_attributes_from_xpath( $expr );
        
        if ( empty( $attributes ) ) {
            return $expr;
        }
        
        // Build simplified XPath using key attributes
        $conditions = [];
        foreach ( $attributes as $attr => $values ) {
            foreach ( $values as $value ) {
                if ( $attr === 'class' ) {
                    $conditions[] = "contains(@class, '{$value}')";
                } elseif ( $attr === 'id' ) {
                    $conditions[] = "@id = '{$value}'";
                } else {
                    $conditions[] = "@{$attr} = '{$value}'";
                }
            }
        }
        
        if ( empty( $conditions ) ) {
            return $expr;
        }
        
        // Try different element types with simplified conditions
        $element_types = [ 'div', 'section', 'article', 'aside', 'p', 'span' ];
        $xpath_variants = [];
        
        foreach ( $element_types as $element ) {
            $xpath_variants[] = "//{$element}[" . implode( ' and ', $conditions ) . "]";
        }
        
        // Return the first variant
        return $xpath_variants[0] ?? $expr;
    }

    /**
     * Extract key terms from XPath expression for content-based matching.
     *
     * @param string $expr
     * @return array
     */
    private function extract_key_terms_from_xpath( string $expr ): array {
        $terms = [];
        
        // Extract text content from contains() functions
        if ( preg_match_all( "/contains\(\.\s*,\s*['\"]([^'\"]+)['\"]\)/", $expr, $matches ) ) {
            $terms = array_merge( $terms, $matches[1] );
        }
        
        // Extract class names that might indicate content type
        if ( preg_match_all( "/contains\(@class\s*,\s*['\"]([^'\"]+)['\"]\)/", $expr, $matches ) ) {
            foreach ( $matches[1] as $class ) {
                // Convert class names to potential content terms
                $content_term = str_replace( [ '-', '_' ], ' ', $class );
                $terms[] = $content_term;
            }
        }
        
        // Remove common WordPress/HTML terms that don't help with content matching
        $filtered_terms = array_filter( $terms, function( $term ) {
            $exclude_terms = [ 'notice', 'error', 'warning', 'success', 'info', 'updated', 'settings' ];
            return ! in_array( strtolower( $term ), $exclude_terms, true );
        } );
        
        return array_unique( $filtered_terms );
    }

    /**
     * Extract attributes from XPath expression for simplified matching.
     *
     * @param string $expr
     * @return array
     */
    private function extract_attributes_from_xpath( string $expr ): array {
        $attributes = [];
        
        // Extract class attributes
        if ( preg_match_all( "/contains\(@class\s*,\s*['\"]([^'\"]+)['\"]\)/", $expr, $matches ) ) {
            $attributes['class'] = $matches[1];
        }
        
        // Extract ID attributes
        if ( preg_match_all( "/@id\s*=\s*['\"]([^'\"]+)['\"]/", $expr, $matches ) ) {
            $attributes['id'] = $matches[1];
        }
        
        // Extract other attributes
        if ( preg_match_all( "/@(\w+)\s*=\s*['\"]([^'\"]+)['\"]/", $expr, $matches ) ) {
            for ( $i = 0; $i < count( $matches[1] ); $i++ ) {
                $attr = $matches[1][$i];
                $value = $matches[2][$i];
                if ( ! isset( $attributes[$attr] ) ) {
                    $attributes[$attr] = [];
                }
                $attributes[$attr][] = $value;
            }
        }
        
        return $attributes;
    }
}

