Yaml 0.8.18
===========
YAML parser and emitter.

## How to use Yaml from other extensions

This extension provides the following YAML-related functions:

`yaml_parse($input)`  
`yaml_parse_url($url)`  
`yaml_parse_file($fileName)`  
`yaml_emit($data)`  
`yaml_emit_file($fileName, $data)`  

Additional arguments are ignored. If PHP has the YAML Data Serialisation extension installed, its native functions are used instead.

[The most used features of YAML](https://github.com/secondparty/dipper) are recognised: normal key/value pairs (strings, scalars, numbers, and booleans), lists, and maps. The parser is rather strict regarding indentation: it must be present also when lines begin with a hyphen, and the same amount of indentation must be used in the whole YAML-block (two-space indents are recommended). (Many common examples of YAML do not follow these rules.)

## Example

```
$yaml = <<<EOF
---
invoice: 34843
date: "2001-01-23"
bill-to: 
  given: Chris
  family: Dumars
  address:
    lines: |-
      458 Walkman Dr.
              Suite #292
    city: Royal Oak
    state: MI
    postal: 48046
ship-to: 
product:
  - sku: BL394D
    quantity: 4
    description: Basketball
    price: 450
  - sku: BL4438H
    quantity: 1
    description: Super Hoop
    price: 2392
tax: 251.420000
total: 4443.520000
comments: Late afternoon is best. Backup contact is Nancy Billsmer @ 338-4338.
...
EOF;

$parsed = yaml_parse($yaml);
```

This is the same [example used in PHP documentation](https://www.php.net/manual/en/function.yaml-parse.php), adapted. Note the indentation after `product:`.

## Installation

[Download extension](https://github.com/GiovanniSalmeri/yellow-yaml/archive/master.zip) and copy zip file into your `system/extensions` folder. Right click if you use Safari.

This extension uses [Dipper](https://github.com/secondparty/dipper) by Second Party.

## Developer

Giovanni Salmeri. [Get help](https://datenstrom.se/yellow/help/)
