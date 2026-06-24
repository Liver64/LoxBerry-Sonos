<?php
/**
 * Sonos4Lox - XML writer helper
 * Version: XML_SUPPORT_RELOCATION_V01_2026_06_13
 *
 * Relocated from the historic system/bin/xml/XmlWriter.php include path.
 * Builds simple XML documents from the array structure used by createMetaDataXml().
 */

class XmlWriterNew
{
    /**
     * Create an XML document from a nested array structure.
     *
     * Supported keys:
     * - _attributes: associative array of XML attributes
     * - _value: scalar text content
     * - child element names with scalar or nested array values
     * - numeric arrays for repeated child elements
     */
    public function createXml(array $data): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

        foreach ($data as $name => $value) {
            if (!is_string($name) || $name === '') {
                continue;
            }
            $xml .= $this->buildElement($name, $value, 0) . "\n";
        }

        return $xml;
    }

    private function buildElement(string $name, $value, int $level): string
    {
        $indent = str_repeat('  ', $level);
        $attributes = '';
        $children = '';
        $text = null;

        if (is_array($value)) {
            if (isset($value['_attributes']) && is_array($value['_attributes'])) {
                foreach ($value['_attributes'] as $attrName => $attrValue) {
                    if (!is_string($attrName) || $attrName === '') {
                        continue;
                    }
                    $attributes .= ' ' . $attrName . '="' . $this->escape((string)$attrValue) . '"';
                }
            }

            if (array_key_exists('_value', $value)) {
                $text = (string)$value['_value'];
            }

            foreach ($value as $childName => $childValue) {
                if ($childName === '_attributes' || $childName === '_value' || is_int($childName)) {
                    continue;
                }

                if (is_array($childValue) && $this->isList($childValue)) {
                    foreach ($childValue as $item) {
                        $children .= "\n" . $this->buildElement((string)$childName, $item, $level + 1);
                    }
                } else {
                    $children .= "\n" . $this->buildElement((string)$childName, $childValue, $level + 1);
                }
            }
        } elseif ($value !== null) {
            $text = (string)$value;
        }

        if ($children !== '') {
            $content = ($text !== null && $text !== '') ? $this->escape($text) : '';
            return $indent . '<' . $name . $attributes . '>' . $content . $children . "\n" . $indent . '</' . $name . '>';
        }

        if ($text !== null && $text !== '') {
            return $indent . '<' . $name . $attributes . '>' . $this->escape($text) . '</' . $name . '>';
        }

        return $indent . '<' . $name . $attributes . '/>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function isList(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }
}
