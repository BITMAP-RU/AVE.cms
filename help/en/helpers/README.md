# Framework helpers

A set of static utilities from `system/App/Helpers/`. Almost all methods
`Helper::method()` (state not stored). Classes autoload, `use` usually does not
required if the code is in the same namespace context; otherwise - `use App\Helpers\Str;`.

## By category

### Strings, arrays, numbers, date
| Helper | Destination |
| --- | --- |
| [Str](Str.md) | Working with strings in UTF-8 (mbstring): case, trimming, slug, camel/snake, masking, UUID. |
| [Arr](Arr.md) | Arrays: `get/set` in dot notation, search, sorting, object↔array, safe (de)serialization. |
| [Number](Number.md) | Formatting numbers, sizes, declination, number in words, float comparison. |
| [Date](Date.md) | Date/time: SQL↔timestamp, human-readable format, declinations. |
| [Locales](Locales.md) | Locale, transliteration, translation and “beautiful” display of dates. |

### HTTP: request and response
| Helper | Destination |
| --- | --- |
| [Request](Request.md) | Access to incoming data: GET/POST/JSON, typed getters, method, AJAX, IP. |
| [Response](Response.md) | Answers: JSON/HTML/text/XML, download, codes 204/404/403/401/422/429/500. |
| [Cookie](Cookie.md) | Read/write/delete cookies. |
| [Url](Url.md) | Link building, redirects, canonical, rewrite, referer. |
| [Ip](Ip.md) | Determination and validation of IP, geo-data, ip↔hex. |
| [Phone](Phone.md) | Normalization, verification and secure masking of phone numbers. |

### Security and Validation
| Helper | Destination |
| --- | --- |
| [Valid](Valid.md) | Validation: static checks and batch `check($data, $rules)`. |
| [Secure](Secure.md) | Cleaning input from XSS, sanitizing, processing phones. |
| [Crypt](Crypt.md) | String encryption/decryption, crc32, radix conversion. |

### Files and directories
| Helper | Destination |
| --- | --- |
| [File](File.md) | Files: read/write (including atomic), mime, download, encodings, names. |
| [Dir](Dir.md) | Directories: creation, crawling, size, copying, rights. |
| [Zip](Zip.md) | Archiving (ZipArchive wrapper). |

### Data, output, miscellaneous
| Helper | Destination |
| --- | --- |
| [Json](Json.md) | Safe encode/decode, payload for API, working with Cyrillic, “fixing” JSON. |
| [Html](Html.md) | HTML compression, gzip output, nl2br, encode/decode, query strings. |
| [Hooks](Hooks.md) | Hook system: `action`/`filter`, priorities (WordPress-like). |
| [Debug](Debug.md) | Dump/echo/print, benchmarks, time/memory measurements, SQL error output. |
| [SoftDelete](SoftDelete.md) | SQL conditions for soft deletion (`deleted_at`/flags). |### Content and text
| Helper | Destination |
| --- | --- |
| [TagParser](TagParser.md) | Parsing tags in templates/content, registering handlers. |
| [SearchText](SearchText.md) | Definition of the Cyrillic alphabet, bringing the layout to Russian. |
| [RussianStemmer](RussianStemmer.md) | Stemming of Russian words (for search). |
| [MarketParser](MarketParser.md) | Client of the external API MarketParser (Yandex.Market-like). |

> Each file contains a purpose, a list of methods with signatures and examples.
