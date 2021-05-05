+++
title = "Basic Types"
weight = 2
+++

## Nil, True, False

Nil, true and false are literal constants. In Phel `nil` is the same as `null` in PHP. Phel's `true` and `false` are the same as PHP's `true` and `false`.

```phel
nil
true
false
```

## Symbol

Symbols are used to name functions and variables in Phel.

```phel
symbol
snake_case_symbol
my-module/my-function
λ
```

## Keywords

Keywords are like symbols that begin with a colon character. However, they are used as constants rather than a name for something.

```phel
:keyword
:range
:0x0x0x
:a-keyword
::
```

## Numbers

Numbers in Phel are equivalent to numbers in PHP. Next to decimal and
float numbers the reader also supports binary, octal and hexadecimal number formats. Binary, octal and hexadecimal formats may contain underscores (`_`) between digits for better readability.

```phel
1337 # integer
+1337 # positive integer
-1337 # negative integer

1.234 # float
+1.234 # positive float
-1.234 # negative float
1.2e3 # float
7E-10 # float

0b10100111001 # binary number
-0b10100111001 # negative binary number
0b101_0011_1001 # binary number with underscores for better readability

0x539 # hexadecimal number
-0x539 # negative hexadecimal number
-0x5_39 # hexadecimal number with underscores

02471 # octal number
-02471 # negativ octal number
024_71 # octal number with underscores
```

## Strings

Strings are surrounded by double quotes. They almost work the same as PHP double quoted strings. One difference is that the dollar sign (`$`) must not be escaped. Internally Phel strings are represented by PHP strings. Therefore, every PHP string function can be used to operate on the string.

String can be written in multiple lines. The line break character is then ignored by the reader.

```phel
"hello world"

"this is\na\nstring"

"this
is
a
string."

"use backslack to escape \" string"

"the dollar must not be escaped: $ or $abc just works"

"Hexadecimal notation is supported: \x41"

"Unicodes can be encoded as in PHP: \u{1000}"
```

## Lists

Lists are a sequence of white space separated values surrounded by parentheses.

```phel
(do 1 2 3)
```

Lists will be interpreted as a function calls, a macro call or special form by the compiler.

## Vectors

Vectors are a sequence of white space separated values surrounded by brackets.

```phel
[1 2 3]
```

A Vector in Phel is an indexed datastructure. In contrast to PHP arrays, Phel vectors cannot be used as Map, HashTable or Dictionary.

## Maps

Maps are represented by a sequence of white-space delimited key value pairs surrounded by curly braces. There must be an even number of items between curly braces or the parser will signal a parse error. The sequence is defined as key1, value1, key2, value2, etc.

```phel
{}
{:key1 "value1" :key2 "value2"}
{(1 2 3) (4 5 6)}
{[] []}
{1 2 3 4 5 6}
```

In contrast to PHP associative arrays, Phel Maps can have any type of keys.

## Sets

Sets are a sequence of white space separated values prefixed by the function `set` and the whole being surrounded by parentheses.

```phel
(set 1 2 3)
```

## Comments

Comments begin with a `#` character and continue until the end of the line. There are no multi-line comments.

```phel
# This is a comment
```

## Arrays (deprecated, use Vectors)

Arrays are similar to vectors but have a leading `@`.

```phel
@[]
@[:a :b :c]
```

An Array in Phel is an indexed datastructure. In contrast to PHP arrays, Phel arrays cannot be used as Map, HashTable or Dictionary.

## Tables (deprecated, use Maps)

Tables are represented by a sequence of white-space delimited key value pairs surrounded by curly braces and the prefix `@`. There must be an even number of items between curly braces or the parser will signal a parse error. The sequence is defined as key1, value1, key2, value2, etc.

```phel
@{}
@{:key1 "value1" :key2 "value2"}
@{(1 2 3) (4 5 6)}
@{@[] @[]}
@{1 2 3 4 5 6}
```

In contrast to PHP associative arrays, Phel tables can have any type of keys.
