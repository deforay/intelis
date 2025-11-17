<?php

namespace App\Helpers;

use Hackzilla\PasswordGenerator\Generator\HumanPasswordGenerator;

class PassphraseHelper
{
    private static array $consonants = ['b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'r', 's', 't', 'v', 'w', 'y', 'z'];
    private static array $vowels = ['a', 'e', 'i', 'o', 'u'];
    private static array $separators = ['-', '.', '_', ' '];
    private static array $allowedSeparators = ['-', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0'];

    /**
     * Generate a passphrase using a word list if available, or a custom method otherwise.
     *
     * @param int $wordCount Number of words
     * @param int $wordLength Length of each word segment (only used if word list is not available)
     * @param bool $capitalize Whether to capitalize each word
     * @param string|null $separator Character to separate words
     * @return string Generated passphrase
     */
    public static function generate(
        int $wordCount = 4,
        int $wordLength = 5,
        bool $capitalize = false,
        ?string $separator = '-'
    ): string {
        // Ensure separator is allowed (only hyphen or numbers)
        if (!in_array($separator, self::$allowedSeparators)) {
            $separator = '-';
        }

        // Use HumanPasswordGenerator if word list is available
        if (file_exists('/usr/share/dict/words')) {
            $generator = new HumanPasswordGenerator();
            $generator->setWordList('/usr/share/dict/words')
                ->setWordCount($wordCount)
                ->setWordSeparator($separator);

            $password = $generator->generatePasswords(1)[0];

            // Clean the generated password to ensure it only has alphabets in words
            $words = explode($separator, (string) $password);
            $cleanWords = [];

            foreach ($words as $word) {
                // Remove any special characters, keep only alphabets
                $cleanWord = preg_replace('/[^a-z]/i', '', $word);
                // Convert to lowercase as required
                $cleanWord = strtolower((string) $cleanWord);
                $cleanWords[] = $cleanWord;
            }

            return implode($separator, $cleanWords);
        }

        // Otherwise, use custom generation method
        return self::generateCustomPassphrase($wordCount, $wordLength, $capitalize, $separator);
    }

    /**
     * Fallback custom passphrase generator if word list is not available.
     *
     * @param int $wordCount
     * @param int $wordLength
     * @param bool $capitalize
     * @param string|null $separator
     * @return string
     */
    private static function generateCustomPassphrase(
        int $wordCount,
        int $wordLength,
        bool $capitalize,
        ?string $separator
    ): string {
        // Ensure separator is allowed (only hyphen or numbers)
        if (!in_array($separator, self::$allowedSeparators)) {
            $separator = '-';
        }

        $passphrase = [];
        $useConsonant = (bool)random_int(0, 1);

        for ($i = 0; $i < $wordCount; $i++) {
            $word = '';
            for ($j = 0; $j < $wordLength; $j++) {
                if ($useConsonant) {
                    $word .= self::$consonants[random_int(0, count(self::$consonants) - 1)];
                } else {
                    $word .= self::$vowels[random_int(0, count(self::$vowels) - 1)];
                }
                $useConsonant = !$useConsonant;
            }

            // Always make words lowercase as required
            $word = strtolower($word);
            $passphrase[] = $word;
        }

        return implode($separator, $passphrase);
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
        $pairCombinations = count(self::$consonants) * count(self::$vowels);
        $wordCombinations = $pairCombinations ** floor($wordLength / 2);

        if ($wordLength % 2 !== 0) {
            $wordCombinations *= (count(self::$consonants) + count(self::$vowels));
        }

        return log($wordCombinations ** $wordCount, 2);
    }
}
