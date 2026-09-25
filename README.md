# passmark_check

A tiny PHP CLI that looks up a CPU on passmark.com and spits out its benchmark specs.

Give it a CPU name/model (like `7540U` or `apple m1`), and it'll search PassMark's site, grab the top matching CPU page, and print out:

- Multithread Rating
- Single Thread Rating
- Clockspeed
- Cores
- Threads

## Usage

```bash
php passmarkcheck.php "<cpu designation>"
```

Examples:

```bash
php passmarkcheck.php "7540U"
php passmarkcheck.php "apple m1"
```

Sample output:

```
https://www.cpubenchmark.net/cpu.php?cpu=AMD+Ryzen+5+7540U&id=5539
Multithread Rating: 18526
Single Thread Rating: 3499
Clockspeed: 3.2 GHz
Cores: 6
Threads: 12
```

## Requirements

- PHP with cURL enabled (no other dependencies)

## Notes

- If nothing matches your search, it'll print an error to stderr and exit with a non-zero code.
- It relies on scraping cpubenchmark.net's HTML, so if PassMark changes their page layout, the parsing might need updating.
