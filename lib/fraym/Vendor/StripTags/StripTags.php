<?php

/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Fraym\Vendor\StripTags;

class StripTags
{
    /**
     * Array of allowed tags and allowed attributes for each allowed tag
     *
     * Tags are stored in the array keys, and the array values are themselves
     * arrays of the attributes allowed for the corresponding tag.
     */
    protected array $_tagsAllowed = [
        'a' => [],
        'b' => [],
        'strong' => [],
        'u' => [],
        'i' => [],
        'table' => [],
        'tbody' => [],
        'thead' => [],
        'tr' => [],
        'td' => [],
        'th' => [],
        'img' => [],
        'div' => [],
        'span' => [],
        'p' => [],
        'h1' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'br' => [],
        'button' => [],
        'quote' => [],
        'blockquote' => [],
        'em' => [],
        'hr' => [],
    ];

    /**
     * Array of allowed attributes for all allowed tags
     *
     * Attributes stored here as values are allowed for all of the allowed tags.
     */
    protected array $_attributesAllowed = [
        'style',
        'align',
        'valign',
        'src',
        'href',
        'target',
        'class',
        'width',
        'title',
    ];

    /**
     * Defined by filter
     */
    public function filter(string $value): string
    {
        // Strip HTML comments first
        while (mb_strpos($value, '<!--') !== false) {
            $pos = mb_strrpos($value, '<!--');
            $start = mb_substr($value, 0, $pos);
            $value = mb_substr($value, $pos);

            // If there is no comment closing tag, strip whole text
            if (!preg_match('/--\s*>/', $value)) {
                $value = '';
            } else {
                $value = preg_replace('/<!(?:--[\s\S]*?--\s*)?(>)/', '', $value);
            }

            $value = $start . $value;
        }

        // Initialize accumulator for filtered data
        $dataFiltered = '';
        // Parse the input data iteratively as regular pre-tag text followed by a
        // tag; either may be empty strings
        preg_match_all('/([^<]*)(<?[^>]*>?)/', $value, $matches);

        // Iterate over each set of matches
        foreach ($matches[1] as $index => $preTag) {
            // If the pre-tag text is non-empty, strip any ">" characters from it
            if (strlen($preTag)) {
                $preTag = str_replace('>', '', $preTag);
            }
            // If a tag exists in this match, then filter the tag
            $tag = $matches[2][$index];

            if (strlen($tag)) {
                $tagFiltered = $this->_filterTag($tag);
            } else {
                $tagFiltered = '';
            }
            // Add the filtered pre-tag text and filtered tag to the data buffer
            $dataFiltered .= $preTag . $tagFiltered;
        }

        // Return the filtered data
        return $dataFiltered;
    }

    /**
     * Filters a single tag against the current option settings
     *
     * @param string $tag
     * @return string
     */
    protected function _filterTag($tag)
    {
        // Parse the tag into:
        // 1. a starting delimiter (mandatory)
        // 2. a tag name (if available)
        // 3. a string of attributes (if available)
        // 4. an ending delimiter (if available)
        $isMatch = preg_match('~(</?)(\w*)((/(?!>)|[^/>])*)(/?>)~', $tag, $matches);

        // If the tag does not match, then strip the tag entirely
        if (!$isMatch) {
            return '';
        }

        // Save the matches to more meaningfully named variables
        $tagStart = $matches[1];
        $tagName = mb_strtolower($matches[2]);
        $tagAttributes = $matches[3];
        $tagEnd = $matches[5];

        // If the tag is not an allowed tag, then remove the tag entirely
        if (!isset($this->_tagsAllowed[$tagName])) {
            return '';
        }

        // Trim the attribute string of whitespace at the ends
        $tagAttributes = trim($tagAttributes);

        // If there are non-whitespace characters in the attribute string
        if (mb_strlen($tagAttributes)) {
            //strip_slashes
            $tagAttributes = stripslashes($tagAttributes);

            // Parse iteratively for well-formed attributes
            preg_match_all('/([\w-]+)\s*=\s*(?:(")(.*?)"|(\')(.*?)\')/s', $tagAttributes, $matches);

            // Initialize valid attribute accumulator
            $tagAttributes = '';

            // Iterate over each matched attribute
            foreach ($matches[1] as $index => $attributeName) {
                $attributeName = mb_strtolower($attributeName);
                $attributeDelimiter = empty($matches[2][$index]) ? $matches[4][$index] : $matches[2][$index];
                $attributeValue = empty($matches[3][$index]) ? $matches[5][$index] : $matches[3][$index];

                // If the attribute is not allowed, then remove it entirely
                if (
                    !array_key_exists($attributeName, $this->_tagsAllowed[$tagName])
                    && !in_array($attributeName, $this->_attributesAllowed)
                ) {
                    continue;
                }

                // Add the attribute to the accumulator
                $tagAttributes .= " $attributeName=" . $attributeDelimiter
                    . $attributeValue . $attributeDelimiter;
            }
        }

        // Reconstruct tags ending with "/>" as backwards-compatible XHTML tag
        if (mb_strpos($tagEnd, '/') !== false) {
            $tagEnd = " $tagEnd";
        }

        // Return the filtered tag
        return $tagStart . $tagName . $tagAttributes . $tagEnd;
    }
}
