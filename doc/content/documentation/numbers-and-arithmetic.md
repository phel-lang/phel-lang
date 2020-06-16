+++
title = "Numbers and Arithmetic"
weight = 3
+++

Phel support integer and floating-point numbers. Both use the underlying PHP implementation. Integers can be specified in decimal (base 10), hexadecimal (base 16), octal (base 8) and binary (base 2) notation. Binary, octal and hexadecimal formats may contain underscores (`_`) between digits for better readability.

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

All arithmetic operators are entered in prefix notation.

```phel
# (1 + (2*2) + (10/5) + 3 + 4 + (5 - 6))
(+ 1 (* 2 2) (/ 10 5) 3 4 (- 5 6)) # Evaluates to 13
```

Some operators support zero, one or multiple arguments.

```phel
(+) # Evaluates to 0
(+ 1) # Evaluates to 1
(+ 1 2) # Evalutaes to 3
(+ 1 2 3 4 5 6 7 8 9) # Evaluates to 45

(-) # Evaluates to 0
(- 1) # Evaluates to -1
(- 2 1) # Evaluates to 1
(- 3 2 1) # Evaluates to 0

(*) # Evaluates to 1
(* 2) # Evaluates to 2
(* 2 3 4) #Evaluates to 24

(/) # Evaluates to 1
(/ 2) # Evaluates to 0.5 (reciprocal of 2)
(/ 24 4 2) #Evaluates to 3
```

Further numeric operations are `%` to compute the remainder of two values and `**` to raise a number to the power. All numeric operations can be found in the API documentation.

Some numeric operations can result in an undefined or unrepresentable value. These values are call _Not a Number_ (NaN). Phel represents this values by the constant `NAN`. You can check if a result is NaN by using the `nan?` function.

```phel
(nan? 1) # false
(nan? (log -1)) # true
(nan? NAN) # true
```

## Bitwise Operators

Phel allows the evaluation and manipulation of specific bits within an integer.

```phel
# Bitwise and
(bit-and 0b1100 0b1001) # Evaluates to 8 (0b1000)

# Bitwise or
(bit-or 0b1100 0b1001) # Evaluates to 13 (0b1101)

# Bitwise xor
(bit-xor 0b1100 0b1001) # Evaluates to 5 (0b0101)

# Bitwise complement
(bit-not 0b0111) # Evaluates to -8

# Shifts bit n steps to the left
(bit-shift-left 0b1101 1) # Evaluates to 26 (0b11010)

# Shifts bit n steps to the right
(bit-shift-right 0b1101 1) # Evaluates to 6 (0b0110)

# Set bit at index n
(bit-set 0b1011 2) # Evalutes to (0b1111)

# Clear bit at index n
(bit-clear 0b1011 3) # Evaluates to 15 (0b0011)

# Flip bit at index n
(bit-flip 0b1011 2) # Evaluates to 15 (0b1111)

# Test bit at index n
(bit-test 0b1011 0) # Evaluates to true
(bit-test 0b1011 2) # Evaluates to false
```