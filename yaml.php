<?php
// Yaml extension, https://github.com/GiovanniSalmeri/yellow-yaml

class YellowYaml {
    const VERSION = "0.8.18";

/**
 * Dipper
 * A demi-YAML parser.
 * View full documentation at http://github.com/secondparty/dipper
 *
 * Copyright (c) 2014-2015, Second Party
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 *    may be used to endorse or promote products derived from this software
 *    without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

    private static $replacements = [];
    private static $replacement_types = [];
    private static $indent_size = 0;
    private static $empty_indent = null;
    private static $i = 0;
    private static $booleans = [
        'true'  => true,
        'false' => false,
        //'~'     => null, // deleted
        'null'  => null
    ];
    private static $max_line_length = 80;

    public static function parse($yaml) {
        self::$replacements = [];
        self::$replacement_types = [];
        self::$i = 0;
        $yaml = self::prepare($yaml);
        self::setIndent($yaml);
        $structures = self::breakIntoStructures($yaml);
        return self::parseStructures($structures, true);
    }

    public static function make($php) {
        $php = (array) $php;
        self::$indent_size = 2;
        self::$empty_indent = str_repeat(' ', self::$indent_size);
        $output = "---\n" . self::build($php) . "\n...\n"; // added ...
        return $output;
    }

    private static function parseStructures($structures, $is_root=false) {
        $output = [];
        foreach ($structures as $structure) {
            if (strpos($structure, '"')!==false) {
                $structure = preg_replace_callback('/".*?(?<!\\\)"/m', function($item) {
                    $key = '__r@-' . self::$i++ . '__';
                    self::$replacement_types[$key] = '"';
                    self::$replacements[$key] = substr($item[0], 1, -1);
                    return $key;
                }, $structure);
            }
            if (strpos($structure, '\'')!==false) {
                $structure = preg_replace_callback('/\'.*?(?<!\\\)\'/m', function($item) {
                    $key = '__r@-' . self::$i++ . '__';
                    self::$replacement_types[$key] = '\'';
                    self::$replacements[$key] = substr($item[0], 1, -1);
                    return $key;
                }, $structure);
            }
            if (strpos($structure, '#')!==false) {
                $colon = strpos($structure, ':');
                if ($colon!==false) {
                    $first_value_char = substr(trim(substr($structure, $colon + 1)), 0, 1);
                    if ($first_value_char!=='>' && $first_value_char!=='|') {
                        $structure = preg_replace('/#.*?$/m', '', $structure);
                    }
                }
            }
            if ($result = self::parseStructure($structure)) {
                if ($is_root && $structure[0]==='-' && empty($result[1]) && !empty($result[0])) {
                    $output[] = $result[0];
                } else {
                    $output[$result[0]] = $result[1];
                }
            }
        }
        return $output;
    }

    private static function parseStructure($structure) {
        $out = self::breakIntoKeyValue($structure);
        $key = $out[0];
        $value = $out[1];
        if (!isset($key) && empty($value)) {
            return null;
        }
        $first_two = substr($value, 0, 2);
        $first_character = substr($first_two, 0, 1);
        $trimmed_lower = strtolower(trim($value));
        if ($value==='') {
            $new_value = null;
        } elseif ($first_two==='__' && substr($value, 0, 5)==='__r@-' && substr($value, -2)==='__') {
            $new_value = self::unreplaceDeep($value);
        } elseif (array_key_exists($trimmed_lower, self::$booleans)) {
            $new_value = self::$booleans[$trimmed_lower];
        } elseif ($first_character==='[' && substr($trimmed_lower, -1)===']') {
            if (strpos($trimmed_lower, ',')===false && strlen(trim($trimmed_lower, '[] '))===0) {
                $new_value = [];
            } else {
                $new_value = explode(',', trim(self::unreplaceAll($value, false), '[]'));
                foreach ($new_value as &$item) {
                    $item = self::parseStructure($item);
                }
            }
        } elseif ($first_character==='{' && substr($trimmed_lower, -1)==='}') {
            $adjusted = self::unreplaceAll(preg_replace('/,\s*/s', "\n", trim($value, '{} ')), true);
            $structures = self::breakIntoStructures(self::outdent($adjusted));
            $new_value = self::parseStructures($structures);
        } elseif ($first_character==='|') {
            $new_value = self::unreplaceAll(substr($value, strpos($value, "\n") + 1), true);
        } elseif ($first_character==='>') {
            $new_value = self::unreplaceAll(preg_replace('/^(\S[^\n]*)\n(?=\S)/m', '$1 ', substr($value, strpos($value, "\n") + 1)), true);
        } elseif ($first_two==='- ' || $first_two==="-\n") {
            $items = self::breakIntoStructures($value);
            $new_value = [];
            foreach ($items as $item) {
                $item = trim(self::outdent(substr($item, 1)));
                if ((strpos($item, ': ') || strpos($item, ":\n")) && substr($item, 0, 1)!=='{' && substr($item, -1)!=='}') {
                    $structures = self::breakIntoStructures($item);
                    $new_value[] = self::parseStructures($structures);
                } else {
                    $new_value[] = self::parseStructure($item);
                }
            }
        } elseif (strpos($value, ': ') || strpos($value, ":\n")) {
            $structures = self::breakIntoStructures(self::outdent($value));
            $new_value = self::parseStructures($structures);
        } elseif (is_numeric($trimmed_lower) || $first_two==='0o') {
            if (strpos($value, '.')!==false) {
                $new_value = (float) $value;
            } elseif ($first_two==='0x') {
                $new_value = hexdec($value);
            } elseif ($first_character==='0') {
                $new_value = octdec($value);
            } else {
                $new_value = (int) $value;
            }
        } elseif ($first_two==='0o') {
            $new_value = octdec(substr($value, 2));
        } elseif ($first_two==='0x') {
            $new_value = hexdec($value);
        } elseif ($trimmed_lower==='.inf' || $trimmed_lower==='(inf)') {
            $new_value = INF;
        } elseif ($trimmed_lower==='-.inf' || $trimmed_lower==='(-inf)') {
            $new_value = -INF;
        } elseif ($trimmed_lower==='.nan' || $trimmed_lower==='(nan)') {
            $new_value = NAN;
        } else {
            $new_value = rtrim(self::unreplaceAll($value, true));
        }
        if (empty($key)) {
            return $new_value;
        } else {
            return [ trim(self::unreplaceDeep($key)), $new_value ];
        }
    }

    private static function breakIntoKeyValue($text) {
        $colon = strpos($text, ':');
        if (empty($colon) || (substr($text, 0, 1)==='{' && substr($text, -1)==='}')) {
            return [ null, self::outdent($text) ];
        }
        $key = substr($text, 0, $colon);
        $value = self::outdent(substr($text, $colon + 1));
        return [ $key, $value ];
    }

    private static function setIndent($yaml) {
        self::$indent_size = 0;
        if (preg_match('/^( +)\S/m', $yaml, $matches)) {
            self::$indent_size = strlen($matches[1]);
        }
        self::$empty_indent = str_repeat(' ', self::$indent_size);
    }

    private static function prepare($yaml) { // rewritten
        $first_pass = "";
        $lines = preg_split('/\R/', $yaml);
        foreach ($lines as $line) {
            if (substr($line, 0, 1)!=='#' && strpos($line, '---')!==0) {
                if (strpos($line, '...')===0) break;
                $first_pass .= $line . "\n";
            }
        }
        return $first_pass;
    }

    private static function breakIntoStructures($yaml) {
        $lines = explode("\n", $yaml);
        $parts = [];
        $chunk = null;
        foreach ($lines as $line) {
            if (isset($line[0]) && $line[0]!==' ' && $line[0]!=="\n") {
                if ($chunk!==null) {
                    $parts[] = rtrim($chunk);
                }
                $chunk = $line;
            } else {
                $chunk = $chunk . "\n" . $line;
            }
        }
        $parts[] = rtrim($chunk);
        return $parts;
    }

    private static function unreplace($text) {
        if (!isset(self::$replacements[$text]) || strpos($text, '__r@-')===false) {
            return $text;
        }
        return self::$replacements[$text];
    }

    private static function unreplaceDeep($text) {
        $text = self::unreplace($text);
        if (strpos($text, '__r@-')!==false) {
            $text = self::unreplaceAll($text, true);
        }
        return $text;
    }

    private static function unreplaceAll($text, $include_type=false) {
        if (!is_string($text) || strpos($text, '__r@-')===false) {
            return $text;
        }
        while (strpos($text, '__r@-')!==false) {
            $text = preg_replace_callback('/__r@-\d+__/', function ($matches) use ($include_type) {
                if ($include_type) {
                    return self::$replacement_types[$matches[0]] . self::unreplaceDeep($matches[0]) . self::$replacement_types[$matches[0]];
                }
                return self::unreplaceDeep($matches[0]);
            }, $text);
        }
        return $text;
    }

    private static function outdent($value) {
        $lines = explode("\n", $value);
        $out = '';
        foreach ($lines as $line) {
            if (isset($line[0]) && $line[0]!==' ' && $line[0]!=="\n") {
                return $value;
            }
            if (!isset($line[0])) {
                $out = $out . "\n" . self::$empty_indent;
            } elseif (substr($line, 0, self::$indent_size)===self::$empty_indent) {
                $out = $out . "\n" . substr($line, self::$indent_size);
            } else {
                $out = $out . ltrim($line, ' ');
            }
        }
        return ltrim($out);
    }

    private static function build($value, $depth=0) {
        if ($value==='' || is_null($value) || $value==='null' || $value==='~') {
            return '';
        } elseif (is_array($value)) {
            if (!count($value)) {
                return '[]';
            }
            $output = [];
            if (array_keys($value)===range(0, count($value) - 1)) {
                foreach ($value as $subvalue) {
                    $result = self::build($subvalue, $depth + 1);
                    if (is_array($subvalue)) {
                        $output[] = "-\n" . $result;
                    } else {
                        $output[] = "- " . $result;
                    }
                }
            } else {
                foreach ($value as $key => $subvalue) {
                    $result = self::build($subvalue, $depth + 1);
                    if (is_array($subvalue) && count($subvalue)) {
                        $output[] = $key . ":\n" . $result;
                    } else {
                        $output[] = $key . ": " . $result;
                    }
                }
            }
            if ($depth>0) {
                foreach ($output as &$line) {
                    $line = str_repeat(self::$empty_indent, $depth) . $line;
                }
            }
            return join("\n", $output);
        } elseif (is_bool($value)) {
            if ($value) {
                return 'true';
            }
            return 'false';
        } elseif (!is_string($value) && (is_int($value) || is_float($value))) {
            if (is_infinite($value)) {
                if ($value>0) {
                    return '(inf)';
                }
                return '(-inf)';
            } elseif (is_nan($value)) {
                return '(NaN)';
            }
            return (string) $value;
        }
        if (is_object($value)) {
            if (!method_exists($value, '__toString')) {
                return '';
            }
            $value = (string) $value;
        }
        $needs_quoting = strpos($value, ':')!==false || $value==='true' || $value==='false' || is_numeric($value);
        $needs_double_quoting = strpos($value, '\"')!==false;
        $needs_scalar = strpos($value, "\n")!==false || strlen($value)>self::$max_line_length;
        $needs_literal = strpos($value, "\n")!==false;
        if ($needs_scalar) {
            $string = ">";
            if ($needs_literal) {
                $string = "|";
            }
            $string = $string . "\n" . wordwrap($value, (self::$max_line_length - self::$indent_size * $depth + 1), "\n");
            $output = explode("\n", $string);
            $first = true;
            foreach ($output as &$line) {
                if ($first) {
                    $first = null;
                    continue;
                }
                $line = str_repeat(self::$empty_indent, $depth) . $line;
            }
            return join("\n", $output);
        } elseif ($needs_quoting) {
            return trim('\'' . str_replace('\'', '\\\'', $value) . '\'');
        } elseif ($needs_double_quoting) {
            return '"' . $value . '"';
        }
        return trim($value);
    }
}

if (!extension_loaded("yaml")) {
    function yaml_parse($input, $pos = null, $ndocs = null, $callbacks = null) {
        return YellowYaml::parse($input);
    }
    function yaml_parse_url($url, $pos = null, $ndocs = null, $callbacks = null) {
        return yaml_parse_file($url);
    }
    function yaml_parse_file($filename, $pos = null, $ndocs = null, $callbacks = null) {
        $content = file_get_contents($filename);
        if (substr($content, 0, 3)=="\xef\xbb\xbf") $content = substr($content, 3);
        return $content===false ? false : YellowYaml::parse($content);
    }
    function yaml_emit($data, $encoding = null, $linebreak = null, $callbacks = null) {
        return YellowYaml::make($data);
    }
    function yaml_emit_file($filename, $data, $encoding = null, $linebreak = null, $callbacks = null) {
        $content = YellowYaml::make($data);
        return (bool)file_put_contents($filename, $content);
    }
}
