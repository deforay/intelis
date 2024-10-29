<?php

namespace App\Helpers;

class PassphraseHelper
{
    private static $consonants = [
        'b',
        'c',
        'd',
        'f',
        'g',
        'h',
        'j',
        'k',
        'l',
        'm',
        'n',
        'p',
        'r',
        's',
        't',
        'v',
        'w',
        'y',
        'z'
    ];

    private static $vowels = ['a', 'e', 'i', 'o', 'u'];

    private static $separators = ['-', '.', '_', ' '];

    /**
     * Generate a complete passphrase
     *
     * @param int $wordCount Number of word segments
     * @param int $wordLength Length of each word segment
     * @param bool $capitalize Whether to capitalize words
     * @param string|null $separator Fixed separator (null for random)
     * @return string Generated passphrase
     */
    public static function generate(
        int $wordCount = 4,
        int $wordLength = 5,
        bool $capitalize = false,
        ?string $separator = '-'
    ): string {
        $sep = $separator ?? self::$separators[array_rand(self::$separators)];
        $passphrase = [];
        $useConsonant = (bool)random_int(0, 1);

        // Generate the entire passphrase
        for ($i = 0; $i < $wordCount; $i++) {
            $word = '';

            // Generate one word segment
            for ($j = 0; $j < $wordLength; $j++) {
                if ($useConsonant) {
                    $word .= self::$consonants[random_int(0, count(self::$consonants) - 1)];
                } else {
                    $word .= self::$vowels[array_rand(self::$vowels)];
                }
                $useConsonant = !$useConsonant;
            }

            // Capitalize if requested
            if ($capitalize) {
                $word = ucfirst($word);
            }

            $passphrase[] = $word;
        }

        return implode($sep, $passphrase);
    }

    /**
     * Calculate approximate entropy of the passphrase
     *
     * @param int $wordCount Number of words
     * @param int $wordLength Length of each word
     * @return float Approximate entropy in bits
     */
    public static function calculateEntropy(int $wordCount = 4, int $wordLength = 5): float
    {
        // Possible consonant-vowel pairs
        $pairCombinations = count(self::$consonants) * count(self::$vowels);

        // Number of possible words with alternating consonants and vowels
        $wordCombinations = pow($pairCombinations, floor($wordLength / 2));

        // For odd word length, add an extra character (consonant or vowel)
        if ($wordLength % 2 !== 0) {
            $wordCombinations *= (count(self::$consonants) + count(self::$vowels));
        }

        // Calculate total entropy across all words in passphrase
        return log($wordCombinations ** $wordCount, 2);
    }
}
